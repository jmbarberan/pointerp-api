<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Maestros\Clientes;

class Transacciones extends Modelo {
  
  public function initialize() {
    $this->setSource('transacciones');

    $this->hasOne('ClienteId', Clientes::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relCliente',
    ]);
  }
  
  public function jsonSerialize () : array {
    $res = $this->asUnicodeArray(["Notas"]);
    if ($this->relCliente != null) { 
      $res['relCliente'] = $this->relCliente->asUnicodeArray("Nombres");
    }
  }
}