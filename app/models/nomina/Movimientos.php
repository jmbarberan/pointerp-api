<?php

namespace Pointerp\Modelos\Nomina;

use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Nomina\Empleados;

class Movimientos extends Modelo {
  public function initialize() {
    $this->setConnectionService('dbNomina');
    $this->getModelsManager()->setModelSchema($this, 'nomina');
    $this->setSource('movimientos');
      
    $this->hasOne('empleado_id', Empleados::class, 'id', [
      'reusable' => true, // cache
      'alias'    => 'relEmpleado',
    ]);
  }

  public function jsonSerialize () : array {
    return $this->asUnicodeArray(["descripcion"]);
    if ($this->relEmpleado != null) {
      $res['relEmpleado'] = $this->relEmpleado->asUnicodeArray(["nombres", "direccion", "cargo"]);
    }
  }
}