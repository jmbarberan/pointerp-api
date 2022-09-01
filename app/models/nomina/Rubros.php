<?php

namespace Pointerp\Modelos\Nomina;

use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Nomina\Registros;

class Rubros extends Modelo
{
    public function initialize() {
        $this->setConnectionService('dbNomina');
        $this->getModelsManager()->setModelSchema($this, 'nomina');
        $this->setSource('rubros');

        $this->hasOne('origen', Registros::class, 'id', [
          'reusable' => true, // cache
          'alias'    => 'relOrigen',
        ]); 

        $this->hasOne('periodo', Registros::class, 'id', [
          'reusable' => true, // cache
          'alias'    => 'relPeriodo',
        ]);

        $this->hasOne('formula', Registros::class, 'id', [
          'reusable' => true, // cache
          'alias'    => 'relFormula',
        ]);
    }
    
    public function jsonSerialize () : array {
      $res = $this->toArray(); //asUnicodeArray(["denominacion", "descripcion"]);
      if ($this->relOrigen != null) {
        $res['relOrigen'] = $this->relOrigen->toArray();
      }
      if ($this->relPeriodo != null) {
        $res['relPeriodo'] = $this->relPeriodo->toArray();
      }
      if ($this->relFormula != null) {
        $res['relFormula'] = $this->relFormula->toArray();
      }
      return $res;
    }
}