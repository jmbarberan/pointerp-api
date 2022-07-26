<?php

namespace Pointerp\Controladores;

use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\Ventas\VentasMin;

class CxcController extends ControllerBase {
  
  public function cuentaCorrienteAction() {
    $this->view->disable();
    $suc = $this->dispatcher->getParam('sucursal');
    $cli = $this->dispatcher->getParam('cliente');
    $res = [];
    $condicion = "Tipo in (11, 12) AND SucursalId = " . $suc . " AND Estado = 0";
    if ($cli > 0) {
      $condicion .= " AND ClienteId = " . $cli;
    }
    $ventas = VentasMin::find([
      'conditions' => $condicion,
      'order' => 'ClienteId'
    ]);
    $condiciondb = "Tipo = 15 AND SucursalId = " . $suc . " AND Estado = 0";
    if ($cli > 0) {
      $condiciondb .= " AND ClienteId = " . $cli;
    }
    $notasdb = VentasMin::find([
      'conditions' => $condiciondb,
      'order' => 'ClienteId'
    ]);

    $idx = 1;
    foreach ($ventas as $v) {
      $vta = (object) [
        'Indice' => $idx,
        'Id' => $v->Id,
        'Tipo' => $v->Tipo,
        'Numero' => $v->Numero,
        'Fecha' => $v->Fecha,
        'ClienteId' => $v->ClienteId,
        'relCliente' => $v->relCliente,
        'Total' => $v->Subtotal + $v->SubtotalEx,
        'Cobros' => $v->Abonos
      ];
      $idx++;
      array_push($res, $vta);
    }

    foreach ($notasdb as $nd) {
      $ndb = (object) [
        'Indice' => $idx,
        'Id' => $nd->Id,
        'Tipo' => $nd->Tipo,
        'Numero' => $nd->Numero,
        'Fecha' => $nd->Fecha,
        'ClienteId' => $nd->ClienteId,
        'relCliente' => $nd->relCliente,
        'Total' => $nd->Valor,
        'Cobros' => $nd->Abonos
      ];
      $idx++;
      array_push($res, $ndb);
    }

    if (count($res) > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }
}