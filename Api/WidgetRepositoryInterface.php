<?php

namespace Sutunam\CmsDataSync\Api;

use Magento\Widget\Model\Widget\Instance;
use Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterface;

interface WidgetRepositoryInterface
{
    /**
     * Save widget
     *
     * @param WidgetInstanceInterface $widgetInstance
     * @return WidgetInstanceInterface
     */
    public function save(WidgetInstanceInterface $widgetInstance);

    /**
     * Get Widget by identifier and store ids list
     *
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
