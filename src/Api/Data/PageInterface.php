<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */
namespace Sutunam\CmsDataSync\Api\Data;

/**
 * CMS page interface.
 * @api
 * @since 100.0.2
 */
interface PageInterface extends \Magento\Cms\Api\Data\PageInterface
{
    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    public const ID = 'id';

    public const ACTIVE = 'active';

    public const STORE_ID = 'store_id';

    public const STORE_CODE = 'store_code';

    public const MEDIA = 'media';
    public const IMAGE = 'image';

    /**#@-*/

    /**
     * Get Store Id
     *
     * @return int[]
     */
    public function getStoreId();

    /**
     * Set Store Id
     *
     * @param int[] $storeIds
     * @return PageInterface
     */
    public function setStoreId(array $storeIds);

    /**
     * Get Store Code
     *
     * @return string[]
     */
    public function getStoreCode();

    /**
     * Set Store Code
     *
     * @param string[] $stores
     * @return PageInterface
     */
    public function setStoreCode(array $stores);

    /**
     * Get Media
     *
     * @return string[]
     */
    public function getMedia();

    /**
     * Set Media
     *
     * @param string[] $media
     * @return PageInterface
     */
    public function setMedia(array $media);
}
