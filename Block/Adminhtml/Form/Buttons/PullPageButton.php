<?php
namespace Sutunam\CmsDataSync\Page\Adminhtml\Form\Buttons;

use Magento\Backend\Block\Widget\Context;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Block\Adminhtml\Page\Edit\GenericButton;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Psr\Log\LoggerInterface;
use Sutunam\CmsDataSync\Helper\ApiHelper;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;

class PullPageButton extends GenericButton implements ButtonProviderInterface
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
     * Constructor
     *
     * @param Context $context
     * @param PageRepositoryInterface $pageRepository
     * @param ConfigHelper $configHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        PageRepositoryInterface $pageRepository,
        ConfigHelper $configHelper,
        LoggerInterface $logger
    ) {
        $this->configHelper = $configHelper;
        $this->logger = $logger;
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
            return [
                'label' => __('Pull from %1', $this->configHelper->getSiteName()),
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
                "type" => ApiHelper::CMS_TYPE_BLOCK,
                "action" => ApiHelper::REQUEST_SYNC_TYPE_PULL,
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
