<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class Sucursales extends Modelo
{
    public function initialize()
    {
        $this->setSource('sucursales');
    }   
}