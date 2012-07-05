<?php
namespace IMAG\davSrv\Router;

interface RouterInterface
{
    function handleRoute($path);
    function setDrupalUrlRoot($path);
    function setDocumentRoot($path);
    function setRootCollection(array $collection);
    static function getRootCollection();
    function getDocumentRootCleared();
    function getDrupalUrlRootCleared();
}