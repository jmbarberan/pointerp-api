<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Subscripciones extends Modelo
{
    public function initialize()
    {
        $this->setSource('subscripciones');
    }

    public function jsonSerialize () : array {
        return $this->ToUnicodeArray();
    }
}