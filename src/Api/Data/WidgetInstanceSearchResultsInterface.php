<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */
namespace Sutunam\CmsDataSync\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface for cms widget search results.
 * @api
 * @since 100.0.2
 */
interface WidgetInstanceSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get widget list.
     *
     * @return \Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterface[]
     */
    public function getItems();

    /**
     * Set widget list.
     *
     * @param \Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
