<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Modelo extends Model
{
    public function onConstruct()
    {
        $this->skipAttributesOnCreate(['Id',]);
    }

    public function toUnicodeArray() {
        $res = $this->toArray();        
        array_walk_recursive($res, function(&$item) {
            if (is_string($item)) {
                $item = utf8_encode($item);
            }
        });
        return $res; 
    }

     public function asUnicodeArray($arry) {
        $res = $this->toArray();
        foreach ($arry as $atrib) {
            if (is_string($res[$atrib])) {
                if (mb_check_encoding($res[$atrib], "UTF-8")) {
                    $res[$atrib] = utf8_encode($res[$atrib]);
                } else {
                    $res[$atrib] = mb_convert_encoding($res[$atrib], 'UTF-8', 'UTF-8');
                }
            }
        }
        return $res;
     }
}