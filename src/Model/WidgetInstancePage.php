<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Model;

use Magento\Framework\DataObject;
use Sutunam\CmsDataSync\Api\Data\WidgetInstancePageInterface;

class WidgetInstancePage extends DataObject implements WidgetInstancePageInterface
{
    /**
     * @inheritdoc
     */
    public function getPageId()
    {
        return $this->_getData(self::PAGE_ID);
    }

    /**
     * @inheritdoc
     */
    public function getInstanceId()
    {
        return $this->_getData(self::INSTANCE_ID);
    }

    /**
     * @inheritdoc
     */
    public function getPageGroup()
    {
        return $this->_getData(self::PAGE_GROUP);
    }

    /**
     * @inheritdoc
     */
    public function getLayoutHandle()
    {
        return $this->_getData(self::LAYOUT_HANDLE);
    }

    /**
     * @inheritdoc
     */
    public function getBlockReference()
    {
        return $this->_getData(self::BLOCK_REFERENCE);
    }

    /**
     * @inheritdoc
     */
    public function getPageFor()
    {
        return $this->_getData(self::PAGE_FOR);
    }

    /**
     * @inheritdoc
     */
    public function getEntities()
    {
        return $this->_getData(self::ENTITIES);
    }

    /**
     * @inheritdoc
     */
    public function getPageTemplate()
    {
        return $this->_getData(self::PAGE_TEMPLATE);
    }

    /**
     * @inheritdoc
     */
    public function setPageId($pageId)
    {
        $this->setData(self::PAGE_ID, $pageId);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setInstanceId($instanceId)
    {
        $this->setData(self::INSTANCE_ID, $instanceId);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setPageGroup($pageGroup)
    {
        $this->setData(self::PAGE_GROUP, $pageGroup);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setLayoutHandle($layoutHandle)
    {
        $this->setData(self::LAYOUT_HANDLE, $layoutHandle);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setBlockReference($blockReference)
    {
        $this->setData(self::BLOCK_REFERENCE, $blockReference);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setPageFor($pageFor)
    {
        $this->setData(self::PAGE_FOR, $pageFor);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setEntities($entities)
    {
        $this->setData(self::ENTITIES, $entities);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setPageTemplate($pageTemplate)
    {
        $this->setData(self::PAGE_TEMPLATE, $pageTemplate);

        return $this;
    }
}
