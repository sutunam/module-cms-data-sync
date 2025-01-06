<?php

namespace Sutunam\CmsDataSync\Model\Processors\Cms;

use Laminas\Http\Request;
use Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory;
use Magento\Widget\Model\Widget\InstanceFactory;
use Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterface;
use Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterface;
use Sutunam\CmsDataSync\Api\ProcessorInterface;
use Sutunam\CmsDataSync\Api\WidgetRepositoryInterface;
use Sutunam\CmsDataSync\Helper\ApiConnection;
use Sutunam\CmsDataSync\Helper\ApiHelper;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;
use Sutunam\CmsDataSync\Helper\Data as DataHelper;
use Sutunam\CmsDataSync\Logger\Logger;

class Widget implements ProcessorInterface
{

    public const SUTUNAM_CMS_WIDGET_API_ENDPOINT = 'sutunam/cmsWidget/';

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
     * @var Logger
     */
    protected $logger;

    /**
     * Construct
     *
     * @param WidgetRepositoryInterface $widgetRepository
     * @param InstanceFactory $instanceFactory
     * @param ApiHelper $apiHelper
     * @param ApiConnection $apiConnection
     * @param DataHelper $dataHelper
     * @param ConfigHelper $configHelper
     * @param CollectionFactory $instanceCollectionFactory
     * @param Logger $logger
     */
    public function __construct(
        WidgetRepositoryInterface $widgetRepository,
        InstanceFactory $instanceFactory,
        ApiHelper $apiHelper,
        ApiConnection $apiConnection,
        DataHelper $dataHelper,
        ConfigHelper $configHelper,
        CollectionFactory $instanceCollectionFactory,
        Logger $logger
    ) {
        $this->instanceFactory = $instanceFactory;
        $this->widgetRepository = $widgetRepository;
        $this->apiHelper = $apiHelper;
        $this->apiConnection = $apiConnection;
        $this->dataHelper = $dataHelper;
        $this->configHelper = $configHelper;
        $this->instanceCollectionFactory = $instanceCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * Pull cms widget instance from remote host by my id
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

        $widgetInstance = $this->getWigetInstance($id);
        if (!$widgetInstance) {
            $result['message'] = __('Couldn\'t load CMS widget content.');
        }

        $widgetInstanceStoreIds = $widgetInstance->getStoreIds();
        $widgetInstanceStoreCodes = $this->dataHelper->getStoreCodesByIds($widgetInstanceStoreIds);
        $remoteStoreIds = $this->dataHelper->getRemoteStoreIdsListByMyStoreCodes($widgetInstanceStoreCodes);
        if (!$remoteStoreIds) {
            $result['message'] = __('The store scope of widget is not match.');
            return $result;
        }

        $remoteWidgetInstances = $this->apiHelper->getDataByIdentifier(
            $widgetInstance->getData(WidgetInstanceInterface::IDENTIFIER),
            ApiHelper::CMS_TYPE_WIDGET,
            ['store_ids' => implode(',', $remoteStoreIds)]
        );

        if (!$remoteWidgetInstances) {
            $result['message'] = __(
                'There is no remote widget instance with identifier %1',
                $widgetInstance->getData(WidgetInstanceInterface::IDENTIFIER)
            );
            return $result;
        }

        if (count($remoteWidgetInstances) != 1) {
            $result['message'] = __('The store scope of widget instance is not match.');
            return $result;
        }

        $remoteWidgetInstance = $remoteWidgetInstances[0];

        $myThemeId = $this->dataHelper->getThemeIdByCode($remoteWidgetInstance[WidgetInstanceInterface::THEME_CODE]);
        if (!$myThemeId) {
            $result['message'] = __(
                'There is no theme corresponding to the remote theme code %.',
                $remoteWidgetInstance[WidgetInstanceInterface::THEME_CODE]
            );
            return $result;
        }

        try {
            $data = $this->dataHelper->formatWidgetInstanceParams($remoteWidgetInstance);
            $widgetInstance->setData($data);
            $widgetInstance->setId($id);
            $widgetInstance->setThemeId($myThemeId);
            $widgetInstance->setStoreIds($widgetInstanceStoreIds);
            $widgetInstance->setPageGroups(
                $this->dataHelper->formatPageGroupsToSave($remoteWidgetInstance[WidgetInstanceInterface::PAGE_GROUPS])
            );
            $widgetInstance->save();
            if ($this->configHelper->isSyncMedia()) {
                foreach ($widgetInstance->getWidgetParameters() as $widgetParameter) {
                    $this->dataHelper->saveAllImageFromCmsContent($widgetParameter, true);
                }
            }
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
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
            'message' => ''
        ];

        $allRemoteWidgetInstances = $this->apiHelper->getAllCms(ApiHelper::CMS_TYPE_WIDGET);
        if (!$allRemoteWidgetInstances) {
            $result['message'] = __(
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
            $myStoreIds = $this->dataHelper->getMyStoreIdsListByRemoteStoreCodes(
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

            if ($myWidgetInstances->getSize() > 1) {
                continue;
            }

            try {
                $myWidgetInstance = $this->instanceFactory->create();

                $myWidgetInstanceId = $myWidgetInstances->getFirstItem()->getId();
                $myWidgetInstanceStoreIds = [];

                if ($myWidgetInstanceId) {
                    $myWidgetInstance = $this->getWigetInstance($myWidgetInstanceId);
                    $myWidgetInstanceStoreIds = $myWidgetInstance->getStoreIds();
                }

                if (!$this->validateWidgetInstance($remoteWidgetInstance, $myWidgetInstanceStoreIds, $myStoreIds)) {
                    $result['message'] .= __(
                        'Pull Widget ID: %1 ||Status: %2.<br>',
                        $myWidgetInstanceId,
                        __('Skipped')
                    );
                    continue;
                }

                $myWidgetInstanceStoreIds  = $myWidgetInstanceStoreIds ?: $myStoreIds;

                $data = $this->dataHelper->formatWidgetInstanceParams($remoteWidgetInstance);

                $myWidgetInstance->setData($data);
                $myWidgetInstance->setStoreIds($myWidgetInstanceStoreIds);
                $myWidgetInstance->setThemeId($myThemeId);
                $myWidgetInstance->setPageGroups(
                    $this->dataHelper->formatPageGroupsToSave(
                        $remoteWidgetInstance[WidgetInstanceInterface::PAGE_GROUPS]
                    )
                );

                if ($this->configHelper->isOverwrite()) {
                    $myWidgetInstance->setId($myWidgetInstanceId);
                    $myWidgetInstance->save();
                } elseif (!$myWidgetInstanceId) {
                    $myWidgetInstance->save();
                } else {
                    $result['message'] .= __(
                        'Pull Widget ID: %1 ||Status: %2.<br>',
                        $myWidgetInstanceId,
                        __('Skipped')
                    );
                    continue;
                }

                if ($this->configHelper->isSyncMedia()) {
                    foreach ($myWidgetInstance->getWidgetParameters() as $widgetParameter) {
                        $this->dataHelper->saveAllImageFromCmsContent(
                            $widgetParameter,
                            $this->configHelper->isOverwrite()
                        );
                    }
                }

                if ($myWidgetInstanceId) {
                    $result['total_update_success']++;
                    $result['message'] .= __(
                        'Pull Widget ID: %1 ||Status: %2.<br>',
                        $myWidgetInstanceId,
                        __('Updated Successfully')
                    );
                } else {
                    $result['total_create_success']++;
                    $result['message'] .= __(
                        'Pull Widget ID: %1 ||Status: %2.<br>',
                        $myWidgetInstanceId,
                        __('Created Successfully')
                    );
                }
            } catch (\Exception $e) {
                $result['total_failed']++;
                $this->logger->critical($e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Push cms widget to remote host by my id
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
            'message' => ''
        ];
        $entity = $this->getWigetInstance($id);
        if ($entity) {
            $remoteStoreIds = $this->dataHelper->getRemoteStoreIdsListByMyStoreIds($entity->getStoreIds());

            $data = ['widgetInstance' => $this->dataHelper->formatWidgetInstanceParams($entity)];
            $data['widgetInstance'][WidgetInstanceInterface::PAGE_GROUPS] =
                $this->dataHelper->formatPageGroupsToParams($entity->getPageGroups());

            $remoteWidgetInstances = $this->apiHelper->getDataByIdentifier(
                $entity->getData(WidgetInstanceInterface::IDENTIFIER),
                ApiHelper::CMS_TYPE_WIDGET,
                ['store_ids' => implode(',', $remoteStoreIds)]
            );

            if ($this->configHelper->isSyncMedia()) {
                $media = [];
                foreach ($entity->getWidgetParameters() as $widgetParameter) {
                    foreach ($this->dataHelper->getAllImageFromCmsContent($widgetParameter) as $image) {
                        $media[] = $image;
                    }
                }
                $data['widgetInstance'][WidgetInstanceInterface::MEDIA] = $media;
            }

            if (!$remoteWidgetInstances) {
                $data['widgetInstance'][WidgetInstanceInterface::STORE_IDS] = $remoteStoreIds;
                $result = $this->apiConnection->request(
                    Request::METHOD_POST,
                    self::SUTUNAM_CMS_WIDGET_API_ENDPOINT,
                    [],
                    $data
                );
                $result['type'] = 'created';
                return $result;
            }

            foreach ($remoteWidgetInstances as $remoteWidgetInstance) {
                $remoteWidgetInstanceId = $remoteWidgetInstance[WidgetInstanceInterface::ID];
                $data['widgetInstance'][WidgetInstanceInterface::STORE_IDS] =
                    explode(',', $remoteWidgetInstance[WidgetInstanceInterface::STORE_IDS]);
                $data['widgetInstance'][WidgetInstanceInterface::ID] = $remoteWidgetInstanceId;
                $response = $this->apiConnection->request(
                    Request::METHOD_PUT,
                    self::SUTUNAM_CMS_WIDGET_API_ENDPOINT . $remoteWidgetInstanceId,
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
            $result['message'] = __('Couldn\'t load CMS widget content.');
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
            'message' => ''
        ];

        $allWidgetInstanceIds = $this->instanceCollectionFactory->create()->getAllIds();
        if (!$allWidgetInstanceIds) {
            $result['message'] = __(
                'There is no remote widget instance on the remote host %1',
                $this->configHelper->getSiteName()
            );
            return $result;
        }

        foreach ($allWidgetInstanceIds as $widgetId) {
            $response = $this->push($widgetId);
            if (!$response['success']) {
                $result['total_failed']++;
                $result['message'] .= __(
                    'Push Widget ID: %1 ||Status: %2.<br>',
                    $widgetId,
                    $response['message']
                );
                continue;
            }

            if ($response['type'] == 'created') {
                $result['total_create_success']++;
                $result['message'] .= __(
                    'Push Widget ID: %1 ||Status: %2.<br>',
                    $widgetId,
                    __('Created Successfully')
                );
                continue;
            }

            $result['message'] .= __(
                'Push Widget ID: %1 ||Status: %2.<br>',
                $widgetId,
                __('Updated Successfully')
            );
            $result['total_update_success']++;
        }
        return $result;
    }

    /**
     * Get Widget Instance by ID
     *
     * @param int|string $id
     * @return false|\Magento\Widget\Model\Widget\Instance
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

    /**
     * Validate widget instance
     *
     * @param WidgetInstanceInterface $remoteWidgetInstance
     * @param array $myWidgetInstanceStoreIds
     * @param array $mappingStoreIds
     * @return bool
     */
    public function validateWidgetInstance($remoteWidgetInstance, $myWidgetInstanceStoreIds, $mappingStoreIds)
    {
        //Get list diff stores id from widget found in collection with list store gotten from remote codes list
        $diffIds = array_diff($myWidgetInstanceStoreIds, $mappingStoreIds);
        if ($diffIds) {
            //Check there's any other remote widget using diff ids
            $remoteWidgetInstanceUsingDiffIds = $this->apiHelper->getDataByIdentifier(
                $remoteWidgetInstance[WidgetInstanceInterface::IDENTIFIER],
                ApiHelper::CMS_TYPE_WIDGET,
                $this->dataHelper->getRemoteStoreIdsListByMyStoreIds($diffIds)
            );

            //If yes, skip it
            if ($remoteWidgetInstanceUsingDiffIds) {
                return false;
            }
        }
        return true;
    }
}
