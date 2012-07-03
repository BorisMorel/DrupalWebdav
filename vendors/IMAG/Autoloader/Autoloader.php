<?php
namespace IMAG\Autoloader;

class Autoloader
{
    public function register()
    {
        spl_autoload_register(__NAMESPACE__.'\Autoloader::loader');
    }

    static private function loader($class)
    {
        $class = preg_replace('#\\\#', '/', $class);
        $class = preg_replace('#^[^/]+/#', '', $class);

        $path = dirname(__FILE__).'/../'.$class.'.php';

        if (file_exists($path)) {
            require_once($path);

            return true;
        }

        return false;
    }

}

