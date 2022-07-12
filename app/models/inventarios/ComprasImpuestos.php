<?php

namespace Pointerp\Modelos\Inventarios;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Maestros\Impuestos;

class ComprasImpuestos extends Modelo {
  public function initialize() {
    $this->setSource('compraimpuestos');

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