<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Sutunam\CmsDataSync\Model;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Model\ResourceModel\Block as ResourcePage;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\FilterFactory;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\EntityManager\HydratorInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Sutunam\CmsDataSync\Api\PageRepositoryInterface as SutunamPageRepositoryInterface;
use Sutunam\CmsDataSync\Api\Data\PageInterface as SutunamPageInterface;
use Sutunam\CmsDataSync\Api\Data\PageInterfaceFactory as SutunamPageInterfaceFactory;
use Sutunam\CmsDataSync\Api\Data\PageSearchResultsInterfaceFactory as SutunamPageSearchResultsInterfaceFactory;
use Sutunam\CmsDataSync\Helper\Config as SutunamConfigHelper;
use Sutunam\CmsDataSync\Helper\Data as SutunamDataHelper;

/**
 * Default page repo impl.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PageRepository implements SutunamPageRepositoryInterface
{
    /**
     * @var PageCollectionFactory
     */
    protected $pageCollectionFactory;

    /**
     * @var SutunamPageSearchResultsInterfaceFactory
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
     * @var SutunamPageInterfaceFactory
     */
    private $sutunamPageInterface;

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
     * @var ResourcePage
     */
    private $resource;

    /**
     * @var HydratorInterface|mixed
     */
    private $hydrator;

    /**
     * @param PageCollectionFactory $pageCollectionFactory
     * @param SutunamPageSearchResultsInterfaceFactory $sutunamSearchResultsFactory
     * @param SutunamDataHelper $sutunamDataHelper
     * @param SutunamConfigHelper $sutunamConfigHelper
     * @param SutunamPageInterfaceFactory $sutunamPageInterface
     * @param SearchCriteriaInterface $searchCriteria
     * @param FilterFactory $filterFactory
     * @param FilterGroup $filterGroup
     * @param DataObjectHelper $dataObjectHelper
     * @param StoreManagerInterface $storeManager
     * @param ResourcePage $resource
     * @param CollectionProcessorInterface|null $collectionProcessor
     * @param HydratorInterface|null $hydrator
     */
    public function __construct(
        PageCollectionFactory                    $pageCollectionFactory,
        SutunamPageSearchResultsInterfaceFactory $sutunamSearchResultsFactory,
        SutunamDataHelper                        $sutunamDataHelper,
        SutunamConfigHelper                      $sutunamConfigHelper,
        SutunamPageInterfaceFactory              $sutunamPageInterface,
        SearchCriteriaInterface                  $searchCriteria,
        FilterFactory                            $filterFactory,
        FilterGroup                              $filterGroup,
        DataObjectHelper                         $dataObjectHelper,
        StoreManagerInterface                    $storeManager,
        ResourcePage                             $resource,
        CollectionProcessorInterface             $collectionProcessor = null,
        ?HydratorInterface                       $hydrator = null
    ) {
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->sutunamSearchResultsFactory = $sutunamSearchResultsFactory;
        $this->sutunamDataHelper = $sutunamDataHelper;
        $this->sutunamConfigHelper = $sutunamConfigHelper;
        $this->sutunamPageInterface = $sutunamPageInterface;
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
     * Load Page data by given Page Identity
     *
     * @param string $pageId
     * @return Page
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($pageId)
    {
        $page = $this->sutunamPageInterface->create();
        $page->load($pageId);
        $page->setStoreCode($this->sutunamDataHelper->getStoreCodesByIds($page->getStores()));

        if (!$page->getId()) {
            throw new NoSuchEntityException(__('The CMS page with the "%1" ID doesn\'t exist.', $pageId));
        }
        return $page;
    }

    /**
     * Load Page data by given Page Identifier and store ids list
     *
     * @param string $pageIdentifier
     * @param string $storeIds
     * @return \Sutunam\CmsDataSync\Api\Data\PageSearchResultsInterface
     */
    public function getByIdentifier($pageIdentifier, $storeIds)
    {
        $searchedCollection = $this->pageCollectionFactory->create()
            ->addFieldToFilter(PageInterface::IDENTIFIER, ['eq' => $pageIdentifier])
            ->addFieldToFilter(SutunamPageInterface::STORE_ID, ['in' => explode(',', $storeIds)]);

        $items = [];
        foreach ($searchedCollection->getItems() as $block) {
            $blockDto = $this->sutunamPageInterface->create();
            $this->dataObjectHelper->populateWithArray(
                $blockDto,
                $block->getData(),
                SutunamPageInterface::class
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
     * Load Page data collection by given search criteria
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @param SearchCriteriaInterface $criteria
     * @return \Sutunam\CmsDataSync\Api\Data\PageSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria)
    {
        $collection = $this->pageCollectionFactory->create();

        $this->collectionProcessor->process($criteria, $collection);

        $items = [];
        foreach ($collection->getItems() as $page) {
            $pageDto = $this->sutunamPageInterface->create();
            $this->dataObjectHelper->populateWithArray(
                $pageDto,
                $page->getData(),
                SutunamPageInterface::class
            );
            $pageDto->setId($page->getId());
            $pageDto->setStoreCode($this->sutunamDataHelper->getStoreCodesByIds($page->getStores()));
            $items[] = $pageDto;
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
                'Magento\Cms\Model\Api\SearchCriteria\PageCollectionProcessor'
            );
        }
        return $this->collectionProcessor;
    }

    /**
     * Save Page data
     *
     * @param SutunamPageInterface $page
     * @return object|SutunamPageInterface
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function save(SutunamPageInterface $page)
    {
        if (empty($page->getStoreId())) {
            $page->setStoreId([$this->storeManager->getStore()->getId()]);
        }

        if ($page->getMedia() && $this->sutunamConfigHelper->isSyncMedia()) {
            foreach ($page->getMedia() as $media) {
                $this->sutunamDataHelper->saveMediaFromRemoteHost($media, $this->sutunamConfigHelper->isOverwrite());
            }
        }

        if ($page->getId() && $page instanceof Page && !$page->getOrigData()) {
            $page = $this->hydrator->hydrate($this->getById($page->getId()), $this->hydrator->extract($page));
        }

        try {
            $this->resource->save($page);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $page;
    }
}
