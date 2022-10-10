<?php

namespace Pointerp\Modelos\Cxc;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class ComprobanteDocumentos extends Modelo {
  
  public function initialize() {
    $this->setSource('comprobantedocumentos');
  }
  
  public function jsonSerialize () : array {
    return $this->toArray();
  }
}