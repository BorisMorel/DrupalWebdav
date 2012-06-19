<?php

require_once(dirname(__FILE__).'/vendors/ezcomponents/Base/src/base.php');


function IMAGFile($class) {
    if (file_exists(dirname(__FILE__).'/vendors/IMAG/'.$class.'.php')) {
        require_once(dirname(__FILE__).'/vendors/IMAG/'.$class.'.php');
    }
}


spl_autoload_register("ezcBase::autoload");
spl_autoload_register("IMAGFile");
