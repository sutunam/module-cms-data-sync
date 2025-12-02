<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Model;

use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Model\ResourceModel\Block as ResourceBlock;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Store\Model\StoreManagerInterface;
use Sutunam\CmsDataSync\Api\Data\BlockSearchResultsInterface;
use Sutunam\CmsDataSync\Helper\Data as SutunamDataHelper;
use Sutunam\CmsDataSync\Helper\Config as SutunamConfigHelper;
use Sutunam\CmsDataSync\Api\Data\BlockInterfaceFactory as SutunamBlockInterfaceFactory;
use Sutunam\CmsDataSync\Api\Data\BlockInterface as SutunamBlockInterface;
use Sutunam\CmsDataSync\Api\BlockRepositoryInterface as SutunamBlockRepositoryInterface;
use Sutunam\CmsDataSync\Api\Data\BlockSearchResultsInterfaceFactory as SutunamBlockSearchResultsInterfaceFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Default block repo impl.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BlockRepository implements SutunamBlockRepositoryInterface
{
    /**
     * @var BlockCollectionFactory
     */
    protected $blockCollectionFactory;

    /**
     * @var SutunamBlockSearchResultsInterfaceFactory
     */
    protected $sutunamSearchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var SutunamDataHelper
     */
    private $sutunamDataHelper;

    /**
     * @var SutunamConfigHelper
     */
    private $sutunamConfigHelper;

    /**
     * @var SutunamBlockInterfaceFactory
     */
    private $sutunamBlockInterface;

    /**
     * @var DataObjectHelper
     */
    private $dataObjectHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ResourceBlock
     */
    private $resource;

    /**
     * @var BlockFactory
     */
    private $blockFactory;

    /**
     * @param BlockCollectionFactory $blockCollectionFactory
     * @param SutunamBlockSearchResultsInterfaceFactory $sutunamSearchResultsFactory
     * @param SutunamDataHelper $sutunamDataHelper
     * @param SutunamConfigHelper $sutunamConfigHelper
     * @param SutunamBlockInterfaceFactory $sutunamBlockInterface
     * @param DataObjectHelper $dataObjectHelper
     * @param StoreManagerInterface $storeManager
     * @param ResourceBlock $resource
     * @param CollectionProcessorInterface $collectionProcessor
     * @param BlockFactory $blockFactory
     */
    public function __construct(
        BlockCollectionFactory $blockCollectionFactory,
        SutunamBlockSearchResultsInterfaceFactory $sutunamSearchResultsFactory,
        SutunamDataHelper $sutunamDataHelper,
        SutunamConfigHelper $sutunamConfigHelper,
        SutunamBlockInterfaceFactory $sutunamBlockInterface,
        DataObjectHelper $dataObjectHelper,
        StoreManagerInterface $storeManager,
        ResourceBlock $resource,
        CollectionProcessorInterface $collectionProcessor,
        BlockFactory $blockFactory
    ) {
        $this->blockCollectionFactory = $blockCollectionFactory;
        $this->sutunamSearchResultsFactory = $sutunamSearchResultsFactory;
        $this->sutunamDataHelper = $sutunamDataHelper;
        $this->sutunamConfigHelper = $sutunamConfigHelper;
        $this->sutunamBlockInterface = $sutunamBlockInterface;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        $this->collectionProcessor = $collectionProcessor;
        $this->blockFactory = $blockFactory;
    }

    /**
     * Load Block data by given Block Identity
     *
     * @param string $blockId
     * @return Block
     * @throws NoSuchEntityException
     */
    public function getById($blockId)
    {
        $block = $this->blockFactory->create();
        $this->resource->load($block, $blockId);

        if (!$block->getId()) {
            throw new NoSuchEntityException(__('CMS block with ID “%1” does not exist', $blockId));
        }
        $block->setStoreCode($this->sutunamDataHelper->getStoreCodesByIds($block->getStores()));

        return $block;
    }

    /**
     * Load Block data by given Block Identifier and store ids list
     *
     * @param string $blockIdentifier
     * @param string $storeIds
     * @return BlockSearchResultsInterface
     */
    public function getByIdentifier($blockIdentifier, $storeIds)
    {
        $searchedCollection = $this->blockCollectionFactory->create()
            ->addFieldToFilter(BlockInterface::IDENTIFIER, ['eq' => $blockIdentifier])
            ->addFieldToFilter(SutunamBlockInterface::STORE_ID, ['in' => explode(',', $storeIds)]);

        $items = [];
        foreach ($searchedCollection->getItems() as $block) {
            /** @var Block $blockDto */
            $blockDto = $this->sutunamBlockInterface->create();
            $this->dataObjectHelper->populateWithArray(
                $blockDto,
                $block->getData(),
                SutunamBlockInterface::class
            );
            $blockDto->setData($block->getData());
            $blockDto->setId($block->getId());
            $blockDto->setStoreCode($this->sutunamDataHelper->getStoreCodesByIds($block->getStores()));
            $items[] = $blockDto;
        }

        $searchResults = $this->sutunamSearchResultsFactory->create();
        $searchResults->setItems($items);
        $searchResults->setTotalCount($searchedCollection->getSize());

        return $searchResults;
    }

    /**
     * Load Block data collection by given search criteria
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @param SearchCriteriaInterface $criteria
     * @return BlockSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria)
    {
        $collection = $this->blockCollectionFactory->create();

        $this->collectionProcessor->process($criteria, $collection);

        $items = [];
        foreach ($collection->getItems() as $block) {
            /** @var Block $blockDto */
            $blockDto = $this->sutunamBlockInterface->create();
            $this->dataObjectHelper->populateWithArray(
                $blockDto,
                $block->getData(),
                SutunamBlockInterface::class
            );
            $blockDto->setData($block->getData());
            $blockDto->setId($block->getId());
            $blockDto->setStoreCode($this->sutunamDataHelper->getStoreCodesByIds($block->getStores()));
            $items[] = $blockDto;
        }

        $searchResults = $this->sutunamSearchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * Save block data
     *
     * @param SutunamBlockInterface $block
     * @return object|SutunamBlockInterface
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function save(SutunamBlockInterface $block)
    {
        /** @var Block $block */
        if ($block->getId() && !$this->sutunamConfigHelper->isOverwrite()) {
            throw new CouldNotSaveException(__('CMS block cannot be overwritten'));
        }
        $block->setStoreCode($this->sutunamDataHelper->getStoreCodesByIds($block->getStores()));

        if (empty($block->getStoreId())) {
            $block->setStoreId([$this->storeManager->getStore()->getId()]);
        }

        try {
            if ($block->getMedia() && $this->sutunamConfigHelper->isSyncMedia()) {
                foreach ($block->getMedia() as $media) {
                     $this->sutunamDataHelper->saveMediaFromRemoteHost(
                         $media,
                         $this->sutunamConfigHelper->isOverwrite()
                     );
                }
            }
            $this->resource->save($block);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $block;
    }
}
