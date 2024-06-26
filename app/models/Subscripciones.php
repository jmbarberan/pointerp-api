<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Subscripciones extends Modelo
{
    public function initialize()
    {
        $this->setConnectionService('dbSubscripciones');
        $this->getModelsManager()->setModelSchema($this, 'subscripciones');
        $this->setSource('subscripciones');
    }

    public function jsonSerialize () : array {
        return $this->asUnicodeArray(["nombre", "usuario"]);
    }
}