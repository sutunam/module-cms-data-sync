<?php
namespace Sutunam\CmsDataSync\Model\Processors\Cms;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\PageRepository;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
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
    public const SUTUNAM_CMS_PAGE_API_ENDPOINT = "sync/cmsPage/";

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
     * @var StoreManagerInterface
     */
    protected $storeManager;

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
     * @param StoreManagerInterface $storeManager
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
        StoreManagerInterface $storeManager,
        Logger $logger
    ) {
        $this->pageFactory = $pageFactory;
        $this->pageRepository = $pageRepository;
        $this->apiHelper = $apiHelper;
        $this->apiConnection = $apiConnection;
        $this->dataHelper = $dataHelper;
        $this->configHelper = $configHelper;
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->storeManager = $storeManager;
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
            'notice_message' => '',
            'error_message' => ''
        ];

        $page = $this->getPage($id);
        if (!$page) {
            $result['error_message'] = __('Couldn\'t load CMS page content.');
        }

        $pageStoreIds = $page->getStores();
        $pageStoreCodes = $this->dataHelper->getStoreCodesByIds($pageStoreIds);
        $remoteStoreIds = $this->dataHelper->getRemoteStoreIdsListByMyStoreCodes($pageStoreCodes);
        $mappedStoresIds = $remoteStoreIds[DataHelper::STORES_MAPPED];

        if (!$mappedStoresIds) {
            $result['error_message'] = __('The store scope of page is not match.');
            return $result;
        }

        $remotePages = $this->apiHelper->getDataByIdentifier(
            $page->getIdentifier(),
            ApiHelper::CMS_TYPE_PAGE,
            ['store_ids' => implode(',', $mappedStoresIds)]
        );
        if (!$remotePages) {
            $result['error_message'] = __('There is no remote page with identifier %1', $page->getIdentifier());
            return $result;
        }

        if (count($remotePages) != 1) {
            $result['error_message'] = __('The store scope of page is not match.');
            return $result;
        }

        $remotePage = $remotePages[0];

        if ($this->configHelper->isOverwrite()) {
            $storeCodesOfRemotePage = $remotePage[SutunamPageInterface::STORE_CODE];
            $mappingStoreIdByCodesOfRemotePage = $this->dataHelper->getMyMappedStoreIdsListByRemoteStoreCodes(
                $storeCodesOfRemotePage
            );

            if ($diff = array_diff($mappingStoreIdByCodesOfRemotePage, $pageStoreIds)) {
                $checkPageUsingDiffStores = $this->pageCollectionFactory->create()
                    ->addFieldToFilter(PageInterface::IDENTIFIER, ['eq' => $page->getIdentifier()])
                    ->addFieldToFilter(SutunamPageInterface::STORE_ID, ['in' => $diff])
                    ->addFieldToFilter(PageInterface::PAGE_ID, ['neq' => $id])
                    ->getSize();
                if (!$checkPageUsingDiffStores) {
                    $pageStoreIds = $this->dataHelper->mergeTwoArrays(
                        $mappingStoreIdByCodesOfRemotePage,
                        $remoteStoreIds[DataHelper::STORES_UNMAPPED]
                    );
                }
            } else {
                $pageStoreIds = $mappingStoreIdByCodesOfRemotePage;
            }
        }

        try {
            $page->setData($remotePage);
            $page->setContent($this->dataHelper->replaceDomainInContentByAction(
                $page->getContent(),
                ApiHelper::REQUEST_SYNC_TYPE_PULL
            ));
            $page->setStoreId(array_map('intval', $pageStoreIds));

            $page->setId($id);
            $page->save();
            if ($this->configHelper->isSyncMedia()) {
                $this->dataHelper->saveAllImageFromCmsContent($page->getContent(), true);
            }
        } catch (\Exception $e) {
            $result['error_message'] = $e->getMessage();
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
            'error_message' => '',
            'notice_message' => '',
            'success_message' => ''
        ];

        $allRemotePages = $this->apiHelper->getAllCms(ApiHelper::CMS_TYPE_PAGE);
        if (!$allRemotePages) {
            $result['error_message'] = __(
                'There is no remote page on the remote host %1',
                $this->configHelper->getSiteName()
            );
            return $result;
        }
        foreach ($allRemotePages as $remotePage) {
            //Get Stores Ids list on my site that have the same values code with remote codes list
            $myStoreIds = $this->dataHelper->getMyMappedStoreIdsListByRemoteStoreCodes(
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

            // This variable is to check what store ids were used on my site,
            // so we can create the new cms with the stores weren't used when overwrite is disabled.
            $usagedStores = [];

            if ($myPages->getSize() > 0) {
                foreach ($myPages as $myPage) {
                    $usagedStores = $this->dataHelper->mergeTwoArrays($usagedStores, $myPage->getStores());
                    if (!$this->configHelper->isOverwrite()) {
                        $result['notice_message'] .= __(
                            'Pull Page ID: %1 || Status: %2.<br>',
                            $myPage->getId(),
                            __('Overwrite disabled - Skipped')
                        );
                        continue;
                    }

                    $response = $this->pull($myPage->getId());
                    if (!$response['success']) {
                        $result['total_failed']++;
                        $result['error_message'] .= ($response['error_message'] ? __(
                            'Pull Page ID: %1 || Status: %2.<br>',
                            $myPage->getId(),
                            $response['error_message']
                        ) : '');
                        $result['notice_message'] .= ($response['notice_message'] ? __(
                            'Pull Page ID: %1 || Status: %2.<br>',
                            $myPage->getId(),
                            $response['notice_message']
                        ) : '');
                        continue;
                    }

                    $result['success_message'] .= __(
                        'Pull Page ID: %1 || Status: %2.<br>',
                        $myPage->getId(),
                        __('Updated Successfully')
                    );
                    $result['total_update_success']++;
                }
            }

            if (!array_diff($myStoreIds, $usagedStores)) {
                continue;
            }

            try {
                $myPage = $this->pageFactory->create();

                unset($remotePage[SutunamPageInterface::ID]);
                $myPageStoreIds = array_diff($myStoreIds, $usagedStores);

                $myPage->setData($remotePage);
                $myPage->setStoreId(array_map('intval', $myPageStoreIds));
                $myPage->setContent($this->dataHelper->replaceDomainInContentByAction(
                    $myPage->getContent(),
                    ApiHelper::REQUEST_SYNC_TYPE_PULL
                ));

                // @phpstan-ignore-next-line
                $myPage->save();

                $result['success_message'] .= __(
                    'Pull Page ID: %1 || Status: %2.<br>',
                    $myPage->getId(),
                    __('Created Successfully')
                );
                $result['total_create_success']++;

                if ($this->configHelper->isSyncMedia()) {
                    $this->dataHelper->saveAllImageFromCmsContent(
                        $myPage->getContent(),
                        $this->configHelper->isOverwrite()
                    );
                }
            } catch (\Exception $e) {
                $result['total_failed']++;
                $result['error_message'] .= __(
                    'Pull Page || Status: Create - %1.<br>',
                    $e->getMessage()
                );
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
            'success' => false,
            'type' => '',
            'error_message' => '',
            'notice_message' => '',
            'success_message' => '',
        ];

        $entity = $this->getPage($id);
        if ($entity) {
            $remoteCmsSyncConfigs = $this->dataHelper->getCmsSyncConfigs();
            if (!$remoteCmsSyncConfigs) {
                $result['error_message'] = __(
                    'Something went wrong when trying to get configs of remote host %1',
                    $this->configHelper->getSiteName()
                );
                return $result;
            }

            $isRemoteHostOverWritable = $remoteCmsSyncConfigs[ConfigHelper::XML_CMS_DATA_SYNC_IS_OVERWRITE] ?: 0;

            $remoteStoreIds = $this->dataHelper->getMappedRemoteStoreIdsListByMyStoreIds($entity->getStores());

            if (!$remoteStoreIds) {
                $result['error_message'] = __('No remote store found by page id %1', $id);
                return $result;
            }

            $data = ["page" => [
                PageInterface::IDENTIFIER => $entity->getIdentifier(),
                PageInterface::TITLE => $entity->getTitle(),
                PageInterface::PAGE_LAYOUT  => $entity->getPageLayout(),
                PageInterface::META_TITLE => $entity->getMetaTitle(),
                PageInterface::META_KEYWORDS => $entity->getMetaKeywords(),
                PageInterface::META_DESCRIPTION => $entity->getMetaDescription(),
                PageInterface::CONTENT_HEADING => $entity->getContentHeading(),
                PageInterface::CONTENT => $this->dataHelper->replaceDomainInContentByAction(
                    $entity->getContent(),
                    ApiHelper::REQUEST_SYNC_TYPE_PUSH
                ),
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
                ApiHelper::CMS_TYPE_PAGE
            );

            $data['page'][SutunamPageInterface::MEDIA] = $this->dataHelper->getAllImageFromCmsContent(
                $entity->getContent()
            );

            if (!$remotePages) {
                $data['page'][SutunamPageInterface::STORE_ID] = $remoteStoreIds;
                $response = $this->apiConnection->request(
                    Request::METHOD_POST,
                    self::SUTUNAM_CMS_PAGE_API_ENDPOINT,
                    [],
                    $data
                );

                if ($response['success']) {
                    $result['success'] = $response['success'];
                    $result['type'] = 'created';
                    $result['success_message'] = __(
                        'Created new remote page with ID: %1 (My page ID: %2) on remote host %3 successfully',
                        $response['data'][SutunamPageInterface::ID],
                        $id,
                        $this->configHelper->getSiteName()
                    );
                    return $result;
                }

                $result['error_message'] = __(
                    'Created new remote page for my page ID: %1 failed - Reason:%2',
                    $id,
                    $response['message']
                );

                return $result;
            }

            $remoteUsedStoreCodes = [];

            foreach ($remotePages as $remotePage) {
                $remoteUsedStoreCodes = $this->dataHelper->mergeTwoArrays(
                    $remoteUsedStoreCodes,
                    $remotePage[SutunamPageInterface::STORE_CODE]
                );
            }

            $myStoreIdsByRemoteStoreCodes
                = $this->dataHelper->getMyMappedStoreIdsListByRemoteStoreCodes($remoteUsedStoreCodes);
            $checkNumOfPageWithIdentifier = $this->pageCollectionFactory->create()
                ->addFieldToFilter(PageInterface::IDENTIFIER, ['eq' => $entity->getIdentifier()])
                ->addFieldToFilter(SutunamPageInterface::STORE_ID, ['in' => $myStoreIdsByRemoteStoreCodes])
                ->getSize();
            if ($checkNumOfPageWithIdentifier > 1) {
                $result['error_message'] = __(
                    'Update remote page for my page ID: %1 failed - Reason:%2',
                    $id,
                    'There\'s other CMS page affects with the same remote CMS page except page ID: ' . $id
                );
                return $result;
            }

            $countRemotePages = count($remotePages);
            $remoteUsedStoreIds = [];
            foreach ($remotePages as $remotePage) {
                $remotePageId = $remotePage[SutunamPageInterface::ID];
                $remotePageStoreIds = $remotePage[SutunamPageInterface::STORE_ID];
                $remoteUsedStoreIds = $this->dataHelper->mergeTwoArrays($remoteUsedStoreIds, $remotePageStoreIds);
                if (!$isRemoteHostOverWritable) {
                    $result['notice_message'] .= __(
                        'Update remote page for my page ID: %1 (Remote Page ID: %2) was skipped'
                        .' - Reason: Overwrite disabled on the remote host %3',
                        $id,
                        $remotePageId,
                        $this->configHelper->getSiteName()
                    );
                    continue;
                }

                $storeIdsParams = $remotePageStoreIds;
                if ($countRemotePages == 1) {
                    $storeIdsParams = $remoteStoreIds;
                    $remoteUsedStoreIds = $remoteStoreIds;
                }
                $data['page'][SutunamPageInterface::STORE_ID] = $this->dataHelper->mergeTwoArrays(
                    $storeIdsParams,
                    $this->dataHelper->getUnmappedRemoteStoreIdsByRemoteStoreCodes(
                        $remotePage[SutunamPageInterface::STORE_CODE]
                    )
                );

                $data['page'][SutunamPageInterface::ID] = $remotePageId;

                $response = $this->apiConnection->request(
                    Request::METHOD_PUT,
                    self::SUTUNAM_CMS_PAGE_API_ENDPOINT . $remotePageId,
                    [],
                    $data
                );

                if ($response['success']) {
                    $result['success'] = true;
                    $result['success_message'] .= __(
                        'Update remote page for my page ID: %1
                        (Remote page ID: %2) on remote host %2 successfully',
                        $id,
                        $remotePageId,
                        $this->configHelper->getSiteName()
                    );
                } else {
                    $result['error_message'] .= __(
                        'Update remote page for my page ID: %1
                        (Remote Page ID: %2) failed - Reason:%3',
                        $id,
                        $remotePageId,
                        $response['message']
                    );
                }
            }

            if ($diff = array_diff($remoteStoreIds, $remoteUsedStoreIds)) {
                $data['page'][SutunamPageInterface::STORE_ID] = $diff;
                unset($data['page'][SutunamPageInterface::ID]);
                $response = $this->apiConnection->request(
                    Request::METHOD_POST,
                    self::SUTUNAM_CMS_PAGE_API_ENDPOINT,
                    [],
                    $data
                );

                if ($response['success']) {
                    $result['success'] = true;
                    $result['success_message'] .= __(
                        'Created new remote page with ID: %1 (
                        My page ID: %2) on remote host %3 successfully',
                        $response['data'][SutunamPageInterface::ID],
                        $id,
                        $this->configHelper->getSiteName()
                    );
                } else {
                    $result['error_message'] .= __(
                        'Created new remote page for my page ID: %1 failed - Reason:%2',
                        $id,
                        $response['message']
                    );
                }
            }
            $result['type'] = 'updated';
        } else {
            $result['error_message'] = __("Couldn't load CMS page content");
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
            'error_message' => '',
            'notice_message' => '',
            'success_message' => '',
        ];

        $allPageIds = $this->pageCollectionFactory->create()->getAllIds();
        if (!$allPageIds) {
            $result['error_message'] = __(
                'There is no remote page on the remote host %1',
                $this->configHelper->getSiteName()
            );
            return $result;
        }

        foreach ($allPageIds as $pageId) {
            $response = $this->push($pageId);
            if (!$response['success']) {
                $result['total_failed']++;
                $result['error_message'] .= ($response['error_message'] ? __(
                    'Push Page ID: %1 ||Status: %2.<br>',
                    $pageId,
                    $response['error_message']
                ) : '');
                $result['notice_message'] .= ($response['notice_message'] ? __(
                    'Push Page ID: %1 ||Status: %2.<br>',
                    $pageId,
                    $response['notice_message']
                ) : '');
                continue;
            }

            if ($response['type'] == 'created') {
                $result['total_create_success']++;
                $result['success_message'] .= __(
                    'Push Page ID: %1 ||Status: %2.<br>',
                    $pageId,
                    __('Created Successfully')
                );
                continue;
            }

            $result['success_message'] .= __(
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
}
