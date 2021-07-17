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
    $this->addPut('/comprobantes/{id}/modificar/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'ventaModificarEstado',
    ]);
    $this->addPost('/comprobantes/guardar', [
      'controller' => $controlador,
      'action'     => 'ventaGuardar',
    ]);
  }
}