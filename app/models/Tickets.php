<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Tickets extends Modelo
{
    public function initialize()
    {
        $this->setConnectionService('dbSubscripciones');
        $this->getModelsManager()->setModelSchema($this, 'subscripciones');
        $this->setSource('tickets');
    }

    public function jsonSerialize () : array {
        return $this->asUnicodeArray(["nombre", "usuario"]);
    }
}