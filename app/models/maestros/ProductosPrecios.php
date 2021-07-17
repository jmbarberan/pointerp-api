<?php

namespace Pointerp\Modelos\Maestros;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class ProductosPrecios extends Modelo {
  public function initialize() {
    $this->setSource('productoprecios');
  }
}