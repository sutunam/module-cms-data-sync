<?php

namespace Sutunam\CmsDataSync\Plugin\Magento\Widget\Controller\Adminhtml\Widget\Instance;

class MassDelete
{
    /**
     * Around Execute
     *
     * @param \Magento\Widget\Controller\Adminhtml\Widget\Instance\MassDelete $subject
     * @param \Closure $proceed
     * @return mixed
     */
    public function aroundExecute(
        \Magento\Widget\Controller\Adminhtml\Widget\Instance\MassDelete $subject,
        \Closure $proceed
    ) {
        $allParams = $subject->getRequest()->getParams();
        if ($ids = $subject->getRequest()->getParam('ids')) {
            $allParams['delete'] = $ids;
        }
        $subject->getRequest()->setParams($allParams);
        return $proceed();
    }
}
