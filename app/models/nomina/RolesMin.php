<?php

namespace Pointerp\Modelos\Nomina;

use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Nomina\RolesEmpleados;
use Pointerp\Modelos\Nomina\RolesRubros;

class RolesMin extends Modelo
{
    public function initialize() {
        $this->setConnectionService('dbNomina');
        $this->getModelsManager()->setModelSchema($this, 'nomina');
        $this->setSource('roles');
    }
    
    public function jsonSerialize () : array {
        return $this->toArray();
    }
}