<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class Empresas extends Modelo
{
    public function initialize()
    {
        $this->setSource('empresas');
    } 
    
    public function jsonSerialize () : array {
        return $this->ToUnicodeArray();
    }
}