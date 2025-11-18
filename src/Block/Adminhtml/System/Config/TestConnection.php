<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;

class TestConnection extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Sutunam_CmsDataSync::system/config/test_connection.phtml';

    /**
     * @inheritdoc
     */
    protected function _getFieldMapping()
    {
        return [
            'url' => 'cms_data_sync_connection_site_url',
            'username' => 'cms_data_sync_connection_username',
            'password' => 'cms_data_sync_connection_password',
        ];
    }

    /**
     * Unset some non-related element parameters
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue(); // @phpstan-ignore-line
        return parent::render($element);
    }

    /**
     * Get the button and scripts contents
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $originalData = $element->getOriginalData(); // @phpstan-ignore-line
        $this->addData(
            [
                'button_label' => __($originalData['button_label']),
                'html_id' => $element->getHtmlId(),
                'ajax_url' => $this->_urlBuilder->getUrl('cms_data_sync/action/testConnection'),
                'field_mapping' => str_replace('"', '\\"', json_encode($this->_getFieldMapping()))
            ]
        );

        return $this->_toHtml();
    }
}
