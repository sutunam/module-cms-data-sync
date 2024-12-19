<?php
namespace Sutunam\CmsDataSync\Logger;

class Logger extends \Monolog\Logger
{
    /**
     * Save log
     *
     * @param String $message
     * @param String $type
     * @return void
     */
    public function saveLog($message, $type = null)
    {
        $this->info($type . $message);
    }
}
