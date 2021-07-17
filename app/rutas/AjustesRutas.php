<?php

namespace Pointerp\Rutas;

class AjustesRutas extends \Phalcon\Mvc\Router\Group
{
  public function initialize()
  {
    $controlador = 'ajustes';
    $this->setPaths(['namespace' => 'Pointerp\Controladores',]);
    $this->setPrefix('/api/v4/ajustes');

    $this->addGet('/tablas/registros/{id}', [
      'controller' => $controlador,
      'action'     => 'clavePorId',
    ]);
    $this->addGet('/tablas/{tabla}/registros', [
      'controller' => $controlador,
      'action'     => 'clavesPorTabla',
    ]);
    $this->addGet('/sucursales/empresa/{emp}', [
      'controller' => $controlador,
      'action'     => 'sucursalesPorEmpresa',
    ]);
    $this->addGet('/plantillas/tipo/{tipo}', [
      'controller' => $controlador,
      'action'     => 'plantillasPorTipo',
    ]);
  }
}