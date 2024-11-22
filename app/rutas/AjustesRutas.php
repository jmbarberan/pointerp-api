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
    $this->addGet('/tablas/{tabla}/registro/{indice}', [
      'controller' => $controlador,
      'action'     => 'clavePorTablaIndice',
    ]);
    $this->addGet('/sucursales/empresa/{emp}', [
      'controller' => $controlador,
      'action'     => 'sucursalesPorEmpresa',
    ]);
    $this->addGet('/empresas/estado/{est}', [
      'controller' => $controlador,
      'action'     => 'empresaPorEstado',
    ]);
    $this->addGet('/plantillas/tipo/{tipo}', [
      'controller' => $controlador,
      'action'     => 'plantillasPorTipo',
    ]);
    $this->addGet('/encabezados', [
      'controller' => $controlador,
      'action'     => 'encabezados',
    ]);
    $this->addGet('/empresa/{emp}/clave/{cve}', [
      'controller' => $controlador,
      'action'     => 'empresaClavePorCId',
    ]);
  }
}