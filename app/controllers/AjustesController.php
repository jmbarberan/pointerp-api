<?php

namespace Pointerp\Controladores;

use Phalcon\Di\Di;
use Pointerp\Modelos\EmpresaClaves;
use Pointerp\Modelos\EmpresaParametros;
use Pointerp\Modelos\Maestros\Registros;
use Pointerp\Modelos\Sucursales;
use Pointerp\Modelos\Empresas;
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
        'order' => 'Indice'
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

  public function clavePorTablaIndiceAction() {
    $this->view->disable();
    $tabla = $this->dispatcher->getParam('tabla');
    $indice = $this->dispatcher->getParam('indice');
    $res = Registros::findFirst([
        'conditions' => 'TablaId = :tid: and Indice = :ind:',
        'bind' => ['tid' => $tabla, 'ind' => $indice]
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

  public function empresaPorEstadoAction() {
    $this->view->disable();
    $est = $this->dispatcher->getParam('est');
    $params = [];
    if ($est != 9) {
        $params = [
            'conditions' => 'Estado = :est:',
            'bind' => ['est' => $est]
        ];
    }
    $res = Empresas::find($params);
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

  public function encabezadosAction() {
    /*$connection = new PgConnection(
        [
            "host"     => "localhost",
            "username" => "postgres",
            "password" => "jmbg",
            "dbname"   => "viniapro",
            "port"     => "5435",
        ]
    );
    $result = $connection->fetchAll(
        "SELECT id, dbhost, dbname, dbuser, dbpass, dbport, dbdriver FROM subscripciones.subscripciones Where usuario = '" . $this->request->getHeaders()['Subscriber'] . "'"
    );
    $con = reset($result);*/
    $codigo = $this->request->getHeaders()['Authorization'] . "'";
    //$deco = base64_encode($this->request->getHeaders()['Authorization'] . "'"); 
    $deco = base64_decode($codigo);
    //print_r($deco);
    //echo $deco;
    $this->response->setStatusCode(200, 'Ok');
    $this->response->setContentType('text/plain', 'UTF-8');
    $this->response->setContent($deco);
    $this->response->send();
  }

  public function empresaClavePorCIdAction() {
    $this->view->disable();
    $emp = $this->dispatcher->getParam('emp');
    $cve = $this->dispatcher->getParam('cve');
    $clave = EmpresaClaves::findFirst([
        'conditions' => 'EmpresaId = :empId: and Clave = :clave:',
        'bind' => ['empId' => $emp, 'clave' => $cve],
        'order' => 'Indice'
    ]);
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($clave));
    $this->response->send();
  }

  public function parametrosPorEmpresaAction() {
    $this->view->disable();
    $emp = $this->dispatcher->getParam('emp');
    $params = EmpresaParametros::find([
        'conditions' => 'EmpresaId = :empId:',
        'bind' => ['empId' => $emp],
        'order' => 'Indice'
    ]);
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($params));
    $this->response->send();
  }
}