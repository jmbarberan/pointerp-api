<?php

namespace Pointerp\Modelos\Ventas;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Maestros\Impuestos;

class VentasImpuestos extends Modelo {
  public function initialize() {
    $this->setSource('ventaimpuestos');

    $this->hasOne('ImpuestoId', Impuestos::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relImpuesto',
    ]);
  }

  public function jsonSerialize () : array {
    $res = $this->toArray();
    if ($this->relImpuesto != null) {   
      $res['relImpuesto'] = $this->relImpuesto->toArray();
    }
    return $res;
  } 
}