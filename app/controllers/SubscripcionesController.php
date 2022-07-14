<?php

namespace Pointerp\Controladores;

use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
//use Pointerp\Modelos\Maestros\Registros;
use Pointerp\Modelos\Subscripciones;

class SubscripcionesController extends ControllerBase  {

  public function conexionPorCodigoAction() {
    $cred = $this->request->getJsonRawBody();
    $di = Di::getDefault();
    $phql = 'SELECT * FROM Pointerp\Modelos\Subscripciones 
        WHERE usuario = "%s"';
    $qry = new Query(sprintf($phql, $cred->codigo), $di);
    $rws = $qry->execute();
    $this->response->setStatusCode(404, 'Not found');
    $rus['resultado'] = false;
    if ($rws->count() === 1) {
      $sub = $rws->getFirst();
      $rus['resultado'] = true;
      $rus['dbhost'] = $sub->dbhost;
      $rus['dbuser'] = $sub->dbuser;
      $rus['dbpass'] = $sub->dbpass;
      $rus['dbport'] = $sub->dbport;
      /*$rus['dbtype'] = $sub->dbtype;
      $rus['sshost'] = $sub->sshost;
      $rus['sshport'] = $sub->sshport;
      $rus['sshuser'] = $sub->sshuser;
      $rus['sshtype'] = $sub->sshtype;
      $rus['sshkey'] = $sub->sshkey;*/
        
        $this->response->setStatusCode(200, 'Ok');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rus));
    $this->response->send();
  }

  public function codigoValidoAction() {
    $res = (object) [
      'res' => false,
      'cid' => 0,
      'tipo' => 0,
      'msj' => 'El codigo no es valido'
    ];
    $datos = $this->request->getJsonRawBody();
    $this->response->setStatusCode(404, 'Not Found');
    if (strlen($datos->codigo) > 0) {
      $sub = Subscripciones::findFirst([
        'conditions' => "clave = '" . trim(base64_decode($datos->codigo)) . "'"
      ]);
      if ($sub != null) {
        $this->response->setStatusCode(200, 'Ok');
        $res->res = true;
        $res->cid = $sub->id;
        $res->tipo = $sub->dbname;
        $res->msj = 'Codigo correcto';
      }
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  // codigoExistente 
  // enviarReseteo
  // actualizarCodigo

}
