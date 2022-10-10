<?php

namespace Pointerp\Modelos\Cxc;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class ComprobanteItems extends Modelo {
  
  public function initialize() {
    $this->setSource('comprobanteitems');
  }
  
  public function jsonSerialize () : array {
    return $this->toArray();
  }
}