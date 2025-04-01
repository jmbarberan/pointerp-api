<?php

namespace Pointerp\Modelos\Ventas;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Sucursales;
use Pointerp\Modelos\Maestros\Clientes;
use Pointerp\Modelos\Inventarios\Bodegas;
use Pointerp\Modelos\Ventas\VentasItems;

class Ventas extends Modelo
{
  public function initialize() {
    $this->setSource('ventas');

    $this->hasOne('SucursalId', Sucursales::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relSucursal',
    ]);
    $this->hasOne('BodegaId', Bodegas::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relBodega',
    ]);
    $this->hasOne('ClienteId', Clientes::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relCliente',
    ]);
    $this->hasMany('Id', VentasItems::class, 'VentaId',
    [
      'reusable' => true,
      'alias'    => 'relItems'
    ]);
  }
  
  public function jsonSerialize () : array {
    $res = $this->asUnicodeArray(["Notas"]);
    if ($this->relSucursal != null) {   
      $res['relSucursal'] = $this->relSucursal->toArray();
    }
    if ($this->relBodega != null) {   
      $res['relBodega'] = $this->relBodega->toArray();
    }
    if ($this->relCliente != null) {   
      $res['relCliente'] = $this->relCliente->asUnicodeArray(["Nombres"]);
    }
    if ($this->relItems != null) {   
      $items = [];
      foreach ($this->relItems as $it) {
        if ($it->relProducto != null) {
          $insItem = $it->toArray();
          if (isset($it->relProducto) && isset($it->relProducto->relImposiciones)) { 
            $insItem['relProducto'] = $it->relProducto->toArray();
            $imposiciones = [];
            foreach ($it->relProducto->relImposiciones as $impo) {
              if ($impo->relImpuesto != null) {
                $impoItem = $impo->toArray();
                $impoItem['relImpuesto'] = $impo->relImpuesto->toArray();
                array_push($imposiciones, $impoItem);
              }
            }
            $insItem['relProducto']['relImposiciones'] = $imposiciones;
          }
          array_push($items, $insItem);
        }
      }
      $res['relItems'] = $items;
    }
    return $res;
  }
}
