<?php

namespace Pointerp\Modelos\Nomina;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class Registros extends Modelo {
  public function initialize() {
    $this->setConnectionService('dbNomina');
    $this->getModelsManager()->setModelSchema($this, 'nomina');
    $this->setSource('registros');
  }

  public function jsonSerialize () : array {
    return $this->asUnicodeArray(["denominacion", "codigo"]);
  }
}