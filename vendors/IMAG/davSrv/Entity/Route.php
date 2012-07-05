<?php
namespace IMAG\davSrv\Entity;

class Route implements \Serializable
{
    /**
     * Type of route
     * Ref: \IMAG\davSrv\Router\Router::ROUTE_NODE_*
     */
    private $type;

    /**
     * User viewing url
     */
    private $path;

    /**
     * System real url
     */
    private $url;

    /**
     * Route parameters
     */
    private $arguments;

    /**
     * Node class to manage this route
     */
    private $class;

    public function getType()
    {
        return $this->type;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getArguments()
    {
        return $this->arguments;
    }
    
    public function getArgument($name)
    {
        if (!isset($this->arguments[$name])) {
            return false;
        }
        
        return $this->arguments[$name];
    }

    public function getClass()
    {
        return $this->class;
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
        
        return $this;
    }

    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    public function serialize()
    {
        return serialize(array(
            $this->type,
            $this->path,
            $this->url,
            $this->arguments
        ));
    }

    public function unserialize($serialized)
    {
        list(
            $this->type,
            $this->path,
            $this->url,
            $this->arguments
        ) = unserialize($serialized);
    }

}