<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Impuestos extends Modelo {
  public function initialize() {
    $this->setSource('impuestos');
  }
}