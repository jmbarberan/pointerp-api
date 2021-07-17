<?php

namespace Pointerp\Controladores;

use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\Maestros\Registros;
use Pointerp\Modelos\Sucursales;
use Pointerp\Modelos\Reportes;

class AjustesController extends ControllerBase  {

  public function clavePorIdAction() {
    $this->view->disable();
    $id = $this->dispatcher->getParam('id');
    $res = Registros::findFirstById($id);

    if ($res != null) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $res = [];
        $this->response->setStatusCode(404, 'Not found');
    }
    
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function clavesPorTablaAction() {
    $this->view->disable();
    $tabla = $this->dispatcher->getParam('tabla');
    $res = Registros::find([
        'conditions' => 'TablaId = :tid:',
        'bind' => ['tid' => $tabla,],
        'order' => 'indice'
    ]);

    if ($res->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function sucursalesPorEmpresaAction() {
    $this->view->disable();
    $emp = $this->dispatcher->getParam('emp');
    $res = Sucursales::find([
        'conditions' => 'EmpresaId = :id:',
        'bind' => ['id' => $emp]
    ]);

    if ($res->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function plantillasPorTipoAction() {
    $this->view->disable();
    $tipo = $this->dispatcher->getParam('tipo');
    $res = Reportes::find([
        'conditions' => 'PlantillaTransaccion = :tipo:',
        'bind' => ['tipo' => $tipo,],
    ]);

    if ($res->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }
}