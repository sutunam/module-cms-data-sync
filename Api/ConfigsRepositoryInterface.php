<?php

namespace Sutunam\CmsDataSync\Api;

interface ConfigsRepositoryInterface
{
    /**
     * Get All Sutunam CmsDataSync configs
     *
     * @return string
     */
    public function getSyncCmsConfigs();
}
