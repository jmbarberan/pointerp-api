<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Usuarios extends Modelo
{
    public function initialize()
    {
        $this->setSource('usuarios');
    }
}