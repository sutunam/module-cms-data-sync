<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Controller\Adminhtml\Action;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Sutunam\CmsDataSync\Helper\ApiConnection;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;

class TestConnection extends Action implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var ApiConnection
     */
    protected $apiConnection;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * Construct
     *
     * @param Action\Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ApiConnection $apiConnection
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        ApiConnection $apiConnection,
        ConfigHelper $configHelper
    ) {
        $this->apiConnection = $apiConnection;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->configHelper = $configHelper;
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $result = [
            'success' => false,
            'errorMessage' => '',
        ];

        $request = $this->getRequest();
        $password = $request->getParam('password');
        if ($password == '******') {
            $password = $this->configHelper->getPassword();
        }
        try {
            $response = $this->apiConnection->getAdminToken(
                true,
                $request->getParam('username'),
                $password,
                $request->getParam('url')
            );
            if ($response) {
                $result['success'] = true;
            }
        } catch (\Exception $exception) {
            $result['errorMessage'] = $exception->getMessage();
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($result);
    }
}
