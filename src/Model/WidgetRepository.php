<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Model;

use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\EntityManager\HydratorInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Model\Widget\Instance;
use Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterface;
use Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterface;
use Sutunam\CmsDataSync\Api\WidgetRepositoryInterface;
use Sutunam\CmsDataSync\Helper\Config;

class WidgetRepository implements WidgetRepositoryInterface
{
    /**
     * @var \Magento\Widget\Model\Widget\InstanceFactory
     */
    protected $instance;

    /**
     * @var \Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory
     */
    protected $instanceCollectionFactory;

    /**
     * @var \Sutunam\CmsDataSync\Helper\Data
     */
    protected $dataHelper;

    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * @var \Sutunam\CmsDataSync\Api\Data\WidgetInstanceSearchResultsInterfaceFactory
     */
    protected $searchResultsInterface;

    /**
     * @var \Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterfaceFactory
     */
    protected $widgetInstanceInterface;

    /**
     * @var \Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterfaceFactory
     */
    protected $widgetInstancePageInterface;

    /**
     * @var DataObjectHelper
     */
    protected $dataObjectHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var HydratorInterface|mixed
     */
    protected $hydrator;

    /**
     * Construct
     *
     * @param \Magento\Widget\Model\Widget\InstanceFactory $instance
     * @param \Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory $instanceCollectionFactory
     * @param \Sutunam\CmsDataSync\Helper\Data $dataHelper
     * @param Config $configHelper
     * @param \Sutunam\CmsDataSync\Api\Data\WidgetInstanceSearchResultsInterfaceFactory $searchResultsInterface
     * @param \Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterfaceFactory $widgetInstanceInterface
     * @param \Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterfaceFactory $widgetInstancePageInterface
     * @param DataObjectHelper $dataObjectHelper
     * @param StoreManagerInterface $storeManager
     * @param HydratorInterface|null $hydrator
     */
    public function __construct(
        \Magento\Widget\Model\Widget\InstanceFactory $instance,
        \Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory $instanceCollectionFactory,
        \Sutunam\CmsDataSync\Helper\Data $dataHelper,
        \Sutunam\CmsDataSync\Helper\Config $configHelper,
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
        $this->configHelper = $configHelper;
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
     * @throws NoSuchEntityException
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
            ->addFieldToFilter(WidgetInstanceInterface::IDENTIFIER, ['eq' => $identifier]);

        if ($storeIds) {
            $searchedCollection->addStoreFilter(explode(',', $storeIds));
        }

        $items = [];
        foreach ($searchedCollection->getAllIds() as $instanceId) {
            $items[] = $this->convertInstanceToDTO($instanceId);
        }
        $searchResults = $this->searchResultsInterface->create();
        $searchResults->setItems($items);
        $searchResults->setTotalCount($this->instanceCollectionFactory->create()->getSize());

        return $searchResults;
    }

    /**
     * Save widget data
     *
     * @param WidgetInstanceInterface $widgetInstance
     * @return Instance
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function save(WidgetInstanceInterface $widgetInstance)
    {
        /** @var WidgetInstance $widgetInstance */
        if ($widgetInstance->getId() && !$this->configHelper->isOverwrite()) {
            throw new CouldNotSaveException(__('The CMS block cannot be overwritten'));
        }

        /** @var WidgetInstance $instance */
        $instance = $this->instance->create();

        if ($widgetInstance->getId()) {
            $instance->load($widgetInstance->getId()); /** @phpstan-ignore-line */
        }

        if ($widgetInstance->getCode() && !$this->dataHelper->getThemeIdByCode($widgetInstance->getThemeCode())) {
            throw new CouldNotSaveException(__('The CMS block cannot be overwritten'));
        }

        try {
            if (!$widgetInstance->getOrigData()) {
                $instance->setData($widgetInstance->getData());
                $instance->setThemeId($this->dataHelper->getThemeIdByCode($widgetInstance->getThemeCode()));

                if (empty($widgetInstance->getStoreIds())) {
                    $instance->setStoreIds([$this->storeManager->getStore()->getId()]);
                }

                if (!empty($widgetInstance->getPageGroups())) {
                    $pageGroupsArr = $this->dataHelper->formatPageGroupsToSave($widgetInstance->getPageGroups());
                    $instance->setPageGroups($pageGroupsArr);
                }
                $instance->save(); /** @phpstan-ignore-line */

                if ($widgetInstance->getMedia() && $this->configHelper->isSyncMedia()) {
                    foreach ($widgetInstance->getMedia() as $media) {
                        $this->dataHelper->saveMediaFromRemoteHost($media, $this->configHelper->isOverwrite());
                    }
                }
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
    public function convertInstanceToDTO($instanceId)
    {
        /** @var WidgetInstance $widgetDTO */
        $widgetDTO = $this->widgetInstanceInterface->create();
        /** @var WidgetInstance $loadInstance */
        $loadInstance = $this->instance->create()->load($instanceId); /** @phpstan-ignore-line */

        $this->dataObjectHelper->populateWithArray(
            $widgetDTO,
            $loadInstance->getData(),
            WidgetInstanceInterface::class
        );

        $widgetDTO->setData($loadInstance->getData());
        $widgetDTO->setId($loadInstance->getId());
        $widgetDTO->setStoreCodes($this->dataHelper->getStoreCodesByIds($loadInstance->getStoreIds()));
        $widgetDTO->setThemeCode($this->dataHelper->getThemeCodeById(
            $loadInstance->getData(WidgetInstanceInterface::THEME_ID)
        ));
        $widgetDTO->setPageGroups($loadInstance->getPageGroups());

        return $widgetDTO;
    }
}
