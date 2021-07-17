<?php

namespace Pointerp\Rutas;

class InventariosRutas extends \Phalcon\Mvc\Router\Group
{
  public function initialize()
  {
    $controlador = 'inventarios';
    $this->setPaths(['namespace' => 'Pointerp\Controladores',]);
    $this->setPrefix('/api/v4/inventarios');

    $this->addGet('/productos/{id}', [
      'controller' => $controlador,
      'action'     => 'productoPorId',
    ]);
    $this->addGet('/productos/emp/{emp}/tipo/{tipo}/estado/{estado}/atributo/{atrib}/filtro/{filtro}/buscar', [
      'controller' => $controlador,
      'action'     => 'productosBuscar',
    ]);
    $this->addGet('/productos/{id}/bodega/{bodega}/existencia', [
      'controller' => $controlador,
      'action'     => 'exitenciasProducto',
    ]);

    $this->addGet('/productos/bodega/{bodega}/existencia', [
      'controller' => $controlador,
      'action'     => 'exitenciasTodos',
    ]);
    $this->addGet('/productos/bodega/{bodega}/existencia/ceros/{zeros}', [
      'controller' => $controlador,
      'action'     => 'exitenciasTodos',
    ]);
    $this->addGet('/productos/bodega/{bodega}/ceros', [
      'controller' => $controlador,
      'action'     => 'productosEnCero',
    ]);
    $this->addPost('/productos/guardar', [
      'controller' => $controlador,
      'action'     => 'productoGuardar',
    ]);
    $this->addPut('/productos/{id}/modificar/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'productoModificarEstado',
    ]);
    $this->addGet('/productos/{id}/existe/{ced}/nombre/{nom}', [
      'controller' => $controlador,
      'action'     => 'productoRegistrado',
    ]);

    // MOVIMIENTOS
    $this->addGet('/movimientos/{id}', [
      'controller' => $controlador,
      'action'     => 'movimientoPorId',
    ]);
    $this->addGet('/movimientos/bodega/{bodega}/clase/{clase}/estado/{estado}/desde/{desde}/hasta/{hasta}/tipo/{tipo}/filtro/{filtro}/buscar', [
      'controller' => $controlador,
      'action'     => 'movimientosBuscar',
    ]);
    $this->addPut('/movimientos/{id}/modificar/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'movimientoModificarEstado',
    ]);
    $this->addPost('/movimientos/guardar', [
      'controller' => $controlador,
      'action'     => 'movimientoGuardar',
    ]);

    // Bodegas
    $this->addGet('/bodegas/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'bodegasPorEstado',
    ]);
  }
}