<?php

namespace Pointerp\Modelos\Maestros;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Maestros\Registros;
use Pointerp\Modelos\Maestros\ProductosPrecios;
use Pointerp\Modelos\Maestros\ProductosImposiciones;

class Productos extends Modelo {
  
  public function initialize() {
    $this->setSource('productos');

    $this->hasOne('Grupo', Registros::class, 'Indice', [
      'reusable' => true, // cache
      'alias'    => 'relCategoria',
      'params'   => [
      'conditions' => 'TablaId = :type:',
      'bind'       => [
        'type' => 4,
      ]
    ]
    ]);
    $this->hasOne('Tipo', Registros::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relTipo',
    ]);

    $this->hasMany('Id', ProductosPrecios::class, 'ProductoId',
      [
        'reusable' => true,
        'alias'    => 'relPrecios'
      ]
    );

    $this->hasMany('Id', ProductosImposiciones::class, 'ProductoId',
      [
        'reusable' => true,
        'alias'    => 'relImposiciones'
      ]
    );
  }

  public function jsonSerialize () : array {
    $res = $this->asUnicodeArray([ 'Codigo', 'Nombre', 'Descripcion', 'Medida' ]);
    if ($this->relCategoria != null) {
      $res['relCategoria'] = $this->relCategoria->asUnicodeArray([ 'Denominacion' ]);
    }
    if ($this->relTipo != null) {
      $res['relTipo'] = $this->relTipo->toArray();
    }
    if ($this->relPrecios != null) {
      $res['relPrecios'] = $this->relPrecios->toArray();
    }
    if ($this->relImposiciones != null) {   
      $items = [];
      foreach ($this->relImposiciones as $it) {
        if ($it->relImpuesto != null) {
          $ins = $it->toArray();
          $ins['relImpuesto'] = $it->relImpuesto->toArray();
          array_push($items, $ins);
        }
      }
      $res['relImposiciones'] = $items;
    }
    return $res;
  }
}