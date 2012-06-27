<?php

class WebdavDrupalBackend extends ezcWebdavSimpleBackend
{
    private static
        $singleton,
        $properties = array (
            'getcontentlength',
            'getlastmodified',
            'resourcetype'
        )
        ;

    private
        $router
        ;
  
    public static function getInstance(WebdavDrupalRouter $router)
    {
        if (!isset(static::$singleton)) {
            static::$singleton = new static($router);
        }

        return static::$singleton;
    }

    public function __construct(WebdavDrupalRouter $router)
    {
        $this->router = $router;
    }

    protected function createCollection($path)
    {
        $route = $this->router
            ->handleRoute($path);
        
        if (!method_exists($this, $route->create)) {
            throw new ezcWebdavInconsistencyException('Unable to create collection here');
        }
        
        $this->{$route->create}($route);
    }

    public function nodeNodeCreate(stdClass $route)
    {
        $node = new stdClass();
        $node->type = $route->arguments['node_type'];
        node_object_prepare($node);

        $node->title = $route->arguments['node'];
        $node->revision = true;

        node_save($node);
    }

    protected function createResource($path, $content = null)
    {
        /**
         * See setResourceContents()
         */
    }

    protected function setResourceContents($path, $content)
    {
        $route = $this->router
            ->handleRoute($path);

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
            throw new ezcWebdavInconsistencyException('Bad Credentials');
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
        $route = $this->router
            ->handleRoute($path);

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
        $route = $this->router
            ->handleRoute($path);

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
        $fromRoute = $this->router
            ->handleRoute($fromPath);
        $toRoute = $this->router
            ->handleRoute($toPath);

        $fromNode = $this->{$fromRoute->exists}($fromRoute);

        $node = node_load($fromNode->nid);
        $node->title = $toRoute->arguments['node'];

        node_save($node);
    }

    protected function performDelete($path)
    {
        $route = $this->router
            ->handleRoute($path);
        
        if (!method_exists($this, $route->delete)) {
            throw new ezcWebdavInconsistencyException("method delete not yet implemented for this path");
        }

        return $this->{$route->delete}($route);
    }

    private function nodeNodeDelete(stdClass $route)
    {
        $dbNode = $this->{$route->exists}($route);
        
        if ((bool) $dbNode === true) {
            node_delete($dbNode->nid);
        }
    }

    private function nodeContentDelete(stdClass $route) 
    {
        $nodeNode = $this->nodeNode($route);
        if (!$node = node_load($nodeNode->nid)) {
            dd($route);
            throw new ezcWebdavInconsistencyException('Unable to load node');
        }

        if (!node_access('delete', $node)) {
            throw new ezcWebdavInconsistencyException('Bad Credentials');

            /*
              return new ezcWebdavMultistatusResponse(
              array (
              new ezcWebdavErrorResponse(ezcWebdavResponse::STATUS_403, $route->path, 'Bad credentials'),
              )
              );
            */
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
        $route = $this->router
            ->handleRoute($path);

        if ($route->type === WebdavDrupalRouter::ROUTE_NODE_CONTENT) {
            return false;
        }

        return true;
        
    }
    protected function nodeExists($path)
    {
        $route = $this->router
            ->handleRoute($path);

        return (bool) $this->{$route->exists}($route);
    }

    protected function getCollectionMembers($path)
    {
        $route = $this->router
            ->handleRoute($path);
        
        return $this->{$route->collection}($route);
    }

    private function nodeRoot(stdClass $route)
    {
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

    private function getContentLength($path)
    {
        $length = ezcWebdavGetContentLengthProperty::COLLECTION;

        if(!$this->isCollection($path)) {
            $route = $this->router
                ->handleRoute($path);
            $length = (string) filesize('public://'.$route->arguments['content']);
        }

        return !empty($length) ? $length : '0';
    }

    private function nodeRootCollection(stdClass $route)
    {
        if (false === $this->router->getRootCollection()) {
            return array();
        }
        
        foreach($this->router->getRootCollection() as $item) {
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