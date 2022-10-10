<?php

namespace Pointerp\Modelos\Cxc;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Cxc\ComprobanteDocumentos;
use Pointerp\Modelos\Cxc\ComprobanteItems;

class Comprobantes extends Modelo {
  
  public function initialize() {
    $this->setSource('comprobantes');

    $this->hasOne('SucursalId', Sucursales::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relSucursal',
    ]);

    $this->hasMany('Id', ComprobanteDocumentos::class, 'ComprobanteId',
    [
      'reusable' => true,
      'alias'    => 'relDocumentos'
    ]);

    $this->hasMany('Id', ComprobanteItems::class, 'ComprobanteId',
    [
      'reusable' => true,
      'alias'    => 'relItems'
    ]);
  }
  
  public function jsonSerialize () : array {
    $res = $this->toArray();
    
    if ($this->relSucursal != null) {
      $res['relSucursal'] = $this->relSucursal->toArray();
    }
    if ($this->relDocumentos != null) {
      $res['relDocumentos'] = $this->relDocumentos->toArray();
    }
    if ($this->relItems != null) {
      $res['relItems'] = $this->relItems->toArray();
    }
    return $res;
  }
}