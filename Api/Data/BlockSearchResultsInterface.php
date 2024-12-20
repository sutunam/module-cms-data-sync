<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sutunam\CmsDataSync\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface for cms block search results.
 * @api
 * @since 100.0.2
 */
interface BlockSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get blocks list.
     *
     * @return \Sutunam\CmsDataSync\Api\Data\BlockInterface[]
     */
    public function getItems();

    /**
     * Set blocks list.
     *
     * @param \Sutunam\CmsDataSync\Api\Data\BlockInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
