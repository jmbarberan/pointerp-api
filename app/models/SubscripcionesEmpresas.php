<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class SubscripcionesEmpresas extends Modelo
{
    public function initialize()
    {
        $this->setConnectionService('dbSubscripciones');
        $this->getModelsManager()->setModelSchema($this, 'subscripciones');
        $this->setSource('empresas');
    } 
    
    public function jsonSerialize () : array {
        return $this->ToUnicodeArray();
    }
}