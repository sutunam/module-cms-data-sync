<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Controller\Adminhtml\Action;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Sutunam\CmsDataSync\Helper\ApiHelper;
use Sutunam\CmsDataSync\Model\ProcessorFactory;

class MassSync extends Action implements HttpPostActionInterface
{
    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var ProcessorFactory
     */
    protected $processorFactory;

    /**
     * Constructor
     *
     * @param Action\Context $context
     * @param Filter $filter
     * @param ProcessorFactory $processorFactory
     */
    public function __construct(
        Action\Context $context,
        Filter $filter,
        ProcessorFactory $processorFactory
    ) {
        $this->filter = $filter;
        $this->processorFactory = $processorFactory;
        parent::__construct($context);
    }

    /**
     * Execute function
     *
     * @return Redirect|ResultInterface|(Redirect&ResultInterface)
     * @throws LocalizedException
     */
    public function execute()
    {
        $type = $this->getRequest()->getParam('type');
        $action = $this->getRequest()->getParam('action');
        $ids = $this->getRequest()->getParam('ids');
        if (!$ids) {
            $ids = $this->filter->getCollection($this->processorFactory->getCollection($type))->getAllIds();
        }

        if ($ids && $type && $action) {
            $success = 0;
            $processor = $this->processorFactory->create($type, []);
            foreach ($ids as $id) {
                $result = [];

                if ($action == ApiHelper::REQUEST_SYNC_TYPE_PULL) {
                    $result = $processor->pull($id);
                }

                if ($action == ApiHelper::REQUEST_SYNC_TYPE_PUSH) {
                    $result = $processor->push($id);
                }

                if (isset($result['success']) && $result['success']) {
                    $success++;
                }
                if (!empty($result['error_message'])) {
                    $this->messageManager->addErrorMessage($result['error_message']);
                }
                if (!empty($result['notice_message'])) {
                    $this->messageManager->addNotice($result['notice_message']);
                }
            }
            if ($success) {
                $message = __("Synced successfully");
                if ($success > 1) {
                    $message = __("%1 item(s) synced successfully", $success);
                }
                $this->messageManager->addSuccessMessage($message);
            }
        } else {
            $this->messageManager->addErrorMessage(__("An error occurred. Please try again"));
        }
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        return $resultRedirect;
    }
}
