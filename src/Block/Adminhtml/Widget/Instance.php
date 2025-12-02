<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Block\Adminhtml\Widget;

use Magento\Backend\Block\Widget\Context;
use Sutunam\CmsDataSync\Helper\ApiHelper;

class Instance extends \Magento\Widget\Block\Adminhtml\Widget\Instance
{
    /**
     * @var \Sutunam\CmsDataSync\Helper\Config
     */
    protected $configHelper;

    /**
     * Construct
     *
     * @param \Sutunam\CmsDataSync\Helper\Config $configHelper
     * @param Context $context
     */
    public function __construct(
        \Sutunam\CmsDataSync\Helper\Config $configHelper,
        Context $context
    ) {
        $this->configHelper = $configHelper;
        parent::__construct($context);
    }

    /**
     * Block constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        if ($this->configHelper->isEnabled()) {
            $this->buttonList->add('push_all', [
                'label' => __('Push all to %1', $this->configHelper->getSiteName()),
                'class' => 'primary',
                'on_click' => 'require([\'Magento_Ui/js/modal/confirm\'], function(confirm) {
                confirm({
                    title: "Confirmation",
                    content: "Are you sure you want to sync all?",
                    actions: {
                        confirm: function () {
                            setLocation("' . $this->getActionUrl(ApiHelper::REQUEST_SYNC_TYPE_PUSH) . '");
                        },
                        cancel: function () {}
                    }
                });
            })'
            ]);
            $this->buttonList->add('pull_all', [
                'label' => __('Pull all to %1', $this->configHelper->getSiteName()),
                'class' => 'primary',
                'on_click' => 'require([\'Magento_Ui/js/modal/confirm\'], function(confirm) {
                confirm({
                    title: "Confirmation",
                    content: "Are you sure you want to sync all?",
                    actions: {
                        confirm: function () {
                            setLocation("' . $this->getActionUrl(ApiHelper::REQUEST_SYNC_TYPE_PULL) . '");
                        },
                        cancel: function () {}
                    }
                });
            })'
            ]);
        }
    }

    /**
     * Get action url
     *
     * @param string $action
     * @return string
     */
    protected function getActionUrl($action)
    {
        return $this->getUrl(
            'cms_data_sync/action/syncAll',
            [
                'type' => ApiHelper::CMS_TYPE_WIDGET,
                'action' => $action
            ]
        );
    }
}
