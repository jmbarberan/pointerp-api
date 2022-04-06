<?php

namespace Pointerp\Modelos\Inventarios;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Maestros\Productos;

class ComprasItems extends Modelo {
  public function initialize() {
    $this->setSource('compraitems');

    $this->hasOne('ProductoId', Productos::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relProducto',
    ]);
  }

  public function jsonSerialize () : array {
    $res = $this->toArray();
    if ($this->relProducto != null) {   
      $res['relProducto'] = $this->relProducto->toArray();
    }
    return $res;
  } 
}