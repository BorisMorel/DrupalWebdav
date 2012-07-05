<?php
namespace IMAG\davSrv\Nodes;

use IMAG\davSrv\Router\Router;

class NodeRoot extends NodeBase
{
    private static
        $singleton
        ;

    public static function getInstance()
    {
        if (!isset(static::$singleton)) {
            static::$singleton = new static();
        }

        return static::$singleton;
    }

    public function exists()
    {
        return true;
    }

    public function getCollectionMembers()
    {
        if (false === Router::getRootCollection()) {
            return array();
        }

        foreach (Router::getRootCollection() as $folder) {
            $t[] = new \ezcWebdavCollection($this->getRoute()->getPath().'/'.$folder);
        }

        return $t;
    }

    public function getCreatedAt()
    {
        return new \ezcWebdavDateTime('@'.time());
    }

    public function getUpdatedAt()
    {
        return new \ezcWebdavDateTime('@'.time());
    }

    public function createCollection()
    {
        return false;
    }

    public function createResource()
    {
        return false;
    }

    public function setResourceContents($content)
    {
        return false;
    }

    public function getResourceContents()
    {
        return false;
    }

    public function delete()
    {
        return false;
    }

    public function copy(NodeInterface $to)
    {
        return false;
    }

}