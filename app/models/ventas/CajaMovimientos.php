<?php

namespace Pointerp\Modelos\Ventas;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Sucursales;
use Pointerp\Modelos\Usuarios;
use Pointerp\Modelos\Ventas\Cajas;
use Pointerp\Modelos\Ventas\VentasItems;

class CajaMovimientos extends Modelo
{
  public function initialize() {
    $this->setSource('ventas');

    $this->hasOne('SucursalId', Sucursales::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relSucursal',
    ]);
    $this->hasOne('CajaId', Cajas::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relCaja',
    ]);
    $this->hasOne('UsuarioId', Usuarios::class, 'Id', [
        'reusable' => true, // cache
        'alias'    => 'relUsuario',
      ]);
  }
  
  public function jsonSerialize () : array {
    $res = $this->toUnicodeArray();
    if ($this->relSucursal != null) {   
      $res['relSucursal'] = $this->relSucursal->toArray();
    }
    if ($this->relCaja != null) {   
      $res['relCaja'] = $this->relCaja->toArray();
    }
    if ($this->relUsuario != null) {   
      $res['relUsuario'] = $this->relUsuario->toArray();
    }
    return $res;
  }
}
