<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class Reportes extends Modelo
{
    public function initialize()
    {
        $this->setSource('reportes');
    }

    public function jsonSerialize () : array {
        return $this->ToUnicodeArray();
    }
}