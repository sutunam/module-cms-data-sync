<?php
namespace Sutunam\CmsDataSync\Block\Adminhtml\Listing\Buttons;

use Magento\Backend\Block\Widget\Context;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Block\Adminhtml\Page\Edit\GenericButton;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Sutunam\CmsDataSync\Helper\ApiHelper;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;

class PullAllCmsPage extends GenericButton implements ButtonProviderInterface
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageRepositoryInterface $pageRepository
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Context $context,
        PageRepositoryInterface $pageRepository,
        ConfigHelper $configHelper,
    ) {
        $this->configHelper = $configHelper;
        parent::__construct($context, $pageRepository);
    }

    /**
     * Return button attributes array
     *
     * @return array
     */
    public function getButtonData()
    {
        if (!$this->configHelper->isEnabled()) {
            return [];
        }

        return [
            'label' => __('Pull All from %1', $this->configHelper->getSiteName()),
            'class' => 'primary',
            'on_click' => 'require([\'Magento_Ui/js/modal/confirm\'], function(confirm) {
                confirm({
                    title: "Confirmation",
                    content: "Are you sure you want to sync all?",
                    actions: {
                        confirm: function () {
                            setLocation("' . $this->getActionUrl() . '");
                        },
                        cancel: function () {}
                    }
                });
            })'
        ];
    }

    /**
     * Get action url
     *
     * @return string
     */
    protected function getActionUrl()
    {
        return $this->getUrl(
            'cms_data_sync/action/syncAll',
            [
                'type' => ApiHelper::CMS_TYPE_PAGE,
                'action' => ApiHelper::REQUEST_SYNC_TYPE_PULL
            ]
        );
    }
}
