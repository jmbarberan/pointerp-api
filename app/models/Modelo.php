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
        $res = $this->toArray();
        array_walk_recursive($res, function(&$item) {
            if (is_string($item)) {
                $item = utf8_encode($item);
            }
        });
        return $res;
    }
}