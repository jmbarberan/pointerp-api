<?php

namespace Pointerp\Rutas;

class MaestrosRutas extends \Phalcon\Mvc\Router\Group
{
  public function initialize()
  {
    $controlador = 'maestros';
    $this->setPaths(['namespace' => 'Pointerp\Controladores',]);
    $this->setPrefix('/api/v4/maestros');

    $this->addGet('/clientes/{id}', [
      'controller' => $controlador,
      'action'     => 'clientePorId',
    ]);

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

    $this->addPost('/clientes/guardar', [
      'controller' => $controlador,
      'action'     => 'clienteGuardar',
    ]);

    $this->addGet('/clientes/buscar/externo/{identificacion}', [
      'controller' => $controlador,
      'action'     => 'buscarCedulaSRI',
    ]);

    $this->addPut('/clientes/modificar/estado', [
      'controller' => $controlador,
      'action'     => 'clienteCambiarEstado',
    ]);

    $this->addGet('/proveedores/cedula/{ced}', [
      'controller' => $controlador,
      'action'     => 'proveedoresPorCedula',
    ]);

    $this->addGet('/proveedores/estado/{estado}/filtro/{filtro}/emp/{emp}/buscar', [
      'controller' => $controlador,
      'action'     => 'proveedoresPorNombresEstado',
    ]);

    $this->addGet('/impuestos/estado/{est}', [
      'controller' => $controlador,
      'action'     => 'impuestosPorEstado',
    ]);

    $this->addGet('/clientes/sri', [
      'controller' => $controlador,
      'action'     => 'clientesSriLista',
    ]);

    $this->addPut('/clientes/sri/modificar/estado', [
      'controller' => $controlador,
      'action'     => 'clienteSriCambiarEstado',
    ]);

    $this->addPost('/clientes/sri/guardar', [
      'controller' => $controlador,
      'action'     => 'clienteSriGuardar',
    ]);
  }
}