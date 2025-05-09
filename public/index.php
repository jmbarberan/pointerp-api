<?php

declare(strict_types=1);

require realpath('..') . "/vendor/autoload.php";

use Phalcon\Di\FactoryDefault;

error_reporting(E_ALL);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');



/*$version = explode('.', PHP_VERSION);
if ($version[0] >= 8) {
    $debug = new \Phalcon\Support\Debug();
    $debug->listen();
} else {
    $debug = new \Phalcon\Debug();
    $debug->listen();
}*/


try {
    /**
     * The FactoryDefault Dependency Injector automatically registers
     * the services that provide a full stack framework.
     */
    $di = new FactoryDefault();

    /**
     * Read services
     */
    include APP_PATH . '/config/services.php';

    /**
     * Get config service for use in inline setup below
     */
    $config = $di->getConfig();

    /**
     * Include Autoloader
     */
    include APP_PATH . '/config/loader.php';
    
    /**
     * Handle routes
     */
    include APP_PATH . '/config/router.php';

    /**
     * Handle the request
     */
    $application = new \Phalcon\Mvc\Application($di);

    include APP_PATH . '/library/ComprobantesElectronicos.php';
    include APP_PATH . '/library/PreFlightListener.php';
    include APP_PATH . '/models/Constantes.php';


    date_default_timezone_set('America/Guayaquil');
    echo $application->handle($_SERVER['REQUEST_URI'])->getContent();
} catch (\Exception $e) {
    echo $e->getMessage() . '<br>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
}
