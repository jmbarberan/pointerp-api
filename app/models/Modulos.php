<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class Modulos extends Modelo
{
    public function initialize()
    {
        $this->setSource('modulos');
    } 
    
    public function jsonSerialize () : array {
        return $this->ToUnicodeArray();
    }
}