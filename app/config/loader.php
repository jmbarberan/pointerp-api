<?php

use Phalcon\Autoload\Loader;

$loader = new Loader();

$dirs = [
    $config->application->controllersDir,
    $config->application->modelsDir,
    $config->application->modMaestrosDir,
    $config->application->modVentasDir,
    $config->application->modInventariosDir,
    $config->application->modNominaDir,
    $config->application->modCxcDir,
    $config->application->rutasDir,
    $config->application->libraryDir,
];

$names = [
    'Pointerp\Controladores'        => '../app/controllers/',
    'Pointerp\Modelos'              => '../app/models/',
    'Pointerp\Modelos\Maestros'     => '../app/models/maestros',
    'Pointerp\Modelos\Inventarios'  => '../app/models/inventarios',
    'Pointerp\Modelos\Ventas'       => '../app/models/ventas',
    'Pointerp\Modelos\Nomina'       => '../app/models/nomina',
    'Pointerp\Modelos\Cxc'          => '../app/models/cxc',
    'Pointerp\Rutas'                => '../app/rutas/',
    'Pointerp\Library'              => '../app/library/',    
];

/**
 * Se registran los directorios y nombres tomados del archivo de configuracion
 */
$loader->setDirectories($dirs);
$loader->setNamespaces($names, true);
/*$loader->registerFiles([
    APP_PATH . '/../../vendor/autoload.php'
]);*/
$loader->register();
