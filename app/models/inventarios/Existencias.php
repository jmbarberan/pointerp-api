<?php

namespace Pointerp\Modelos\Inventarios;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Maestros\Productos;
use Pointerp\Modelos\inventarios\Bodegas;

class Existencias extends Modelo {
  public function initialize() {
    $this->setSource('existenciadatos');

    $this->hasOne('ProductoId', Productos::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relProducto',
    ]);
    $this->hasOne('BodegaId', Bodegas::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relBodega',
    ]);
  }

  public function jsonSerialize () : array {
    $res = $this->toArray();
    if ($this->relProducto != null) {
      $res['relProducto'] = $this->relProducto->toArray();
    }
    if ($this->relBodega != null) {
      $res['relBodega'] = $this->relBodega->toArray();
    }
    return $res;
  }
}