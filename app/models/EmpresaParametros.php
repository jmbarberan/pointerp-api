<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class EmpresaParametros extends Modelo
{
    public function initialize()
    {
        $this->setSource('empresaparametros');
    } 
    
    public function jsonSerialize () : array {
        return $this->ToUnicodeArray();
    }
}