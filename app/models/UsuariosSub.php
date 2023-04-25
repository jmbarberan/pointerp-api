<?php

namespace Pointerp\Modelos;

class UsuariosSub extends Modelo
{
    public function initialize()
    {
        $this->setConnectionService('dbSubscripciones');
        $this->getModelsManager()->setModelSchema($this, 'subscripciones');
        $this->setSource('usuarios');
    }
}