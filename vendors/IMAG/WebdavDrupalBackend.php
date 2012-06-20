<?php

class WebdavDrupalBackend extends ezcWebdavSimpleBackend
{
    const
        ROUTE_NODE_ROOT = 0,
        ROUTE_NODE_TYPE = 1,
        ROUTE_NODE_NODE = 2,
        ROUTE_NODE_CONTENT = 3
        ;

    private static
        $singleton,
        $rootCollection = array(),
        $routing = array (
            self::ROUTE_NODE_ROOT    => array (
                'pattern' => "/",
                'exists'  => 'nodeRoot',
                'collection' => 'nodeRootCollection',
            ),
            self::ROUTE_NODE_TYPE    => array (
                'pattern' => "/%node_type%",
                'exists'  => 'nodeType',
                'collection' => 'nodeTypeCollection',
            ),
            self::ROUTE_NODE_NODE    => array (
                'pattern' => "/%node_type%/%node%",
                'exists' => "nodeNode",
                'collection' => 'nodeNodeCollection',
            ),
            self::ROUTE_NODE_CONTENT => array (
                'pattern' => "/%node_type%/%node%/%content%",
                'exists' => "nodeContent",
                'createResource' => 'nodeContentCreate'
            )
        ),
        $properties = array (
            'getcontentlength',
            'getlastmodified',
            'resourcetype'
        )
        ;

    private
        $drupalUrlRoot,
        $documentRoot
        ;
        
    public function getInstance()
    {
        if (!isset(static::$singleton)) {
            static::$singleton = new static();
        }

        return static::$singleton;
    }
    
    public function propFind(ezcWebdavPropFindRequest $request)
    {
        return parent::propFind($request);
    }

    protected function createCollection($path)
    {
    }

    protected function createResource($path, $content = null)
    {
        $route = $this->handleRoute($path);

        if(!isset($route->createResource)) {
            $route->createResource = 'FAKE';
        }

        if (!method_exists($this, $route->createResource)) {
            throw new ezcWebdavInconsistencyException('Unallowed to create resource here !');
        }

        $this->{$route->createResource}($route);

        
    }

    private function nodeContentCreate(stdClass $route)
    {
        $file = array(
            'filename' => $route->arguments['content'],
            'uri'      => 'public://'.$route->arguments['content'],
            'filemime' => 'image/jpeg',
            'status'   => 1
        );

        $file = (object) $file;
        file_put_contents($file->uri, 'toto');

        $savedFile = file_save($file);        
    }

    protected function setResourceContents($path, $content)
    {

    }

    protected function getResourceContents($path)
    {

    }

    public function setProperty($path, ezcWebdavProperty $property)
    {
    }

    public function removeProperty($path, ezcWebdavProperty $property)
    {
    }
    public function resetProperties($path, ezcWebdavPropertyStorage $properties)
    {
    }

    public function getProperty($path, $propertyName, $namespace = 'DAV:')
    {
        switch ($propertyName) {
        case 'getcontentlength':
            $property = new ezcWebdavGetContentLengthProperty();
            $property->length = $this->getContentLength($path);
            return $property;

        case 'getlastmodified':
            $property = new ezcWebdavGetLastModifiedProperty();
            $property->date = new ezcWebdavDateTime('@' .  time());
            return $property;
        
        case 'resourcetype':
            $property = new ezcWebdavResourceTypeProperty();
            $property->type = $this->isCollection($path) ?
                ezcWebdavResourceTypeProperty::TYPE_COLLECTION : 
                ezcWebdavResourceTypeProperty::TYPE_RESOURCE;
            return $property;
        }
    }

    public function getAllProperties($path)
    {
        $storage = new ezcWebdavBasicPropertyStorage();
        foreach(static::$properties as $property) {
            $storage->attach(
                $this->getProperty($path, $property)
            );
        }

        return $storage;
    }

    protected function performCopy($fromPath, $toPath, $depth = ezcWebdavRequest::DEPTH_INFINITY)
    {
    }
    protected function performDelete($path)
    {
    }
    protected function isCollection($path)
    {
        $route = $this->handleRoute($path);

        if ($route->type === static::ROUTE_NODE_CONTENT) {
            return false;
        }

        return true;
        
    }
    protected function nodeExists($path)
    {
        $route = $this->handleRoute($path);

        return (bool) $this->{$route->exists}($route);
    }
    protected function getCollectionMembers($path)
    {
        $route = $this->handleRoute($path);
        
        return $this->{$route->collection}($route);
    }

    /**
     * Doesn't forget that the root route ( / ) containt module name. E.G /davsrv is the root route ( / ).
     */
    private function handleRoute($path)
    {
        $routes = static::$routing;
        $cleanPath = $this->fixPath($path);
        foreach($routes as $key => $route) {
            $pattern = $this->eregRoute($route['pattern']);
            if (!preg_match("#^$pattern$#", $cleanPath)) {
                continue;
            }
            
            $obj = new stdClass();
            $obj->type = $key;
            $obj->path = $path;
            $obj->url = $cleanPath;
            $obj->arguments = $this->addArguments($cleanPath, $route);
            foreach($route as $attr => $val) {
                $obj->{$attr} = $val;
            }
            return $obj;
        }

        return false;     
    }

    private function addArguments($path, $route)
    {
        preg_match_all('/%([^%]+)%/', $route['pattern'], $params);
        preg_match_all('#/([^/]+)#', $path, $values);

        if (count($params[1]) != count($values[1])) {
            throw new Exception('The count of this array must be equal');
        }

        foreach($params[1] as $key => $param) {
            $res[$param] = $values[1][$key];
        }

        return $res;
    }

    private function eregRoute($route)
    {
        return preg_replace('/%[^%]+%/', '[^/]+', $route);
    }

    /**
     * TODO : Enhancement this function ... Because the count of / is very nosePif ;)
     */

    private function fixPath($path)
    {
        $cleanUrlRoot = $this->getDrupalUrlRootCleared();
        $cleanDocumentRoot = $this->getDocumentRootCleared();

        $url = preg_replace("#$cleanUrlRoot#", '', $path);
        $url = static::sanitize($url);

        $newPath = 
            '/'
            .$cleanDocumentRoot
            .((!empty($cleanDocumentRoot) && !empty($url)) ? '/' : '')
            .$url
            ;

        return $newPath;
    }

    private function nodeRoot(stdClass $route)
    {
        dd($route, 'nodeRoot');
        return true;
    }

    private function nodeType(stdClass $route)
    {
        $res = db_select('node_type', 'nt')
            ->fields('nt')
            ->condition('nt.type', $route->arguments['node_type'], 'LIKE')
            ->execute()
            ->fetchObject()
            ;

        return $res;
    }

    private function nodeNode(stdClass $route)
    {
        $res = db_select('node', 'n')
            ->fields('n')
            ->condition('n.type', $route->arguments['node_type'], 'LIKE')
            ->condition('n.title', $route->arguments['node'], 'LIKE')
            ->execute()
            ->fetchObject()
            ;
        
        return $res;
    }

    private function nodeContent(stdClass $route)
    {
        return false;
    }

    public function setDrupalUrlRoot($path)
    {
        $this->drupalUrlRoot = $path;

        return $this;
    }

    public function setDocumentRoot($path)
    {
        $this->documentRoot = $path;
        
        return $this;
    }
    
    public function setRootCollection(array $collection)
    {
        static::$rootCollection = $collection;

        return $this;
    }

    public function getDocumentRootCleared()
    {
        return static::sanitize($this->documentRoot);
    }

    public function getDrupalUrlRootCleared()
    {
        return static::sanitize($this->drupalUrlRoot);
    }

    private static function sanitize($path)
    {
        return trim($path, '/');
    }

    private function getContentLength($path)
    {
        $length = ezcWebdavGetContentLengthProperty::COLLECTION;
        if(!$this->isCollection($path)) {
            $length = (string) '3541';
        }
        
        return $length;
    }

    private function nodeRootCollection(stdClass $route)
    {
        if (!isset(static::$rootCollection)) {
            return array();
        }
        
        foreach(static::$rootCollection as $item) {
            $t[] = new ezcWebdavCollection($route->path.'/'.$item);
        }

        return $t;
    }

    private function nodeTypeCollection(stdClass $route)
    {
        $query = db_select('node', 'n');
        $query->join('node_revision', 'nr', 'n.vid = nr.vid');
        $res = $query
            ->fields('n')
            ->fields('nr')
            ->condition('n.type', $route->arguments['node_type'], 'LIKE')
            ->execute()
            ->fetchAll()
            ;
        
        // TODO : Now add the childs ;)

        foreach($res as $obj) {
            $t[] = new ezcWebdavCollection($route->path.'/'.$obj->title);
        }

        return $t;
    }

    private function nodeNodeCollection(stdClass $route)
    {
        $files = $this->getFiles($route);
      
        foreach($files as $file) {
            $t[] = new ezcWebdavResource($route->path.'/'.$file->field_upload_description);
        }

        return $t;
    }

    private function getFiles(stdClass $route)
    {
        $fileField = $this->getDbFileField($route);
        
        $query = db_select(sprintf('field_data_%s', $fileField->field_name), 'ff');
        $query->join('node', 'n', 'n.nid = ff.entity_id');
        $files = $query
            ->fields('ff')
            ->condition('n.title', $route->arguments['node'], '=')
            ->execute()
            ->fetchAll()
            ;

        return $files;
    }

    private function getDbFileField(stdClass $route)
    {
        $query = db_select('field_config_instance', 'fci');
        $query->join('field_config', 'fc', 'fci.field_id = fc.id');
        $res = $query
            ->fields('fci')
            ->condition('fci.bundle', $route->arguments['node_type'], 'LIKE')
            ->condition('fc.type', 'file', 'LIKE')
            ->execute()
            ->fetchObject()
            ;

        return $res;
    }
}