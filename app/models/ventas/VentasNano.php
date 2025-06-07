<?php

namespace Pointerp\Modelos\Ventas;

use Pointerp\Modelos\Modelo;

class VentasNano extends Modelo
{
  public function initialize() {
    $this->setSource('ventas');
  }
  
  public function jsonSerialize () : array {
    return $this->toArray();
  }
}
