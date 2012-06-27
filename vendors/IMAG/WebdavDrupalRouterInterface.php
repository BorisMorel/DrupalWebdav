<?php

interface WebdavDrupalRouterInterface
{
    function handleRoute($path);
    function setDrupalUrlRoot($path);
    function setDocumentRoot($path);
    function setRootCollection(array $collection);
    function getRootCollection();
    function getDocumentRootCleared();
    function getDrupalUrlRootCleared();
}