<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Api\Data;

interface WidgetInstanceInterface
{
    public const ID = 'id';

    public const INSTANCE_ID = 'instance_id';

    public const INSTANCE_TYPE = 'instance_type';

    public const THEME_ID = 'theme_id';

    public const THEME_CODE = 'theme_code';

    public const STORE_IDS = 'store_ids';

    public const STORE_CODES = 'store_codes';

    public const TITLE = 'title';

    public const WIDGET_PARAMETERS = 'widget_parameters';

    public const IDENTIFIER = 'identifier';

    public const SORT_ORDER = 'sort_order';

    public const PAGE_GROUPS = 'page_groups';

    public const MEDIA = 'media';

    /**
     * Get Id
     *
     * @return int|null
     */
    public function getId();

    /**
     * Get Instance Type
     *
     * @return string|null
     */
    public function getInstanceType();

    /**
     * Get theme id
     *
     * @return int
     */
    public function getThemeId();

    /**
     * Get theme code
     *
     * @return string
     */
    public function getThemeCode();

    /**
     * Get store ids list
     *
     * @return int[]
     */
    public function getStoreIds();

    /**
     * Get store codes
     *
     * @return string[]
     */
    public function getStoreCodes();

    /**
     * Get Widget params
     *
     * @return mixed
     */
    public function getWidgetParameters();

    /**
     * Get Identifier
     *
     * @return string|null
     */
    public function getIdentifier();

    /**
     * Get title
     *
     * @return string|null
     */
    public function getTitle();

    /**
     * Get sort order
     *
     * @return int
     */
    public function getSortOrder();

    /**
     * Get Page Groups
     *
     * @return \Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterface[]
     */
    public function getPageGroups();

    /**
     * Get Media
     *
     * @return string[]
     */
    public function getMedia();

    /**
     * Set Id
     *
     * @param int $id
     * @return WidgetInstanceInterface
     */
    public function setId($id);

    /**
     * Set Instance type
     *
     * @param string $instanceType
     * @return WidgetInstanceInterface
     */
    public function setInstanceType($instanceType);

    /**
     * Set theme id
     *
     * @param string $themeId
     * @return WidgetInstanceInterface
     */
    public function setThemeId($themeId);

    /**
     * Set theme code
     *
     * @param string $themeCode
     * @return WidgetInstanceInterface
     */
    public function setThemeCode($themeCode);

    /**
     * Set store ids
     *
     * @param int[] $storeIds
     * @return WidgetInstanceInterface
     */
    public function setStoreIds($storeIds);

    /**
     * Set store codes
     *
     * @param string[] $storeCodes
     * @return WidgetInstanceInterface
     */
    public function setStoreCodes($storeCodes);

    /**
     * Set widget params
     *
     * @param mixed $widgetParameters
     * @return WidgetInstanceInterface
     */
    public function setWidgetParameters($widgetParameters);

    /**
     * Set identifier
     *
     * @param string $identifier
     * @return WidgetInstanceInterface
     */
    public function setIdentifier($identifier);

    /**
     * Set title
     *
     * @param string|null $title
     * @return WidgetInstanceInterface
     */
    public function setTitle($title);

    /**
     * Set sort order
     *
     * @param int $sortOrder
     * @return WidgetInstanceInterface
     */
    public function setSortOrder($sortOrder);

    /**
     * Set page groups
     *
     * @param \Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterface[] $pageGroups
     * @return WidgetInstanceInterface
     */
    public function setPageGroups($pageGroups);

    /**
     * Set media
     *
     * @param string[] $media
     * @return WidgetInstanceInterface
     */
    public function setMedia($media);
}
