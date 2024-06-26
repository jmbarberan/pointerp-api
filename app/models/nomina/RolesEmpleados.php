<?php

namespace Pointerp\Modelos\Nomina;

use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Nomina\Empleados;

class RolesEmpleados extends Modelo {
  public function initialize() {
    $this->setConnectionService('dbNomina');
    $this->getModelsManager()->setModelSchema($this, 'nomina');
    $this->setSource('roles_empleados');
      
    $this->hasOne('empleado_id', Empleados::class, 'id', [
      'reusable' => true, // cache
      'alias'    => 'relEmpleado',
    ]);
  }

  public function jsonSerialize () : array {
    $res = $this->toArray();
    if ($this->relEmpleado != null) {
      $res['relEmpleado'] = $this->relEmpleado->asUnicodeArray(["nombres", "direccion", "cargo"]);
    }
    return res;
  }
}