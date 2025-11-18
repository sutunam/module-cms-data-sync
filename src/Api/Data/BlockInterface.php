<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */
namespace Sutunam\CmsDataSync\Api\Data;

/**
 * CMS block interface.
 * @api
 * @since 100.0.2
 */
interface BlockInterface extends \Magento\Cms\Api\Data\BlockInterface
{
    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    public const ID = 'id';

    public const ACTIVE = 'active';

    public const STORE_ID = 'store_id';

    public const STORE_CODE = 'store_code';

    public const MEDIA = 'media';

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
     * @return BlockInterface
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
     * @return BlockInterface
     */
    public function setStoreCode(array $stores);

    /**
     * Get media
     *
     * @return string[]
     */
    public function getMedia();

    /**
     * Set media
     *
     * @param string[] $media
     * @return BlockInterface
     */
    public function setMedia(array $media);
}
