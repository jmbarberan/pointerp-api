<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class ClientesSri extends Modelo
{
    public function initialize()
    {
        $this->setConnectionService('dbSubscripciones');
        $this->getModelsManager()->setModelSchema($this, 'subscripciones');
        $this->setSource('clientes_sri');
    }
}