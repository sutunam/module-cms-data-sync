<?php

namespace Sutunam\CmsDataSync\Api\Data;

use \Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterface;

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

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @return string|null
     */
    public function getInstanceType();

    /**
     * @return int
     */
    public function getThemeId();

    /**
     * @return string
     */
    public function getThemeCode();

    /**
     * @return int[]
     */
    public function getStoreIds();

    /**
     * @return string[]
     */
    public function getStoreCodes();

    /**
     * @return mixed
     */
    public function getWidgetParameters();

    /**
     * @return string|null
     */
    public function getIdentifier();

    /**
     * @return string|null
     */
    public function getTitle();

    /**
     * @return int
     */
    public function getSortOrder();

    /**
     * @return \Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterface[]
     */
    public function getPageGroups();

    /**
     * @param int $id
     * @return WidgetInstanceInterface
     */
    public function setId($id);

    /**
     * @param string $instanceType
     * @return WidgetInstanceInterface
     */
    public function setInstanceType($instanceType);

    /**
     * @param int $themeId
     * @return WidgetInstanceInterface
     */
    public function setThemeId($themeId);

    /**
     * @param string $themeCode
     * @return WidgetInstanceInterface
     */
    public function setThemeCode($themeCode);

    /**
     * @param int[] $storeIds
     * @return WidgetInstanceInterface
     */
    public function setStoreIds($storeIds);

    /**
     * @param string[] $storeCodes
     * @return WidgetInstanceInterface
     */
    public function setStoreCodes($storeCodes);

    /**
     * @param mixed $widgetParameters
     * @return WidgetInstanceInterface
     */
    public function setWidgetParameters($widgetParameters);

    /**
     * @param string $identifier
     * @return WidgetInstanceInterface
     */
    public function setIdentifier($identifier);

    /**
     * @param string|null $title
     * @return WidgetInstanceInterface
     */
    public function setTitle($title);

    /**
     * @param int $sortOrder
     * @return WidgetInstanceInterface
     */
    public function setSortOrder($sortOrder);

    /**
     * @param \Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterface[] $pageGroups
     * @return WidgetInstanceInterface
     */
    public function setPageGroups($pageGroups);

}
