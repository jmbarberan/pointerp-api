<?php

defined('BASE_PATH') || define('BASE_PATH', getenv('BASE_PATH') ?: realpath(dirname(__FILE__) . '/../..'));
defined('APP_PATH') || define('APP_PATH', BASE_PATH . '/app');

return new \Phalcon\Config([
    'database' => [
        'adapter'     => 'Mysql',
        'host'        => 'localhost',
        'username'    => $_ENV['DB_USER'],
        'password'    => $_ENV['DB_PASS'],
        'dbname'      => $_ENV['DB_NAME'],
        'charset'     => 'utf8',
        'port'        => 3306
    ],
    'application' => [
        'appDir'         => APP_PATH . '/',
        'controllersDir' => APP_PATH . '/controllers/',
        'modelsDir'      => APP_PATH . '/models/',
        'modMaestrosDir' => APP_PATH . '/models/maestros',
        'modVentasDir'  => APP_PATH . '/models/ventas',
        'modInventariosDir' => APP_PATH . '/models/inventarios',
        'migrationsDir'  => APP_PATH . '/migrations/',
        'viewsDir'       => APP_PATH . '/views/',
        'libraryDir'     => APP_PATH . '/library/',
        'rutasDir'       => APP_PATH . '/rutas/',
        'cacheDir'       => BASE_PATH . '/cache/',
        'baseUri'        => '/',
    ],
    'entorno' => [
        'origen'        => '*',
        'tokenDuracion' => 12,
        'tokenSize'     => 16,
    ],
    'cors' => [
        'origen' => '*', // https://ecumedica.netlify.app
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
