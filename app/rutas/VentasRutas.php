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
    $this->addGet('/comprobantes/tipo/{tipo}/sucursal/{sucursal}/secuencial', [
      'controller' => $controlador,
      'action'     => 'ventaTraerSecuencial',
    ]);
    $this->addGet('/comprobantes/sucursal/{sucursal}/secuencial', [
      'controller' => $controlador,
      'action'     => 'ventaGenerarSecuencialCE',
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
    $this->addPut('/comprobantes/actualizarDataCE', [
      'controller' => $controlador,
      'action'     => 'ventaActualizarEstadoCE',
    ]);
    $this->addPatch('/comprobantes/{id}/autorizar', [
      'controller' => $controlador,
      'action'     => 'ventaAutorizar',
    ]);
    $this->addPatch('/comprobantes/{id}/verificar', [
      'controller' => $controlador,
      'action'     => 'ventaVerificar',
    ]);
    $this->addGet('/comprobantes/{id}/enviar-correo', [
      'controller' => 'FirmaElectronica',
      'action'     => 'enviarComprobantePorEmail',
    ]);
    $this->addPost('/comprobantes/guardar', [
      'controller' => $controlador,
      'action'     => 'ventaGuardar',
    ]);
    $this->addPost('/comprobantes/sincronizar', [
      'controller' => $controlador,
      'action'     => 'ventasListaGuardar',
    ]);
    $this->addPost('/comprobantes/guardar/usuario/{usuario}/caja/{caja}/cobrar', [
      'controller' => $controlador,
      'action'     => 'ventaCrearCobrar',
    ]);
    $this->addPost('/comprobantes/guardar-win/usuario/{usuario}/caja/{caja}/cobrar/{cobrar}', [
      'controller' => $controlador,
      'action'     => 'ventaWinGuardarNuevo',
    ]);
    $this->addGet('/comprobantes/sucursal/{sucursal}/estado/{estado}/desde/{desde}/hasta/{hasta}/electronicos', [
      'controller' => $controlador,
      'action'     => 'ventasDiarioCE',
    ]);
    $this->addGet('/cajas/empresa/{empresa}/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'cajasPorEstado',
    ]);
    $this->addGet('/cajas/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'cajasPorEstado',
    ]);
    $this->addGet('/cajas/empresa/{empresa}/usuario/{usuario}', [
      'controller' => $controlador,
      'action'     => 'cajasPorUsuario',
    ]);
    $this->addPost('/cajas/guardar', [
      'controller' => $controlador,
      'action'     => 'cajaGuardar',
    ]);
    $this->addPut('/cajas/{id}/modificar/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'cajaModificarEstado',
    ]);
    $this->addGet('/cajas/movimientos/sucursal/{sucursal}/clase/{clase}/estado/{estado}/desde/{desde}/hasta/{hasta}/tipo/{tipo}/filtro/{filtro}/buscar', [
      'controller' => $controlador,
      'action'     => 'valesBuscar',
    ]);
    $this->addPost('/cajas/movimientos/guardar', [
      'controller' => $controlador,
      'action'     => 'valeGuardar',
    ]);
    $this->addPut('/cajas/movimientos/{id}/modificar/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'valeModificarEstado',
    ]);
    $this->addGet('/cajas/{caja}/sucursal/{sucursal}/movimientos/desde/{desde}/hasta/{hasta}/cuadre', [
      'controller' => $controlador,
      'action'     => 'cuadreCajaMovimientos',
    ]);
  }
}