<?php

namespace Pointerp\Modelos\Maestros;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class ProductosImagenes extends Modelo {
  public function initialize() {
    $this->setSource('productoimagenes');
  }
}