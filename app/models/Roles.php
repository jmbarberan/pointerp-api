<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Roles extends Modelo
{
    public function initialize()
    {
        $this->setSource('roles');
    }   
}