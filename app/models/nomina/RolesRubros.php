<?php

namespace Pointerp\Modelos\Nomina;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class RolesRubros extends Modelo {
  public function initialize() {
    $this->setConnectionService('dbNomina');
    $this->getModelsManager()->setModelSchema($this, 'nomina');
    $this->setSource('roles_rubros');
  }

  public function jsonSerialize () : array {
    return $this->toArray();
  }
}