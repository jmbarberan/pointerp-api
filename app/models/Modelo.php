<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Modelo extends Model
{
    public function onConstruct()
    {
        $this->skipAttributesOnCreate(['Id',]);
    }

    public function ToUnicodeArray() {
        array_walk_recursive($this->toArray(), function(&$item) {
            if (is_string($item)) {
                $item = utf8_encode($item);
            }
        });
    }
}