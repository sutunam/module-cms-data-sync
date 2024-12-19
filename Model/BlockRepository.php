<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Sutunam\CmsDataSync\Model;

use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\ResourceModel\Block as ResourceBlock;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\FilterFactory;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\EntityManager\HydratorInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Store\Model\StoreManagerInterface;
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
     * @var SearchCriteriaInterface
     */
    private $searchCriteria;

    /**
     * @var FilterFactory
     */
    private $filterFactory;

    /**
     * @var FilterGroup
     */
    private $filterGroup;

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
     * @var HydratorInterface|mixed
     */
    private $hydrator;

    /**
     * Constructor
     *
     * @param BlockCollectionFactory $blockCollectionFactory
     * @param SutunamBlockSearchResultsInterfaceFactory $sutunamSearchResultsFactory
     * @param SutunamDataHelper $sutunamDataHelper
     * @param SutunamConfigHelper $sutunamConfigHelper
     * @param SutunamBlockInterfaceFactory $sutunamBlockInterface
     * @param SearchCriteriaInterface $searchCriteria
     * @param FilterFactory $filterFactory
     * @param FilterGroup $filterGroup
     * @param DataObjectHelper $dataObjectHelper
     * @param StoreManagerInterface $storeManager
     * @param ResourceBlock $resource
     * @param CollectionProcessorInterface|null $collectionProcessor
     * @param HydratorInterface|null $hydrator
     */
    public function __construct(
        BlockCollectionFactory $blockCollectionFactory,
        SutunamBlockSearchResultsInterfaceFactory $sutunamSearchResultsFactory,
        SutunamDataHelper $sutunamDataHelper,
        SutunamConfigHelper $sutunamConfigHelper,
        SutunamBlockInterfaceFactory $sutunamBlockInterface,
        SearchCriteriaInterface $searchCriteria,
        FilterFactory $filterFactory,
        FilterGroup $filterGroup,
        DataObjectHelper $dataObjectHelper,
        StoreManagerInterface $storeManager,
        ResourceBlock $resource,
        CollectionProcessorInterface $collectionProcessor = null,
        ?HydratorInterface $hydrator = null
    ) {
        $this->blockCollectionFactory = $blockCollectionFactory;
        $this->sutunamSearchResultsFactory = $sutunamSearchResultsFactory;
        $this->sutunamDataHelper = $sutunamDataHelper;
        $this->sutunamConfigHelper = $sutunamConfigHelper;
        $this->sutunamBlockInterface = $sutunamBlockInterface;
        $this->searchCriteria = $searchCriteria;
        $this->filterFactory = $filterFactory;
        $this->filterGroup = $filterGroup;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        $this->collectionProcessor = $collectionProcessor ?: $this->getCollectionProcessor();
        $this->hydrator = $hydrator ?? ObjectManager::getInstance()->get(HydratorInterface::class);
    }

    /**
     * Load Block data by given Block Identity
     *
     * @param string $blockId
     * @return Block
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($blockId)
    {
        $block = $this->sutunamBlockInterface->create();
        $block->load($blockId);
        $block->setStoreCode($this->sutunamDataHelper->getStoreCodesByIds($block->getStores()));

        if (!$block->getId()) {
            throw new NoSuchEntityException(__('The CMS block with the "%1" ID doesn\'t exist.', $blockId));
        }
        return $block;
    }

    /**
     * Load Block data by given Block Identifier and store ids list
     *
     * @param string $blockIdentifier
     * @param string $storeIds
     * @return \Sutunam\CmsDataSync\Api\Data\BlockSearchResultsInterface
     */
    public function getByIdentifier($blockIdentifier, $storeIds)
    {
        $searchedCollection = $this->blockCollectionFactory->create()
            ->addFieldToFilter(BlockInterface::IDENTIFIER, ['eq' => $blockIdentifier])
            ->addFieldToFilter(SutunamBlockInterface::STORE_ID, ['in' => explode(',', $storeIds)]);

        $items = [];
        foreach ($searchedCollection->getItems() as $block) {
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
     * @return \Sutunam\CmsDataSync\Api\Data\BlockSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria)
    {
        $collection = $this->blockCollectionFactory->create();

        $this->collectionProcessor->process($criteria, $collection);

        $items = [];
        foreach ($collection->getItems() as $block) {
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
     * Retrieve collection processor
     *
     * @return CollectionProcessorInterface
     */
    private function getCollectionProcessor()
    {
        //phpcs:disable Magento2.PHP.LiteralNamespaces
        if (!$this->collectionProcessor) {
            $this->collectionProcessor = \Magento\Framework\App\ObjectManager::getInstance()->get(
                'Magento\Cms\Model\Api\SearchCriteria\BlockCollectionProcessor'
            );
        }
        return $this->collectionProcessor;
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
        if (empty($block->getStoreId())) {
            $block->setStoreId([$this->storeManager->getStore()->getId()]);
        }

        if ($block->getMedia() && $this->sutunamConfigHelper->isSyncMedia()) {
            foreach ($block->getMedia() as $media) {
                $this->sutunamDataHelper->saveMediaFromRemoteHost($media);
            }
        }

        if ($block->getId() && $block instanceof Block && !$block->getOrigData()) {
            $block = $this->hydrator->hydrate($this->getById($block->getId()), $this->hydrator->extract($block));
        }

        try {
            $this->resource->save($block);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $block;
    }
}
