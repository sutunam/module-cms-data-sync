<?php

namespace Sutunam\CmsDataSync\Helper;

use Laminas\Http\Client;
use Laminas\Http\Client\Adapter\Curl;
use Laminas\Http\Request;
use Magento\Framework\App\Request\DataPersistor;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Sutunam\CmsDataSync\Helper\Config as ConfigHelper;
use Sutunam\CmsDataSync\Logger\Logger;

class ApiConnection
{
    public const REQUEST_RESPONSE_CODE_OK = 200;

    public const REQUEST_RESPONSE_CODE_UNAUTHORIZED = 401;

    public const API_INTEGRATION_ADMIN_TOKEN = 'integration/admin/token';

    public const RESTRICT_GET_ADMIN_TOKEN_RETRY_TIME = 3;

    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var UrlInterface
     */
    protected $url;

    /**
     * @var DataPersistor
     */
    protected $dataPersistor;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param Config $configHelper
     * @param ManagerInterface $messageManager
     * @param UrlInterface $url
     * @param DataPersistor $dataPersistor
     * @param Logger $logger
     */
    public function __construct(
        ConfigHelper $configHelper,
        ManagerInterface $messageManager,
        UrlInterface $url,
        DataPersistor $dataPersistor,
        Logger $logger
    ) {
        $this->configHelper = $configHelper;
        $this->messageManager = $messageManager;
        $this->url = $url;
        $this->dataPersistor = $dataPersistor;
        $this->logger = $logger;
    }

    /**
     * Request Api
     *
     * @param string $requestType
     * @param string $endpoint
     * @param array $requestParams
     * @param array $requestBody
     * @return array
     * @throws \Exception
     */
    public function request(
        $requestType = Request::METHOD_GET,
        $endpoint = null,
        $requestParams = [],
        $requestBody = []
    ) {
        $response = [
            'success' => false,
            'data' => [],
            'message' => '',
        ];

        if (!$this->configHelper->isEnabled()) {
            $response['message'] = __('SyncCmsData is not enabled');
            $this->logger->info($response['message']);
            return $response;
        }

        if (!$this->settingCheck()) {
            $response['message'] = __('The extension settings are not configured correctly.');
            $this->logger->info($response['message']);
            return $response;
        }

        $accessToken = $this->getAdminToken();
        $targetUrl = $this->configHelper->getSiteUrl();

        $requestEndpoint = $targetUrl . ConfigHelper::API_PREFIX . $endpoint;
        $bearToken = 'Bearer ' . $accessToken;

        $options = [
            'adapter'   => Curl::class,
            'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
            'timeout' => 30
        ];

        $client = new Client(
            $requestEndpoint,
            $options
        );

        $client->setHeaders([
            'Authorization' => $bearToken,
            'Content-Type'  => 'application/json',
        ]);

        $client->setMethod($requestType);

        if ($requestParams) {
            $client->setParameterGet($requestParams);
        }
        if ($requestBody) {
            $client->setRawBody(json_encode($requestBody));
        }

        $client->setOptions($options);
        $this->logger->info("\n===========================================\n");
        $this->logger->info("Request: \n" . json_encode($client->getRequest()));

        $responseRequest = $client->send();

        if (!$responseRequest->isSuccess()
            && $responseRequest->getStatusCode() != self::RESTRICT_GET_ADMIN_TOKEN_RETRY_TIME) {
            $response['message'] = __("HTTP Error: " . $responseRequest->getStatusCode()
                . "\n" . $responseRequest->getReasonPhrase());
            $this->logger->info("Response: " . $response['message']);
            return $response;
        }

        if ($responseRequest->getStatusCode() == self::REQUEST_RESPONSE_CODE_UNAUTHORIZED) {
            $retry_time = $this->dataPersistor->get('retry_time') ?? 0;
            if ($retry_time < self::RESTRICT_GET_ADMIN_TOKEN_RETRY_TIME) {
                $this->getAdminToken(true);
                $this->dataPersistor->set('retry_time', ++$retry_time);
                return $this->request($requestType, $endpoint, $requestParams, $requestBody);
            }
            $response['message'] = __("HTTP Error: " . $responseRequest->getStatusCode()
                . "\n" . $responseRequest->getReasonPhrase());
            $this->logger->info("Response: " . $response['message']);
            return $response;
        }

        $response['data'] = json_decode($responseRequest->getBody(), true);
        $response['success'] = true;
        $this->dataPersistor->clear('retry_time');
        $this->logger->info("Response: " . $responseRequest->getStatusCode() . " \n" . json_encode($response));
        return $response;
    }

    /**
     * Check config for button sync on edit page
     *
     * @return bool
     */
    protected function settingCheck()
    {
        if (!$this->configHelper->getSiteName()) {
            $settingUrl = $this->url->getUrl('adminhtml/system_config/edit', ['section'=>'cms_data_sync']);
            $this->messageManager->addErrorMessage(
                "The extension settings are not configured correctly. "
                . "Please <a href='$settingUrl'>click here</a> to configure them."
            );
            return false;
        }
        return true;
    }

    /**
     * Get Admin token
     *
     * @param bool $refresh
     * @return mixed
     * @throws \Exception
     */
    public function getAdminToken($refresh = false)
    {
        if (!$refresh) {
            if ($currentToken = $this->dataPersistor->get('cmsdatasync/admin_token')) {
                return $currentToken;
            }
        }

        $requestBody = [
            'username' => $this->configHelper->getUsername(),
            'password' => $this->configHelper->getPassword()
        ];

        $options = [
            'adapter'   => Curl::class,
            'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
            'timeout' => 30
        ];

        $requestEndpoint = $this->configHelper->getSiteUrl()
            . ConfigHelper::API_PREFIX
            . self::API_INTEGRATION_ADMIN_TOKEN;

        $client = new Client(
            $requestEndpoint,
            $options
        );

        $client->setHeaders([
            'Content-Type'  => 'application/json',
        ]);

        $client->setMethod(Request::METHOD_POST);
        $client->setRawBody(json_encode($requestBody));
        $client->setOptions($options);
        $this->logger->info("\n===========================================\n");
        $this->logger->info("Request: \n" . json_encode($client->getRequest()));

        $responseRequest = $client->send();
        if (!$responseRequest || !$responseRequest->isSuccess()) {
            $this->logger->info("\nHTTP Error: " . $responseRequest->getStatusCode()
                . "\n" . $responseRequest->getReasonPhrase());
            return '';
        }

        $this->logger->info(
            "\nResponse: " . $responseRequest->getStatusCode()
            . " \n" . json_encode($responseRequest->getBody())
        );
        $token = json_decode($responseRequest->getBody(), true);

        $this->dataPersistor->set('cmsdatasync/admin_token', $token);
        return $token;
    }
}
