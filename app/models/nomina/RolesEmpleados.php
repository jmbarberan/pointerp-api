<?php

namespace Pointerp\Modelos\Nomina;

use Phalcon\Mvc\Model;
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
    return $this->asUnicodeArray(["descripcion"]);
    if ($this->relEmpleado != null) {
      $res['relEmpleado'] = $this->relEmpleado->asUnicodeArray(["nombres", "direccion", "cargo"]);
    }
  }
}