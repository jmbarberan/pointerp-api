<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Comandos extends Modelo
{
    public function initialize()
    {
        $this->setSource('comandos');
    }

    public function jsonSerialize () : array {
        return $this->ToUnicodeArray();
    }
}