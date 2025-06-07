<?php
use Phalcon\Http\Request;

defined('BASE_PATH') || define('BASE_PATH', getenv('BASE_PATH') ?: realpath(dirname(__FILE__) . '/../..'));
defined('APP_PATH') || define('APP_PATH', BASE_PATH . '/app');

return new \Phalcon\Config\Config([
    'dbfallback' => [
        'adapter'     => 'Mysql',
        'host'        => getenv('DB_HOST'),
        'username'    => getenv('DB_USER'),
        'password'    => getenv('DB_PASS'),
        'dbname'      => getenv('DB_NAME'),
        'charset'     => 'utf8',
        'port'        => 3306
    ],
    'dbsubscripciones' => [
        'adapter'     => 'Postgresql',
        'host'        => getenv('DBS_HOST'),
        'username'    => getenv('DBS_USER'),
        'password'    => getenv('DBS_PASS'),
        'dbname'      => getenv('DBS_NAME'),
        'charset'     => 'utf8',
        'port'        => getenv('DBS_PORT'),
    ],
    'application' => [
        'appDir'            => APP_PATH . '/',
        'controllersDir'    => APP_PATH . '/controllers/',
        'modelsDir'         => APP_PATH . '/models/',
        'modMaestrosDir'    => APP_PATH . '/models/maestros',
        'modVentasDir'      => APP_PATH . '/models/ventas',
        'modNominaDir'      => APP_PATH . '/models/nomina',
        'modInventariosDir' => APP_PATH . '/models/inventarios',
        'modCxcDir'         => APP_PATH . '/models/cxc',
        'migrationsDir'     => APP_PATH . '/migrations/',
        'viewsDir'          => APP_PATH . '/views/',
        'libraryDir'        => APP_PATH . '/library/',
        'rutasDir'          => APP_PATH . '/rutas/',
        'cacheDir'          => BASE_PATH . '/cache/',
        'baseUri'           => '/',
    ],
    'entorno' => [
        'origen'        => getenv('CORS_ORIGEN'),
        'tokenDuracion' => 12,
        'tokenSize'     => 16,
        'sharedemps'    => 0,
        'exclusive'     => 0,
        'subscripcion'  => 0,
    ],
    'cors' => [
        'origen' => getenv('CORS_ORIGEN'), // https://ecumedica.netlify.app
        'exposedHeaders' => [],
        // Should be in lowercases.
        'allowedHeaders' => ['x-requested-with', 'content-type', 'authorization'],
        // Should be in uppercase.
        'allowedMethods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        // Requests originating from here can entertain CORS.
        'allowedOrigins' => [
            '*', // https://ecumedica.netlify.app
        ],
        // Cache preflight for 7 days (expressed in seconds).
        'maxAge'         => 604800,
    ],
]);
