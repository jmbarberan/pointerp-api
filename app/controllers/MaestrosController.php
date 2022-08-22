<?php
//declare(strict_types=1);

namespace Pointerp\Controladores;

use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\Registros;
use Pointerp\Modelos\SubscripcionesEmpresas;
use Pointerp\Modelos\Maestros\Clientes;
use Pointerp\Modelos\Maestros\Proveedores;

class MaestrosController extends ControllerBase  {
  
  #region Clientes
  public function clientesPorCedulaAction() {
    $ced = $this->dispatcher->getParam('ced');    
    $rows = Clientes::find([
      'conditions' => 'Identificacion = :ced:',
      'bind' => [ 'ced' => $ced ]
    ]);
    if ($rows->count() > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $rows = Clientes::find([
        'conditions' => 'Codigo = :cod:',
        'bind' => [ 'cod' => $ced ]
      ]);
      if ($rows->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
      } else {
        $this->response->setStatusCode(404, 'Not found');
      }
    }
    $this->response->setContentType('application/json');    
    $this->response->setContent(json_encode($rows));
    $this->response->send();
  }

  public function clientesBuscarAction() {
    $estado = $this->dispatcher->getParam('estado');
    $filtro = $this->dispatcher->getParam('filtro');
    $atrib = $this->dispatcher->getParam('atrib');
    $emp = $this->dispatcher->getParam('emp');
    $filtroSP = str_replace('%20', ' ', $filtro);
    $filtroSP = str_replace('  ', ' ',trim($filtroSP));
    // eñes
    $filtroSP = str_replace('%C3%91' , 'Ñ',$filtroSP);
    $filtroSP = str_replace('%C3%B1' , 'ñ',$filtroSP);
    $config = Di::getDefault()->getConfig();
    $condicion = '';  
    $ex = $this->subscripcion['id'];
    if ($this->subscripcion['exclusive'] === 1) {
      $condicion .= ($this->subscripcion['sharedemps'] === 1 ? '' : 'EmpresaId = ' . $emp . ' AND ');
    } else {
      $emps = SubscripcionesEmpresas::find([
        'conditions' => 'subscripcion_id = ' . $this->subscripcion['id']
      ]);
      $condicion = '';
      foreach ($emps as $e) {
        $condicion .= strlen($condicion) > 0 ? ', ' . $e->empresa_id : $e->empresa_id;
      }
      $condicion = (strlen($condicion) > 0 ? 'EmpresaId in (' . $condicion . ')' : 'EmpresaId = ' . $emp);
      $condicion .= ' AND ';
    }
    
    if ($atrib != 'Nombres') {
      $condicion .= $atrib . ' = :fil:';
    } else {
      $filtroSP = strtoupper($filtroSP);
      $filtro = '%' . str_replace(' ' , '%', $filtroSP) . '%';
      $condicion .= 'UPPER('.$atrib.') LIKE :fil:';
    }
    if ($estado == 0) { // Estado = 1: motrar todos
        $condicion .= ' AND Estado = 0';
    }
    $rows = Clientes::find([
      'conditions' => $condicion,
      'bind' => [ 'fil' => $filtro ]
    ]);
    if ($rows->count() > 0) {
      $this->response->setStatusCode(200, 'Ok => ' . $ex);
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rows));
    $this->response->send();
  }

  public function clientesPorNombresEstadoAction() {
    $estado = $this->dispatcher->getParam('estado');
    $filtro = $this->dispatcher->getParam('filtro');
    $emp = $this->dispatcher->getParam('emp');
    $filtroSP = str_replace('%20', ' ', $filtro);
    $filtroSP = str_replace('  ', ' ',trim($filtroSP));
    // eñes
    $filtroSP = str_replace('%C3%91' , 'Ñ',$filtroSP);
    $filtroSP = str_replace('%C3%B1' , 'ñ',$filtroSP);
    $filtro = str_replace(' ' , '%', $filtroSP) . '%';
    $cve = new Clientes();
    if ($this->subscripcion['exclusive'] === 1) {
      $condicion = $this->subscripcion['sharedemps'] === 1 ? '' : 'EmpresaId = ' . $emp . ' AND ';
    } else {
      $emps = SubscripcionesEmpresas::find([
        'conditions' => 'subscripcion_id = ' . $this->subscripcion['id']
      ]);
      $condicion = '';
      foreach ($emps as $e) {
        $condicion .= strlen($condicion) > 0 ? ', ' . $e->empresa_id : $e->empresa_id;
      }
      $condicion = (strlen($condicion) > 0 ? 'EmpresaId in (' . $condicion . ')' : 'EmpresaId = ' . $emp);
      $condicion .= ' AND ';
    }

    $condicion .= 'UPPER(Nombres) like UPPER(:fil:)';
    if ($estado == 0) {
        $condicion .= ' AND Estado = 0';
    }
    $rows = Clientes::find([
      'conditions' => $condicion,
      'bind' => [ 'fil' => $filtro ]
    ]);
    if ($rows->count() > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found ' . $filtro);
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rows));
    $this->response->send();
  }
  #endregion

  #region Proveedores
  public function proveedoresPorCedulaAction() {
    $ced = $this->dispatcher->getParam('ced');
    $rows = Proveedores::find([
      'conditions' => 'Identificacion = :ced:',
      'bind' => [ 'ced' => $ced ]
    ]);
    if ($rows->count() > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rows));
    $this->response->send();
  }

  public function proveedoresPorNombresEstadoAction() {
    $estado = $this->dispatcher->getParam('estado');
    $filtro = $this->dispatcher->getParam('filtro');
    $filtroSP = str_replace('%20', ' ', $filtro);
    $filtroSP = str_replace('%C3%91' , 'Ñ',$filtroSP);
    $filtroSP = str_replace('%C3%B1' , 'ñ',$filtroSP);
    $filtroSP = preg_replace('/\s+/', ' ', $filtroSP);
    $filtro = str_replace(' ' , '%', $filtroSP) . '%';
    $condicion = 'UPPER(Nombre) like UPPER(:fil:)';
    if ($estado == 0) {
        $condicion .= ' AND Estado = 0';
    }
    $rows = Proveedores::find([
      'conditions' => $condicion,
      'bind' => [ 'fil' => $filtro ]
    ]);
    if ($rows->count() > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found ' . $filtro);
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rows));
    $this->response->send();
  }
  #endregion
}