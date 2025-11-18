<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Block\Adminhtml\Form\Buttons;

use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Sutunam\CmsDataSync\Helper\ApiHelper;

class WidgetButton extends Container
{
    /**
     * @var \Sutunam\CmsDataSync\Helper\Config
     */
    protected $configHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $params;

    /**
     * Constructor
     *
     * @param Context $context
     * @param \Sutunam\CmsDataSync\Helper\Config $configHelper
     * @param LoggerInterface $logger
     * @param array $params
     * @param array $data
     */
    public function __construct(
        Context $context,
        \Sutunam\CmsDataSync\Helper\Config $configHelper,
        LoggerInterface $logger,
        array $params = [],
        array $data = []
    ) {
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->params = $params;
        parent::__construct($context, $data);
        $this->addButton(
            'sync' . $this->params['action'],
            $this->getButtonData()
        );
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

        $label = $this->params['action'] == ApiHelper::REQUEST_SYNC_TYPE_PULL ?
            'Pull from %1' : ($this->params['action'] == ApiHelper::REQUEST_SYNC_TYPE_PUSH ? 'Push to %1' : '');
        return [
            'label' => __($label, $this->configHelper->getSiteName()),
            'on_click' => sprintf("location.href ='%s'", $this->getActionUrl()),
            'sort_order' => 0,
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
            'cms_data_sync/action/sync',
            [
                "type" => $this->params['type'],
                "action" => $this->params['action'],
                "id" => $this->templateContext->getRequest()->getParam('instance_id')
            ]
        );
    }
}
