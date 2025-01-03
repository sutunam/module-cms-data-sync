<?php

namespace Sutunam\CmsDataSync\Model;

use Magento\Widget\Model\Widget\Instance;
use Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterface;

class WidgetInstance extends Instance implements WidgetInstanceInterface
{
    /**
     * @inheritdoc
     */
    public function getInstanceType()
    {
        return $this->_getData(self::INSTANCE_TYPE);
    }

    /**
     * @inheritdoc
     */
    public function getThemeId()
    {
        return $this->_getData(self::THEME_ID);
    }


    /**
     * @inheritdoc
     */
    public function getThemeCode()
    {
        return $this->_getData(self::THEME_CODE);
    }

    /**
     * @inheritdoc
     */
    public function getStoreIds()
    {
        return $this->_getData(self::STORE_IDS);
    }

    /**
     * @inheritdoc
     */
    public function getStoreCodes()
    {
        return $this->_getData(self::STORE_CODES);
    }

    /**
     * @inheritdoc
     */
    public function getWidgetParameters()
    {
        return $this->_getData(self::WIDGET_PARAMETERS);
    }

    /**
     * @inheritdoc
     */
    public function getIdentifier()
    {
        return $this->_getData(self::IDENTIFIER);
    }

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return $this->_getData(self::TITLE);
    }

    /**
     * @inheritdoc
     */
    public function getSortOrder()
    {
        return $this->_getData(self::SORT_ORDER);
    }

    /**
     * @inheritdoc
     */
    public function getPageGroups()
    {
        return $this->_getData(self::PAGE_GROUPS);
    }

    /**
     * @inheritdoc
     */
    public function getMedia()
    {
        return $this->_getData(self::MEDIA);
    }

    /**
     * @inheritdoc
     */
    public function setInstanceType($instanceType)
    {
        $this->setData(self::INSTANCE_TYPE, $instanceType);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setThemeId($themeId)
    {
        $this->setData(self::THEME_ID, $themeId);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setThemeCode($themeCode)
    {
        $this->setData(self::THEME_CODE, $themeCode);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setStoreIds($storeIds)
    {
        $this->setData(self::STORE_IDS, $storeIds);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setStoreCodes($storeCodes)
    {
        $this->setData(self::STORE_CODES, $storeCodes);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setWidgetParameters($widgetParameters)
    {
        $this->setData(self::WIDGET_PARAMETERS, $widgetParameters);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setIdentifier($identifier)
    {
        $this->setData(self::IDENTIFIER, $identifier);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setTitle($title)
    {
        $this->setData(self::TITLE, $title);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setSortOrder($sortOrder)
    {
        $this->setData(self::SORT_ORDER, $sortOrder);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setPageGroups($pageGroups)
    {
        $this->setData(self::PAGE_GROUPS, $pageGroups);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setMedia($media)
    {
        $this->setData(self::MEDIA, $media);

        return $this;
    }
}
