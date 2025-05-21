<?php

namespace Sutunam\CmsDataSync\Helper;

use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filter\FilterManager;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory;
use Magento\Store\Model\StoreRepository;
use Magento\Theme\Model\ResourceModel\Theme;
use Magento\Theme\Model\ThemeFactory;
use Magento\Widget\Model\Widget\Instance;
use Sutunam\CmsDataSync\Api\Data\BlockInterface as SutunamBlockInterface;
use Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterface;
use Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterface;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;
use Sutunam\CmsDataSync\Logger\Logger;

class Data
{
    public const STORES_MAPPED = 'stores_mapped';

    public const STORES_UNMAPPED = 'stores_unmapped';

    /**
     * @var array
     */
    protected $remoteStoreViews = [];

    /**
     * @var array
     */
    protected $remoteCmsSyncConfigs = [];

    /**
     * @var ApiHelper
     */
    protected $apiHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CollectionFactory
     */
    protected $storeCollectionFactory;
    /**
     * @var BlockCollectionFactory
     */
    protected $blockCollectionFactory;

    /**
     * @var StoreRepository
     */
    protected $storeRepository;

    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * @var FilterManager
     */
    protected $filter;

    /**
     * @var \Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory
     */
    protected $instanceCollectionFactory;

    /**
     * @var ThemeFactory
     */
    protected $theme;

    /**
     * @var Theme
     */
    protected $themeResource;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param ApiHelper $apiHelper
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $storeCollectionFactory
     * @param BlockCollectionFactory $blockCollectionFactory
     * @param StoreRepository $storeRepository
     * @param Filesystem $fileSystem
     * @param Config $configHelper
     * @param FilterManager $filter
     * @param \Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory $instanceCollectionFactory
     * @param ThemeFactory $theme
     * @param Theme $themeResource
     * @param Logger $logger
     */
    public function __construct(
        ApiHelper $apiHelper,
        StoreManagerInterface $storeManager,
        CollectionFactory $storeCollectionFactory,
        BlockCollectionFactory $blockCollectionFactory,
        StoreRepository $storeRepository,
        FileSystem $fileSystem,
        ConfigHelper $configHelper,
        FilterManager $filter,
        \Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory $instanceCollectionFactory,
        ThemeFactory $theme,
        Theme $themeResource,
        Logger $logger
    ) {
        $this->apiHelper = $apiHelper;
        $this->storeManager = $storeManager;
        $this->storeCollectionFactory = $storeCollectionFactory;
        $this->blockCollectionFactory = $blockCollectionFactory;
        $this->storeRepository = $storeRepository;
        $this->fileSystem = $fileSystem;
        $this->configHelper = $configHelper;
        $this->filter = $filter;
        $this->instanceCollectionFactory = $instanceCollectionFactory;
        $this->theme = $theme;
        $this->themeResource = $themeResource;
        $this->logger = $logger;
    }

    /**
     * Get Store Id by Store Code
     *
     * @param string $code
     * @return int|null
     */
    public function getStoreIdByCode($code)
    {
        try {
            $store = $this->storeRepository->get($code);
            return $store->getId();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Get store code list by id lists
     *
     * @param array $storeIds
     * @return array
     */
    public function getStoreCodesByIds($storeIds)
    {
        try {
            $result = [];
            foreach ($storeIds as $storeId) {
                $result[$storeId] = $this->storeManager->getStore($storeId)->getCode();
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->critical($e->getMessage());
        }
        return $result;
    }

    /**
     * Get remote store id list by my store codes
     *
     * @param array $myStoreCodes
     * @return array
     */
    public function getRemoteStoreIdsListByMyStoreCodes($myStoreCodes)
    {
        $result = [
            self::STORES_MAPPED => [],
            self::STORES_UNMAPPED => []
        ];

        if (!$this->remoteStoreViews) {
            try {
                $this->remoteStoreViews = $this->apiHelper->requestStoreInfo();
            } catch (\Exception $e) {
                $this->logger->critical($e);
                return $result;
            }
        }

        foreach ($myStoreCodes as $myStoreId => $myStoreCode) {
            if (!isset($this->remoteStoreViews[$myStoreCode])) {
                $result[self::STORES_UNMAPPED][$myStoreCode] = $myStoreId;
                continue;
            }
            $result[self::STORES_MAPPED][$myStoreCode]
                = (int) $this->remoteStoreViews[$myStoreCode][ApiHelper::TYPE_ID];
        }

        return $result;
    }

    /**
     * Get remote store id list by my store codes
     *
     * @param array $remoteStoreCodes
     * @return array
     */
    public function getUnmappedRemoteStoreIdsByRemoteStoreCodes($remoteStoreCodes)
    {
        $result = [];

        if (!$this->remoteStoreViews) {
            try {
                $this->remoteStoreViews = $this->apiHelper->requestStoreInfo();
            } catch (\Exception $e) {
                $this->logger->critical($e);
                return $result;
            }
        }

        foreach ($remoteStoreCodes as $remoteStoreCode) {
            if ($this->getStoreIdByCode($remoteStoreCode) != null) {
                continue;
            }
            $result[] = $this->remoteStoreViews[$remoteStoreCode][ApiHelper::TYPE_ID];
        }

        return $result;
    }

    /**
     * Get remote store id list by my store ids
     *
     * @param array $myStoreIds
     * @return array
     */
    public function getMappedRemoteStoreIdsListByMyStoreIds($myStoreIds)
    {
        $myStoreCodes = $this->getStoreCodesByIds($myStoreIds);
        return $this->getRemoteStoreIdsListByMyStoreCodes($myStoreCodes)[self::STORES_MAPPED];
    }

    /**
     * Get my store id list by remote store codes
     *
     * @param array $remoteStoreCodes
     * @return array
     */
    public function getMyMappedStoreIdsListByRemoteStoreCodes($remoteStoreCodes)
    {
        $result = [];
        foreach ($remoteStoreCodes as $remoteStoreCode) {
            if ($this->getStoreIdByCode($remoteStoreCode) == null) {
                continue;
            }
            $result[] = (int) $this->getStoreIdByCode($remoteStoreCode);
        }

        return $result;
    }

    /**
     * Get remote Cms sync configs
     *
     * @return array
     */
    public function getCmsSyncConfigs()
    {
        if (!$this->remoteCmsSyncConfigs) {
            $this->remoteCmsSyncConfigs = $this->apiHelper->getCmsSyncConfigs();
        }

        return $this->remoteCmsSyncConfigs;
    }

    /**
     * Save media from remote host
     *
     * @param string $remoteImagePath
     * @param bool|int $isOverwrite
     * @return false|string
     */
    public function saveMediaFromRemoteHost($remoteImagePath, $isOverwrite)
    {
        try {
            $remoteImageUrlFull = $this->configHelper->getSiteUrl() .
                $this->configHelper->getRemoteMediaSubdir() .
                $remoteImagePath;
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $remoteImageContent = file_get_contents($remoteImageUrlFull);
            if ($remoteImageContent === false) {
                $this->logger->error('Remote image file could not be opened: ' . $remoteImageUrlFull);
                return false;
            }

            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $imageName = basename($remoteImagePath);
            $subDir = str_replace($imageName, '', $remoteImagePath);

            $mediaDirectory = $this->fileSystem->getDirectoryWrite(DirectoryList::MEDIA);
            $mediaAbsolutePath = $mediaDirectory->getAbsolutePath();

            if (!$mediaDirectory->isExist($subDir)) {
                $mediaDirectory->create($subDir);
            }

            $filePath = $mediaAbsolutePath . $subDir . $imageName;

            if ($isOverwrite) {
                $mediaDirectory->writeFile($filePath, $remoteImageContent);
                return $filePath;
            }

            if (!$mediaDirectory->isFile($filePath)) {
                $mediaDirectory->writeFile($filePath, $remoteImageContent);
            }
            return $filePath;
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
        return false;
    }

    /**
     * Get all image in cms content
     *
     * @param string $cmsContent
     * @return array
     */
    public function getAllImageFromCmsContent($cmsContent)
    {
        try {
            $mediaPaths = [];
            $cmsContentDecode = $this->decodingCmsContent($cmsContent);
            preg_match_all(
                '/{{\s*media\s+url\s*=\s*(wysiwyg\/[^}\s]+)\s*}}/',
                $cmsContentDecode,
                $matches1
            );
            $mediaPaths = array_merge($mediaPaths, $matches1[1]);

            preg_match_all(
                '/src=["\'].*?wysiwyg\/([^"\']+\.(?:jpg|jpeg|png|gif|webp))["\']/',
                $cmsContentDecode,
                $matches2
            );
            $mediaPaths = array_merge($mediaPaths, array_map(function ($val) {
                return 'wysiwyg/' . $val;
            }, $matches2[1]));

            preg_match_all(
                '/data-background-images\s*=\s*"([^"]+)"/',
                $cmsContentDecode,
                $jsonMatches
            );

            foreach ($jsonMatches[1] as $jsonString) {
                $jsonDecoded = json_decode(htmlspecialchars_decode($jsonString), true);
                if (!is_array($jsonDecoded)) {
                    continue;
                }
                foreach ($jsonDecoded as $imgPath) {
                    if (preg_match(
                        '/{{\s*media\s+url\s*=\s*(wysiwyg\/[^}\s]+)\s*}}/',
                        $imgPath,
                        $match
                    )) {
                        $mediaPaths[] = $match[1];
                    }
                }
            }

            return array_values(array_unique($mediaPaths));
        } catch (NoSuchEntityException $e) {
            return [];
        }
    }

    /**
     * Decoding CMS content (should be decoding the cms content because widget param potentially has encoding content)
     *
     * @param string $string
     * @return string
     */
    public function decodingCmsContent($string)
    {
        while ($string !== html_entity_decode($string, ENT_QUOTES | ENT_HTML5)) { // phpcs:ignore
            $string = html_entity_decode($string, ENT_QUOTES | ENT_HTML5); // phpcs:ignore
        }

        return $string;
    }

    /**
     * Save all media from cms content
     *
     * @param string $cmsContent
     * @param bool|int $isOverwrite
     * @return void
     */
    public function saveAllImageFromCmsContent($cmsContent, $isOverwrite = false)
    {
        $allImages = $this->getAllImageFromCmsContent($cmsContent);
        foreach ($allImages as $imagePath) {
            $this->saveMediaFromRemoteHost($imagePath, $isOverwrite);
        }
    }

    /**
     * Generated title to identifier
     *
     * @param string|null $title
     * @return string
     */
    public function generateIdentifier($title)
    {
        $checkDuplicatedTitle = $this->instanceCollectionFactory->create()->addFieldToFilter(
            'title',
            ['eq' => $title]
        );
        $lowercaseTitle = strtolower($title);
        if ($checkDuplicatedTitle->getSize() > 1) {
            return $this->filter->translitUrl($lowercaseTitle) . '-' . time();
        }

        return $this->filter->translitUrl($lowercaseTitle);
    }

    /**
     * Get Theme code by theme id
     *
     * @param int|string $themeId
     * @return string
     */
    public function getThemeCodeById($themeId)
    {
        /** @phpstan-ignore-next-line */
        return $this->theme->create()->load($themeId)->getCode();
    }

    /**
     * Get Theme id by theme code
     *
     * @param string|null $themeCode
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getThemeIdByCode($themeCode)
    {
        $connection = $this->themeResource->getConnection();

        $select = $connection->select()->from(
            $this->themeResource->getMainTable(),
            'theme_id'
        )->where('code = :code');

        $bind = [':code' => (string)$themeCode];

        return $connection->fetchOne($select, $bind);
    }

    /**
     * Convert page group from param to correct format using for saving
     *
     * @param WidgetInstancePageInterface[]|mixed $listPageGroup
     * @return array
     */
    public function formatPageGroupsToSave($listPageGroup)
    {
        $result = [];
        foreach ($listPageGroup as $pageGroup) {
            $page = [];
            $page[WidgetInstancePageInterface::PAGE_GROUP] = $pageGroup[WidgetInstancePageInterface::PAGE_GROUP] ?? '';
            $page[WidgetInstancePageInterface::PAGES] = [WidgetInstancePageInterface::LAYOUT_HANDLE => ''];
            $page[WidgetInstancePageInterface::PAGE_LAYOUTS] = [WidgetInstancePageInterface::LAYOUT_HANDLE => ''];
            $page[$pageGroup[WidgetInstancePageInterface::PAGE_GROUP]] = [
                WidgetInstancePageInterface::PAGE_ID => 0,
                WidgetInstancePageInterface::LAYOUT_HANDLE =>
                    $pageGroup[WidgetInstancePageInterface::LAYOUT_HANDLE] ?? '',
                WidgetInstancePageInterface::FOR => $pageGroup[WidgetInstancePageInterface::PAGE_FOR] ?? '',
                WidgetInstancePageInterface::BLOCK => $pageGroup[WidgetInstancePageInterface::BLOCK_REFERENCE] ?? '',
                WidgetInstancePageInterface::TEMPLATE => $pageGroup[WidgetInstancePageInterface::PAGE_TEMPLATE] ?? '',
                WidgetInstancePageInterface::ENTITIES => $pageGroup[WidgetInstancePageInterface::ENTITIES] ?? '',
            ];
            $result[] = $page;
        }
        return $result;
    }

    /**
     * Convert page groups to the correct format of API
     *
     * @param array $listPageGroupParams
     * @return array
     */
    public function formatPageGroupsToParams($listPageGroupParams)
    {
        $pageGroups = [];
        foreach ($listPageGroupParams as $pageGroup) {
            $pageGroups[] = [
                WidgetInstancePageInterface::PAGE_ID => 0,
                WidgetInstancePageInterface::PAGE_GROUP => $pageGroup[WidgetInstancePageInterface::PAGE_GROUP] ?? '',
                WidgetInstancePageInterface::LAYOUT_HANDLE =>
                    $pageGroup[WidgetInstancePageInterface::LAYOUT_HANDLE] ?? '',
                WidgetInstancePageInterface::BLOCK_REFERENCE =>
                    $pageGroup[WidgetInstancePageInterface::BLOCK_REFERENCE] ?? '',
                WidgetInstancePageInterface::PAGE_FOR => $pageGroup[WidgetInstancePageInterface::PAGE_FOR] ?? '',
                WidgetInstancePageInterface::ENTITIES => $pageGroup[WidgetInstancePageInterface::ENTITIES] ?? '',
                WidgetInstancePageInterface::PAGE_TEMPLATE =>
                    $pageGroup[WidgetInstancePageInterface::PAGE_TEMPLATE] ?? '',
            ];
        }
        return $pageGroups;
    }

    /**
     * Format widget instance params to correct format
     *
     * @param WidgetInstanceInterface|Instance|array $widgetInstanceParams
     * @param string $action
     * @return array
     */
    public function formatWidgetInstanceParams($widgetInstanceParams, $action): array
    {
        if ($widgetInstanceParams instanceof DataObject) {
            $widgetInstanceParams = $widgetInstanceParams->getData();
        }

        $widgetInstanceWidgetParams = $this->replaceDomainInContentByAction(
            $widgetInstanceParams[WidgetInstanceInterface::WIDGET_PARAMETERS],
            $action
        ) ?? [];

        return [
            WidgetInstanceInterface::IDENTIFIER => $widgetInstanceParams[WidgetInstanceInterface::IDENTIFIER] ?? '',
            WidgetInstanceInterface::TITLE => $widgetInstanceParams[WidgetInstanceInterface::TITLE] ?? '',
            WidgetInstanceInterface::INSTANCE_TYPE =>
                $widgetInstanceParams[WidgetInstanceInterface::INSTANCE_TYPE] ?? '',
            WidgetInstanceInterface::THEME_CODE =>
                $this->getThemeCodeById($widgetInstanceParams[WidgetInstanceInterface::THEME_ID]) ?? '',
            WidgetInstanceInterface::WIDGET_PARAMETERS => $widgetInstanceWidgetParams,
            WidgetInstanceInterface::SORT_ORDER => $widgetInstanceParams[WidgetInstanceInterface::SORT_ORDER] ?? '',
        ];
    }

    /**
     * Replace Content BlockId
     *
     * @param string $html
     * @param string $action
     * @return string
     * @phpcs:disable Generic.Metrics.NestingLevel.TooHigh
     */
    public function replaceContentBlockId(string $html, string $action): string
    {
        try {
            // Load HTML to DOMDocument
            $dom = new \DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query('//div[@data-content-type="block"]');

            $search = [];
            $replace = [];

            foreach ($nodes as $node) {
                $widgetText = $dom->saveXML($node);

                if (preg_match('/block_id="([^"]+)"/', $widgetText, $matches)) {
                    $oldBlockId = $matches[1];

                    if ($action === ApiHelper::REQUEST_SYNC_TYPE_PUSH) {
                        $blockInContent = $this->blockCollectionFactory->create()
                            ->addFieldToFilter(BlockInterface::BLOCK_ID, $oldBlockId)
                            ->setPageSize(1)->getFirstItem();

                        if ($blockInContent->getId()) {
                            $remoteBlock = $this->apiHelper->getDataByIdentifier(
                                $blockInContent->getIdentifier(),
                                ApiHelper::CMS_TYPE_BLOCK,
                                ['store_ids' => implode(',', $blockInContent->getStores())]
                            );

                            if (!empty($remoteBlock[0]['id'])) {
                                $newBlockId = $remoteBlock[0]['id'];
                                $search[] = 'block_id="' . $oldBlockId . '"';
                                $replace[] = 'block_id="' . $newBlockId . '"';
                            }
                        }
                    } elseif ($action === ApiHelper::REQUEST_SYNC_TYPE_PULL) {
                        $allRemoteBlocks = $this->apiHelper->getAllCms(ApiHelper::CMS_TYPE_BLOCK);

                        foreach ($allRemoteBlocks as $block) {
                            if ($block['id'] == $oldBlockId && isset($block[BlockInterface::IDENTIFIER])) {
                                $mappedStoreIds = $this->getMyMappedStoreIdsListByRemoteStoreCodes(
                                    $block[SutunamBlockInterface::STORE_CODE] ?? []
                                );

                                $blockInContent = $this->blockCollectionFactory->create()->addFieldToFilter(
                                    BlockInterface::IDENTIFIER,
                                    $block[BlockInterface::IDENTIFIER]
                                )->addFieldToFilter(
                                    SutunamBlockInterface::STORE_ID,
                                    ['in' => implode(',', $mappedStoreIds)]
                                )->setPageSize(1)->getFirstItem();

                                if ($blockInContent->getId()) {
                                    $search[] = 'block_id="' . $oldBlockId . '"';
                                    $replace[] = 'block_id="' . $blockInContent->getId() . '"';
                                }
                            }
                        }
                    }
                }
            }

            if (!empty($search)) {
                $html = str_replace($search, $replace, $html);
            }
        } catch (NoSuchEntityException $exception) {
            return $html;
        }

        return $html;
    }

    /**
     * Replace the domain existed on cms content
     *
     * @param string $cmsContent
     * @param string $action
     * @return string|null
     */
    public function replaceDomainInContentByAction($cmsContent, $action): ?string
    {
        try {
            $remoteSiteUrl = $this->configHelper->getSiteUrl();
            $mySiteUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
            $cmsContent = ($action == ApiHelper::REQUEST_SYNC_TYPE_PULL) ? str_replace(
                str_replace('/', '\/', $remoteSiteUrl),
                str_replace('/', '\/', $mySiteUrl),
                $cmsContent
            ) : (($action == ApiHelper::REQUEST_SYNC_TYPE_PUSH) ? str_replace(
                str_replace('/', '\/', $mySiteUrl),
                str_replace('/', '\/', $remoteSiteUrl),
                $cmsContent
            ) : $cmsContent);

            $cmsContent = ($action == ApiHelper::REQUEST_SYNC_TYPE_PULL) ? str_replace(
                $remoteSiteUrl,
                $mySiteUrl,
                $cmsContent
            ) : (($action == ApiHelper::REQUEST_SYNC_TYPE_PUSH) ? str_replace(
                $mySiteUrl,
                $remoteSiteUrl,
                $cmsContent
            ) : $cmsContent);

            return $this->replaceContentBlockId($cmsContent, $action);
        } catch (NoSuchEntityException $exception) {
            return null;
        }
    }
}
