<?php

namespace Pointerp\Controladores;

use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\Nomina\Registros;
use Pointerp\Modelos\Nomina\Cargos;
use Pointerp\Modelos\Nomina\Empleados;
use Pointerp\Modelos\Nomina\EmpleadosCuentas;

class NominaController extends ControllerBase {
  
  #region Tablas
  public function registrosPorTablaAction() {
    $this->view->disable();
    $tab = $this->dispatcher->getParam('tabla');
    $sub = $this->dispatcher->getParam('sub');
    $emp = $this->dispatcher->getParam('emp');
    $res = [];
    $res = Registros::find([
      'conditions' => "subscripcion_id = " . $sub . " AND empresa_id = " . $emp . " AND tabla_id = " . $tab,
      'order' => 'indice'
    ]);
    if (count($res) > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function registroPorTablaIndiceAction() {
    $this->view->disable();
    $tab = $this->dispatcher->getParam('tabla');
    $idx = $this->dispatcher->getParam('indice');
    $sub = $this->dispatcher->getParam('sub');
    $emp = $this->dispatcher->getParam('emp');
    $res = [];
    $res = Registros::find([
      'conditions' => "subscripcion_id = " . $sub . " AND empresa_id = " . $emp . " AND tabla_id = " . $tab . " AND indice = " . $idx,
      'order' => 'indice'
    ]);
    if (count($res) > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function registroPorId() {
    $this->view->disable();
    $id = $this->dispatcher->getParam('id');
    $res = [];
    $res = Registros::findFirstById([
      'conditions' => "id = " . $id,
      'order' => 'indice'
    ]);
    if (count($res) > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }
  #endregion

  #region Cargos
  public function cargosPorEstadoAction() {
    $est = $this->dispatcher->getParam('estado');
    $sub = $this->dispatcher->getParam('sub');
    $emp = $this->dispatcher->getParam('emp');
    $condiciones = "subscripcion_id = " . $sub . " AND empresa_id = " . $emp;
    if ($est == 0) {
      $condiciones .= " AND estado = " . $est;
    }
    $res = Cargos::find([
      'conditions' => $condiciones,
      'order' => 'denominacion'
    ]);
    if (count($res) > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function cargoPorIdAction() {
    $id = $this->dispatcher->getParam('id');
    $res = Cargos::findFirstById($id);
    if (count($res) > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function cargoModificarEstadoAction() {
    $id = $this->dispatcher->getParam('id');
    $est = $this->dispatcher->getParam('estado');
    $res = Cargos::findFirstById($id);
    if ($res != null) {
      $res->estado = $est;
      if($res->update()) {
        $msj = "Registro procesado exitosamente";
        $this->response->setStatusCode(200, 'Ok');
      } else {
        $this->response->setStatusCode(404, 'Error');
        $msj = "No se puede actualizar el registro: " . "\n";
        foreach ($res->getMessages() as $m) {
          $msj .= $m . "\n";
        }
      }
    } else {
      $msj = "No se encontro el registro";
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($msj));
    $this->response->send();
  }

  public function cargoGuardarAction() {
    try {
      $datos = $this->request->getJsonRawBody();
      $ret = (object) [
        'res' => false,
        'cid' => -1,
        'msj' => 'Los datos no se pudieron procesar'
      ];
      $this->response->setStatusCode(406, 'Not Acceptable');
      if ($datos->id > 0) {
        $car = Cargos::findFirstById($datos->id);
        $car->subscripcion_id = $datos->subscripcion_id;
        $car->empresa_id = $datos->empresa_id;
        $car->denominacion = $datos->denominacion;
        $car->departamento_id = $datos->departamento_id;
        $car->descripcion = $datos->descripcion;
        $car->remuneracion_tipo = $datos->remuneracion_tipo;
        $car->remuneracion_valor = $datos->remuneracion_valor;        
        $car->referencia = $datos->referencia;
        $car->actualizacion = $datos->actualizacion;
        $car->estado = $datos->estado;
        if($car->update()) {
          $ret->res = true;
          $this->response->setStatusCode(200, 'Ok');
          $ret->cid = 0;
          $ret->msj = "Se actualizo exitosamente los datos del registro";
        } else {
          $msj = "Los datos no se puedieron actualizar:" . "\n";
          foreach ($car->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = -1;
          $ret->msj = $msj;
        }
      } else {
        $carn = new Cargos();
        $carn->subscripcion_id = $datos->subscripcion_id;
        $carn->empresa_id = $datos->empresa_id;
        $carn->denominacion = $datos->denominacion;
        $carn->departamento_id = $datos->departamento_id;
        $carn->descripcion = $datos->descripcion;
        $carn->remuneracion_tipo = $datos->remuneracion_tipo;
        $carn->remuneracion_valor = $datos->remuneracion_valor;        
        $carn->referencia = $datos->referencia;
        $carn->actualizacion = $datos->actualizacion;
        $carn->estado = $datos->estado;
        if ($carn->create()) {
          $ret->res = true;
          $ret->cid = 0;
          $ret->msj = "Se guardó exitosamente el nuevo registro";
          $this->response->setStatusCode(201, 'Created');
        } else {
          $msj = "No se pudo crear el nuevo registro: " . "\n";
          foreach ($carn->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = -1;
          $ret->msj = $msj;
        }
      }
    } catch (Exception $e) {
      $ret->res = false;
      $ret->cid = -1;
      $ret->msj = $e->getMessage();
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }
  #endregion

  #region Empleados
  public function empleadoPorIdAction() {
    $id = $this->dispatcher->getParam('id');
    $res = Empleados::findFirstById($id);
    if ($res != null) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function empleadosBuscarAction() {
    $tipo = $this->dispatcher->getParam('tipo');
    $estado = $this->dispatcher->getParam('estado');
    $filtro = $this->dispatcher->getParam('filtro');
    $atrib = $this->dispatcher->getParam('atrib');
    $emp = $this->dispatcher->getParam('emp');
    $sub = $this->dispatcher->getParam('sub');
    $filtroSP = str_replace('%20', ' ', $filtro);
    $filtroSP = str_replace('  ', ' ',trim($filtroSP));
    // eñes
    $filtroSP = str_replace('%C3%91' , 'Ñ',$filtroSP);
    $filtroSP = str_replace('%C3%B1' , 'ñ',$filtroSP);
    $condicion = 'empresa_id = :emp: AND subscripcion_id = :sub: AND ';
    if ($atrib == 'cedula') {
      $filtro = $filtroSP;
      $condicion .= $atrib . ' = :fil:';
    } else { // Nombres puede buscar 
      $filtroSP = strtoupper($filtroSP);
      $filtro = str_replace(' ' , '%', $filtroSP) . '%';
      $condicion .= "UPPER(".$atrib.") LIKE :fil:";
      if ($tipo == 1) {
        $filtro = '%' . $filtro;
      }
    }
    
    if ($estado == 0) { // Estado = 1: motrar todos
        $condicion .= ' AND estado = 0';
    }
    $rows = Empleados::find([
      'conditions' => $condicion,
      'bind' => [ 'fil' => $filtro, 'emp' => $emp, 'sub' => $sub ],
      'order' => 'nombres'
    ]);
    if ($rows->count() > 0) {
      $this->response->setStatusCode(200, 'Ok ' . $filtro);
    } else {
      $this->response->setStatusCode(404, 'Not found ' . $filtro);
    }
    $this->response->setContentType('application/json');
    $this->response->setContent(json_encode($rows));
    $this->response->send();
  }

  public function empleadosBuscarMinAction() {
    $filtro = $this->dispatcher->getParam('filtro');
    $tipo = $this->request->getQuery('tipo', 'int');
    $estado = $this->request->getQuery('estado', 'int');
    $atrib = $this->request->getQuery('atributo', 'string');
    $emp = $this->request->getQuery('emp', 'int');
    $sub = $this->request->getQuery('sub','int');
    $filtroSP = str_replace('%20', ' ', $filtro);
    $filtroSP = str_replace('  ', ' ',trim($filtroSP));
    // eñes
    $filtroSP = str_replace('%C3%91' , 'Ñ',$filtroSP);
    $filtroSP = str_replace('%C3%B1' , 'ñ',$filtroSP);
    $condicion = 'empresa_id = :emp: AND subscripcion_id = :sub: AND ';
    if ($atrib == 'cedula') {
      $filtro = $filtroSP;
      $condicion .= $atrib . ' = :fil:';
    } else { // Nombres puede buscar 
      $filtroSP = strtoupper($filtroSP);
      $filtro = str_replace(' ' , '%', $filtroSP) . '%';
      $condicion .= "UPPER(".$atrib.") LIKE :fil:";
      if ($tipo == 1) {
        $filtro = '%' . $filtro;
      }
    }
    
    if ($estado == 0) { // Estado = 1: motrar todos
        $condicion .= ' AND estado = 0';
    }
    $rows = Empleados::find([
      'conditions' => $condicion,
      'bind' => [ 'fil' => $filtro, 'emp' => $emp, 'sub' => $sub ],
      'order' => 'nombres'
    ]);
    if ($rows->count() > 0) {
      $this->response->setStatusCode(200, 'Ok ' . $filtro);
    } else {
      $this->response->setStatusCode(404, 'Not found ' . $filtro);
    }
    $this->response->setContentType('application/json');
    $this->response->setContent(json_encode($rows));
    $this->response->send();
  }

  public function empleadoRegistradoAction() {
    $ced = $this->dispatcher->getParam('cedula');
    $nom = $this->dispatcher->getParam('nombres');
    $id = $this->dispatcher->getParam('id');
    $sub = $this->dispatcher->getParam('sub');
    $emp = $this->dispatcher->getParam('emp');
    $nom = str_replace('%20', ' ', $nom);
    $nom = str_replace('  ', ' ', $nom);
    $condicion = "id != :id: AND subscripcion_id = :sub: AND empresa_id = :emp:";
    $params = [ 'id' => $id, 'sub' => $sub, 'emp' => $emp ];
    if (strlen($ced) >= 10) {
      $condicion .= ' AND (cedula = :ced: OR nombres = :nom:)';
      $params += [ 'ced' => $ced, 'nom' => $nom ];
    } else {
      $condicion .= ' AND nombres = :nom:';
      $params += [ 'nom' => $nom ];
    }
    $rows = Empleados::find([
      'conditions' => $condicion,
      'bind' => $params
    ]);
    $this->response->setStatusCode(200, 'Ok');
    $existe = false;
    $res = 'Se puede registrar los nuevos datos';
    if ($rows->count() > 0) {      
      $existe = true;
      $res = 'Estos datos ya estan registrados como ' . $rows[0]->nombres;
      $this->response->setStatusCode(406, 'Not Acceptable');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function empleadoPorCedulaAction() {
    $ced = $this->dispatcher->getParam('cedula');
    $sub = $this->dispatcher->getParam('sub');
    $emp = $this->dispatcher->getParam('emp');
    $ret = (object) [
      'res' => false,
      'cid' => 0,
      'data' => "",
      'msj' => 'No se encontro esta cedula '
    ];
    $rows = Empleados::find([
      'conditions' => 'cedula = :ced: AND subscripcion_id = :sub: AND empresa_id = :emp:',
      'bind' => [ 'ced' => $ced, 'sub' => $sub, 'emp' => $emp ]
    ]);
    if ($rows->count() > 0) {
      $ret->res = true;
      $ret->cid = $rows[0]->id;
      $ret->data = $rows[0];
      $ret->msj = "Empleado registrado";
      $this->response->setStatusCode(200, 'Ok');
    }  else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function empleadoGuardarAction() {
    try {
      $datos = $this->request->getJsonRawBody();
      $ret = (object) [
        'res' => false,
        'cid' => -1,
        'msj' => 'Los datos no se pudieron procesar'
      ];
      $this->response->setStatusCode(406, 'Not Acceptable');
      if ($datos->id > 0) {
        // Traer paciente por id
        $emp = Empleados::findFirstById($datos->id);
        $emp->subscripcion_id = $datos->subscripcion_id;
        $emp->empresa_id = $datos->empresa_id;
        $emp->cedula = $datos->cedula;
        $emp->nombres = $datos->nombres;
        $emp->direccion = $datos->direccion;
        $emp->telefonos = $datos->telefonos;
        $emp->email = $datos->email;
        $emp->cargo_id = $datos->cargo_id;
        $emp->cargo = $datos->cargo;
        $emp->sueldo = $datos->sueldo;
        $emp->entrada_fecha = $datos->entrada_fecha;
        $emp->aseguramiento_fecha = $datos->aseguramiento_fecha;
        $emp->ministerio_fecha = $datos->ministerio_fecha;
        $emp->departamento_id = $datos->departamento_id;
        $emp->referencia = $datos->referencia;
        $emp->estado = $datos->estado;
        if($emp->update()) {
          $ret->res = true;
          $comp = "parcialmete";
          $this->response->setStatusCode(200, 'Ok');
          foreach ($emp->cuentasEliminadas as $celi) {
            $ctaeli = EmpleadosCuentas::findFirstById($celi->id);
            if ($ctaeli != null) {
              $ctaeli->delete();
            }
          }
          foreach ($emp->relCuentas as $cta) {
            $ctaMod = new EmpleadosCuentas();
            if ($cta->id > 0) {
              $ctaMod = EmpleadosCuentas::findFirstById($celi->id);
            }
            $ctaMod->empleadi_id = $emp->id;
            $ctaMod->entidad = $cta->entidad;
            $ctaMod->tipo = $cta->tipo;
            $ctaMod->cuenta = $cta->cuenta;
            $ctaMod->actualizacion = $cta->actualizacion;
            $ctaMod->referencia = $cta->referencia;
            if ($cta->id > 0) {
              $ctaMod->update();
            } else {
              $emp->create();
            }
          }
          $comp = "exitosamente";
          $ret->cid = $emp;
          $ret->msj = "Se actualizo " . $comp . " los datos del registro";
        } else {
          $msj = "Los datos no se puedieron actualizar:" . "\n";
          foreach ($emp->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = -1;
          $ret->msj = $msj;
        }
      } else {   
        $empn = new Empleados();
        $empn->subscripcion_id = $datos->subscripcion_id;
        $empn->empresa_id = $datos->empresa_id;
        $empn->cedula = $datos->cedula;
        $empn->nombres = $datos->nombres;
        $empn->direccion = $datos->direccion;
        $empn->telefonos = $datos->telefonos;
        $empn->email = $datos->email;
        $empn->cargo_id = $datos->cargo_id;
        $empn->cargo = $datos->cargo;
        $empn->sueldo = $datos->sueldo;
        $empn->entrada_fecha = $datos->entrada_fecha;
        $empn->aseguramiento_fecha = $datos->aseguramiento_fecha;
        $empn->ministerio_fecha = $datos->ministerio_fecha;
        $empn->departamento_id = $datos->departamento_id;
        $empn->referencia = $datos->referencia;
        $empn->estado = $datos->estado;   
        if ($empn->create()) {          
          $ret->res = true;
          $comp = "parcialmente";
          $this->response->setStatusCode(201, 'Created');  
          foreach ($empn->relCuentas as $cta) {
            $newCta = new EmpleadosCuentas();
            $newCta->empleadi_id = $empn->id;
            $newCta->entidad = $cta->entidad;
            $newCta->tipo = $cta->tipo;
            $newCta->cuenta = $cta->cuenta;
            $newCta->actualizacion = $cta->actualizacion;
            $newCta->referencia = $cta->referencia;
            $newCta->create();
          }
          $comp = "exitosamente";
          $ret->cid = $empn;
          $ret->msj = "Se guardó " . $comp . " el nuevo registro";
        } else {
          $msj = "No se pudo crear el nuevo registro: " . "\n";
          foreach ($empn->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = -1;
          $ret->msj = $msj;
        }
      }
    } catch (Exception $e) {
      $ret->res = false;
      $ret->cid = -1;
      $ret->msj = $e->getMessage();
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function empleadoModificarEstadoAction() {
    $id = $this->dispatcher->getParam('id');
    $est = $this->dispatcher->getParam('est');
    $res = Empleados::findFirstById($id);
    if ($res != null) {
      $res->estado = $est;
      if($res->update()) {
        $msj = "Estado actualizado";
        $this->response->setStatusCode(200, 'Ok');
      } else {
        $this->response->setStatusCode(404, 'Error');
        $msj = "No se puede actualizar los datos: " . "\n";
        foreach ($res->getMessages() as $m) {
          $msj .= $m . "\n";
        }
      } 
    } else {
      $msj = "No se encontro el registro";
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($msj));
    $this->response->send();
  }
  #endregion

}
