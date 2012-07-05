<?php
namespace IMAG\davSrv\Backend;

use IMAG\davSrv\Router\Router;

class Backend extends \ezcWebdavSimpleBackend
{
    private static
        $singleton,
        $properties = array (
            'getcontentlength',
            'getlastmodified',
            'resourcetype',
            'creationdate',
            'displayname',
            //'getcontenttype',
            'getetag',
            'supportedlock',
            'lockdiscovery',
        )
        ;

    private
        $router,
        $checkExten = false
        ;
  
    public static function getInstance()
    {
        if (!isset(static::$singleton)) {
            static::$singleton = new static();
        }

        return static::$singleton;
    }

    public function setExtensionCheck()
    {
        $this->checkExten = true;

        return $this;
    }

    public function setRouter(Router $router)
    {
        $this->router = $router;
        
        return $this;
    }

    protected function createCollection($path)
    {
        $node = $this->getNodeObject($path);

        $node->createCollection();
    }

    private function getNodeObject($path)
    {
        $route = $this->router
            ->handleRoute($path);

        $class = $route->getClass();
        $obj = $class::getInstance()
            ->setRoute($route);
            ;

        return $obj;
    }

    protected function createResource($path, $content = null)
    {
        /**
         * See setResourceContents()
         */
        
        $node = $this->getNodeObject($path);
        
        $node->createResource();
    }

    protected function setResourceContents($path, $content)
    {
        $node = $this->getNodeObject($path);

        $node->setResourceContents($content);
    }

    protected function getResourceContents($path)
    {
        $node = $this->getNodeObject($path);
        
        return $node->getResourceContents();
    }

    public function setProperty($path, \ezcWebdavProperty $property)
    {
    }

    public function removeProperty($path, \ezcWebdavProperty $property)
    {
    }
    public function resetProperties($path, \ezcWebdavPropertyStorage $properties)
    {
    }

    public function getProperty($path, $propertyName, $namespace = 'DAV:')
    {
        switch ($propertyName) {
        case 'getcontentlength':
            $property = new \ezcWebdavGetContentLengthProperty();
            $property->length = $this->getContentLength($path);
            return $property;

        case 'getlastmodified':
            $property = new \ezcWebdavGetLastModifiedProperty();
            $property->date = $this->getUpdatedAt($path);
            return $property;
        
        case 'resourcetype':
            $property = new \ezcWebdavResourceTypeProperty();
            $property->type = $this->isCollection($path) ?
                \ezcWebdavResourceTypeProperty::TYPE_COLLECTION : 
                \ezcWebdavResourceTypeProperty::TYPE_RESOURCE;
            return $property;

        case 'creationdate':
            $property = new \ezcWebdavCreationDateProperty();
            $property->date = $this->getCreatedAt($path);
            return $property;

        case 'displayname':
            $property = new \ezcWebdavDisplayNameProperty();
            $property->displayName = urldecode( basename( $path ) );
            return $property;

        case 'getetag':
            $property = new \ezcWebdavGetEtagProperty();
            $property->etag = $this->getETag( $path );
            return $property;

        case 'supportedlock':
            $property = new \ezcWebdavSupportedLockProperty();
            return $property;

        case 'lockdiscovery':
            $property = new \ezcWebdavLockDiscoveryProperty();
            return $property;
        }
    }

    public function getAllProperties($path)
    {
        $storage = new \ezcWebdavBasicPropertyStorage();
        foreach(static::$properties as $property) {
            $storage->attach(
                $this->getProperty($path, $property)
            );
        }

        return $storage;
    }

    private function getUpdatedAt($path)
    {
        $node = $this->getNodeObject($path);
        
        return $node->getUpdatedAt();
    }

    private function getCreatedAt($path)
    {
        $node = $this->getNodeObject($path);

        return $node->getCreatedAt();
    }

    protected function performCopy($fromPath, $toPath, $depth = \ezcWebdavRequest::DEPTH_INFINITY)
    {

        $node = $this->getNodeObject($fromPath);
        $fromNode = clone $node;
        $toNode = $this->getNodeObject($toPath);

        $fromNode->copy($toNode);
    }

    protected function performDelete($path)
    {
        $node = $this->getNodeObject($path);

        $node->delete();
    }

    protected function isCollection($path)
    {
        $node = $this->getNodeObject($path);

        if ($node->getType() === Router::ROUTE_NODE_CONTENT) {
            return false;
        }

        return true;
        
    }

    protected function nodeExists($path)
    {
        $node = $this->getNodeObject($path);

        return (bool) $node->exists();
    }

    protected function getCollectionMembers($path)
    {
        $node = $this->getNodeObject($path);
        
        return $node->getCollectionMembers();
    }

    private function getContentLength($path)
    {
        $length = \ezcWebdavGetContentLengthProperty::COLLECTION;

        if(!$this->isCollection($path)) {
            $node = $this->getNodeObject($path);

            $length = (string) $node->getContentLength();
        }

        return !empty($length) ? $length : '0';
    }

    private function nodeRootCollection(\stdClass $route)
    {
        if (false === $this->router->getRootCollection()) {
            return array();
        }
        
        foreach($this->router->getRootCollection() as $item) {
            $t[] = new \ezcWebdavCollection($route->path.'/'.$item);
        }

        return $t;
    }

}