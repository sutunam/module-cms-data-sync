<?php
namespace Sutunam\CmsDataSync\Api;

interface ProcessorInterface
{
    /**
     * Pull entity.
     *
     * @param int $id
     * @return array
     */
    public function pull($id);

    /**
     * Pull all entity.
     *
     * @return array
     */
    public function pullAll();

    /**
     * Push entity.
     *
     * @param int $id
     * @return array
     */
    public function push($id);

    /**
     * Push all entity.
     *
     * @return array
     */
    public function pushAll();
}
