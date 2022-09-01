<?php

namespace Pointerp\Modelos\Nomina;

use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Nomina\Empleados;
use Pointerp\Modelos\Nomina\Registros;

class Movimientos extends Modelo {
  public function initialize() {
    $this->setConnectionService('dbNomina');
    $this->getModelsManager()->setModelSchema($this, 'nomina');
    $this->setSource('movimientos');
      
    $this->hasOne('empleado_id', Empleados::class, 'id', [
      'reusable' => true, // cache
      'alias'    => 'relEmpleado',
    ]);

    $this->hasOne('origen', Registros::class, 'id', [
      'reusable' => true, // cache
      'alias'    => 'relOrigen',
    ]);
  }

  public function jsonSerialize () : array {
    $res = $this->toArray();
    if ($this->relEmpleado != null) {
      $res['relEmpleado'] = $this->relEmpleado->toArray();
    }
    if ($this->relOrigen != null) {
      $res['relOrigen'] = $this->relOrigen->toArray();
    }
    return $res;
  }
}