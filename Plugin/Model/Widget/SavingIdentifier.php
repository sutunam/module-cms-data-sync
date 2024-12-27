<?php

namespace Sutunam\CmsDataSync\Plugin\Model\Widget;

use Magento\Framework\App\RequestFactory;
use Sutunam\CmsDataSync\Helper\Data as DataHelper;

class SavingIdentifier
{
    /**
     * @var RequestFactory
     */
    protected $request;

    /**
     * @var DataHelper
     */
    protected $dataHelper;

    public function __construct(
        RequestFactory $request,
        DataHelper $dataHelper
    ) {
        $this->request = $request;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Before Save
     *
     * @param \Magento\Widget\Model\Widget\Instance $instance
     * @return \Magento\Widget\Model\Widget\Instance
     */
    public function beforeSave(\Magento\Widget\Model\Widget\Instance $instance) {
        $identifierParam = $this->request->create()->getParam('identifier');
        if (!$identifierParam || !$instance->getData('identifier')) {
            $instance->setData('identifier', $this->dataHelper->generateIdentifier($instance->getData('title')));
            return $instance;
        }
        $instance->setData('identifier', $identifierParam);
        return $instance;
    }
}
