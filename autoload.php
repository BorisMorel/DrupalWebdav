<?php

require_once(dirname(__FILE__).'/vendors/ezcomponents/Base/src/base.php');

function __autoload($class) {
    ezcBase::autoload($class);
}
