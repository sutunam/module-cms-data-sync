<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */
namespace Sutunam\CmsDataSync\Model;

use Sutunam\CmsDataSync\Api\Data\PageInterface;

class Page extends \Magento\Cms\Model\Page implements PageInterface
{
    /**
     * @inheritdoc
     */
    public function getStoreId()
    {
        return $this->_getData(self::STORE_ID);
    }

    /**
     * @inheritdoc
     */
    public function setStoreId(array $storeIds)
    {
        $this->setData(self::STORE_ID, $storeIds);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getStoreCode()
    {
        return $this->_getData(self::STORE_CODE);
    }

    /**
     * @inheritdoc
     */
    public function setStoreCode(array $stores)
    {
        $this->setData(self::STORE_CODE, $stores);

        return $this;
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
    public function setMedia(array $media)
    {
        $this->setData(self::MEDIA, $media);

        return $this;
    }
}
