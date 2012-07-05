<?php
namespace IMAG\davSrv\Nodes;

class NodeType extends NodeBase
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
        $res = db_select('node_type', 'nt')
            ->fields('nt')
            ->condition('nt.type', $this->getRoute()
                        ->getArgument('node_type'), 'LIKE')
            ->execute()
            ->fetchObject()
            ;

        return $res;
    }

    public function getCollectionMembers()
    {
        $query = db_select('node', 'n');
        $query->join('node_revision', 'nr', 'n.vid = nr.vid');
        $res = $query
            ->fields('n')
            ->fields('nr')
            ->condition('n.type', $this->getRoute()
                        ->getArgument('node_type'), 'LIKE')
            ->execute()
            ->fetchAll()
            ;
        
        foreach($res as $obj) {
            $path = $this->getRoute()->getPath().'/'.$obj->title;
            
            /**
             * TODO Add childs
             *
             * $childRoute = $this->router->handleRoute($path);
             * $childs = $this->{$childRoute->collection}($childRoute);
             */
 
            $t[] = new \ezcWebdavCollection($path);
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

    public function createResource()
    {
        /**
         * Unallowed to create resource for this type
         */
    }

    public function setResourceContents($content)
    {
        /**
         * Unallowed to create resource for this type
         */
    }

    public function getResourceContents()
    {
        /**
         * No Ressource ; only collection
         */
    }

    public function createCollection()
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