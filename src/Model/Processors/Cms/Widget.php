<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Model\Processors\Cms;

use Laminas\Http\Request;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory;
use Magento\Widget\Model\Widget\Instance;
use Magento\Widget\Model\Widget\InstanceFactory;
use Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterface;
use Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterface;
use Sutunam\CmsDataSync\Api\ProcessorInterface;
use Sutunam\CmsDataSync\Api\WidgetRepositoryInterface;
use Sutunam\CmsDataSync\Helper\ApiConnection;
use Sutunam\CmsDataSync\Helper\ApiHelper;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;
use Sutunam\CmsDataSync\Helper\Data as DataHelper;
use Sutunam\CmsDataSync\Model\WidgetInstance;
use Psr\Log\LoggerInterface;

class Widget implements ProcessorInterface
{

    public const SUTUNAM_CMS_WIDGET_API_ENDPOINT = 'sync/cmsWidget/';

    /**
     * @var WidgetRepositoryInterface
     */
    protected $widgetRepository;

    /**
     * @var InstanceFactory
     */
    protected $instanceFactory;

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
     * @var CollectionFactory
     */
    protected $instanceCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param WidgetRepositoryInterface $widgetRepository
     * @param InstanceFactory $instanceFactory
     * @param ApiHelper $apiHelper
     * @param ApiConnection $apiConnection
     * @param DataHelper $dataHelper
     * @param ConfigHelper $configHelper
     * @param CollectionFactory $instanceCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        WidgetRepositoryInterface $widgetRepository,
        InstanceFactory $instanceFactory,
        ApiHelper $apiHelper,
        ApiConnection $apiConnection,
        DataHelper $dataHelper,
        ConfigHelper $configHelper,
        CollectionFactory $instanceCollectionFactory,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->instanceFactory = $instanceFactory;
        $this->widgetRepository = $widgetRepository;
        $this->apiHelper = $apiHelper;
        $this->apiConnection = $apiConnection;
        $this->dataHelper = $dataHelper;
        $this->configHelper = $configHelper;
        $this->instanceCollectionFactory = $instanceCollectionFactory;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Pull cms widget instance from remote host by my id
     *
     * @param string $id
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

        /** @var WidgetInstance $widgetInstance */
        $widgetInstance = $this->getWigetInstance($id);
        if (!$widgetInstance) {
            $result['notice_message'] = __('Could\'t load CMS widget content.');
        }

        $widgetInstanceStoreIds = $widgetInstance->getStoreIds();
        $widgetInstanceStoreCodes = $this->dataHelper->getStoreCodesByIds($widgetInstanceStoreIds);
        $remoteStoreIds = $this->dataHelper->getRemoteStoreIdsListByMyStoreCodes($widgetInstanceStoreCodes);

        $mappedStoresIds = $remoteStoreIds[DataHelper::STORES_MAPPED];
        if (!$mappedStoresIds) {
            $result['error_message'] = __('The store scope of widget is not match.');
            return $result;
        }

        $remoteWidgetInstances = $this->apiHelper->getDataByIdentifier(
            $widgetInstance->getData(WidgetInstanceInterface::IDENTIFIER),
            ApiHelper::CMS_TYPE_WIDGET,
            ['store_ids' => implode(',', $mappedStoresIds)]
        );

        if (!$remoteWidgetInstances) {
            $result['error_message'] = __(
                'There is no remote widget instance with identifier %1',
                $widgetInstance->getData(WidgetInstanceInterface::IDENTIFIER)
            );
            return $result;
        }

        if (count($remoteWidgetInstances) != 1) {
            $result['error_message'] = __('The store scope of widget instance is not match.');
            return $result;
        }

        $remoteWidgetInstance = $remoteWidgetInstances[0];

        $myThemeId = $this->dataHelper->getThemeIdByCode($remoteWidgetInstance[WidgetInstanceInterface::THEME_CODE]);
        if (!$myThemeId) {
            $result['error_message'] = __(
                'There is no theme corresponding to the remote theme code %.',
                $remoteWidgetInstance[WidgetInstanceInterface::THEME_CODE]
            );
            return $result;
        }

        if ($this->configHelper->isOverwrite()) {
            $storeCodesOfRemoteWidget = $remoteWidgetInstance[WidgetInstanceInterface::STORE_CODES];
            $mappingStoreIdByCodesOfRemoteWidget = $this->dataHelper->getMyMappedStoreIdsListByRemoteStoreCodes(
                $storeCodesOfRemoteWidget
            );

            if ($diff = array_diff($mappingStoreIdByCodesOfRemoteWidget, $widgetInstanceStoreIds)) {
                $checkInstanceUsingDiffStores = $this->instanceCollectionFactory->create()
                    ->addFieldToFilter(WidgetInstanceInterface::IDENTIFIER, ['eq' => $widgetInstance->getIdentifier()])
                    ->addFieldToFilter(WidgetInstanceInterface::INSTANCE_ID, ['neq' => $id])
                    ->addStoreFilter($diff)
                    ->getSize();
                if (!$checkInstanceUsingDiffStores) {
                    $widgetInstanceStoreIds = array_merge(
                        $remoteStoreIds[DataHelper::STORES_UNMAPPED],
                        $mappingStoreIdByCodesOfRemoteWidget
                    );
                }
            } else {
                $widgetInstanceStoreIds = $mappingStoreIdByCodesOfRemoteWidget;
            }
        }

        try {
            $data = $this->dataHelper->formatWidgetInstanceParams(
                $remoteWidgetInstance,
                ApiHelper::REQUEST_SYNC_TYPE_PULL
            );
            $widgetInstance->setData($data);
            $widgetInstance->setThemeId($myThemeId);
            $widgetInstance->setStoreIds($widgetInstanceStoreIds);
            $widgetInstance->setPageGroups(
                $this->dataHelper->formatPageGroupsToSave($remoteWidgetInstance[WidgetInstanceInterface::PAGE_GROUPS])
            );
            $widgetInstance->setId($id);
            $widgetInstance->save(); /** @phpstan-ignore-line */
            if ($this->configHelper->isSyncMedia()) {
                $this->dataHelper->saveAllImageFromCmsContent(
                    json_encode($widgetInstance->getWidgetParameters()),
                    $this->configHelper->isOverwrite()
                );
            }
        } catch (\Exception $e) {
            $result['error_message'] = $e->getMessage();
            $this->logger->critical($e->getMessage());
        }
        $result['success'] = true;
        return $result;
    }

    /**
     * Pull all cms widget from remote host
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

        $allRemoteWidgetInstances = $this->apiHelper->getAllCms(ApiHelper::CMS_TYPE_WIDGET);
        if (!$allRemoteWidgetInstances) {
            $result['error_message'] = __(
                'There is no remote widget instance on the remote host %1',
                $this->configHelper->getSiteName()
            );
            return $result;
        }
        foreach ($allRemoteWidgetInstances as $remoteWidgetInstance) {
            $myThemeId = $this->dataHelper->getThemeIdByCode(
                $remoteWidgetInstance[WidgetInstanceInterface::THEME_CODE]
            );

            if (!$myThemeId) {
                continue;
            }

            //Get Stores Ids list on my site that have the same values code with remote codes list
            $myStoreIds = $this->dataHelper->getMyMappedStoreIdsListByRemoteStoreCodes(
                $remoteWidgetInstance[WidgetInstanceInterface::STORE_CODES]
            );

            //Skip this remote widget if there's not stores compatibled
            if (!$myStoreIds) {
                continue;
            }

            //Get widgets by identifier and my store list
            $myWidgetInstances = $this->instanceCollectionFactory->create()
                ->addFieldToFilter(
                    WidgetInstanceInterface::IDENTIFIER,
                    [
                        'eq' => $remoteWidgetInstance[WidgetInstanceInterface::IDENTIFIER]
                    ]
                )
                ->addStoreFilter($myStoreIds);

            $usagedStores = [];

            if ($myWidgetInstances->getSize() > 0) {
                foreach ($myWidgetInstances as $myWidgetInstance) {
                    // phpcs:ignore Magento2.Performance.ForeachArrayMerge.ForeachArrayMerge
                    $usagedStores = array_merge($myWidgetInstance->getStoreIds(), $usagedStores);
                    if (!$this->configHelper->isOverwrite()) {
                        $result['notice_message'] .= __(
                            'Pull Widget ID: %1 || Status: %2.<br>',
                            $myWidgetInstance->getId(),
                            __('Overwrite disabled - Skipped')
                        );
                        continue;
                    }

                    $response = $this->pull($myWidgetInstance->getId());
                    if (!$response['success']) {
                        $result['total_failed']++;
                        $result['error_message'] .= ($response['error_message'] ? __(
                            'Pull Widget ID: %1 || Status: %2.<br>',
                            $myWidgetInstance->getId(),
                            $response['error_message']
                        ) : '');
                        $result['notice_message'] .= ($response['notice_message'] ? __(
                            'Pull Widget ID: %1 || Status: %2.<br>',
                            $myWidgetInstance->getId(),
                            $response['notice_message']
                        ) : '');
                        continue;
                    }

                    $result['success_message'] .= __(
                        'Pull Widget ID: %1 || Status: %2.<br>',
                        $myWidgetInstance->getId(),
                        __('Updated successfully')
                    );
                    $result['total_update_success']++;
                }
            }

            if (!array_diff($myStoreIds, $usagedStores)) {
                continue;
            }

            try {
                $myWidgetInstance = $this->instanceFactory->create();

                unset($remoteWidgetInstance[WidgetInstanceInterface::ID]);
                $myWidgetInstanceStoreIds  = array_diff($myStoreIds, $usagedStores);

                $data = $this->dataHelper->formatWidgetInstanceParams(
                    $remoteWidgetInstance,
                    ApiHelper::REQUEST_SYNC_TYPE_PULL
                );

                $myWidgetInstance->setData($data);
                $myWidgetInstance->setStoreIds(implode(',', $myWidgetInstanceStoreIds));
                $myWidgetInstance->setThemeId($myThemeId);
                $myWidgetInstance->setPageGroups(
                    $this->dataHelper->formatPageGroupsToSave(
                        $remoteWidgetInstance[WidgetInstanceInterface::PAGE_GROUPS]
                    )
                );

                // @phpstan-ignore-next-line
                $myWidgetInstance->save();

                if ($this->configHelper->isSyncMedia()) {
                    $this->dataHelper->saveAllImageFromCmsContent(
                        json_encode($myWidgetInstance->getWidgetParameters()),
                        $this->configHelper->isOverwrite()
                    );
                }

                $result['total_create_success']++;
                $result['success_message'] .= __(
                    'Pull Widget ID: %1 ||Status: %2.<br>',
                    $myWidgetInstance->getId(),
                    __('Created successfully')
                );

            } catch (\Exception $e) {
                $result['total_failed']++;
                $result['error_message'] .= __(
                    'Pull Widget || Status: Create - %1.<br>',
                    $e->getMessage()
                );
                $this->logger->critical($e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Push cms widget to remote host by my id
     *
     * @param string $id
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

        $entity = $this->getWigetInstance($id);
        if ($entity) {
            $remoteCmsSyncConfigs = $this->dataHelper->getCmsSyncConfigs();
            if (!$remoteCmsSyncConfigs) {
                $result['error_message'] = __(
                    'Failed to retrieve configuration from remote host %1',
                    $this->configHelper->getSiteName()
                );
                return $result;
            }

            $isRemoteHostOverWritable = $remoteCmsSyncConfigs[ConfigHelper::XML_CMS_DATA_SYNC_IS_OVERWRITE] ?: 0;

            $remoteStoreIds = $this->dataHelper->getMappedRemoteStoreIdsListByMyStoreIds($entity->getStoreIds());
            if (!$remoteStoreIds) {
                $result['error_message'] = __('No remote store found by widget id %1', $id);
                return $result;
            }

            $data = [
                'widgetInstance'
                => $this->dataHelper->formatWidgetInstanceParams($entity, ApiHelper::REQUEST_SYNC_TYPE_PUSH)
            ];
            $data['widgetInstance'][WidgetInstanceInterface::PAGE_GROUPS] =
                $this->dataHelper->formatPageGroupsToParams($entity->getPageGroups());

            $remoteWidgetInstances = $this->apiHelper->getDataByIdentifier(
                $entity->getData(WidgetInstanceInterface::IDENTIFIER),
                ApiHelper::CMS_TYPE_WIDGET,
                ['store_ids' => implode(',', $remoteStoreIds)]
            );

            $data['widgetInstance'][WidgetInstanceInterface::MEDIA] = $this->dataHelper->getAllImageFromCmsContent(
                json_encode($entity->getWidgetParameters())
            );

            if (!$remoteWidgetInstances) {
                $data['widgetInstance'][WidgetInstanceInterface::STORE_IDS] = $remoteStoreIds;
                $response = $this->apiConnection->request(
                    Request::METHOD_POST,
                    self::SUTUNAM_CMS_WIDGET_API_ENDPOINT,
                    [],
                    $data
                );

                if ($response['success']) {
                    $result['success'] = $response['success'];
                    $result['type'] = 'created';
                    $result['success_message'] = __(
                        'Created new remote widget instance with ID: %1
                        (My widget instance ID: %2) on remote host %3 successfully',
                        $response['data'][WidgetInstanceInterface::ID],
                        $id,
                        $this->configHelper->getSiteName()
                    );
                    return $result;
                }

                $result['error_message'] = __(
                    'Created new remote widget instance for my widget instance ID: %1 failed - Reason:%2',
                    $id,
                    $response['message']
                );

                return $result;
            }

            $remoteUsedStoreCodes = [];

            foreach ($remoteWidgetInstances as $remoteWidgetInstance) {
                // phpcs:ignore Magento2.Performance.ForeachArrayMerge.ForeachArrayMerge
                $remoteUsedStoreCodes = array_merge(
                    $remoteWidgetInstance[WidgetInstanceInterface::STORE_CODES],
                    $remoteUsedStoreCodes
                );
            }

            $myStoreIdsByRemoteIds
                = $this->dataHelper->getMyMappedStoreIdsListByRemoteStoreCodes($remoteUsedStoreCodes);
            $checkNumOfInstanceWithIdentifier = $this->instanceCollectionFactory->create()
                ->addFieldToFilter(
                    WidgetInstanceInterface::IDENTIFIER,
                    ['eq' => $entity->getData(WidgetInstanceInterface::IDENTIFIER)]
                )
                ->addStoreFilter($myStoreIdsByRemoteIds)
                ->getSize();

            if ($checkNumOfInstanceWithIdentifier > 1) {
                $result['error_message'] = __(
                    'Update remote widget instance for my widget instance ID: %1 failed - Reason:%2',
                    $id,
                    'There\'s other CMS widget instance affects
                    with the same remote CMS widget instance except widget instance ID: ' . $id
                );
                return $result;
            }

            $countRemoteWidgetInstances = count($remoteWidgetInstances);
            $remoteUsedStoreIds = [];

            foreach ($remoteWidgetInstances as $remoteWidgetInstance) {
                $remoteWidgetInstanceId = $remoteWidgetInstance[WidgetInstanceInterface::ID];
                $remoteWidgetInstanceStoreIds = explode(
                    ',',
                    $remoteWidgetInstance[WidgetInstanceInterface::STORE_IDS]
                );
                // phpcs:ignore Magento2.Performance.ForeachArrayMerge.ForeachArrayMerge
                $remoteUsedStoreIds = array_merge($remoteWidgetInstanceStoreIds, $remoteUsedStoreIds);

                if (!$isRemoteHostOverWritable) {
                    $result['notice_message'] .= __(
                        'Update remote widget instance for my widget instance ID: %1
                        (Remote widget instance ID: %2) was skipped - Reason: Overwrite disabled on the remote host %3',
                        $id,
                        $remoteWidgetInstanceId,
                        $this->configHelper->getSiteName()
                    );
                    continue;
                }

                $storeIdsParams = $remoteWidgetInstanceStoreIds;
                if ($countRemoteWidgetInstances == 1) {
                    $storeIdsParams = $remoteStoreIds;
                    $remoteUsedStoreIds = $remoteStoreIds;
                }
                // phpcs:ignore Magento2.Performance.ForeachArrayMerge.ForeachArrayMerge
                $data['widgetInstance'][WidgetInstanceInterface::STORE_IDS] = array_merge(
                    $this->dataHelper->getUnmappedRemoteStoreIdsByRemoteStoreCodes(
                        $remoteWidgetInstance[WidgetInstanceInterface::STORE_CODES]
                    ),
                    $storeIdsParams
                );

                $data['widgetInstance'][WidgetInstanceInterface::ID] = $remoteWidgetInstanceId;

                $response = $this->apiConnection->request(
                    Request::METHOD_PUT,
                    self::SUTUNAM_CMS_WIDGET_API_ENDPOINT . $remoteWidgetInstanceId,
                    [],
                    $data
                );

                if ($response['success']) {
                    $result['success'] = true;
                    $result['success_message'] .= __(
                        'Update remote widget instance for my widget instance ID: %1
                        (Remote widget instance ID: %2) on remote host %2 successfully',
                        $id,
                        $remoteWidgetInstanceId,
                        $this->configHelper->getSiteName()
                    );
                } else {
                    $result['error_message'] .= __(
                        'Update remote widget instance for my widget instance ID: %1
                        (Remote widget instance ID: %2) failed - Reason:%3',
                        $id,
                        $remoteWidgetInstanceId,
                        $response['message']
                    );
                }
            }

            if ($diff = array_diff($remoteStoreIds, $remoteUsedStoreIds)) {
                $data['widgetInstance'][WidgetInstanceInterface::STORE_IDS] = $diff;
                unset($data['widgetInstance'][WidgetInstanceInterface::ID]);
                $response = $this->apiConnection->request(
                    Request::METHOD_POST,
                    self::SUTUNAM_CMS_WIDGET_API_ENDPOINT,
                    [],
                    $data
                );

                if ($response['success']) {
                    $result['success_message'] .= __(
                        'Created new remote widget instance with ID: %1
                        (My widget instance ID: %2) on remote host %3 successfully',
                        $response['data'][WidgetInstanceInterface::ID],
                        $id,
                        $this->configHelper->getSiteName()
                    );
                } else {
                    $result['error_message'] .= __(
                        'Created new remote widget instance for my widget instance ID: %1 failed - Reason:%2',
                        $id,
                        $response['message']
                    );
                }
            }
            $result['type'] = 'updated';
        } else {
            $result['error_message'] = __('Couldn\'t load CMS widget content.');
        }
        return $result;
    }

    /**
     * Push all cms widget to remote host
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

        $allWidgetInstanceIds = $this->instanceCollectionFactory->create()->getAllIds();
        if (!$allWidgetInstanceIds) {
            $result['error_message'] = __(
                'There is no remote widget instance on the remote host %1',
                $this->configHelper->getSiteName()
            );
            return $result;
        }

        foreach ($allWidgetInstanceIds as $widgetId) {
            $response = $this->push($widgetId);
            if (!$response['success']) {
                $result['total_failed']++;
                $result['error_message'] .= ($response['error_message'] ? __(
                    'Push Widget ID: %1 ||Status: %2.<br>',
                    $widgetId,
                    $response['error_message']
                ) : '');
                $result['notice_message'] .= ($response['notice_message'] ? __(
                    'Push Widget ID: %1 ||Status: %2.<br>',
                    $widgetId,
                    $response['notice_message']
                ) : '');
                continue;
            }

            if ($response['type'] == 'created') {
                $result['total_create_success']++;
                $result['success_message'] .= __(
                    'Push Widget ID: %1 ||Status: %2.<br>',
                    $widgetId,
                    __('Created successfully')
                );
                continue;
            }

            $result['success_message'] .= __(
                'Push Widget ID: %1 ||Status: %2.<br>',
                $widgetId,
                __('Updated successfully')
            );
            $result['total_update_success']++;
        }
        return $result;
    }

    /**
     * Get Widget Instance by ID
     *
     * @param int|string $id
     * @return false|Instance
     */
    protected function getWigetInstance($id)
    {
        try {
            /** @phpstan-ignore-next-line */
            return $this->instanceFactory->create()->load($id);
        } catch (\Exception $exception) {
            $this->logger->critical($exception->getMessage());
        }
        return false;
    }
}
