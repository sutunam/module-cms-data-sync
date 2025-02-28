<?php
namespace Sutunam\CmsDataSync\Controller\Adminhtml\Action;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Sutunam\CmsDataSync\Helper\ApiHelper;
use Sutunam\CmsDataSync\Model\ProcessorFactory;

class Sync extends Action implements HttpGetActionInterface
{
    /**
     * @var ProcessorFactory
     */
    protected $processorFactory;

    /**
     * Constructor
     *
     * @param Action\Context $context
     * @param ProcessorFactory $processorFactory
     */
    public function __construct(
        Action\Context $context,
        ProcessorFactory $processorFactory
    ) {
        $this->processorFactory = $processorFactory;
        parent::__construct($context);
    }

    /**
     * Execute Function
     *
     * @return Redirect|ResultInterface|(Redirect&ResultInterface)
     */
    public function execute()
    {
        $type = $this->getRequest()->getParam('type');
        $action = $this->getRequest()->getParam('action');
        $id = null;
        if ($this->getRequest()->getParam("id")) {
            $id = $this->getRequest()->getParam("id");
        }
        if ($id && $type && $action) {
            $processor = $this->processorFactory->create($type, []);
            $result = [];
            if ($action == ApiHelper::REQUEST_SYNC_TYPE_PULL) {
                $result = $processor->pull($id);
            }

            if ($action == ApiHelper::REQUEST_SYNC_TYPE_PUSH) {
                $result = $processor->push($id);
            }
            if (isset($result['success']) && $result['success']) {
                $this->messageManager->addSuccessMessage(__("Synced successfully"));
            } else {
                $this->messageManager->addErrorMessage($result['error_message']);
                $this->messageManager->addNotice($result['notice_message']);
            }
        } else {
            $this->messageManager->addErrorMessage(__("Some error occurred. Please try again"));
        }
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        return $resultRedirect;
    }
}
