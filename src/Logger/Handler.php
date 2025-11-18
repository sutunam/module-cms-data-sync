<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Logger;

use Monolog\Logger;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Store\Model\StoreManagerInterface;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * @var TimezoneInterface
     */
    protected $localeDate;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Constructor
     *
     * @param TimezoneInterface $localeDate
     * @param DriverInterface $filesystem
     * @param StoreManagerInterface $storeManager
     * @param string|null $filePath
     * @param string|null $fileName
     */
    public function __construct(
        TimezoneInterface $localeDate,
        DriverInterface $filesystem,
        StoreManagerInterface $storeManager,
        ?string $filePath = null,
        ?string $fileName = null
    ) {
        $this->localeDate = $localeDate;
        $this->storeManager = $storeManager;
        $this->setFileName($fileName);
        parent::__construct(
            $filesystem,
            $filePath,
            $this->fileName
        );
    }

    /**
     * Reset file name everyday
     *
     * @param string|null $fileName
     * @return void
     */
    public function setFileName($fileName)
    {
        $date = $this->localeDate->date()->format('Y-m-d');
        $this->fileName = $fileName ?: '/var/log/CmsDataSync/ApiConnect-' . $date . '.log';
    }
}
