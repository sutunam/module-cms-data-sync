<?php
namespace Sutunam\CmsDataSync\Model\Processors\Cms;

use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\BlockRepository;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Sutunam\CmsDataSync\Api\Data\BlockInterface as SutunamBlockInterface;
use Sutunam\CmsDataSync\Api\ProcessorInterface;
use Sutunam\CmsDataSync\Helper\ApiConnection;
use Sutunam\CmsDataSync\Helper\ApiHelper;
use Sutunam\CmsDataSync\Helper\Data as DataHelper;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;
use Laminas\Http\Request;
use Sutunam\CmsDataSync\Logger\Logger;

class Block implements ProcessorInterface
{
    public const SUTUNAM_CMS_BLOCK_API_ENDPOINT = "sutunam/cmsBlock/";

    /**
     * @var BlockRepository
     */
    protected $blockRepository;

    /**
     * @var BlockFactory
     */
    protected $blockFactory;

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
     * @var BlockCollectionFactory
     */
    protected $blockCollectionFactory;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param BlockRepository $blockRepository
     * @param BlockFactory $blockFactory
     * @param ApiHelper $apiHelper
     * @param ApiConnection $apiConnection
     * @param DataHelper $dataHelper
     * @param ConfigHelper $configHelper
     * @param BlockCollectionFactory $blockCollectionFactory
     * @param Logger $logger
     */
    public function __construct(
        BlockRepository $blockRepository,
        BlockFactory $blockFactory,
        ApiHelper $apiHelper,
        ApiConnection $apiConnection,
        DataHelper $dataHelper,
        ConfigHelper $configHelper,
        BlockCollectionFactory $blockCollectionFactory,
        Logger $logger
    ) {
        $this->blockFactory = $blockFactory;
        $this->blockRepository = $blockRepository;
        $this->apiHelper = $apiHelper;
        $this->apiConnection = $apiConnection;
        $this->dataHelper = $dataHelper;
        $this->configHelper = $configHelper;
        $this->blockCollectionFactory = $blockCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * Pull cms block from remote host by my id
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

        $block = $this->getBlock($id);
        if (!$block) {
            $result['message'] = __('Couldn\'t load CMS block content.');
        }

        $blockStoreIds = $block->getStores();
        $blockStoreCodes = $this->dataHelper->getStoreCodesByIds($blockStoreIds);
        $remoteStoreIds = $this->dataHelper->getRemoteStoreIdsListByMyStoreCodes($blockStoreCodes);
        if (!$remoteStoreIds) {
            $result['message'] = __('The store scope of block is not match.');
            return $result;
        }

        $remoteBlocks = $this->apiHelper->getDataByIdentifier(
            $block->getIdentifier(),
            ApiHelper::CMS_TYPE_BLOCK,
            ['store_ids' => implode(',', $remoteStoreIds)]
        );
        if (!$remoteBlocks) {
            $result['message'] = __('There is no remote block with identifier %1', $block->getIdentifier());
            return $result;
        }

        if (count($remoteBlocks) != 1) {
            $result['message'] = __('The store scope of block is not match.');
            return $result;
        }

        $remoteBlock = $remoteBlocks[0];
        try {
            $block->setData($remoteBlock);
            $block->setId($id);
            $block->save();
            if ($this->configHelper->isSyncMedia()) {
                $this->dataHelper->saveAllImageFromCmsContent($block->getContent(), true);
            }
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            $this->logger->critical($e->getMessage());
        }
        $result['success'] = true;
        return $result;
    }

    /**
     * Pull all cms block from remote host
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

        $allRemoteBlocks = $this->apiHelper->getAllCms(ApiHelper::CMS_TYPE_BLOCK);
        if (!$allRemoteBlocks) {
            $result['message'] = __(
                'There is no remote block on the remote host %1',
                $this->configHelper->getSiteName()
            );
            return $result;
        }
        foreach ($allRemoteBlocks as $remoteBlock) {
            //Get Stores Ids list on my site that have the same values code with remote codes list
            $myStoreIds = $this->dataHelper->getMyStoreIdsListByRemoteStoreCodes(
                $remoteBlock[SutunamBlockInterface::STORE_CODE]
            );

            //Skip this remote block if there's not stores compatibled
            if (!$myStoreIds) {
                continue;
            }

            //Get Blocks by identifier and my store list
            $myBlocks = $this->blockCollectionFactory->create()
                ->addFieldToFilter(BlockInterface::IDENTIFIER, ['eq' => $remoteBlock[BlockInterface::IDENTIFIER]])
                ->addFieldToFilter(SutunamBlockInterface::STORE_ID, ['in' => $myStoreIds]);

            if ($myBlocks->getSize() > 1) {
                continue;
            }

            try {
                $myBlock = $this->blockFactory->create();

                $myBlockId = $myBlocks->getFirstItem()->getId();
                $myBlockStoreIds = [];

                if ($myBlockId) {
                    $myBlock = $this->blockRepository->getById($myBlockId);
                    $myBlockStoreIds = $myBlocks->getFirstItem()->getStores();
                }

                if (!$this->validateBlock($remoteBlock, $myBlockStoreIds, $myStoreIds)) {
                    continue;
                }

                $myBlockStoreIds  = !empty($myBlockStoreIds) ? $myBlockStoreIds : $myStoreIds;

                $myBlock->setData($remoteBlock);
                $myBlock->setStoreId(array_map('intval', $myBlockStoreIds));

                if ($this->configHelper->isOverwrite()) {
                    $myBlock->setId($myBlockId);
                    /** @phpstan-ignore-next-line */
                    $myBlock->save();
                } elseif (!$myBlockId) {
                    /** @phpstan-ignore-next-line */
                    $myBlock->save();
                } else {
                    continue;
                }

                if ($this->configHelper->isSyncMedia()) {
                    $this->dataHelper->saveAllImageFromCmsContent(
                        $myBlock->getContent(),
                        $this->configHelper->isOverwrite()
                    );
                }

                if ($myBlockId) {
                    $result['total_update_success']++;
                } else {
                    $result['total_create_success']++;
                }
            } catch (\Exception $e) {
                $result['total_failed']++;
                $this->logger->critical($e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Push cms block to remote host by my id
     *
     * @param int $id
     * @return array
     * @throws \Exception
     */
    public function push($id)
    {
        $result = [
            "success" => false,
            "message" => ""
        ];
        $entity = $this->getBlock($id);
        if ($entity) {
            $remoteStoreIds = $this->dataHelper->getRemoteStoreIdsListByMyStoreIds($entity->getStores());
            $data = ["block" => [
                BlockInterface::IDENTIFIER => $entity->getIdentifier(),
                BlockInterface::TITLE => $entity->getTitle(),
                BlockInterface::CONTENT => $entity->getContent(),
                SutunamBlockInterface::ACTIVE => $entity->isActive()
            ]];

            $remoteBlocks = $this->apiHelper->getDataByIdentifier(
                $entity->getIdentifier(),
                ApiHelper::CMS_TYPE_BLOCK,
                ['store_ids' => implode(',', $remoteStoreIds)]
            );

            if ($this->configHelper->isSyncMedia()) {
                $data['block'][SutunamBlockInterface::MEDIA] = $this->dataHelper->getAllImageFromCmsContent(
                    $entity->getContent()
                );
            }

            if (!$remoteBlocks) {
                $data['block'][SutunamBlockInterface::STORE_ID] = $remoteStoreIds;
                $result = $this->apiConnection->request(
                    Request::METHOD_POST,
                    self::SUTUNAM_CMS_BLOCK_API_ENDPOINT,
                    [],
                    $data
                );
                return $result;
            }

            foreach ($remoteBlocks as $remoteBlock) {
                $remoteBlockId = $remoteBlock['id'];
                $data['block'][SutunamBlockInterface::STORE_ID] = $remoteBlock[SutunamBlockInterface::STORE_ID];
                $response = $this->apiConnection->request(
                    Request::METHOD_PUT,
                    self::SUTUNAM_CMS_BLOCK_API_ENDPOINT . $remoteBlockId,
                    [],
                    $data
                );
                if ($response['success']) {
                    $result['success'] = true;
                }
            }

        } else {
            $result['response'] = __("Couldn't load CMS page content.");
        }
        return $result;
    }

    /**
     * Push all cms block to remote host
     *
     * @return array
     */
    public function pushAll()
    {
        $result = [
            'total_success' => 0,
            'total_failed' => 0,
            'message' => ''
        ];

        $allBlockIds = $this->blockCollectionFactory->create()->getAllIds();
        if (!$allBlockIds) {
            $result['message'] = __(
                'There is no remote block on the remote host %1',
                $this->configHelper->getSiteName()
            );
            return $result;
        }

        foreach ($allBlockIds as $blockId) {
            $response = $this->push($blockId);
            if (!$response['success']) {
                $result['total_failed']++;
                continue;
            }
            $result['total_success']++;
        }
        return $result;
    }

    /**
     * Get block by id
     *
     * @param int $id
     * @return false|\Magento\Cms\Model\Block
     */
    protected function getBlock($id)
    {
        try {
            return $this->blockRepository->getById($id);
        } catch (\Exception $exception) {
            $this->logger->critical($exception->getMessage());
        }
        return false;
    }

    /**
     * Validate block
     *
     * @param mixed $remoteBlock
     * @param array $myBlockStoreIds
     * @param array $mappingStoreIds
     * @return bool
     */
    public function validateBlock($remoteBlock, $myBlockStoreIds, $mappingStoreIds)
    {
        //Get list diff stores id from block found in collection with list store gotten from remote codes list
        $diffIds = array_diff($myBlockStoreIds, $mappingStoreIds);
        if ($diffIds) {
            //Check there's any other remote block using diff ids
            $remoteBlockUsingDiffIds = $this->apiHelper->getDataByIdentifier(
                $remoteBlock[BlockInterface::IDENTIFIER],
                ApiHelper::CMS_TYPE_BLOCK,
                $this->dataHelper->getRemoteStoreIdsListByMyStoreIds($diffIds)
            );

            //If yes, skip it
            if ($remoteBlockUsingDiffIds) {
                return false;
            }
        }
        return true;
    }
}
