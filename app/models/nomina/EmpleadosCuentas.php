<?php

namespace Pointerp\Modelos\Nomina;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Nomina\Registros;

class EmpleadosCuentas extends Modelo {
  public function initialize() {
    $this->setConnectionService('dbNomina');
    $this->getModelsManager()->setModelSchema($this, 'nomina');
    $this->setSource('empleados_cuentas');
      
    $this->hasOne('tipo', Registros::class, 'id', [
      'reusable' => true, // cache
      'alias'    => 'relTipoCuenta',
    ]);
  }

  public function jsonSerialize () : array {
    return $this->toArray();
    if ($this->relTipo != null) {
      $res['relTipo'] = $this->relTipo->asUnicodeArray(["denominacion"]);
    }
  }
}