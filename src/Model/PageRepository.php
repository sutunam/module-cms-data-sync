<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Model;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Model\ResourceModel\Page as ResourcePage;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\FilterFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Sutunam\CmsDataSync\Api\Data\PageSearchResultsInterface;
use Sutunam\CmsDataSync\Api\PageRepositoryInterface as SutunamPageRepositoryInterface;
use Sutunam\CmsDataSync\Api\Data\PageInterface as SutunamPageInterface;
use Sutunam\CmsDataSync\Api\Data\PageInterfaceFactory as SutunamPageInterfaceFactory;
use Sutunam\CmsDataSync\Api\Data\PageSearchResultsInterfaceFactory as SutunamPageSearchResultsInterfaceFactory;
use Sutunam\CmsDataSync\Helper\Config as SutunamConfigHelper;
use Sutunam\CmsDataSync\Helper\Data as SutunamDataHelper;
use Magento\Cms\Model\Api\SearchCriteria\PageCollectionProcessor;

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
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @param PageCollectionFactory $pageCollectionFactory
     * @param SutunamPageSearchResultsInterfaceFactory $sutunamSearchResultsFactory
     * @param SutunamDataHelper $sutunamDataHelper
     * @param SutunamConfigHelper $sutunamConfigHelper
     * @param SutunamPageInterfaceFactory $sutunamPageInterface
     * @param DataObjectHelper $dataObjectHelper
     * @param StoreManagerInterface $storeManager
     * @param ResourcePage $resource
     * @param PageFactory $pageFactory
     * @param CollectionProcessorInterface|null $collectionProcessor
     */
    public function __construct(
        PageCollectionFactory                    $pageCollectionFactory,
        SutunamPageSearchResultsInterfaceFactory $sutunamSearchResultsFactory,
        SutunamDataHelper                        $sutunamDataHelper,
        SutunamConfigHelper                      $sutunamConfigHelper,
        SutunamPageInterfaceFactory              $sutunamPageInterface,
        DataObjectHelper                         $dataObjectHelper,
        StoreManagerInterface                    $storeManager,
        ResourcePage                             $resource,
        PageFactory                              $pageFactory,
        ?CollectionProcessorInterface            $collectionProcessor = null,
    ) {
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->sutunamSearchResultsFactory = $sutunamSearchResultsFactory;
        $this->sutunamDataHelper = $sutunamDataHelper;
        $this->sutunamConfigHelper = $sutunamConfigHelper;
        $this->sutunamPageInterface = $sutunamPageInterface;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        $this->pageFactory = $pageFactory;
        $this->collectionProcessor = $collectionProcessor ?: $this->getCollectionProcessor();
    }

    /**
     * Load Page data by given Page Identity
     *
     * @param string $pageId
     * @return Page
     * @throws NoSuchEntityException
     */
    public function getById($pageId)
    {
        $page = $this->pageFactory->create();
        $page->load((int) $pageId); /** @phpstan-ignore-line */
        $page->setStoreCode($this->sutunamDataHelper->getStoreCodesByIds($page->getStores()));

        if (!$page->getId()) {
            throw new NoSuchEntityException(__('CMS page with ID “%1” does not exist', $pageId));
        }
        return $page;
    }

    /**
     * Load Page data by given Page Identifier and store ids list
     *
     * @param string $pageIdentifier
     * @param string $storeIds
     * @return PageSearchResultsInterface
     */
    public function getByIdentifier($pageIdentifier, $storeIds)
    {
        $searchedCollection = $this->pageCollectionFactory->create()
            ->addFieldToFilter(PageInterface::IDENTIFIER, ['eq' => $pageIdentifier])
            ->setPageSize(1);

        $items = [];
        foreach ($searchedCollection->getItems() as $page) {
            /** @var Page $pageDto */
            $pageDto = $this->sutunamPageInterface->create();
            $this->dataObjectHelper->populateWithArray(
                $pageDto,
                $page->getData(),
                SutunamPageInterface::class
            );
            $pageDto->setData($page->getData());
            $pageDto->setId($page->getId());
            $pageDto->setStoreCode($this->sutunamDataHelper->getStoreCodesByIds($page->getStores()));
            $items[] = $pageDto;
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
     * @return PageSearchResultsInterface
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
        if (!$this->collectionProcessor) {
            // @phpstan-ignore-next-line
            $this->collectionProcessor = ObjectManager::getInstance()->get(PageCollectionProcessor::class);
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
        /** @var Page $page */
        if ($page->getId() && !$this->sutunamConfigHelper->isOverwrite()) {
            throw new CouldNotSaveException(__('CMS page cannot be overwritten'));
        }

        $page->setStoreCode($this->sutunamDataHelper->getStoreCodesByIds($page->getStores()));

        if (empty($page->getStoreId())) {
            $page->setStoreId([$this->storeManager->getStore()->getId()]);
        }

        try {
            if ($page->getMedia() && $this->sutunamConfigHelper->isSyncMedia()) {
                foreach ($page->getMedia() as $media) {
                    $this->sutunamDataHelper->saveMediaFromRemoteHost(
                        $media,
                        $this->sutunamConfigHelper->isOverwrite()
                    );
                }
            }
            $this->resource->save($page);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $page;
    }
}
