<?php

namespace Sutunam\CmsDataSync\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use \Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class InitIdentifierForExistedCMS implements DataPatchInterface
{
    /**
     * @var \Sutunam\CmsDataSync\Helper\Data
     */
    protected $dataHelper;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
     protected  $resourceConnection;

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
     * Set identifier to rthe widget empty identifier
     *
     * @return void
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
                return;
            }
            $caseSql = '';
            $ids = [];
            foreach ($widgets as $widget) {
                $id = (int)$widget['instance_id'];
                $title = $widget['title'];
                $escapedIdentifier = $connection->quote($this->dataHelper->generateIdentifier($title));

                $caseSql .= "WHEN {$id} THEN {$escapedIdentifier} ";
                $ids[] = $id;
            }
            $idsList = implode(',', $ids);
            $sql = "UPDATE {$tableName}
                SET identifier = CASE instance_id
                    {$caseSql}
                END
                WHERE instance_id IN ({$idsList})";

            $connection->query($sql);
        } catch (\Exception $e) {
            $this->logger->critical('Error updating widget identifiers: ' . $e->getMessage());
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
