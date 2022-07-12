<?php

namespace Pointerp\Modelos\Inventarios;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Sucursales;
use Pointerp\Modelos\inventarios\Bodegas;
use Pointerp\Modelos\inventarios\ComprasItems;
use Pointerp\Modelos\inventarios\ComprasImpuestos;
use Pointerp\Modelos\maestros\Proveedores;

class Compras extends Modelo
{
  public function initialize() {
    $this->setSource('compras');

    $this->hasOne('BodegaId', Bodegas::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relBodega',
    ]);
    $this->hasOne('SucursalId', Sucursales::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relSucursal',
    ]);
    $this->hasOne('ProveedorId', Proveedores::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relProveedor',
    ]);
    $this->hasMany('Id', ComprasItems::class, 'CompraId',
    [
      'reusable' => true,
      'alias'    => 'relItems'
    ]);
    $this->hasMany('Id', ComprasImpuestos::class, 'CompraId',
    [
      'reusable' => true,
      'alias'    => 'relImpuestos'
    ]);
  }
  
  public function jsonSerialize () : array {
    $res = $this->asUnicodeArray([ 'Notas' ]);
    if ($this->relBodega != null) {   
      $res['relBodega'] = $this->relBodega->toArray();
    }
    if ($this->relSucursal != null) {   
      $res['relSucursal'] = $this->relSucursal->toArray();
    }
    if ($this->relProveedor != null) {   
      $res['relProveedor'] = $this->relProveedor->toArray();
    }
    if ($this->relItems != null) {   
      $items = [];
      foreach ($this->relItems as $compraItem) {
        $itemToAdd = $compraItem->toArray();  
        if ($compraItem->relProducto != null) {
          $productoArray = $compraItem->relProducto->toArray();
          if ($compraItem->relProducto->relImposiciones != null) {
            $imposicionesPorProducto = [];
            foreach ($compraItem->relProducto->relImposiciones as $imp) {
              $imposicionToAdd = $imp->toArray();
              if ($imp->relImpuesto != null) {
                $imposicionToAdd['relImpuesto'] = $imp->relImpuesto->toArray();
              }
              array_push($imposicionesPorProducto, $imposicionToAdd);
            }            
            $productoArray['relImposiciones'] = $imposicionesPorProducto;
          }
          $itemToAdd['relProducto'] = $productoArray;
          array_push($items, $itemToAdd);
        }
      }
      $res['relItems'] = $items;
    }
    if ($this->relImpuestos != null) {   
      $imps = [];
      foreach ($this->relImpuestos as $it) {
        if ($it->relImpuesto != null) {
          $ins = $it->toArray();
          $ins['relImpuesto'] = $it->relImpuesto->toArray();
          array_push($imps, $ins);
        }
      }
      $res['relImpuestos'] = $imps;
    }
    return $res;
  }
}
