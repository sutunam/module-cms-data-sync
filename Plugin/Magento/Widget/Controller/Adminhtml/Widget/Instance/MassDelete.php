<?php

namespace Sutunam\CmsDataSync\Plugin\Magento\Widget\Controller\Adminhtml\Widget\Instance;

class MassDelete
{
    /**
     * Before Execute
     *
     * @param \Magento\Widget\Controller\Adminhtml\Widget\Instance\MassDelete $subject
     * @return mixed
     */
    public function beforeExecute(\Magento\Widget\Controller\Adminhtml\Widget\Instance\MassDelete $subject)
    {
        $allParams = $subject->getRequest()->getParams();
        if ($ids = $subject->getRequest()->getParam('ids')) {
            $allParams['delete'] = $ids;
        }
        $subject->getRequest()->setParams($allParams);
        return $subject;
    }
}
