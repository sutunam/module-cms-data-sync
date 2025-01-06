<?php
namespace Sutunam\CmsDataSync\Block\Adminhtml\Form\Buttons;

use Magento\Backend\Block\Widget\Context;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Block\Adminhtml\Block\Edit\GenericButton;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Psr\Log\LoggerInterface;
use Sutunam\CmsDataSync\Helper\ApiHelper;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;

class BlockButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var \Magento\Cms\Api\Data\BlockInterface|null
     */
    protected $_block;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $data;

    /**
     * @param Context $context
     * @param BlockRepositoryInterface $blockRepository
     * @param ConfigHelper $configHelper
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        BlockRepositoryInterface $blockRepository,
        ConfigHelper $configHelper,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->data = $data;
        parent::__construct($context, $blockRepository);
    }

    /**
     * Return button attributes array
     *
     * @return array|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getButtonData()
    {
        if (!$this->configHelper->isEnabled()) {
            return [];
        }

        $this->_block = $this->getBlock();
        if ($this->_block) {
            $label = $this->data['action'] == ApiHelper::REQUEST_SYNC_TYPE_PULL ?
                'Pull from %1' : ($this->data['action'] == ApiHelper::REQUEST_SYNC_TYPE_PUSH ? 'Push to %1' : '');
            return [
                'label' => __($label, $this->configHelper->getSiteName()),
                'on_click' => sprintf("location.href ='%s'", $this->getActionUrl()),
                'sort_order' => 0,
            ];
        }
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
                "type" => $this->data['type'],
                "action" => $this->data['action'],
                "id" => $this->_block->getId()
            ]
        );
    }

    /**
     * Get current block
     *
     * @return \Magento\Cms\Api\Data\BlockInterface|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getBlock()
    {
        try {
            return $this->blockRepository->getById(
                $this->context->getRequest()->getParam('block_id')
            );
        } catch (NoSuchEntityException $e) {
            $this->logger->critical($e->getMessage());
        }
        return null;
    }
}
