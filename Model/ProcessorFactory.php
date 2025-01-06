<?php
namespace Sutunam\CmsDataSync\Model;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\ObjectManagerInterface;
use Sutunam\CmsDataSync\Api\ProcessorInterface;

class ProcessorFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var array
     */
    private $processors;

    /**
     * @var array
     */
    private $collections;

    /**
     * Constructor
     *
     * @param ObjectManagerInterface $objectManager
     * @param array $processors
     * @param array $collections
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        array $processors,
        array $collections
    ) {
        $this->objectManager = $objectManager;
        $this->processors = $processors;
        $this->collections = $collections;
    }

    /**
     * Create process
     *
     * @param string $processor
     * @param array $arguments
     * @return ProcessorInterface
     */
    public function create(string $processor, array $arguments = []): ProcessorInterface
    {
        return $this->objectManager->create($this->processors[$processor], $arguments);
    }

    /**
     * Get collection
     *
     * @param string $collections
     * @return AbstractCollection
     */
    public function getCollection($collections): AbstractCollection
    {
        return $this->collections[$collections]->create();
    }
}
