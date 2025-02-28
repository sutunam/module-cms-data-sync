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
use Twilio\Rest\Api;

class Block implements ProcessorInterface
{
    public const SUTUNAM_CMS_BLOCK_API_ENDPOINT = "sync/cmsBlock/";

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
        ];

        $block = $this->getBlock($id);
        if (!$block) {
            $result['error_message'] = __('Couldn\'t load CMS block content.');
            return $result;
        }

        $blockStoreIds = $block->getStores();
        $blockStoreCodes = $this->dataHelper->getStoreCodesByIds($blockStoreIds);
        $remoteStoreIds = $this->dataHelper->getRemoteStoreIdsListByMyStoreCodes($blockStoreCodes);

        $mappedStoresIds = $remoteStoreIds[DataHelper::STORES_MAPPED];
        if (!$mappedStoresIds) {
            $result['error_message'] = __('The store scope of block is not match.');
            return $result;
        }

        $remoteBlocks = $this->apiHelper->getDataByIdentifier(
            $block->getIdentifier(),
            ApiHelper::CMS_TYPE_BLOCK,
            ['store_ids' => implode(',', $mappedStoresIds)]
        );
        if (!$remoteBlocks) {
            $result['error_message'] = __('There is no remote block with identifier %1', $block->getIdentifier());
            return $result;
        }

        if (count($remoteBlocks) != 1) {
            $result['error_message'] = __('The store scope of block is not match.');
            return $result;
        }

        $remoteBlock = $remoteBlocks[0];

        if ($this->configHelper->isOverwrite()) {
            $storeCodesOfRemoteBlock = $remoteBlock[SutunamBlockInterface::STORE_CODE];
            $mappingStoreIdByCodesOfRemoteBlock = $this->dataHelper->getMyMappedStoreIdsListByRemoteStoreCodes(
                $storeCodesOfRemoteBlock
            );

            if ($diff = array_diff($mappingStoreIdByCodesOfRemoteBlock, $blockStoreIds)) {
                $checkBlockUsingDiffStores = $this->blockCollectionFactory->create()
                    ->addFieldToFilter(BlockInterface::IDENTIFIER, ['eq' => $block->getIdentifier()])
                    ->addFieldToFilter(SutunamBlockInterface::STORE_ID, ['in' => $diff])
                    ->addFieldToFilter(BlockInterface::BLOCK_ID, ['neq' => $id])
                    ->getSize();
                if (!$checkBlockUsingDiffStores) {
                    $blockStoreIds = $this->dataHelper->mergeTwoArrays(
                        $mappingStoreIdByCodesOfRemoteBlock,
                        $remoteStoreIds[DataHelper::STORES_UNMAPPED]
                    );
                }
            } else {
                $blockStoreIds = $mappingStoreIdByCodesOfRemoteBlock;
            }
        }

        try {
            $block->setData($remoteBlock);
            $block->setContent($this->dataHelper->replaceDomainInContentByAction(
                $block->getContent(),
                ApiHelper::REQUEST_SYNC_TYPE_PULL
            ));
            $block->setStoreId(array_map('intval', $blockStoreIds));

            $block->setId($id);
            // @phpstan-ignore-next-line
            $block->save();
            if ($this->configHelper->isSyncMedia()) {
                $this->dataHelper->saveAllImageFromCmsContent($block->getContent(), true);
            }
        } catch (\Exception $e) {
            $result['error_message'] = $e->getMessage();
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
            'error_message' => '',
            'notice_message' => '',
            'success_message' => ''
        ];

        $allRemoteBlocks = $this->apiHelper->getAllCms(ApiHelper::CMS_TYPE_BLOCK);
        if (!$allRemoteBlocks) {
            $result['error_message'] = __(
                'There is no remote block on the remote host %1',
                $this->configHelper->getSiteName()
            );
            return $result;
        }
        foreach ($allRemoteBlocks as $remoteBlock) {
            //Get Stores Ids list on my site that have the same values code with remote codes list
            $myStoreIds = $this->dataHelper->getMyMappedStoreIdsListByRemoteStoreCodes(
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

            // This variable is to check what store ids were used on my site,
            // so we can create the new cms with the stores weren't used when overwrite is disabled.
            $usagedStores = [];

            // if there's no cms mapped -> create the new cms
            if ($myBlocks->getSize() > 0) {
                foreach ($myBlocks as $myBlock) {
                    $usagedStores = $this->dataHelper->mergeTwoArrays($usagedStores, $myBlock->getStores());
                    if (!$this->configHelper->isOverwrite()) {
                        $result['notice_message'] .= __(
                            'Pull Block ID: %1 || Status: %2.<br>',
                            $myBlock->getId(),
                            __('Overwrite disabled - Skipped')
                        );
                        continue;
                    }

                    $response = $this->pull($myBlock->getId());
                    if (!$response['success']) {
                        $result['total_failed']++;
                        $result['error_message'] .= ($response['error_message'] ? __(
                            'Pull Block ID: %1 || Status: %2.<br>',
                            $myBlock->getId(),
                            $response['error_message']
                        ) : '');
                        $result['notice_message'] .= ($response['notice_message'] ? __(
                            'Pull Block ID: %1 || Status: %2.<br>',
                            $myBlock->getId(),
                            $response['notice_message']
                        ) : '');
                        continue;
                    }

                    $result['success_message'] .= __(
                        'Pull Block ID: %1 || Status: %2.<br>',
                        $myBlock->getId(),
                        __('Updated Successfully')
                    );
                    $result['total_update_success']++;
                }
            }

            if (!array_diff($myStoreIds, $usagedStores)) {
                continue;
            }

            try {
                $myBlock = $this->blockFactory->create();

                unset($remoteBlock[SutunamBlockInterface::ID]);
                $myBlockStoreIds = array_diff($myStoreIds, $usagedStores);

                $myBlock->setData($remoteBlock);
                $myBlock->setStoreId(array_map('intval', $myBlockStoreIds));
                $myBlock->setContent($this->dataHelper->replaceDomainInContentByAction(
                    $myBlock->getContent(),
                    ApiHelper::REQUEST_SYNC_TYPE_PULL
                ));

                // @phpstan-ignore-next-line
                $myBlock->save();

                if ($this->configHelper->isSyncMedia()) {
                    $this->dataHelper->saveAllImageFromCmsContent(
                        $myBlock->getContent(),
                        $this->configHelper->isOverwrite()
                    );
                }

                $result['success_message'] .= __(
                    'Pull Block ID: %1 || Status: %2.<br>',
                    $myBlock->getId(),
                    __('Created Successfully')
                );
                $result['total_create_success']++;

            } catch (\Exception $e) {
                $result['total_failed']++;
                $result['error_message'] .= __(
                    'Pull Block || Status: Create - %1.<br>',
                    $e->getMessage()
                );
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
            'success' => false,
            'type' => '',
            'error_message' => '',
            'notice_message' => '',
            'success_message' => '',
        ];

        $entity = $this->getBlock($id);
        if ($entity) {
            $remoteCmsSyncConfigs = $this->dataHelper->getCmsSyncConfigs();
            if (!$remoteCmsSyncConfigs) {
                $result['error_message'] = __(
                    'Something went wrong when trying to get configs of remote host %1',
                    $this->configHelper->getSiteName()
                );
                return $result;
            }

            $isRemoteHostOverWritable = $remoteCmsSyncConfigs[ConfigHelper::XML_CMS_DATA_SYNC_IS_OVERWRITE] ?: 0;

            $remoteStoreIds = $this->dataHelper->getMappedRemoteStoreIdsListByMyStoreIds($entity->getStores());
            if (!$remoteStoreIds) {
                $result['error_message'] = __('No remote store found by block id %1', $id);
                return $result;
            }

            $data = ["block" => [
                BlockInterface::IDENTIFIER => $entity->getIdentifier(),
                BlockInterface::TITLE => $entity->getTitle(),
                BlockInterface::CONTENT => $this->dataHelper->replaceDomainInContentByAction(
                    $entity->getContent(),
                    ApiHelper::REQUEST_SYNC_TYPE_PUSH
                ),
                SutunamBlockInterface::ACTIVE => $entity->isActive()
            ]];

            $remoteBlocks = $this->apiHelper->getDataByIdentifier(
                $entity->getIdentifier(),
                ApiHelper::CMS_TYPE_BLOCK,
                ['store_ids' => implode(',', $remoteStoreIds)]
            );

            $data['block'][SutunamBlockInterface::MEDIA] = $this->dataHelper->getAllImageFromCmsContent(
                $entity->getContent()
            );

            if (!$remoteBlocks) {
                $data['block'][SutunamBlockInterface::STORE_ID] = $remoteStoreIds;
                $response = $this->apiConnection->request(
                    Request::METHOD_POST,
                    self::SUTUNAM_CMS_BLOCK_API_ENDPOINT,
                    [],
                    $data
                );

                if ($response['success']) {
                    $result['success'] = $response['success'];
                    $result['type'] = 'created';
                    $result['success_message'] = __(
                        'Created new remote block with ID: %1 (My block ID: %2) on remote host %3 successfully',
                        $response['data'][SutunamBlockInterface::ID],
                        $id,
                        $this->configHelper->getSiteName()
                    );
                    return $result;
                }

                $result['error_message'] = __(
                    'Created new remote block for my block ID: %1 failed - Reason:%2',
                    $id,
                    $response['message']
                );
                return $result;
            }

            // Check what remote stores were used already.
            $remoteUsedStoreCodes = [];

            foreach ($remoteBlocks as $remoteBlock) {
                $remoteUsedStoreCodes = $this->dataHelper->mergeTwoArrays(
                    $remoteUsedStoreCodes,
                    $remoteBlock[SutunamBlockInterface::STORE_CODE]
                );
            }

            $myStoreIdsByRemoteStoreCodes
                = $this->dataHelper->getMyMappedStoreIdsListByRemoteStoreCodes($remoteUsedStoreCodes);
            $checkNumOfBlockWithIdentifier = $this->blockCollectionFactory->create()
                ->addFieldToFilter(BlockInterface::IDENTIFIER, ['eq' => $entity->getIdentifier()])
                ->addFieldToFilter(SutunamBlockInterface::STORE_ID, ['in' => $myStoreIdsByRemoteStoreCodes])
                ->getSize();
            if ($checkNumOfBlockWithIdentifier > 1) {
                $result['error_message'] = __(
                    'Update remote block for my block ID: %1 failed - Reason:%2',
                    $id,
                    'There\'s other CMS block affects with the same remote CMS block except block ID: ' . $id
                );
                return $result;
            }

            $countRemoteBlocks = count($remoteBlocks);
            $remoteUsedStoreIds = [];
            foreach ($remoteBlocks as $remoteBlock) {
                $remoteBlockId = $remoteBlock[SutunamBlockInterface::ID];
                $remoteBlockStoreIds = $remoteBlock[SutunamBlockInterface::STORE_ID];
                $remoteUsedStoreIds = $this->dataHelper->mergeTwoArrays($remoteUsedStoreIds, $remoteBlockStoreIds);

                if (!$isRemoteHostOverWritable) {
                    $result['notice_message'] .= __(
                        '<br>Update remote block for my block ID: %1 (Remote Block ID: %2) was skipped'
                        .' - Reason: Overwrite disabled on the remote host %3',
                        $id,
                        $remoteBlockId,
                        $this->configHelper->getSiteName()
                    );
                    continue;
                }

                $storeIdsParams = $remoteBlockStoreIds;
                if ($countRemoteBlocks == 1) {
                    $storeIdsParams = $remoteStoreIds;
                    $remoteUsedStoreIds = $remoteStoreIds;
                }
                $data['block'][SutunamBlockInterface::STORE_ID] = $this->dataHelper->mergeTwoArrays(
                    $storeIdsParams,
                    $this->dataHelper->getUnmappedRemoteStoreIdsByRemoteStoreCodes(
                        $remoteBlock[SutunamBlockInterface::STORE_CODE]
                    )
                );

                $data['block'][SutunamBlockInterface::ID] = $remoteBlockId;

                $response = $this->apiConnection->request(
                    Request::METHOD_PUT,
                    self::SUTUNAM_CMS_BLOCK_API_ENDPOINT . $remoteBlockId,
                    [],
                    $data
                );

                if ($response['success']) {
                    $result['success'] = true;
                    $result['success_message'] .= __(
                        'Update remote block for my block ID: %1
                        (Remote block ID: %2) on remote host %2 successfully',
                        $id,
                        $remoteBlockId,
                        $this->configHelper->getSiteName()
                    );
                } else {
                    $result['error_message'] .= __(
                        'Update remote block for my block ID: %1
                        (Remote Block ID: %2) failed - Reason:%3',
                        $id,
                        $remoteBlockId,
                        $response['message']
                    );
                }
            }

            if ($diff = array_diff($remoteStoreIds, $remoteUsedStoreIds)) {
                $data['block'][SutunamBlockInterface::STORE_ID] = $diff;
                unset($data['block'][SutunamBlockInterface::ID]);
                $response = $this->apiConnection->request(
                    Request::METHOD_POST,
                    self::SUTUNAM_CMS_BLOCK_API_ENDPOINT,
                    [],
                    $data
                );

                if ($response['success']) {
                    $result['success'] = true;
                    $result['success_message'] .= __(
                        'Created new remote block with ID: %1
                        (My block ID: %2) on remote host %3 successfully',
                        $response['data'][SutunamBlockInterface::ID],
                        $id,
                        $this->configHelper->getSiteName()
                    );
                } else {
                    $result['error_message'] .= __(
                        'Created new remote block for my block ID: %1 failed - Reason:%2',
                        $id,
                        $response['message']
                    );
                }
            }
            $result['type'] = 'updated';
        } else {
            $result['error_message'] = __("Couldn't load CMS block content.");
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
            'total_update_success' => 0,
            'total_create_success' => 0,
            'total_failed' => 0,
            'error_message' => '',
            'notice_message' => '',
            'success_message' => '',
        ];

        $allBlockIds = $this->blockCollectionFactory->create()->getAllIds();
        if (!$allBlockIds) {
            $result['error_message'] = __(
                'There is no remote block on the remote host %1',
                $this->configHelper->getSiteName()
            );
            return $result;
        }

        foreach ($allBlockIds as $blockId) {
            $response = $this->push($blockId);
            if (!$response['success']) {
                $result['total_failed']++;
                $result['error_message'] .= ($response['error_message'] ? __(
                    'Push Block ID: %1 ||Status: %2.<br>',
                    $blockId,
                    $response['error_message']
                ) : '');
                $result['notice_message'] .= ($response['notice_message'] ? __(
                    'Push Block ID: %1 ||Status: %2.<br>',
                    $blockId,
                    $response['notice_message']
                ) : '');
                continue;
            }

            if ($response['type'] == 'created') {
                $result['total_create_success']++;
                $result['success_message'] .= __(
                    'Push Block ID: %1 ||Status: %2.<br>',
                    $blockId,
                    __('Created Successfully')
                );
                continue;
            }

            $result['success_message'] .= __(
                'Push Block ID: %1 ||Status: %2.<br>',
                $blockId,
                __('Updated Successfully')
            );
            $result['total_update_success']++;
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
}
