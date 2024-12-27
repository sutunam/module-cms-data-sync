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

    /**
     * @return int|null
     */
    public function getPageId();

    /**
     * @return int|null
     */
    public function getInstanceId();

    /**
     * @return string
     */
    public function getPageGroup();

    /**
     * @return string
     */
    public function getLayoutHandle();

    /**
     * @return string
     */
    public function getBlockReference();

    /**
     * @return string
     */
    public function getPageFor();

    /**
     * @return mixed
     */
    public function getEntities();

    /**
     * @return string
     */
    public function getPageTemplate();

    /**
     * @param int $pageId
     * @return WidgetInstancePageInterface
     */
    public function setPageId($pageId);

    /**
     * @param int $instanceId
     * @return WidgetInstancePageInterface
     */
    public function setInstanceId($instanceId);

    /**
     * @param string $pageGroup
     * @return WidgetInstancePageInterface
     */
    public function setPageGroup($pageGroup);

    /**
     * @param string $layoutHandle
     * @return WidgetInstancePageInterface
     */
    public function setLayoutHandle($layoutHandle);

    /**
     * @param string $blockReference
     * @return WidgetInstancePageInterface
     */
    public function setBlockReference($blockReference);

    /**
     * @param string $pageFor
     * @return WidgetInstancePageInterface
     */
    public function setPageFor($pageFor);

    /**
     * @param mixed $entities
     * @return WidgetInstancePageInterface
     */
    public function setEntities($entities);

    /**
     * @param string $pageTemplate
     * @return WidgetInstancePageInterface
     */
    public function setPageTemplate($pageTemplate);

}
