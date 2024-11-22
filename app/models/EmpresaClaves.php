<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class EmpresaClaves extends Modelo
{
    public function initialize()
    {
        $this->setSource('empresaclaves');
    } 
    
    public function jsonSerialize () : array {
        return $this->ToUnicodeArray();
    }
}