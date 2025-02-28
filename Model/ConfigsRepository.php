<?php

namespace Sutunam\CmsDataSync\Model;

use Sutunam\CmsDataSync\Api\ConfigsRepositoryInterface;
use Sutunam\CmsDataSync\Helper\Config;

class ConfigsRepository implements ConfigsRepositoryInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Config $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * Get All Sutunam CmsDataSync configs
     *
     * @return string
     */
    public function getSyncCmsConfigs(): string
    {
        return json_encode([
            Config::XML_CMS_DATA_SYNC_ENABLED => $this->config->isEnabled() ?: 0,
            Config::XML_CMS_DATA_SYNC_IS_OVERWRITE => $this->config->isOverwrite() ?: 0,
            Config::XML_CMS_DATA_SYNC_SYNC_MEDIA => $this->config->isSyncMedia() ?: 0
        ]);
    }
}
