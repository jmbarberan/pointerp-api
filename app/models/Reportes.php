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
}