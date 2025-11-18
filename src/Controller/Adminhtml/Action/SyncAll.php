<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Controller\Adminhtml\Action;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Sutunam\CmsDataSync\Helper\ApiHelper;
use Sutunam\CmsDataSync\Model\ProcessorFactory;

class SyncAll extends Action implements HttpGetActionInterface
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
     * Execute
     *
     * @return ResponseInterface|Redirect|(Redirect&ResultInterface)|ResultInterface
     */
    public function execute()
    {
        $type = $this->getRequest()->getParam('type');
        $action = $this->getRequest()->getParam('action');
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if (!$type || !$action) {
            $this->messageManager->addErrorMessage(__('Type/Action of request is required.'));
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }

        try {
            $processor = $this->processorFactory->create($type, []);
            $result = [];

            if ($action == ApiHelper::REQUEST_SYNC_TYPE_PULL) {
                $result = $processor->pullAll();
            }

            if ($action == ApiHelper::REQUEST_SYNC_TYPE_PUSH) {
                $result = $processor->pushAll();
            }
            $message = [];
            if ($createSuccess = $result['total_create_success']) {
                $message[] = __('Successfully created %1 item(s)', $createSuccess);
            }
            if ($updateSuccess = $result['total_update_success']) {
                $message[] = __('Successfully updated %1 item(s)', $updateSuccess);
            }

            if ($message) {
                $this->messageManager->addSuccessMessage(implode(', ', $message));
            }

            if ($totalFailed = $result['total_failed']) {
                $message = __('%1 item(s) have been synced fauikliled', $totalFailed);
                $this->messageManager->addErrorMessage($message);
            }
            if ($result['error_message']) {
                $this->messageManager->addError($result['error_message']);
            }
            if ($result['notice_message']) {
                $this->messageManager->addNotice($result['notice_message']);
            }
            if ($result['success_message']) {
                $this->messageManager->addSuccess($result['success_message']);
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        return $resultRedirect;
    }
}
