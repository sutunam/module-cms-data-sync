<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Block\Adminhtml\Listing\Buttons;

use Magento\Backend\Block\Widget\Context;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Block\Adminhtml\Block\Edit\GenericButton;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Sutunam\CmsDataSync\Helper\ApiHelper;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;

class SyncAllCmsButton implements ButtonProviderInterface
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var array
     */
    protected $data;

    /**
     * Construct
     *
     * @param Context $context
     * @param ConfigHelper $configHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        array $data = []
    ) {
        $this->configHelper = $configHelper;
        $this->data = $data;
        $this->context = $context;
    }

    /**
     * Return button attributes array
     *
     * @return array
     */
    public function getButtonData()
    {
        if (!$this->configHelper->isEnabled()) {
            return [];
        }

        $label = $this->data['action'] == ApiHelper::REQUEST_SYNC_TYPE_PULL ?
            'Pull all to %1' : ($this->data['action'] == ApiHelper::REQUEST_SYNC_TYPE_PUSH ? 'Push all to %1' : '');

        return [
            'label' => __($label, $this->configHelper->getSiteName()),
            'class' => 'primary',
            'on_click' => 'require([\'Magento_Ui/js/modal/confirm\'], function(confirm) {
                confirm({
                    title: "Confirmation",
                    content: "Are you sure you want to sync all?",
                    actions: {
                        confirm: function () {
                            setLocation("' . $this->getActionUrl() . '");
                        },
                        cancel: function () {}
                    }
                });
            })'
        ];
    }

    /**
     * Get action url
     *
     * @return string
     */
    protected function getActionUrl()
    {
        return $this->getUrl(
            'cms_data_sync/action/syncAll',
            [
                'type' => $this->data['type'] ?: '',
                'action' => $this->data['action'] ?: '',
            ]
        );
    }

    /**
     * Generate url by route and parameters
     *
     * @param   string $route
     * @param   array $params
     * @return  string
     */
    public function getUrl($route = '', $params = [])
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
