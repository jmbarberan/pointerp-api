<?php

namespace Pointerp\Modelos\Nomina;

use Pointerp\Modelos\Modelo;

class Cargos extends Modelo
{
    public function initialize() {
        $this->setConnectionService('dbNomina');
        $this->getModelsManager()->setModelSchema($this, 'nomina');
        $this->setSource('cargos');
    }
    
    public function jsonSerialize () : array {
        return $this->asUnicodeArray(["denominacion", "descripcion"]);
    }
}