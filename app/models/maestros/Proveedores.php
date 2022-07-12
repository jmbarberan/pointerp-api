<?php

namespace Pointerp\Modelos\Maestros;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Maestros\Registros;

class Proveedores extends Modelo {

  public function initialize() {
    $this->setSource('proveedores');

    $this->hasOne('IdentificacionTipo', Registros::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relIdentificaTipo',
    ]);
  }

  public function jsonSerialize () : array {
    $res = $this->asUnicodeArray([
      'Nombre',
      'Direccion',
      'Telefono',
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