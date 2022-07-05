<?php

namespace Pointerp\Modelos\Ventas;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Sucursales;
use Pointerp\Modelos\Maestros\Clientes;
use Pointerp\Modelos\Inventarios\Bodegas;
use Pointerp\Modelos\Ventas\VentasItems;

class VentasMin extends Modelo
{
  public function initialize() {
    $this->setSource('ventas');
    
    $this->hasOne('ClienteId', Clientes::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relCliente',
    ]);
  }
  
  public function jsonSerialize () : array {
    $res = $this->toArray();
        
    if ($this->relCliente != null) {   
      $res['relCliente'] = $this->relCliente->toUnicodeArray();
    }
    return $res;
  }
}
