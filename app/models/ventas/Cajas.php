<?php

namespace Pointerp\Modelos\Ventas;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class Cajas extends Modelo
{
    public function initialize()
    {
        $this->setSource('cajas');
    } 
    
    public function jsonSerialize () : array {
        return $this->ToUnicodeArray();
    }
}