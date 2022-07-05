<?php

use Pointerp\Rutas\SeguridadRutas;
use Pointerp\Rutas\AjustesRutas;
use Pointerp\Rutas\InventariosRutas;
use Pointerp\Rutas\MaestrosRutas;
use Pointerp\Rutas\VentasRutas;
use Pointerp\Rutas\SubscripcionesRutas;
use Pointerp\Rutas\CorsRutas;

$router = $di->getRouter();
$router->setDefaultNamespace('Pointerp\Controladores');

$router->mount(new SeguridadRutas());
$router->mount(new CorsRutas());
$router->mount(new AjustesRutas());
$router->mount(new InventariosRutas());
$router->mount(new MaestrosRutas());
$router->mount(new VentasRutas());
$router->mount(new SubscripcionesRutas());

/*$router->addGet('prueba/{texto}', [
  'controller' => 'seguridad',
  'action'     => 'prueba',
]);*/

$router->handle($_SERVER['REQUEST_URI']);
