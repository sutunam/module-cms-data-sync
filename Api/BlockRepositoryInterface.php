<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sutunam\CmsDataSync\Api;

/**
 * CMS block CRUD interface.
 * @api
 * @since 100.0.2
 */
interface BlockRepositoryInterface
{
    /**
     * Save block.
     *
     * @param \Sutunam\CmsDataSync\Api\Data\BlockInterface $block
     * @return \Sutunam\CmsDataSync\Api\Data\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(\Sutunam\CmsDataSync\Api\Data\BlockInterface $block);

    /**
     * Retrieve block by block ID.
     *
     * @param string $blockId
     * @return \Sutunam\CmsDataSync\Api\Data\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($blockId);

    /**
     * Retrieve block by block identifier and stores(optional)
     *
     * @param string $identifier
     * @param string $storeIds
     * @return \Sutunam\CmsDataSync\Api\Data\BlockSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getByIdentifier($identifier, $storeIds);

    /**
     * Retrieve blocks matching the specified criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Sutunam\CmsDataSync\Api\Data\BlockSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);
}
