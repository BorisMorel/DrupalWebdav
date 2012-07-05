<?php
namespace IMAG\davSrv\Nodes;

use IMAG\davSrv\Entity\Route;

abstract class NodeBase implements NodeInterface
{
    private
        $route
        ;

    abstract public static function getInstance();

    public function setRoute(Route $route)
    {
        $this->route = $route;
        
        return $this;
    }

    public function getRoute()
    {
        return $this->route;
    }
    
    public function getType()
    {
        return $this->route
            ->getType();
    }

    protected function getFiles()
    {
        $fileField = $this->getDbFileField();
        
        $query = db_select(sprintf('field_data_%s', $fileField->field_name), 'ff');
        $query->join('node', 'n', 'n.nid = ff.entity_id');
        $query->join('file_managed', 'fm', 'ff.field_upload_fid = fm.fid');
        $files = $query
            ->fields('ff')
            ->fields('fm')
            ->condition('n.title', $this->getRoute()
                        ->getArgument('node'), '=')
            ->execute()
            ->fetchAll()
            ;

        return $files;
    }

    protected function getDbFileField()
    {
        $query = db_select('field_config_instance', 'fci');
        $query->join('field_config', 'fc', 'fci.field_id = fc.id');
        $res = $query
            ->fields('fci')
            ->condition('fci.bundle', $this->getRoute()
                        ->getArgument('node_type'), 'LIKE')
            ->condition('fc.type', 'file', 'LIKE')
            ->execute()
            ->fetchObject()
            ;

        return $res;
    }
}