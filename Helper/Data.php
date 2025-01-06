<?php

namespace Sutunam\CmsDataSync\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DataObject;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory;
use Magento\Store\Model\StoreRepository;
use Magento\Theme\Model\ThemeFactory;
use Magento\Widget\Model\Widget\Instance;
use Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterface;
use Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterface;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;
use Sutunam\CmsDataSync\Logger\Logger;

class Data
{
    /**
     * @var array
     */
    protected $remoteStoreViews = [];

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
     * @var \Magento\Framework\Filter\FilterManager
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
     * @var \Magento\Theme\Model\ResourceModel\Theme
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
     * @param StoreRepository $storeRepository
     * @param Filesystem $fileSystem
     * @param Config $configHelper
     * @param \Magento\Framework\Filter\FilterManager $filter
     * @param \Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory $instanceCollectionFactory
     * @param ThemeFactory $theme
     * @param \Magento\Theme\Model\ResourceModel\Theme $themeResource
     * @param Logger $logger
     */
    public function __construct(
        ApiHelper $apiHelper,
        StoreManagerInterface $storeManager,
        CollectionFactory $storeCollectionFactory,
        StoreRepository $storeRepository,
        FileSystem $fileSystem,
        ConfigHelper $configHelper,
        \Magento\Framework\Filter\FilterManager $filter,
        \Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory $instanceCollectionFactory,
        ThemeFactory $theme,
        \Magento\Theme\Model\ResourceModel\Theme $themeResource,
        Logger $logger
    ) {
        $this->apiHelper = $apiHelper;
        $this->storeManager = $storeManager;
        $this->storeCollectionFactory = $storeCollectionFactory;
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
     * Get remote store id by my store id
     *
     * @param int $myStoreId
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getRemoteStoreId($myStoreId)
    {
        $myStoreDetails = $this->storeManager->getStore($myStoreId);
        if (!$myStoreDetails) {
            return [
                'status' => false,
                'message' => __('There is no store details associated with this store.'),
            ];
        }
        if (!$this->remoteStoreViews) {
            $this->remoteStoreViews = $this->apiHelper->requestStoreInfo(ApiHelper::REQUEST_STORE_VIEWS);
        }

        $remoteStoreView = $this->remoteStoreViews[$myStoreDetails->getCode()] ?: null;
        if (!$remoteStoreView) {
            return [
                'status' => false,
                'message' => __('There is no remote store associated with this store (store_code).'),
            ];
        }

        return [
            'status' => true,
            'message' => null,
            'data' => [
                'id' => $remoteStoreView['id'],
            ]
        ];
    }

    /**
     * Get store code list by id lists
     *
     * @param array $storeIds
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreCodesByIds($storeIds)
    {
        $result = [];
        foreach ($storeIds as $storeId) {
            $result[] = $this->storeManager->getStore($storeId)->getCode();
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
        if (!$this->remoteStoreViews) {
            $this->remoteStoreViews = $this->apiHelper->requestStoreInfo(ApiHelper::REQUEST_STORE_VIEWS);
        }

        $result = [];

        foreach ($myStoreCodes as $myStoreCode) {
            if (!isset($this->remoteStoreViews[$myStoreCode])) {
                continue;
            }
            $result[] = (int) $this->remoteStoreViews[$myStoreCode]['id'];
        }

        return $result;
    }

    /**
     * Get remote store id list by my store ids
     *
     * @param array $myStoreIds
     * @return array
     */
    public function getRemoteStoreIdsListByMyStoreIds($myStoreIds)
    {
        $myStoreCodes = $this->getStoreCodesByIds($myStoreIds);
        return $this->getRemoteStoreIdsListByMyStoreCodes($myStoreCodes);
    }

    /**
     * Get my store id list by remote store codes
     *
     * @param array $remoteStoreCodes
     * @return array
     */
    public function getMyStoreIdsListByRemoteStoreCodes($remoteStoreCodes)
    {
        $result = [];
        foreach ($remoteStoreCodes as $remoteStoreCode) {
            try {
                $result[] = (int) $this->storeRepository->get($remoteStoreCode)->getId();
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }

        return $result;
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
        $result = [];

        preg_match_all('/{{media url="([^"]+)"}}/', $cmsContent, $matches_json);
        preg_match_all('/{{media url=([^}]+)}}/', $cmsContent, $matches);

        if (is_array($matches) && count($matches) > 1) {
            $imagePaths = $matches[1];
            foreach ($imagePaths as $path) {
                $result[] = $path;
            }
        }

        if (is_array($matches_json) && count($matches_json) > 1) {
            $imagePaths = $matches_json[1];
            foreach ($imagePaths as $path) {
                $result[] = $path;
            }
        }

        return $result;
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
        $checkDuplicatedTitle = $this->instanceCollectionFactory->create()->addFieldToFilter('title', ['eq' => $title]);
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
     * @return array
     */
    public function formatWidgetInstanceParams($widgetInstanceParams)
    {
        if ($widgetInstanceParams instanceof DataObject) {
            $widgetInstanceParams = $widgetInstanceParams->getData();
        }

        return [
            WidgetInstanceInterface::IDENTIFIER => $widgetInstanceParams[WidgetInstanceInterface::IDENTIFIER] ?? '',
            WidgetInstanceInterface::TITLE => $widgetInstanceParams[WidgetInstanceInterface::TITLE] ?? '',
            WidgetInstanceInterface::INSTANCE_TYPE =>
                $widgetInstanceParams[WidgetInstanceInterface::INSTANCE_TYPE] ?? '',
            WidgetInstanceInterface::THEME_CODE =>
                $this->getThemeCodeById($widgetInstanceParams[WidgetInstanceInterface::THEME_ID]) ?? '',
            WidgetInstanceInterface::WIDGET_PARAMETERS =>
                $widgetInstanceParams[WidgetInstanceInterface::WIDGET_PARAMETERS] ?? [],
            WidgetInstanceInterface::SORT_ORDER => $widgetInstanceParams[WidgetInstanceInterface::SORT_ORDER] ?? '',
        ];
    }
}
