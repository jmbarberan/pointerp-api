<?php

namespace Pointerp\Modelos\Nomina;

use Phalcon\Mvc\Model;

class Roles extends Modelo
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