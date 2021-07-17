<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Modelo extends Model
{
    public function onConstruct()
    {
        $this->skipAttributesOnCreate(['Id',]);
    }
}