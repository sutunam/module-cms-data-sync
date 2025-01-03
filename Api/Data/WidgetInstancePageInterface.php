<?php

namespace Sutunam\CmsDataSync\Api\Data;

interface WidgetInstancePageInterface
{
    public const PAGE_ID = 'page_id';
    public const INSTANCE_ID = 'instance_id';
    public const PAGE_GROUP = 'page_group';
    public const LAYOUT_HANDLE = 'layout_handle';
    public const BLOCK_REFERENCE = 'block_reference';
    public const PAGE_FOR = 'page_for';
    public const ENTITIES = 'entities';
    public const PAGE_TEMPLATE = 'page_template';

    public const PAGES = 'pages';
    public const PAGE_LAYOUTS = 'page_layouts';
    public const FOR = 'for';
    public const BLOCK = 'block';
    public const TEMPLATE = 'template';

    /**
     * Get Page Id
     *
     * @return int|null
     */
    public function getPageId();

    /**
     * Get instance id
     *
     * @return int|null
     */
    public function getInstanceId();

    /**
     * Get page groups
     *
     * @return string
     */
    public function getPageGroup();

    /**
     * Get layout handle
     *
     * @return string
     */
    public function getLayoutHandle();

    /**
     * Get block reference
     *
     * @return string
     */
    public function getBlockReference();

    /**
     * Get page for
     *
     * @return string
     */
    public function getPageFor();

    /**
     * Get entities
     *
     * @return mixed
     */
    public function getEntities();

    /**
     * Get page template
     *
     * @return string
     */
    public function getPageTemplate();

    /**
     * Set page id
     *
     * @param int $pageId
     * @return WidgetInstancePageInterface
     */
    public function setPageId($pageId);

    /**
     * Set instance id
     *
     * @param int $instanceId
     * @return WidgetInstancePageInterface
     */
    public function setInstanceId($instanceId);

    /**
     * Set page group
     *
     * @param string $pageGroup
     * @return WidgetInstancePageInterface
     */
    public function setPageGroup($pageGroup);

    /**
     * Set layout handle
     *
     * @param string $layoutHandle
     * @return WidgetInstancePageInterface
     */
    public function setLayoutHandle($layoutHandle);

    /**
     * Set block reference
     *
     * @param string $blockReference
     * @return WidgetInstancePageInterface
     */
    public function setBlockReference($blockReference);

    /**
     * Set page for
     *
     * @param string $pageFor
     * @return WidgetInstancePageInterface
     */
    public function setPageFor($pageFor);

    /**
     * Set entities
     *
     * @param mixed $entities
     * @return WidgetInstancePageInterface
     */
    public function setEntities($entities);

    /**
     * Set page template
     *
     * @param string $pageTemplate
     * @return WidgetInstancePageInterface
     */
    public function setPageTemplate($pageTemplate);
}
