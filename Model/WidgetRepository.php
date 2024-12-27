<?php

namespace Sutunam\CmsDataSync\Model;

use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\EntityManager\HydratorInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Model\Widget\Instance;
use Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterface;
use Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterface;
use Sutunam\CmsDataSync\Api\WidgetRepositoryInterface;

class WidgetRepository implements WidgetRepositoryInterface
{
    protected $instance;

    protected $instanceCollectionFactory;

    protected $dataHelper;

    protected $searchResultsInterface;

    protected $widgetInstanceInterface;

    protected $widgetInstancePageInterface;

    protected $dataObjectHelper;

    protected $storeManager;

    protected $hydrator;

    public function __construct(
        \Magento\Widget\Model\Widget\InstanceFactory $instance,
        \Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory $instanceCollectionFactory,
        \Sutunam\CmsDataSync\Helper\Data $dataHelper,
        \Sutunam\CmsDataSync\Api\Data\WidgetInstanceSearchResultsInterfaceFactory $searchResultsInterface,
        \Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterfaceFactory $widgetInstanceInterface,
        \Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterfaceFactory $widgetInstancePageInterface,
        DataObjectHelper $dataObjectHelper,
        StoreManagerInterface $storeManager,
        ?HydratorInterface $hydrator = null
    ) {
        $this->instance = $instance;
        $this->instanceCollectionFactory = $instanceCollectionFactory;
        $this->dataHelper = $dataHelper;
        $this->searchResultsInterface = $searchResultsInterface;
        $this->widgetInstanceInterface = $widgetInstanceInterface;
        $this->widgetInstancePageInterface = $widgetInstancePageInterface;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->storeManager = $storeManager;
        $this->hydrator = $hydrator ?? ObjectManager::getInstance()->get(HydratorInterface::class);
    }

    /**
     * Get all widget
     *
     * @return \Sutunam\CmsDataSync\Api\Data\WidgetInstanceSearchResultsInterface
     */
    public function getAll()
    {
        $items = [];
        foreach ($this->instanceCollectionFactory->create()->getAllIds() as $instanceId) {
            $items[] = $this->convertInstanceToDTO($instanceId);
        }

        $searchResults = $this->searchResultsInterface->create();
        $searchResults->setItems($items);
        $searchResults->setTotalCount($this->instanceCollectionFactory->create()->getSize());

        return $searchResults;
    }

    /**
     * Load widget data by given widget Identifier and store ids list
     *
     * @param string $identifier
     * @param string $storeIds
     * @return \Sutunam\CmsDataSync\Api\Data\WidgetInstanceSearchResultsInterface
     * @throws NoSuchEntityException
     */
    public function getByIdentifier($identifier, $storeIds)
    {
        $searchedCollection = $this->instanceCollectionFactory->create()
            ->addFieldToFilter('identifier', ['eq' => $identifier]);

        if ($storeIds) {
            $searchedCollection->addFieldToFilter('store_ids', ['in' => explode(',', $storeIds)]);
        }

        $items = [];
        foreach ($searchedCollection->getAllIds() as $instanceId) {
            $items[] = $this->convertInstanceToDTO($instanceId);;
        }
        $searchResults = $this->searchResultsInterface->create();
        $searchResults->setItems($items);
        $searchResults->setTotalCount($this->instanceCollectionFactory->create()->getSize());

        return $searchResults;
    }

    /**
     * Save widget data
     *
     * @param WidgetInstanceInterface $block
     * @return object|WidgetInstanceInterface
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function save(WidgetInstanceInterface $widgetInstance)
    {
//        if ($widgetInstance->getInstanceId() && !$this->sutunamConfigHelper->isOverwrite()) {
//            throw new CouldNotSaveException(__('The CMS block cannot be overwritten.'));
//        }
        $instance = $this->instance->create();

        if ($widgetInstance->getId()){
            $instance->load($widgetInstance->getId());
        }

        try {
            if (!$widgetInstance->getOrigData()) {
                $instance->setData($widgetInstance->getData());

                if (empty($widgetInstance->getStoreIds())) {
                    $instance->setStoreIds(implode(',', $this->storeManager->getStore()->getId()));
                }

                if (!empty($widgetInstance->getPageGroups())) {
                    $pageGroupsArr = [];
                    foreach ($widgetInstance->getPageGroups() as $pageGroup) {
                        $pageGroupsArr[] = $pageGroup->getData();
                    }
                    $instance->setPageGroups($pageGroupsArr);
                }

                $instance->save();
            }

        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $instance;
    }

    /**
     * Convert Instance to WidgetInstanceInterface
     *
     * @param int $instanceId
     * @return WidgetInstanceInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function convertInstanceToDTO($instanceId) {
        $widgetDTO = $this->widgetInstanceInterface->create();
        $loadInstance = $this->instance->create()->load($instanceId);

        $this->dataObjectHelper->populateWithArray(
            $widgetDTO,
            $loadInstance->getData(),
            WidgetInstanceInterface::class
        );

        $widgetDTO->setData($loadInstance->getData());
        $widgetDTO->setId($loadInstance->getId());
        $widgetDTO->setStoreCodes($this->dataHelper->getStoreCodesByIds($loadInstance->getStoreIds()));
        $widgetDTO->setThemeCode($this->dataHelper->getThemeCodeById($loadInstance->getData('theme_id')));

        if ($loadInstance->getPageGroups()) {
            $pageGroupsData = [];
            foreach ($loadInstance->getPageGroups() as $pageGroup) {
                $pageGroupData = $this->widgetInstancePageInterface->create();
                $this->dataObjectHelper->populateWithArray(
                    $pageGroupData,
                    $pageGroup,
                    WidgetInstancePageInterface::class
                );
                $pageGroupsData[] = $pageGroupData;
            }
            $widgetDTO->setPageGroups($pageGroupsData);
        }

        return $widgetDTO;
    }


//    public function formatPageGroups($listPageGroup) {
//
//    }
}
