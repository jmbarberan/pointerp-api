<?php

namespace Pointerp\Modelos\Maestros;

use Exception;
use Phalcon\Mvc\Model;
use Pointerp\Modelos\Modelo;
use Pointerp\Modelos\Maestros\Registros;

class Clientes extends Modelo {

  public function initialize() {
    $this->setSource('clientes');

    $this->hasOne('IdentificacionTipo', Registros::class, 'Id', [
      'reusable' => true, // cache
      'alias'    => 'relIdentificaTipo',
    ]);
  }

  public function jsonSerialize () : array {
    $res = $this->asUnicodeArray([
      'Codigo',
      'Nombres',
      'Direccion',
      'Telefonos',
      'Representante',
      'Referencias',
      'Email'
    ]);
    if ($this->relIdentificaTipo != null) {   
      $res['relIdentificaTipo'] = $this->relIdentificaTipo->asUnicodeArray(['Denominacion']);
    }
    return $res;
  }

  public function generarNuevoCodigo($emp) {
    $newcod = "";
    $phql = "SELECT MAX(Codigo) as maxcod FROM Pointerp\Modelos\Maestros\Clientes 
      WHERE Estado = 0 AND Codigo != '99' AND EmpresaId = {$emp}";
    $rws = $this->modelsManager->executeQuery($phql);
    if ($rws->count() === 1) {
      $rmax = $rws->getFirst();
      try {
        $num = intval($rmax['maxcod']);
      } catch (Exception $e) {
        $num = 0;
      }
      if ($num == 0)
        $num = 1000;
      else
        $num += 1;

      $newcod = strval($num);
    }
    $this->Codigo = $newcod;
  }

}