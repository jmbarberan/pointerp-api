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
    $res = $this->asUnicodeArray([
      'Codigo',
      'Nombres',
      'Direccion',
      'Telefonos',
      'Representante',
      'Referencias',
      'Email'
    ]);
    if ($this->relIdentificaTipo != null) {   
      $res['relIdentificaTipo'] = $this->relIdentificaTipo->asUnicodeArray(['Denominacion']);
    }
    return $res;
  }

}