<?php

namespace Pointerp\Modelos\Inventarios;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;

class Bodegas extends Modelo
{
    public function initialize()
    {
        $this->setSource('bodegas');
    }   
}