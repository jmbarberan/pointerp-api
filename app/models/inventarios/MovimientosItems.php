<?php

namespace Pointerp\Modelos\Inventarios;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Maestros\Productos;

class MovimientosItems extends Modelo {
  public function initialize() {
    $this->setSource('kardexitems');

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