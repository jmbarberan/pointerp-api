<?php

namespace Pointerp\Modelos\Maestros;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class Registros extends Modelo {
  public function initialize() {
    $this->setSource('registros');
  }

  public function jsonSerialize () : array {
    return $this->toUnicodeArray();
  }
}