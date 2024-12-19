<?php

namespace Sutunam\CmsDataSync\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const API_PREFIX = 'rest/V1/';
    public const XML_BASE_PATH = "cms_data_sync/connection/";

    public const XML_CMS_DATA_SYNC_ENABLED = "enabled";

    public const XML_CMS_DATA_SYNC_SITE_NAME = "site_name";

    public const XML_CMS_DATA_SYNC_SITE_URL = "site_url";

    public const XML_CMS_DATA_SYNC_USERNAME = "username";

    public const XML_CMS_DATA_SYNC_PASSWORD = "password";

    public const XML_CMS_DATA_SYNC_SYNC_MEDIA = "sync_media";

    public const XML_CMS_DATA_SYNC_REMOTE_MEDIA_ROOTDIR = "remote_media_rootdir";

    public const XML_CMS_DATA_SYNC_REMOTE_MEDIA_SUBDIR = "remote_media_subdir";

    public const XML_CMS_DATA_SYNC_IS_OVERWRITE = "is_overwrite";

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get config with path and scope
     *
     * @param string $path
     * @param string $scope
     * @return mixed
     */
    public function getConfig($path, $scope = ScopeInterface::SCOPE_WEBSITE)
    {
        return $this->scopeConfig->getValue(self::XML_BASE_PATH . $path, $scope);
    }

    /**
     * Get is_enabled config value
     *
     * @param string $scope
     * @return mixed
     */
    public function isEnabled($scope = ScopeInterface::SCOPE_WEBSITE)
    {
        return $this->getConfig(self::XML_CMS_DATA_SYNC_ENABLED, $scope);
    }

    /**
     * Get remote site name
     *
     * @param string $scope
     * @return mixed
     */
    public function getSiteName($scope = ScopeInterface::SCOPE_WEBSITE)
    {
        return $this->getConfig(self::XML_CMS_DATA_SYNC_SITE_NAME, $scope);
    }

    /**
     * Get remote site url
     *
     * @param string $scope
     * @return mixed
     */
    public function getSiteUrl($scope = ScopeInterface::SCOPE_WEBSITE)
    {
        return $this->getConfig(self::XML_CMS_DATA_SYNC_SITE_URL, $scope);
    }

    /**
     * Get account username
     *
     * @param string $scope
     * @return mixed
     */
    public function getUsername($scope = ScopeInterface::SCOPE_WEBSITE)
    {
        return $this->getConfig(self::XML_CMS_DATA_SYNC_USERNAME, $scope);
    }

    /**
     * Get account password
     *
     * @param string $scope
     * @return mixed
     */
    public function getPassword($scope = ScopeInterface::SCOPE_WEBSITE)
    {
        return $this->getConfig(self::XML_CMS_DATA_SYNC_PASSWORD, $scope);
    }

    /**
     * Get sync_media config value
     *
     * @param string $scope
     * @return mixed
     */
    public function isSyncMedia($scope = ScopeInterface::SCOPE_WEBSITE)
    {
        return $this->getConfig(self::XML_CMS_DATA_SYNC_SYNC_MEDIA, $scope);
    }

    /**
     * Get remote media root dir
     *
     * @param string $scope
     * @return mixed
     */
    public function getRemoteMediaRootdir($scope = ScopeInterface::SCOPE_WEBSITE)
    {
        return $this->getConfig(self::XML_CMS_DATA_SYNC_REMOTE_MEDIA_ROOTDIR, $scope);
    }

    /**
     * Get config remote media subdir value
     *
     * @param string $scope
     * @return mixed
     */
    public function getRemoteMediaSubdir($scope = ScopeInterface::SCOPE_WEBSITE)
    {
        return $this->getConfig(self::XML_CMS_DATA_SYNC_REMOTE_MEDIA_SUBDIR, $scope);
    }

    /**
     * Get config is_overwrite value
     *
     * @param string $scope
     * @return mixed
     */
    public function isOverwrite($scope = ScopeInterface::SCOPE_WEBSITE)
    {
        return $this->getConfig(self::XML_CMS_DATA_SYNC_IS_OVERWRITE, $scope);
    }
}
