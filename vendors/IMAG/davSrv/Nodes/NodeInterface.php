<?php
namespace IMAG\davSrv\Nodes;

use IMAG\davSrv\Entity\Route;

interface NodeInterface
{
    public static function getInstance();
    public function getRoute();
    public function setRoute(Route $route);
    public function getType();
    public function exists();
    public function getCollectionMembers();
    public function getCreatedAt();
    public function getUpdatedAt();  
    public function createCollection();
    public function createResource();
    public function setResourceContents($content);
    public function getResourceContents();
    public function delete();
    public function copy(NodeInterface $to);
}