<?php

namespace Pointerp\Rutas;

class VentasRutas extends \Phalcon\Mvc\Router\Group
{
  public function initialize()
  {
    $controlador = 'ventas';
    $this->setPaths(['namespace' => 'Pointerp\Controladores',]);
    $this->setPrefix('/api/v4/ventas');

    // Facturas
    $this->addGet('/comprobantes/{id}', [
      'controller' => $controlador,
      'action'     => 'ventaPorId',
    ]);
    $this->addGet('/comprobantes/tipo/{tipo}/numero/{numero}', [
      'controller' => $controlador,
      'action'     => 'ventaPorNumero',
    ]);
    $this->addGet('/comprobantes/sucursal/{sucursal}/clase/{clase}/estado/{estado}/desde/{desde}/hasta/{hasta}/tipo/{tipo}/filtro/{filtro}/buscar', [
      'controller' => $controlador,
      'action'     => 'ventasBuscar',
    ]);
    $this->addGet('/comprobantes/sucursal/{sucursal}/estado/{estado}/desde/{desde}/hasta/{hasta}/diario', [
      'controller' => $controlador,
      'action'     => 'ventasDiario',
    ]);
    $this->addPut('/comprobantes/{id}/estado/{estado}/modificar', [
      'controller' => $controlador,
      'action'     => 'ventaModificarEstado',
    ]);
    $this->addPatch('/comprobantes/{id}/autorizar', [
      'controller' => $controlador,
      'action'     => 'ventaAutorizar',
    ]);
    $this->addPatch('/comprobantes/{id}/verificar', [
      'controller' => $controlador,
      'action'     => 'ventaVerificar',
    ]);
    $this->addPost('/comprobantes/guardar', [
      'controller' => $controlador,
      'action'     => 'ventaGuardar',
    ]);
    $this->addPost('/comprobantes/guardar/usuario/{usuario}/caja/{caja}/cobrar', [
      'controller' => $controlador,
      'action'     => 'ventaCrearCobrar',
    ]);
  }
}