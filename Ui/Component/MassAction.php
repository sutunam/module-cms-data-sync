<?php

namespace Sutunam\CmsDataSync\Ui\Component;

use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;

class MassAction extends \Magento\Ui\Component\MassAction
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * Constructor
     *
     * @param ConfigHelper $configHelper
     * @param ContextInterface $context
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ConfigHelper $configHelper,
        ContextInterface $context,
        array $components = [],
        array $data = []
    ) {
        $this->configHelper = $configHelper;
        parent::__construct($context, $components, $data);
    }

    /**
     * Prepare massaction sync
     *
     * @return void
     */
    public function prepare()
    {
        parent::prepare();
        if (!$this->configHelper->isEnabled()) {
            $config = $this->getConfiguration();
            $notAllowedActions = ['sync_pull', 'sync_push'];
            $allowedActions = [];
            foreach ($config['actions'] as $action) {
                if (!in_array($action['type'], $notAllowedActions)) {
                    $allowedActions[] = $action;
                }
            }
            $config['actions'] = $allowedActions;
            $this->setData('config', (array)$config);
        }
    }
}
