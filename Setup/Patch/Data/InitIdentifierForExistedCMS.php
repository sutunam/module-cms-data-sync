<?php

namespace Sutunam\CmsDataSync\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

class InitIdentifierForExistedCMS implements DataPatchInterface
{
    /**
     * @var \Sutunam\CmsDataSync\Helper\Data
     */
    protected $dataHelper;

    /**
     * @var \Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory
     */
    protected $instanceCollectionFactory;

    /**
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Construct
     *
     * @param \Sutunam\CmsDataSync\Helper\Data $dataHelper
     * @param \Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory $instanceCollectionFactory
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Sutunam\CmsDataSync\Helper\Data $dataHelper,
        \Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory $instanceCollectionFactory,
        ModuleDataSetupInterface $moduleDataSetup,
        LoggerInterface $logger
    ) {
        $this->dataHelper = $dataHelper;
        $this->instanceCollectionFactory = $instanceCollectionFactory;
        $this->moduleDataSetup = $moduleDataSetup;
        $this->logger = $logger;
    }

    /**
     * Apply
     *
     * @return void
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $collection = $this->instanceCollectionFactory->create()->addFieldToFilter('identifier', ['null' => true]);
        foreach ($collection->getItems() as $item) {
            try {
                $item = $item->load($item->getId());
                $item->setIdentifier($this->dataHelper->generateIdentifier($item->getTitle()));
                $item->save();
            } catch (\Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * Get Dependencies
     *
     * @return array|string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Get Aliases
     *
     * @return array|string[]
     */
    public function getAliases()
    {
        return [];
    }
}
