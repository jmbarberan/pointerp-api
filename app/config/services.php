<?php
declare(strict_types=1);

use Phalcon\Escaper;
use Phalcon\Flash\Direct as Flash;
use Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter;
use Phalcon\Mvc\Model\Manager as ModelsManager;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Php as PhpEngine;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use Phalcon\Session\Adapter\Stream as SessionAdapter;
use Phalcon\Session\Manager as SessionManager;
use Phalcon\Url as UrlResolver;
use Phalcon\Http\Request;
use Phalcon\Db;
use Phalcon\Db\Exception;
use Phalcon\Db\Adapter\Pdo\Postgresql as PgConnection;
//use Pointerp\Library\Prevuelo;


/**
 * Shared configuration service
 */
$di->setShared('config', function () {
    return include APP_PATH . "/config/config.php";
});

/**
 * The URL component is used to generate all kind of urls in the application
 */
$di->setShared('url', function () {
    $config = $this->getConfig();

    $url = new UrlResolver();
    $url->setBaseUri($config->application->baseUri);

    return $url;
});

/**
 * Setting up the view component
 */
$di->setShared('view', function () {
    $config = $this->getConfig();
    $view = new View();
    $view->setDI($this);
    $view->setViewsDir($config->application->viewsDir);

    $view->registerEngines([
        '.volt' => function ($view) {
            $config = $this->getConfig();

            $volt = new VoltEngine($view, $this);

            $volt->setOptions([
                'path' => $config->application->cacheDir,
                'separator' => '_'
            ]);

            return $volt;
        },
        '.phtml' => PhpEngine::class

    ]);

    return $view;
});

/**
 * Database connection is created based in the parameters defined in the configuration file
 */
$di->setShared('db', function () {
    $request = new Request();
    $connection = new PgConnection(
        [
            "host"     => getenv('DBS_HOST'),
            "username" => getenv('DBS_USER'),
            "password" => getenv('DBS_PASS'),
            "dbname"   => getenv('DBS_NAME'),
            "port"     => getenv('DBS_PORT'),
        ]
    );
    $con = $connection->fetchAll(
        "SELECT id, dbhost, dbname, dbuser, dbpass, dbport, dbdriver, sharedemps, dbexclusive " .
        "FROM subscripciones.subscripciones " . 
        "Where clave = '" . trim(base64_decode($request->getHeaders()['Authorization'])) . "'"
    );
    if (count($con) > 0) {
        $con = reset($con);
        $params = [
            'host'     => $con['dbhost'],
            'username' => $con['dbuser'],
            'password' => $con['dbpass'],
            'dbname'   => $con['dbname'],
            //'charset'  => $config->database->charset,
            'port'     => $con['dbport'],
        ];
        $config = $this->getConfig();
        $config->entorno->sharedemps    = $con['sharedemps'];
        $config->entorno->exclusive     = $con['dbexclusive'];
        $config->entorno->subscripcion  = $con['id'];
    } else {
        $params = [
            'host'     => $config->dbfallback->host,
            'username' => $config->dbfallback->user,
            'password' => $config->dbfallback->pass,
            'dbname'   => $config->dbfallback->name,
            //'charset'  => $config->database->charset,
            'port'     => $config->dbfallback->port,
        ];
    }

    $class = 'Phalcon\Db\Adapter\Pdo\\' . $con['dbdriver'];

    return new $class($params);
});

$di->setShared('dbSubscripciones', function () {
    $config = $this->getConfig();

    $class = 'Phalcon\Db\Adapter\Pdo\\' . $config->dbsubscripciones->adapter;
    $params = [
        'host'     => $config->dbsubscripciones->host,
        'username' => $config->dbsubscripciones->username,
        'password' => $config->dbsubscripciones->password,
        'dbname'   => $config->dbsubscripciones->dbname,
        //'charset'  => $config->database->charset,
        'port'     => $config->dbsubscripciones->port,
    ];

    return new $class($params);
});

$di->setShared('dbNomina', function () {
    $request = new Request();
    $connection = new PgConnection(
        [
            "host"     => getenv('DBS_HOST'),
            "username" => getenv('DBS_USER'),
            "password" => getenv('DBS_PASS'),
            "dbname"   => getenv('DBS_NAME'),
            "port"     => getenv('DBS_PORT'),
        ]
    );
    $con = $connection->fetchAll(
        "SELECT id, ndbhost, ndbname, ndbuser, ndbpass, ndbport, ndbdriver, sharedemps, dbexclusive " .
        "FROM subscripciones.subscripciones " . 
        "Where clave = '" . trim(base64_decode($request->getHeaders()['Authorization'])) . "'"
    );
    if (count($con) > 0) {
        $con = reset($con);
        $params = [
            'host'     => $con['ndbhost'],
            'username' => $con['ndbuser'],
            'password' => $con['ndbpass'],
            'dbname'   => $con['ndbname'],
            'port'     => $con['ndbport'],
        ];
        $config = $this->getConfig();
        $config->entorno->sharedemps    = $con['sharedemps'];
        $config->entorno->exclusive     = $con['dbexclusive'];
        $config->entorno->subscripcion  = $con['id'];
    } else {
        $params = [
            'host'     => $config->ndbfallback->host,
            'username' => $config->ndbfallback->user,
            'password' => $config->ndbfallback->pass,
            'dbname'   => $config->ndbfallback->name,
            'port'     => $config->ndbfallback->port,
        ];
    }

    $class = 'Phalcon\Db\Adapter\Pdo\\' . $con['ndbdriver'];

    return new $class($params);
});

/**
 * If the configuration specify the use of metadata adapter use it or use memory otherwise
 */
$di->setShared('modelsMetadata', function () {
    return new MetaDataAdapter();
});


// Administrador de modelos 
$di->setShared("modelsManager", function() {
        return new ModelsManager();
    }
);

/**
 * Register the session flash service with the Twitter Bootstrap classes
 */
$di->set('flash', function () {
    $escaper = new Escaper();
    $flash = new Flash($escaper);
    $flash->setImplicitFlush(false);
    $flash->setCssClasses([
        'error'   => 'alert alert-danger',
        'success' => 'alert alert-success',
        'notice'  => 'alert alert-info',
        'warning' => 'alert alert-warning'
    ]);

    return $flash;
});

/**
 * Start the session the first time some component request the session service
 */
$di->setShared('session', function () {
    $session = new SessionManager();
    $files = new SessionAdapter([
        'savePath' => sys_get_temp_dir(),
    ]);
    $session->setAdapter($files);
    $session->start();

    return $session;
});

$di->setShared('subscripcion', function () {
    $req = new Request();
    $connection = new PgConnection(
        [
            "host"     => getenv('DBS_HOST'),
            "username" => getenv('DBS_USER'),
            "password" => getenv('DBS_PASS'),
            "dbname"   => getenv('DBS_NAME'),
            "port"     => getenv('DBS_PORT'),
        ]
    );
    $con = $connection->fetchAll(
        "SELECT id, sharedemps, dbexclusive " .
        "FROM subscripciones.subscripciones " . 
        "Where clave = '" . trim(base64_decode($req->getHeaders()['Authorization'])) . "'"
    );
    $entorno = [
        'sharedemps'=> 0,
        'exclusive' => 0,
        'id'        => 0,
    ];
    if (count($con) > 0) {
        $con = reset($con);
        $entorno['sharedemps']  = $con['sharedemps'];
        $entorno['exclusive']   = $con['dbexclusive'];
        $entorno['id']          = $con['id'];
    }
    return $entorno;
});
