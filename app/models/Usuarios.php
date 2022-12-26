<?php

namespace Pointerp\Modelos;

class Usuarios extends Modelo
{
  public function initialize()
  {
    $this->setSource('usuarios');
  }

  public function jsonSerialize () : array {
    return $this->ToUnicodeArray();
  }
}