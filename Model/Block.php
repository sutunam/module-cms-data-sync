<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sutunam\CmsDataSync\Model;

use Sutunam\CmsDataSync\Api\Data\BlockInterface;

class Block extends \Magento\Cms\Model\Block implements BlockInterface
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
