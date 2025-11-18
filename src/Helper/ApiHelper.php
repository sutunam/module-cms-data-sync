<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Helper;

use Laminas\Http\Request;
use Magento\Framework\App\Request\DataPersistor;
use Sutunam\CmsDataSync\Helper\ApiConnection;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;
use Sutunam\CmsDataSync\Logger\Logger;

class ApiHelper
{
    public const REQUEST_STORE_WEBSITE = 'websites';
    public const REQUEST_STORE_GROUPS = 'storeGroups';
    public const REQUEST_STORE_VIEWS = 'storeViews';
    public const REQUEST_CMS_SYNC_CONFIGS = 'sync/configs';

    public const TYPE_CODE = 'code';
    public const TYPE_ID = 'id';

    public const CMS_TYPE_BLOCK = 'Block';
    public const CMS_TYPE_PAGE = 'Page';
    public const CMS_TYPE_WIDGET = 'Widget';

    public const REQUEST_SYNC_TYPE_PULL = 'pull';

    public const REQUEST_SYNC_TYPE_PUSH = 'push';

    /**
     * @var \Sutunam\CmsDataSync\Helper\ApiConnection
     */
    protected $api;

    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * @var DataPersistor
     */
    protected $dataPersistor;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $allRemoteBlocks = [];

    /**
     * Constructor
     *
     * @param \Sutunam\CmsDataSync\Helper\ApiConnection $api
     * @param Config $configHelper
     * @param DataPersistor $dataPersistor
     * @param Logger $logger
     */
    public function __construct(
        ApiConnection $api,
        ConfigHelper  $configHelper,
        DataPersistor $dataPersistor,
        Logger $logger
    ) {
        $this->api = $api;
        $this->configHelper = $configHelper;
        $this->dataPersistor = $dataPersistor;
        $this->logger = $logger;
    }

    /**
     * Get remote cms page/block/widget by identifier
     *
     * @param string $identifier
     * @param string $cmsType
     * @param array $requestParam
     * @return false|mixed
     */
    public function getDataByIdentifier($identifier, $cmsType = self::CMS_TYPE_BLOCK, $requestParam = [])
    {
        try {
            $response = $this->api->request(
                Request::METHOD_GET,
                "sync/cms$cmsType/identifier/" . $identifier,
                $requestParam
            );

            if (!$response || !$response['success']) {
                return false;
            }

            return $response['data']['items'];

        } catch (\Exception $exception) {
            $this->logger->critical($exception->getMessage());
        }
        return false;
    }

    /**
     * Get all remote cms block/page/widget depend on cmsType
     *
     * @param string $cmsType
     * @return array|mixed
     */
    public function getAllCms($cmsType)
    {
        try {
            if (!isset($this->allRemoteBlocks[$cmsType]) || empty($this->allRemoteBlocks[$cmsType])) {
                $paramPrefix = 'searchCriteria[filter_groups][0][filters]';
                $queryParams = [
                    $paramPrefix . '[0][field]' => 'identifier',
                    $paramPrefix . '[0][value]' => null,
                    $paramPrefix . '[0][condition_type]' => 'neq',
                ];

                $endpoint = "sync/cms$cmsType/search";

                if ($cmsType == self::CMS_TYPE_WIDGET) {
                    $endpoint = "sync/cms$cmsType";
                    $queryParams = [];
                }

                $response = $this->api->request(Request::METHOD_GET, $endpoint, $queryParams);

                if (!$response || !$response['success']) {
                    return [];
                }
                $this->allRemoteBlocks[$cmsType] = $response['data']['items'];
            }
        } catch (\Exception $exception) {
            $this->logger->critical($exception->getMessage());
        }
        return $this->allRemoteBlocks[$cmsType];
    }

    /**
     * Get remote store info
     *
     * @param string $type
     * @param string $typeKey
     * @return array
     */
    public function requestStoreInfo($type = self::REQUEST_STORE_VIEWS, $typeKey = self::TYPE_CODE)
    {
        $result = [];
        try {
            $response = $this->api->request(Request::METHOD_GET, 'store/' . $type);
            if (!$response || !$response['success']) {
                return $result;
            }

            foreach ($response['data'] as $item) {
                $result[$item[$typeKey]] = $item;
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
        return $result;
    }

    /**
     * Get remote cms sync configs info
     *
     * @return array
     */
    public function getCmsSyncConfigs()
    {
        $result = [];
        try {
            $response = $this->api->request(Request::METHOD_GET, self::REQUEST_CMS_SYNC_CONFIGS);
            if (!$response || !$response['success']) {
                return $result;
            }

            $result = json_decode($response['data'], true);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
        return $result;
    }
}
