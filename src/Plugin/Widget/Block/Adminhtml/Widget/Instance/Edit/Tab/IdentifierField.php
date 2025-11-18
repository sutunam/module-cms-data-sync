<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Plugin\Widget\Block\Adminhtml\Widget\Instance\Edit\Tab;

use Magento\Widget\Block\Adminhtml\Widget\Instance\Edit\Tab\Main;

class IdentifierField
{
    /**
     * Around Get Form Html
     *
     * @param Main $subject
     * @param \Closure $proceed
     * @return mixed
     */
    public function aroundGetFormHtml(Main $subject, \Closure $proceed)
    {
        $form = $subject->getForm();

        if (!is_object($form)) {
            return $proceed();
        }

        $fieldset = $form->getElement('base_fieldset');
        $fieldset->addField(
            'identifier',
            'text',
            [
                'name' => 'identifier',
                'label' => __('Identifier'),
                'id' => 'identifier',
                'title' => __('Identifier'),
                'required' => false,
                'note' => __('If this field is empty, the identifier will be auto generated.'),
                'value' => $subject->getWidgetInstance()->getData('identifier')
            ]
        );

        $subject->setForm($form);
        return $proceed();
    }
}
