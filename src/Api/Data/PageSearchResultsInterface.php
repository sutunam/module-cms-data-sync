<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */
namespace Sutunam\CmsDataSync\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface for cms page search results.
 * @api
 * @since 100.0.2
 */
interface PageSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get pages list.
     *
     * @return \Sutunam\CmsDataSync\Api\Data\PageInterface[]
     */
    public function getItems();

    /**
     * Set pages list.
     *
     * @param \Sutunam\CmsDataSync\Api\Data\PageInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
