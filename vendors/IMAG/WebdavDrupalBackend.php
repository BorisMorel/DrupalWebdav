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
                'pattern'             => "/%node_type%/%node%/%content%",
                'exists'              => "nodeContent",
                'collection'          => 'nodeContentCollection',
                'setResourceContents' => 'nodeContentCreate',
                'getResourceContents' => 'nodeContentGet',
                'getLastModified'     => 'nodeLastModification',
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
        
    public static function getInstance()
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

    public function get(ezcWebdavGetRequest $request)
    {
        return parent::get($request);
    }

    protected function createCollection($path)
    {
    }

    protected function createResource($path, $content = null)
    {
        /**
         * See setResourceContents()
         */
    }

    protected function setResourceContents($path, $content)
    {
        $route = $this->handleRoute($path);

        if(!isset($route->setResourceContents)) {
            $route->setResourceContents = 'FAKE';
        }

        if (!method_exists($this, $route->setResourceContents)) {
            throw new ezcWebdavInconsistencyException('Unallowed to populate resource here !');
        }

        $this->{$route->setResourceContents}($route, $content);
    }

    private function nodeContentCreate(stdClass $route, $content)
    {
        global $user;

        $nodeNode = $this->nodeNode($route);
        if (!$node = node_load($nodeNode->nid)) {
            dd($route);
            throw new ezcWebdavInconsistencyException('Unable to load node');
        }

        if (!node_access('update', $node) || !node_access('create', $node)) {
            $deny = new ezcWebdavErrorResponse(ezcWebdavResponse::STATUS_403, $route->path, 'Bad Credentials');
            dd($deny);
            return $deny;
        }
        
        $file = array(
            'filename' => $route->arguments['content'],
            'uri'      => 'public://'.$route->arguments['content'],
            'filemime' => file_get_mimetype($route->arguments['content']),
            'status'   => 1,
            'display'  => true,
            'uid'      => $user->uid,
        );

        $file = (object) $file;
        
        
        if ($fileExists = $this->fileExists($file->filename)) {
            $file->uri = 'public://'.$this->fileNameNewVersion($fileExists);
        }

        file_put_contents($file->uri, $content);


        $savedFile = file_save($file);

        $fileField = $this->getDbFileField($route);
        
        foreach ($node->{$fileField->field_name}['und'] as $key => $file) {
            $pos = $key + 1;

            if ($file['filename'] == $savedFile->filename) {
                $pos = $key;
                break;
            } 
        }
        
        $node->{$fileField->field_name}['und'][isset($pos) ? $pos : 0] = (array) $savedFile;
        $node->revision = true;

        node_save($node);
    }

    protected function getResourceContents($path)
    {
        $route = $this->handleRoute($path);

        if (!isset($route->getResourceContents)) {
            $route->getResourceContents = 'FAKE';
        }

        if (!method_exists($this, $route->getResourceContents)) {
            throw new ezcWebdavInconsistencyException('Unable to get content with this route');
        }
        
        return $this->{$route->getResourceContents}($route);
    }

    private function nodeContentGet(stdClass $route)
    {
        return file_get_contents('public://'.$route->arguments['content']);
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
            $property->date = $this->getLastModified($path); //new ezcWebdavDateTime('@' .  time());
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

    private function getLastModified($path)
    {
        $route = $this->handleRoute($path);

        if (!isset($route->getLastModified)) {
            return new ezcWebdavDateTime('@'.time());
        }

        return $this->{$route->getLastModified}($route);
    }

    private function nodeLastModification(stdClass $route)
    {
        $file = $this->getFile($route);

        return new ezcWebdavDateTime('@'. $file->timestamp);
    }

    protected function performCopy($fromPath, $toPath, $depth = ezcWebdavRequest::DEPTH_INFINITY)
    {
        throw new ezcWebdavInconsistencyException("Copy isn't implemented yet");
    }

    protected function performDelete($path)
    {
        $route = $this->handleRoute($path);
        
        if ($route->type !== static::ROUTE_NODE_CONTENT) {
            throw new ezcWebdavInconsistencyException('delete is avaible only for content');
        }

        $nodeNode = $this->nodeNode($route);
        if (!$node = node_load($nodeNode->nid)) {
            dd($route);
            throw new ezcWebdavInconsistencyException('Unable to load node');
        }

        if (!node_access('delete', $node)) {
            $deny = new ezcWebdavErrorResponse(ezcWebdavResponse::STATUS_403, $route->path, 'Bad Credentials');

            return $deny;
        }

        $fileField = $this->getDbFileField($route);

        $file = $this->getFile($route);

        if(file_delete($file, true)) {
            foreach ($node->{$fileField->field_name}['und'] as $key => $nodeFile) {
                if ($nodeFile['filename'] != $file->filename) {
                    continue;
                }
                
                unset($node->{$fileField->field_name}['und'][$key]);
            }

            node_save($node);
        }
        
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
        $files = $this->getFiles($route);

        foreach ($files as $file) {
            if ($file->filename == $route->arguments['content']) {
                return true;
            }
        }

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
        $path = trim($path, '/');
        $path = urldecode($path);
        $path = preg_replace('/\s+/', '_', $path);

        return $path;
    }

    private function getContentLength($path)
    {
        $length = ezcWebdavGetContentLengthProperty::COLLECTION;
        if(!$this->isCollection($path)) {
            $route = $this->handleRoute($path);
            $length = (string) filesize('public://'.$route->arguments['content']);
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
            $t[] = new ezcWebdavResource($route->path.'/'.$file->filename);
        }

        return $t;
    }

    private function nodeContentCollection(stdClass $route)
    {
        
    }

    private function fileNameNewVersion(stdClass $file)
    {
        $pathInfo = pathinfo($file->uri);
        $counter = 1;

        if (preg_match('/_([\d]+)$/', $pathInfo['filename'], $matches)) {
            $counter = $matches[1] + 1;
        }

        $pathInfo = pathinfo($file->filename);
        
        return(sprintf('%s_%s.%s', $pathInfo['filename'], $counter, $pathInfo['extension']));
    }

    private function fileExists($filename)
    {
        $t = new stdClass();
        $t->arguments['content'] = $filename;

        $query = $this->getFile($t);

        return $query ? $query : false;
    }

    private function getFile(stdClass $route)
    {
        $query = db_select('file_managed', 'fm');
        $file = $query
            ->fields('fm')
            ->condition('fm.filename', $route->arguments['content'], 'LIKE')
            ->orderBy('fid', 'DESC')
            ->execute()
            ->fetchObject()
            ;

        
        return file_load($file->fid);
    }

    private function getFiles(stdClass $route)
    {
        $fileField = $this->getDbFileField($route);
        
        $query = db_select(sprintf('field_data_%s', $fileField->field_name), 'ff');
        $query->join('node', 'n', 'n.nid = ff.entity_id');
        $query->join('file_managed', 'fm', 'ff.field_upload_fid = fm.fid');
        $files = $query
            ->fields('ff')
            ->fields('fm')
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