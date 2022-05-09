<?php

namespace Pointerp\Controladores;

use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\Maestros\Registros;
use Pointerp\Modelos\Subscripciones;

class SubscripcionesController extends ControllerBase  {

  public function credencialesValidarAction() {
    $cred = $this->request->getJsonRawBody();
    $di = Di::getDefault();
    $phql = 'SELECT * FROM Pointerp\Modelos\Subscripciones 
        WHERE code = "%s"';
    $qry = new Query(sprintf($phql, $cred->code), $di);
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
      $rus['dbtype'] = $sub->dbtype;
      $rus['sshost'] = $sub->sshost;
      $rus['sshport'] = $sub->sshport;
      $rus['sshuser'] = $sub->sshuser;
      $rus['sshtype'] = $sub->sshtype;
      $rus['sshkey'] = $sub->sshkey;
        
        $this->response->setStatusCode(200, 'Ok');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rus));
    $this->response->send();
}

}
