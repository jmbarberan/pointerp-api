<?php

namespace Pointerp\Rutas;

class MaestrosRutas extends \Phalcon\Mvc\Router\Group
{
  public function initialize()
  {
    $controlador = 'maestros';
    $this->setPaths(['namespace' => 'Pointerp\Controladores',]);
    $this->setPrefix('/api/v4/maestros');

    $this->addGet('/clientes/cedula/{ced}', [
      'controller' => $controlador,
      'action'     => 'clientesPorCedula',
    ]);

    $this->addGet('/clientes/estado/{estado}/filtro/{filtro}/emp/{emp}/buscar', [
      'controller' => $controlador,
      'action'     => 'clientesPorNombresEstado',
    ]);

    $this->addGet('/clientes/estado/{estado}/atrib/{atrib}/filtro/{filtro}/emp/{emp}/buscar', [
      'controller' => $controlador,
      'action'     => 'clientesBuscar',
    ]);

    $this->addGet('/proveedores/cedula/{ced}', [
      'controller' => $controlador,
      'action'     => 'proveedoresPorCedula',
    ]);

    $this->addGet('/proveedores/estado/{estado}/filtro/{filtro}/emp/{emp}/buscar', [
      'controller' => $controlador,
      'action'     => 'proveedoresPorNombresEstado',
    ]);
  }
}