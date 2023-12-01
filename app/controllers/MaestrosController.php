<?php
//declare(strict_types=1);

namespace Pointerp\Controladores;

use Pointerp\Modelos\ClientesSri;
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
    //$config = Di::getDefault()->getConfig();
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

  #region clientes sri

  public function clientesSriListaAction() {
    $estado     = $this->request->getQuery('estado', null, 1);
    $filtro     = $this->request->getQuery('filtro');
    $extendido  = $this->request->getQuery('extendido');
    if ($extendido) $filtro = '%' . $filtro;
    $condiciones = "nombres like '{$filtro}%'";
    $opciones = [ 'order' => 'nombres' ];
    if ($estado < 9) {
      $condiciones .= " and activo = {$estado}";
    }
    $opciones['conditions'] = $condiciones;
    $rows = ClientesSri::find($opciones);
    if ($rows->count() > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rows));
    $this->response->send();
  }

  public function clienteSriGuardarAction() {
    $datos = $this->request->getJsonRawBody();
    $ret = (object) [
      'res' => false,
      'cid' => $datos->id,
      'msj' => 'Los datos no se pudieron procesar'
    ];
    $ahora = new \DateTime();
    $cliente = ClientesSri::findFirstById($datos->id);
    if (!isset($cliente)) {
      $cliente = new ClientesSri();
    }
    $cliente->identificacion      = $datos->identificacion;
    $cliente->nombres             = $datos->nombres;
    $cliente->clave_ruc           = $datos->clave_ruc;
    $cliente->email               = $datos->email;
    $cliente->clave_email         = $datos->clave_email;
    $cliente->telefonos           = $datos->telefonos;
    $cliente->direccion           = $datos->direccion;
    $cliente->fecha_ingreso       = $datos->fecha_ingreso;
    $cliente->abono_efectivo      = $datos->abono_efectivo;
    $cliente->abono_transferencia = $datos->abono_transferencia;
    $cliente->abono_fecha         = $datos->abono_fecha;
    $cliente->observaciones       = $datos->observaciones;
    $cliente->activo              = $datos->activo;
    
    if ($datos->id > 0) {
      $cliente->actualizacion = $ahora->format('Y-m-d H:i:s');
      if($cliente->update()) {
        $ret->res = true;
      }
    } else {
      if($cliente->create()) {
        $ret->res = true;
      }
    }
    if ($ret->res) {
      $ret->cid = $cliente->id;
      $ret->msj = "Cliente guardado exitósamente";
    } else {
      $this->response->setStatusCode(500, 'Error');
      $msj = "No se puedo guardar los datos: " . "\n";
      foreach ($cliente->getMessages() as $m) {
        $msj .= $m . "\n";
      }
      $ret->msj = $msj;
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function clienteSriCambiarEstadoAction() {
    $id     = $this->request->getQuery('id', null, 0);
    $activo = $this->request->getQuery('activo', null, true);
    $this->response->setStatusCode(422, 'Unprocessable Content');
    $cliente = ClientesSri::findFirstById($id);
    if ($cliente) {
      $cliente->activo = ($activo === "true" ? 1 : 0);
      if($cliente->update()) {
        $msj = "Operacion ejecutada exitosamente";
        $this->response->setStatusCode(200, 'Ok');
      } else {
        $msj = "Error al intentar eliminar el cliente";
      }
    } else {
      $msj = "No se encontro el Cliente";
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode(boolval($activo)));
    $this->response->send();
  }

  #endregion
}