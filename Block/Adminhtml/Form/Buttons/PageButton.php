<?php
namespace Sutunam\CmsDataSync\Block\Adminhtml\Form\Buttons;

use Magento\Backend\Block\Widget\Context;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Block\Adminhtml\Page\Edit\GenericButton;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Psr\Log\LoggerInterface;
use Sutunam\CmsDataSync\Helper\ApiHelper;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;

class PageButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var \Magento\Cms\Api\Data\PageInterface|null
     */
    protected $_page;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $data;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageRepositoryInterface $pageRepository
     * @param ConfigHelper $configHelper
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        PageRepositoryInterface $pageRepository,
        ConfigHelper $configHelper,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->data = $data;
        parent::__construct($context, $pageRepository);
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

        $this->_page = $this->getPage();
        if ($this->_page) {
            $label = $this->data['type'] == ApiHelper::REQUEST_SYNC_TYPE_PULL ?
                'Pull from %1' : ($this->data['type'] == ApiHelper::REQUEST_SYNC_TYPE_PUSH ? 'Push to %1' : '');
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
                "type" => $this->data['type'] ?: '',
                "action" => $this->data['action'] ?: '',
                "id" => $this->_page->getId()
            ]
        );
    }

    /**
     * Get current page
     *
     * @return \Magento\Cms\Api\Data\PageInterface|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getPage()
    {
        try {
            return $this->pageRepository->getById(
                $this->context->getRequest()->getParam('page_id')
            );
        } catch (NoSuchEntityException $e) {
            $this->logger->critical($e->getMessage());
        }
        return null;
    }
}
