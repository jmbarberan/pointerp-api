<?php

namespace Pointerp\Modelos\Maestros;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Maestros\Registros;

class Clientes extends Modelo {

  public function initialize() {
    $this->setSource('clientes');

    $this->hasOne('IdentificacionTipo', Registros::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relIdentificaTipo',
    ]);
  }

  public function jsonSerialize () : array {
    $res = $this->toArray();
    if ($this->relIdentificaTipo != null) {   
      $res['relIdentificaTipo'] = $this->relIdentificaTipo->toArray();
    }
    return $res;
  }

}