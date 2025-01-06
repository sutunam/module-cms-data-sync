<?php
namespace Sutunam\CmsDataSync\Model\Processors\Cms;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\PageRepository;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Sutunam\CmsDataSync\Api\Data\PageInterface as SutunamPageInterface;
use Sutunam\CmsDataSync\Api\ProcessorInterface;
use Sutunam\CmsDataSync\Helper\ApiConnection;
use Sutunam\CmsDataSync\Helper\ApiHelper;
use Sutunam\CmsDataSync\Helper\Data as DataHelper;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;
use Laminas\Http\Request;
use Sutunam\CmsDataSync\Logger\Logger;

class Page implements ProcessorInterface
{
    public const SUTUNAM_CMS_PAGE_API_ENDPOINT = "sutunam/cmsPage/";

    /**
     * @var PageRepository
     */
    protected $pageRepository;

    /**
     * @var PageFactory
     */
    protected $pageFactory;

    /**
     * @var ApiHelper
     */
    protected $apiHelper;

    /**
     * @var ApiConnection
     */
    protected $apiConnection;

    /**
     * @var DataHelper
     */
    protected $dataHelper;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var PageCollectionFactory
     */
    protected $pageCollectionFactory;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param PageRepository $pageRepository
     * @param PageFactory $pageFactory
     * @param ApiHelper $apiHelper
     * @param ApiConnection $apiConnection
     * @param DataHelper $dataHelper
     * @param ConfigHelper $configHelper
     * @param PageCollectionFactory $pageCollectionFactory
     * @param Logger $logger
     */
    public function __construct(
        PageRepository $pageRepository,
        PageFactory $pageFactory,
        ApiHelper $apiHelper,
        ApiConnection $apiConnection,
        DataHelper $dataHelper,
        ConfigHelper $configHelper,
        PageCollectionFactory $pageCollectionFactory,
        Logger $logger
    ) {
        $this->pageFactory = $pageFactory;
        $this->pageRepository = $pageRepository;
        $this->apiHelper = $apiHelper;
        $this->apiConnection = $apiConnection;
        $this->dataHelper = $dataHelper;
        $this->configHelper = $configHelper;
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * Pull cms page from remote host by my id
     *
     * @param int $id
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function pull($id)
    {
        $result = [
            'success' => false,
            'message' => ''
        ];

        $page = $this->getPage($id);
        if (!$page) {
            $result['message'] = __('Couldn\'t load CMS page content.');
        }

        $pageStoreIds = $page->getStores();
        $pageStoreCodes = $this->dataHelper->getStoreCodesByIds($pageStoreIds);
        $remoteStoreIds = $this->dataHelper->getRemoteStoreIdsListByMyStoreCodes($pageStoreCodes);
        if (!$remoteStoreIds) {
            $result['message'] = __('The store scope of page is not match.');
            return $result;
        }

        $remotePages = $this->apiHelper->getDataByIdentifier(
            $page->getIdentifier(),
            ApiHelper::CMS_TYPE_PAGE,
            ['store_ids' => implode(',', $remoteStoreIds)]
        );
        if (!$remotePages) {
            $result['message'] = __('There is no remote page with identifier %1', $page->getIdentifier());
            return $result;
        }

        if (count($remotePages) != 1) {
            $result['message'] = __('The store scope of page is not match.');
            return $result;
        }

        $remotePage = $remotePages[0];
        try {
            $page->setData($remotePage);
            $page->setId($id);
            $page->setStoreId(array_map('intval', $pageStoreIds));
            $page->save();
            if ($this->configHelper->isSyncMedia()) {
                $this->dataHelper->saveAllImageFromCmsContent($page->getContent(), true);
            }
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            $this->logger->critical($e->getMessage());
        }
        $result['success'] = true;
        return $result;
    }

    /**
     * Pull all cms page from remote host
     *
     * @return array
     */
    public function pullAll()
    {
        $result = [
            'total_update_success' => 0,
            'total_create_success' => 0,
            'total_failed' => 0,
            'message' => ''
        ];

        $allRemotePages = $this->apiHelper->getAllCms(ApiHelper::CMS_TYPE_PAGE);
        if (!$allRemotePages) {
            $result['message'] = __(
                'There is no remote page on the remote host %1',
                $this->configHelper->getSiteName()
            );
            return $result;
        }
        foreach ($allRemotePages as $remotePage) {
            //Get Stores Ids list on my site that have the same values code with remote codes list
            $myStoreIds = $this->dataHelper->getMyStoreIdsListByRemoteStoreCodes(
                $remotePage[SutunamPageInterface::STORE_CODE]
            );

            //Skip this remote page if there's not stores compatibled
            if (!$myStoreIds) {
                continue;
            }

            //Get Pages by identifier and my store list
            $myPages = $this->pageCollectionFactory->create()
                ->addFieldToFilter(PageInterface::IDENTIFIER, ['eq' => $remotePage[PageInterface::IDENTIFIER]])
                ->addFieldToFilter(SutunamPageInterface::STORE_ID, ['in' => $myStoreIds]);

            if ($myPages->getSize() > 1) {
                continue;
            }

            try {
                $myPage = $this->pageFactory->create();

                $myPageId = $myPages->getFirstItem()->getId();
                $myPageStoreIds = [];

                if ($myPageId) {
                    $myPage = $this->pageRepository->getById($myPageId);
                    $myPageStoreIds = $myPages->getFirstItem()->getStores();
                }

                if (!$this->validatePage($remotePage, $myPageStoreIds, $myStoreIds)) {
                    $result['message'] .= __(
                        'Pull Page ID: %1 ||Status: %2.<br>',
                        $myPageId,
                        __('Skipped')
                    );
                    continue;
                }

                $myPageStoreIds  = $myPageStoreIds ?: $myStoreIds;

                $myPage->setData($remotePage);
                $myPage->setStoreId(array_map('intval', $myPageStoreIds));

                if ($this->configHelper->isOverwrite()) {
                    $myPage->setId($myPageId);
                    /** @phpstan-ignore-next-line */
                    $myPage->save();
                } elseif (!$myPageId) {
                    /** @phpstan-ignore-next-line */
                    $myPage->save();
                } else {
                    $result['message'] .= __(
                        'Pull Page ID: %1 ||Status: %2.<br>',
                        $myPageId,
                        __('Skipped')
                    );
                    continue;
                }

                if ($this->configHelper->isSyncMedia()) {
                    $this->dataHelper->saveAllImageFromCmsContent(
                        $myPage->getContent(),
                        $this->configHelper->isOverwrite()
                    );
                }

                if ($myPageId) {
                    $result['message'] .= __(
                        'Pull Page ID: %1 ||Status: %2.<br>',
                        $myPageId,
                        __('Updated Successfully')
                    );
                    $result['total_update_success']++;
                } else {
                    $result['message'] .= __(
                        'Pull Page ID: %1 ||Status: %2.<br>',
                        $myPageId,
                        __('Created Successfully')
                    );
                    $result['total_create_success']++;
                }
            } catch (\Exception $e) {
                $result['total_failed']++;
                $this->logger->critical($e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Push cms page to remote host by my id
     *
     * @param int $id
     * @return array
     * @throws \Exception
     */
    public function push($id)
    {
        $result = [
            "success" => false,
            "type" => "",
            "message" => ""
        ];

        $entity = $this->getPage($id);
        if ($entity) {
            $remoteStoreIds = $this->dataHelper->getRemoteStoreIdsListByMyStoreIds($entity->getStores());
            $data = ["page" => [
                PageInterface::IDENTIFIER => $entity->getIdentifier(),
                PageInterface::TITLE => $entity->getTitle(),
                PageInterface::PAGE_LAYOUT  => $entity->getPageLayout(),
                PageInterface::META_TITLE => $entity->getMetaTitle(),
                PageInterface::META_KEYWORDS => $entity->getMetaKeywords(),
                PageInterface::META_DESCRIPTION => $entity->getMetaDescription(),
                PageInterface::CONTENT_HEADING => $entity->getContentHeading(),
                PageInterface::CONTENT => $entity->getContent(),
                PageInterface::SORT_ORDER => $entity->getSortOrder(),
                PageInterface::LAYOUT_UPDATE_XML => $entity->getLayoutUpdateXml(),
                PageInterface::CUSTOM_THEME => $entity->getCustomTheme(),
                PageInterface::CUSTOM_ROOT_TEMPLATE => $entity->getCustomRootTemplate(),
                PageInterface::CUSTOM_LAYOUT_UPDATE_XML => $entity->getCustomLayoutUpdateXml(),
                PageInterface::CUSTOM_THEME_FROM => $entity->getCustomThemeFrom(),
                PageInterface::CUSTOM_THEME_TO => $entity->getCustomThemeTo(),
                SutunamPageInterface::ACTIVE => $entity->isActive(),
            ]];

            $remotePages = $this->apiHelper->getDataByIdentifier(
                $entity->getIdentifier(),
                ApiHelper::CMS_TYPE_PAGE,
                ['store_ids' => implode(',', $remoteStoreIds)]
            );

            if ($this->configHelper->isSyncMedia()) {
                $data['page'][SutunamPageInterface::MEDIA] = $this->dataHelper->getAllImageFromCmsContent(
                    $entity->getContent()
                );
            }

            if (!$remotePages) {
                $data['page'][SutunamPageInterface::STORE_ID] = $remoteStoreIds;

                $result = $this->apiConnection->request(
                    Request::METHOD_POST,
                    self::SUTUNAM_CMS_PAGE_API_ENDPOINT,
                    [],
                    $data
                );
                $result['type'] = 'created';
                return $result;
            }

            foreach ($remotePages as $remotePage) {
                $remotePageId = $remotePage[SutunamPageInterface::ID];
                $data['page'][SutunamPageInterface::STORE_ID] = $remotePage[SutunamPageInterface::STORE_ID];
                $data['page'][SutunamPageInterface::ID] = $remotePageId;
                $response = $this->apiConnection->request(
                    Request::METHOD_PUT,
                    self::SUTUNAM_CMS_PAGE_API_ENDPOINT . $remotePageId,
                    [],
                    $data
                );
                if ($response['success']) {
                    $result['success'] = true;
                    $result['type'] = 'updated';
                    return $result;
                }

                $result['message'] = $response['message'];
            }
        } else {
            $result['message'] = __("Couldn't load CMS page content.");
        }
        return $result;
    }

    /**
     * Push all cms page to remote host
     *
     * @return array
     */
    public function pushAll()
    {
        $result = [
            'total_update_success' => 0,
            'total_create_success' => 0,
            'total_failed' => 0,
            'message' => ''
        ];

        $allPageIds = $this->pageCollectionFactory->create()->getAllIds();
        if (!$allPageIds) {
            $result['message'] = __(
                'There is no remote page on the remote host %1',
                $this->configHelper->getSiteName()
            );
            return $result;
        }

        foreach ($allPageIds as $pageId) {
            $response = $this->push($pageId);
            if (!$response['success']) {
                $result['total_failed']++;
                $result['message'] .= __(
                    'Push Page ID: %1 ||Status: %2.<br>',
                    $pageId,
                    $response['message']
                );
                continue;
            }

            if ($response['type'] == 'created') {
                $result['total_create_success']++;
                $result['message'] .= __(
                    'Push Page ID: %1 ||Status: %2.<br>',
                    $pageId,
                    __('Created Successfully')
                );
                continue;
            }

            $result['message'] .= __(
                'Push Page ID: %1 ||Status: %2.<br>',
                $pageId,
                __('Updated Successfully')
            );

            $result['total_update_success']++;
        }
        return $result;
    }

    /**
     * Get page by id
     *
     * @param int $id
     * @return false|\Magento\Cms\Model\Page
     */
    protected function getPage($id)
    {
        try {
            return $this->pageRepository->getById($id);
        } catch (\Exception $exception) {
            $this->logger->critical($exception->getMessage());
        }
        return false;
    }

    /**
     * Validate page
     *
     * @param mixed $remotePage
     * @param array $myPageStoreIds
     * @param array $mappingStoreIds
     * @return bool
     */
    public function validatePage($remotePage, $myPageStoreIds, $mappingStoreIds)
    {
        //Get list diff stores id from page found in collection with list store gotten from remote codes list
        $diffIds = array_diff($myPageStoreIds, $mappingStoreIds);
        if ($diffIds) {
            //Check there's any other remote page using diff ids
            $remotePageUsingDiffIds = $this->apiHelper->getDataByIdentifier(
                $remotePage[PageInterface::IDENTIFIER],
                ApiHelper::CMS_TYPE_PAGE,
                $this->dataHelper->getRemoteStoreIdsListByMyStoreIds($diffIds)
            );

            //If yes, skip it
            if ($remotePageUsingDiffIds) {
                return false;
            }
        }
        return true;
    }
}
