<?php

namespace Pointerp\Modelos\Ventas;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Maestros\Productos;

class VentasItems extends Modelo {
  public function initialize() {
    $this->setSource('ventaitems');

    $this->hasOne('ProductoId', Productos::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relProducto',
    ]);
  }

  public function jsonSerialize () : array {
    $res = $this->toArray();
    if ($this->relProducto != null) {
      $prd = $this->relProducto->asUnicodeArray();
      if ($this->relProducto->relImposiciones != null) {   
        $impos = [];
        foreach ($this->relProducto->relImposiciones as $it) {
          if ($it->relImpuesto != null) {
            $ins = $it->toArray();
            $ins['relImpuesto'] = $it->relImpuesto->toArray();
            array_push($impos, $ins);
          }
        }
        $prd['relImposiciones'] = $impos;
      }
      $res['relProducto'] = $prd;      
    }
    return $res;
  } 
}