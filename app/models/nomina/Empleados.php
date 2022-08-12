<?php

namespace Pointerp\Modelos\Nomina;

use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Nomina\Cargos;
use Pointerp\Modelos\Nomina\EmpleadosCuentas;

class Empleados extends Modelo {
  public function initialize() {
    $this->setConnectionService('dbNomina');
    $this->getModelsManager()->setModelSchema($this, 'nomina');
    $this->setSource('empleados');
      
    $this->hasOne('cargo_id', Cargos::class, 'id', [
      'reusable' => true, // cache
      'alias'    => 'relCargo',
    ]);

    $this->hasMany('id', EmpleadosCuentas::class, 'empleado_id',
      [
        'reusable' => true,
        'alias'    => 'relCuentas'
      ]
    );
  }

  public function jsonSerialize () : array {
    $res = $this->asUnicodeArray(["nombres", "direccion", "cargo"]);
    if ($this->relCargo != null) {
      $res['relCargo'] = $this->relCargo->asUnicodeArray(["denominacion", "descripcion"]);
    }
    if ($this->relCuentas != null) {
      $res['relCuentas'] = $this->relCuentas->toArray();
    }
    return $res;
  }
}