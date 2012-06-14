<?php

require_once(dirname(__FILE__).'/autoload.php');

$server = ezcWebdavServer::getInstance();
$backend = new ezcWebdavFileBackend('/tmp');
$server->handle( $backend );