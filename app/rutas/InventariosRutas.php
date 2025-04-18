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
    $this->addGet('/productos/cache', [
      'controller' => $controlador,
      'action'     => 'productosParaCache',
    ]);
    $this->addGet('/productos/emp/{emp}/estado/{estado}/filtro/{filtro}/extendida/{extendida}/seleccionar', [
      'controller' => $controlador,
      'action'     => 'productoSeleccionar',
    ]);
    $this->addGet('/productos/empresa/{empresa}/estado/{estado}/listar', [
      'controller' => $controlador,
      'action'     => 'productosEmpresaEstado',
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
    $this->addPost('/productos/replicar', [
      'controller' => $controlador,
      'action'     => 'productoReplicar',
    ]);
    $this->addPut('/productos/{id}/modificar/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'productoModificarEstado',
    ]);
    $this->addGet('/productos/{id}/existe/{ced}/nombre/{nom}', [
      'controller' => $controlador,
      'action'     => 'productoRegistrado',
    ]);
    $this->addGet('/productos/imagen/{id}', [
      'controller' => $controlador,
      'action'     => 'imagenProductoPorId',
    ]);

    // MOVIMIENTOS
    $this->addGet('/movimientos/{id}', [
      'controller' => $controlador,
      'action'     => 'movimientoPorId',
    ]);
    $this->addGet('/movimientos/bodega/{bodega}/clase/{clase}/estado/{estado}/desde/{desde}/hasta/{hasta}/tipobusca/{tipobusca}/tipo/{tipo}/filtro/{filtro}/buscar', [
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
    $this->addGet('/bodegas/empresa/{empresa}/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'bodegasPorEstado',
    ]);

    #region Compras
    $this->addGet('/compras/{id}', [
      'controller' => $controlador,
      'action'     => 'compraPorId',
    ]);
    $this->addGet('/compras/sucursal/{sucursal}/clase/{clase}/estado/{estado}/desde/{desde}/hasta/{hasta}/tipobusca/{tipobusca}/tipo/{tipo}/filtro/{filtro}/buscar', [
      'controller' => $controlador,
      'action'     => 'comprasBuscar',
    ]);
    $this->addPut('/compras/{id}/modificar/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'compraModificarEstado',
    ]);
    $this->addPost('/compras/guardar', [
      'controller' => $controlador,
      'action'     => 'compraGuardar',
    ]);
    #endregion
  }
}