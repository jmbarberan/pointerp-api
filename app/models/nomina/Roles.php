<?php

namespace Pointerp\Modelos\Nomina;

use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Nomina\RolesEmpleados;
use Pointerp\Modelos\Nomina\RolesRubros;

class Roles extends Modelo
{
    public function initialize() {
        $this->setConnectionService('dbNomina');
        $this->getModelsManager()->setModelSchema($this, 'nomina');
        $this->setSource('roles');

        $this->hasMany('id', RolesEmpleados::class, 'rol_id', [
            'reusable' => true,
            'alias'    => 'relEmpleados'
        ]);
        $this->hasMany('id', RolesRubros::class, 'rol_id', [
            'reusable' => true,
            'alias'    => 'relRubros'
        ]);
    }
    
    public function jsonSerialize () : array {
        $res = $this->toArray();
        if ($this->relEmpleados != null) {
            $items = [];            
            foreach ($this->relEmpleados as $it) {
                if ($it->relEmpleado != null) {
                $ins = $it->toArray();
                $ins['relEmpleado'] = $it->relEmpleado->toArray();
                array_push($items, $ins);
                }
            }
            $res['relEmpleados'] = $items;
        }
        if ($this->relRubros != null) {
            $res['relRubros'] = $this->relRubros->toArray();
        }
        return $res;
    }
}