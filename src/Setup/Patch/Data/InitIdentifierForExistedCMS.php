<?php
/**
 * @author    Sutunam
 * @copyright Copyright (c) 2025 Sutunam https://www.sutunam.com/
 */

namespace Sutunam\CmsDataSync\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use Sutunam\CmsDataSync\Helper\Data;

class InitIdentifierForExistedCMS implements DataPatchInterface
{
    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * @var ResourceConnection
     */
     protected $resourceConnection;

    /**
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Data $dataHelper
     * @param ResourceConnection $resourceConnection
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param LoggerInterface $logger
     */
    public function __construct(
        Data $dataHelper,
        ResourceConnection $resourceConnection,
        ModuleDataSetupInterface $moduleDataSetup,
        LoggerInterface $logger
    ) {
        $this->dataHelper = $dataHelper;
        $this->resourceConnection = $resourceConnection;
        $this->moduleDataSetup = $moduleDataSetup;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('widget_instance');

        try {
            $select = $connection->select()
                ->from($tableName, ['instance_id', 'title'])
                ->where('identifier IS NULL');

            $widgets = $connection->fetchAll($select);

            if (empty($widgets)) {
                $this->logger->critical('No widget instances need updating.');
                return $this;
            }
            $updateData = [];
            foreach ($widgets as $widget) {
                $id = (int)$widget['instance_id'];
                $title = $widget['title'];
                $updateData[] = [
                    'instance_id' => $id,
                    'identifier'  => $this->dataHelper->generateIdentifier($title)
                ];
            }
            $connection->insertOnDuplicate(
                $tableName,
                $updateData,
                ['identifier']
            );
        } catch (\Exception $e) {
            $this->logger->critical('Error updating widget identifiers: ' . $e->getMessage());
        }
        $this->moduleDataSetup->getConnection()->endSetup();
        return $this;
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
