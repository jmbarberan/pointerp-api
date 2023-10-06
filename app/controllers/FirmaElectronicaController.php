<?php

namespace Pointerp\Controladores;

use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\Maestros\Registros;
use Pointerp\Modelos\Sucursales;
use Pointerp\Modelos\Empresas;
use Pointerp\Modelos\Reportes;

use Phalcon\Db;
use Phalcon\Db\Exception;
use Phalcon\Db\Adapter\Pdo\Postgresql as PgConnection;

class FirmaElectronicaController extends ControllerBase  {

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
}  