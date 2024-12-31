<?php

namespace Sutunam\CmsDataSync\Plugin\Model\Widget;

use Magento\Framework\App\RequestFactory;
use Sutunam\CmsDataSync\Api\Data\WidgetInstanceInterface;
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

    /**
     * Construct
     *
     * @param RequestFactory $request
     * @param DataHelper $dataHelper
     */
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
        if ($instance->getData(WidgetInstanceInterface::IDENTIFIER)) {
            return $instance;
        }

        $identifierParam = $this->request->create()->getParam(WidgetInstanceInterface::IDENTIFIER);
        if (!$identifierParam) {
            $instance->setData(
                WidgetInstanceInterface::IDENTIFIER,
                $this->dataHelper->generateIdentifier($instance->getData('title'))
            );
            return $instance;
        }
        $instance->setData(WidgetInstanceInterface::IDENTIFIER, $identifierParam);
        return $instance;
    }
}
