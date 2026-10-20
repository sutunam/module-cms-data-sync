<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

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
