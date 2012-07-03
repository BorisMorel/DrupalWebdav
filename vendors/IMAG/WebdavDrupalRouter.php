<?php 

class WebdavDrupalRouter implements WebdavDrupalRouterInterface
{
    const
        ROUTE_NODE_ROOT = 0,
        ROUTE_NODE_TYPE = 1,
        ROUTE_NODE_NODE = 2,
        ROUTE_NODE_CONTENT = 3
        ;

    private static
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
                'pattern'             => "/%node_type%/%node%",
                'exists'              => "nodeNode",
                'collection'          => 'nodeNodeCollection',
                'create'              => 'nodeNodeCreate',
                'delete'              => 'nodeNodeDelete',
                'modifiedAt'          => 'nodeNodeModifiedAt',
                'createdAt'           => 'nodeNodeCreatedAt',
            ),
            self::ROUTE_NODE_CONTENT => array (
                'pattern'             => "/%node_type%/%node%/%content%",
                'exists'              => "nodeContent",
                'collection'          => 'nodeContentCollection',
                'setResourceContents' => 'nodeContentCreate',
                'getResourceContents' => 'nodeContentGet',
                'modifiedAt'          => 'nodeContentModifiedAt',
                'delete'              => 'nodeContentDelete',
            ),
        ),
        $rootCollection = array()
        ;

    private
        $drupalUrlRoot,
        $documentRoot
        ;
        
    public function handleRoute($path)
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
    
    public function getRootCollection()
    {
        return !empty(static::$rootCollection) ? static::$rootCollection : false; 
    }
   
    public function getDocumentRootCleared()
    {
        return static::sanitize($this->documentRoot);
    }

    public function getDrupalUrlRootCleared()
    {
        return static::sanitize($this->drupalUrlRoot);
    }

    /**
     * TODO : Enhance this function ... Because the count of / is very nosePif ;)
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

    private function eregRoute($route)
    {
        return preg_replace('/%[^%]+%/', '[^/]+', $route);
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

    private static function sanitize($path)
    {
        $path = trim($path, '/');
        $path = urldecode($path);
        $path = preg_replace('/\s+/', '_', $path);

        return $path;
    }
}