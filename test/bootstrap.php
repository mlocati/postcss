<?php

error_reporting(E_ALL);

$timezone_identifier = @date_default_timezone_get();
if (empty($timezone_identifier)) {
    $timezone_identifier = 'UTC';
}
date_default_timezone_set($timezone_identifier);
unset($timezone_identifier);

require_once dirname(__DIR__).'/autoloader.php';

spl_autoload_register(
    function ($class) {
        if (strpos($class, 'PostCSS\\Tests') !== 0) {
            return;
        }
        $file = __DIR__.DIRECTORY_SEPARATOR.'tests'.str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen('PostCSS\\Tests'))).'.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
);

PHPUnit_Framework_Error_Notice::$enabled = true;
