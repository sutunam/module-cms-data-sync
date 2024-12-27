<?php

namespace Sutunam\CmsDataSync\Api;

use Magento\Widget\Model\Widget\Instance;
use Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterface;

interface WidgetRepositoryInterface
{
    /**
     * @param WidgetInstanceInterface $widgetInstance
     * @return WidgetInstanceInterface
     */
    public function save(WidgetInstanceInterface $widgetInstance);

    /**
     * @param string $identifier
     * @param string $storeIds
     * @return \Sutunam\CmsDataSync\Api\Data\WidgetInstanceSearchResultsInterface
     */
    public function getByIdentifier($identifier, $storeIds);

    /**
     * Retrieve all widget
     *
     * @return \Sutunam\CmsDataSync\Api\Data\WidgetInstanceSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAll();

}
