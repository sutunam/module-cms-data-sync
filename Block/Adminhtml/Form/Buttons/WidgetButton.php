<?php
namespace Sutunam\CmsDataSync\Block\Adminhtml\Form\Buttons;

use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;

/**
 * Adminhtml Back button.
 *
 * @category Smile
 * @package  Smile\ElasticsuiteIndices
 * @author   Dmytro ANDROSHCHUK <dmand@smile.fr>
 */
class WidgetButton extends Container
{
    protected $configHelper;

    /**
     * @param Context $context The current context.
     * @param array   $data    Data.
     */
    public function __construct(
        Context $context,
        \Sutunam\CmsDataSync\Helper\Config $configHelper,
        array $data = []
    ) {
        $this->configHelper = $configHelper;
        parent::__construct($context, $data);
        $this->addButton(
            'sync',
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
        return [
            'label' => __('Push to %1', $this->configHelper->getSiteName()),
            'on_click' => '',
            'sort_order' => 0,
        ];
    }
}
