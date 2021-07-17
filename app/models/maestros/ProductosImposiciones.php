<?php

namespace Pointerp\Modelos\Maestros;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class ProductosImposiciones extends Modelo {
  public function initialize() {
    $this->setSource('imposiciones');
    
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