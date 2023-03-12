<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Modelo extends Model
{
    public function onConstruct()
    {
        $this->skipAttributesOnCreate(['Id',]);
        $this->skipAttributesOnCreate(['id',]);
    }

    public function toUnicodeArray() {
        $res = $this->toArray();        
        array_walk_recursive($res, function(&$item) {
            if (is_string($item)) {
                $item = mb_convert_encoding($item, "UTF-8", mb_detect_encoding($item));
            }
        });
        return $res; 
    }

     public function asUnicodeArray($campos) {
        return $this->toArray();
        /*foreach ($campos as $atrib) {
            if (is_string($res[$atrib])) {*/
                /*$unicoded = utf8_encode($res[$atrib]);
                $res[$atrib] = bin2hex($unicoded);*/
                /*$res[$atrib] = preg_replace_callback(function($txt) {
                    return preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", json_encode($txt));
                })*/
                /*$result = preg_replace_callback(
                    /\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1'))),
                    function($txt) { return CallFunction($txt); },
                    $res[$atrib]
                );*/
                
                
                /*if (mb_check_encoding($res[$atrib], "UTF-8")) {
                    $res[$atrib] = utf8_encode($res[$atrib]);
                } else {
                    $res[$atrib] = mb_convert_encoding($res[$atrib], 'UTF-8', 'UTF-8');
                }*/
            /*}
        }*/
        //return $res;
     }
}