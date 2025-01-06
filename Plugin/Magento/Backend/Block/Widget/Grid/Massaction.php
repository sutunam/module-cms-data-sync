<?php

namespace Sutunam\CmsDataSync\Plugin\Magento\Backend\Block\Widget\Grid;

use Magento\Framework\DataObject;
use Sutunam\CmsDataSync\Helper\Config;

class Massaction
{
    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * Construct
     *
     * @param Config $configHelper
     */
    public function __construct(Config $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    /**
     * Around Add item to massaction
     *
     * @param \Magento\Backend\Block\Widget\Grid\Massaction $subject
     * @param \Closure $proceed
     * @param string $itemId
     * @param array|DataObject $item
     * @return \Magento\Backend\Block\Widget\Grid\Massaction|mixed
     */
    public function aroundAddItem(
        \Magento\Backend\Block\Widget\Grid\Massaction $subject,
        \Closure $proceed,
        $itemId,
        $item
    ) {
        $syncAction = ['sync_pull', 'sync_push'];

        if ($itemId == 'sync_pull') {
            $item['label'] = __('Pull from %1', $this->configHelper->getSiteName());
        }

        if ($itemId == 'sync_push') {
            $item['label'] = __('Push to %1', $this->configHelper->getSiteName());
        }

        if (in_array($itemId, $syncAction) && !$this->configHelper->isEnabled()) {
            return $subject;
        }

        return $proceed($itemId, $item);
    }
}
