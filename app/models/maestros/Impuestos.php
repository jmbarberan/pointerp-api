<?php

namespace Pointerp\Modelos\Maestros;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class Impuestos extends Modelo {
  public function initialize() {
    $this->setSource('impuestos');
  }

  public function jsonSerialize () : array {
    return $this->toUnicodeArray();
  }
}