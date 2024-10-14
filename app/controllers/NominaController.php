<?php

namespace Pointerp\Controladores;

use Exception;
use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\Nomina\Registros;
use Pointerp\Modelos\Nomina\Cargos;
use Pointerp\Modelos\Nomina\Rubros;
use Pointerp\Modelos\Nomina\Empleados;
use Pointerp\Modelos\Nomina\EmpleadosCuentas;
use Pointerp\Modelos\Nomina\Movimientos;
use Pointerp\Modelos\Nomina\Roles;
use Pointerp\Modelos\Nomina\RolesMin;
use Pointerp\Modelos\Nomina\RolesEmpleados;
use Pointerp\Modelos\Nomina\RolesRubros;
use Pointerp\Modelos\Nomina\Liquidaciones;

class NominaController extends ControllerBase {
  
  #region Tablas
  public function registrosPorTablaAction() {
    $this->view->disable();
    $tab = $this->dispatcher->getParam('tabla');
    $sub = $this->dispatcher->getParam('sub');
    $emp = $this->dispatcher->getParam('emp');
    $res = Registros::find([
      'conditions' => "subscripcion_id = " . $sub . " AND empresa_id = " . $emp . " AND tabla_id = " . $tab,
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

  public function registroPorTablaIndiceAction() {
    $this->view->disable();
    $tab = $this->dispatcher->getParam('tabla');
    $idx = $this->dispatcher->getParam('indice');
    $sub = $this->dispatcher->getParam('sub');
    $emp = $this->dispatcher->getParam('emp');
    $res = Registros::find([
      'conditions' => "subscripcion_id = " . $sub . " AND empresa_id = " . $emp . " AND tabla_id = " . $tab . " AND indice = " . $idx,
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
    if ($res->count() > 0) {
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

  #region Rubros
  public function rubrosPorEstadoAction() {
    $est = $this->dispatcher->getParam('estado');
    $sub = $this->dispatcher->getParam('sub');
    $emp = $this->dispatcher->getParam('emp');
    $condiciones = "subscripcion_id = " . $sub . " AND empresa_id = " . $emp;
    if ($est == 0) {
      $condiciones .= " AND estado = " . $est;
    }
    $res = Rubros::find([
      'conditions' => $condiciones,
      'order' => 'denominacion'
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

  public function rubroModificarEstadoAction() {
    $id = $this->dispatcher->getParam('id');
    $est = $this->dispatcher->getParam('estado');
    $res = Rubros::findFirstById($id);
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

  public function rubroGuardarAction() {
    try {
      $datos = $this->request->getJsonRawBody();
      $ret = (object) [
        'res' => false,
        'cid' => -1,
        'obj' => null,
        'msj' => 'Los datos no se pudieron procesar'
      ];
      $this->response->setStatusCode(406, 'Not Acceptable');
      if ($datos->id > 0) {
        $rub = Rubros::findFirstById($datos->id);
        $rub->subscripcion_id = $datos->subscripcion_id;
        $rub->empresa_id = $datos->empresa_id;
        $rub->denominacion = $datos->denominacion;
        $rub->origen = $datos->origen;
        $rub->periodo = $datos->periodo;	
        $rub->fecha = $datos->fecha;	
        $rub->formula = $datos->formula;	
        $rub->valor = $datos->valor;	
        $rub->base_indice = $datos->base_indice;	
        $rub->base_valor = $datos->base_valor;	
        $rub->referencia = $datos->referencia;	
        $rub->divisible = $datos->divisible;	
        $rub->meses_aplica = $datos->meses_aplica;
        $rub->estado = $datos->estado;
        if($rub->update()) {
          $ret->res = true;
          $this->response->setStatusCode(200, 'Ok');
          $ret->cid = $rub->id;
          $ret->msj = "Se actualizo exitosamente los datos del registro";
        } else {
          $msj = "Los datos no se puedieron actualizar:" . "\n";
          foreach ($rub->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = -1;
          $ret->msj = $msj;
        }
      } else {
        $rubn = new Rubros();
        $rubn->subscripcion_id = $datos->subscripcion_id;
        $rubn->empresa_id = $datos->empresa_id;
        $rubn->denominacion = $datos->denominacion;
        $rubn->origen = $datos->origen;
        $rubn->periodo = $datos->periodo;	
        $rubn->fecha = $datos->fecha;	
        $rubn->formula = $datos->formula;
        $rubn->valor = $datos->valor;	
        $rubn->base_indice = $datos->base_indice;	
        $rubn->base_valor = $datos->base_valor;	
        $rubn->referencia = $datos->referencia;	
        $rubn->divisible = $datos->divisible;	
        $rubn->meses_aplica = $datos->meses_aplica;
        $rubn->estado = $datos->estado;
        if ($rubn->create()) {
          $ret->res = true;
          $ret->cid = $rubn->id;
          $ret->obj = $rubn;
          $ret->msj = "Se guardó exitosamente el nuevo registro";
          $this->response->setStatusCode(201, 'Created');
        } else {
          $msj = "No se pudo crear el nuevo registro: " . "\n";
          foreach ($rubn->getMessages() as $m) {
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
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
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
    $condicion = "estado != 2 AND id != :id: AND subscripcion_id = :sub: AND empresa_id = :emp:";
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
      $this->response->setStatusCode(200, 'Ok');
    }  else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rows));
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
        $emp->sueldo_seguro = $datos->sueldo_seguro;
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
        $empn->sueldo_seguro = $datos->sueldo_seguro;
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

  #region Movimientos
  public function movimientosBuscarAction() {
    $this->view->disable();
    $sub = $this->dispatcher->getParam('sub');
    $emp = $this->dispatcher->getParam('emp');
    $tipoBusca = $this->dispatcher->getParam('tipo');
    $filtro = $this->dispatcher->getParam('filtro');
    $estado = $this->dispatcher->getParam('estado');
    $clase = $this->dispatcher->getParam('clase');
    $desde = $this->dispatcher->getParam('desde');
    $hasta = $this->dispatcher->getParam('hasta');
    $condicion = "empresa_id = " . $emp . " and subscripcion_id = " . $sub;
    $res = [];
    if ($clase < 2) {
      $condicion .= " AND fecha >= '" . $desde . "' AND fecha <= '" . $hasta . "'";      
    } else {
      if (strlen($filtro) > 0) {
        if ($clase == 2) {
          $filtro = str_replace('%20', ' ', $filtro);
          if ($tipoBusca == 0) {
            // Comenzando por
            $filtro .= '%';
          } else {
            // Conteniendo
            $filtroSP = str_replace('  ', ' ',trim($filtro));
            $filtro = '%' . str_replace(' ' , '%',$filtroSP) . '%';
          }
          $condicion .= " AND descripcion like '" . $filtro . "'";
        } else {
          $condicion .= ' AND numero = ' . $filtro;
        }
      }
    }
    if (strlen($condicion) > 0) {
      $condicion .= ' AND ';
      $condicion .= 'estado != 2';
      $res = Movimientos::find([
        'conditions' => $condicion,
        'order' => 'fecha'
      ]);
    }

    if ($res->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $this->response->setStatusCode(404, 'No se encontraron registros para esta busqueda');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function movimientoGuardarAction() {
    try {
      $datos = $this->request->getJsonRawBody();
      $ret = (object) [
        'res' => false,
        'cid' => -1,
        'msj' => 'Los datos no se pudieron procesar'
      ];
      $this->response->setStatusCode(406, 'Not Acceptable');
      $datos->fecha = str_replace('T', ' ', $datos->fecha);
      $datos->fecha = str_replace('Z', '', $datos->fecha);
      if ($datos->id > 0) {
        // Traer movimiento por id y acualizar
        $mov = Movimientos::findFirstById($datos->id);
        $mov->fecha = $datos->fecha;
        $mov->empleado_id = $datos->empleado_id;
        $mov->descripcion = $datos->descripcion;
        $mov->origen = $datos->origen;
        $mov->valor = $datos->valor;
        $mov->cuotas_numero = $datos->cuotas_numero;
        $mov->cuotas_ejecutadas = $datos->cuotas_ejecutadas;
        $mov->cuotas_inicio = $datos->cuotas_inicio;
        $mov->referencia = $datos->referencia;        
        $mov->estado = $datos->estado;
        if($mov->update()) {
          $ret->res = true;
          $ret->cid = $mov;
          $ret->msj = "Se actualizo correctamente los datos del registro";
          $this->response->setStatusCode(200, 'Ok');
        } else {
          $msj = "No se puede actualizar los datos: " . "\n";
          foreach ($mov->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = -1;
          $ret->msj = $msj;
        }
      } else {
        // Crear movimiento nuevo
        $num = $this->ultimoNumeroMovimiento($datos->tipo, $datos->subscripcion_id, $datos->empresa_id);
        $mov = new Movimientos();
        $mov->numero = $num + 1;
        $mov->subscripcion_id = $datos->subscripcion_id;
        $mov->empresa_id = $datos->empresa_id;
        $mov->tipo = $datos->tipo;
        $mov->fecha = $datos->fecha;
        $mov->empleado_id = $datos->empleado_id;
        $mov->descripcion = $datos->descripcion;
        $mov->origen = $datos->origen;
        $mov->valor = $datos->valor;
        $mov->cuotas_numero = $datos->cuotas_numero;
        $mov->cuotas_ejecutadas = $datos->cuotas_ejecutadas;
        $mov->cuotas_inicio = $datos->cuotas_inicio;
        $mov->referencia = $datos->referencia;        
        $mov->estado = $datos->estado;
        if ($mov->create()) {
          $ret->res = true;
          $ret->cid = $mov;
          $ret->msj = "Se registro correctamente el nuevo movimiento";  
          $this->response->setStatusCode(201, 'Created');
        } else {
          $msj = "No se pudo crear el nuevo registro: " . "\n";
          foreach ($mov->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = 0;
          $ret->msj = $msj;
        }
      }
    } catch (Exception $e) {
      $this->response->setStatusCode(500, 'Error');
      $ret->res = false;
      $ret->cid = 0;
      $ret->msj = $e->getMessage();
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  private function ultimoNumeroMovimiento($tipo, $sub, $emp) {
    return Movimientos::maximum([
      'column' => 'numero',
      'conditions' => 'subscripcion_id = ' . $sub . ' AND empresa_id = ' . $emp
    ]) ?? 0;
  }

  public function movimientoPorIdAction() {
    $id = $this->dispatcher->getParam('id');
    $res = Movimientos::findFirstById($id);
    if ($res != false) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $res = [];
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function movimientoModificarEstadoAction() {
    $id = $this->dispatcher->getParam('id');
    $est = $this->dispatcher->getParam('estado');
    $mov = Movimientos::findFirstById($id);
    if ($mov != false) {
      $mov->estado = $est;
      if($mov->update()) {
        $msj = "La operacion se ejecuto exitosamente";
        $this->response->setStatusCode(200, 'Ok');
      } else {
        $this->response->setStatusCode(404, 'Error');
        $msj = "No se puede actualizar los datos: " . "\n";
        foreach ($mov->getMessages() as $m) {
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

  #region Roles de pago
  public function rolesBuscarAction() {
    $this->view->disable();
    $sub = $this->dispatcher->getParam('sub');
    $emp = $this->dispatcher->getParam('emp');
    $tipoBusca = $this->dispatcher->getParam('tipo');
    $filtro = $this->dispatcher->getParam('filtro');
    $estado = $this->dispatcher->getParam('estado');
    $clase = $this->dispatcher->getParam('clase');
    $desde = $this->dispatcher->getParam('desde');
    $hasta = $this->dispatcher->getParam('hasta');
    $condicion = "empresa_id = " . $emp . " and subscripcion_id = " . $sub;
    $res = [];
    if ($clase < 2) {
      $condicion .= " AND fecha >= '" . $desde . "  0:00:00' AND fecha <= '" . $hasta . "  23:59:59'";
    } else {
      if (strlen($filtro) > 0) {
        if ($clase == 2) {
          $filtro = str_replace('%20', ' ', $filtro);
          if ($tipoBusca == 0) {
            // Comenzando por
            $filtro .= '%';
          } else {
            // Conteniendo
            $filtroSP = str_replace('  ', ' ',trim($filtro));
            $filtro = '%' . str_replace(' ' , '%',$filtroSP) . '%';
          }
          $condicion .= " AND descripcion like '" . $filtro . "'";
        } else {
          $condicion .= ' AND mes = ' . $filtro; // BUSCAMOS POR MES EN LUGAR DE NUMERO
        }
      }
    }
    if (strlen($condicion) > 0) {
      $condicion .= ' AND ';
      $condicion .= 'estado != 2';
      $res = Roles::find([
        'conditions' => $condicion,
        'order' => 'fecha'
      ]);
    }

    if ($res->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $this->response->setStatusCode(404, 'No se encontraron registros para esta busqueda');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function rolesGuardarAction() {
    $datos = $this->request->getJsonRawBody();
    $ret = (object) [
      'res' => false,
      'cid' => -1,
      'msj' => 'Los datos no se pudieron procesar'
    ];
    $this->response->setStatusCode(406, 'Not Acceptable');
    $datos->fecha = str_replace('T', ' ', $datos->fecha);
    $datos->fecha = str_replace('Z', '', $datos->fecha);
    if ($datos->id > 0) {
      $rol = Roles::findFirstById($datos->id);
      $rol->fecha = $datos->fecha;
      $rol->anio = $datos->anio;
      $rol->mes	= $datos->mes;
      $rol->desde	= $datos->desde;
      $rol->hasta	= $datos->hasta;
      $rol->cuenta = $datos->cuenta;
      $rol->referencia = $datos->referencia;
      $rol->fecha	= $datos->fecha;
      $rol->contabilizar = $datos->contabilizar;
      $rol->pagado = $datos->pagado;
      $rol->descripcion = $datos->descripcion;
      $rol->estado = $datos->estado;
      if($rol->update()) {
        $phqle = 'DELETE FROM Pointerp\Modelos\Nomina\RolesEmpleados 
              WHERE rol_id = ' . $datos->id;
        $qrye = new Query($phqle, Di::getDefault());
        $qrye->execute();
        $phqlr = 'DELETE FROM Pointerp\Modelos\Nomina\RolesRubros 
              WHERE rol_id = ' . $datos->id;
        $qryr = new Query($phqlr, Di::getDefault());
        $qryr->execute();
        foreach ($datos->relEmpleados as $re) {
          $ins = new RolesEmpleados();
          $ins->rol_id = $rol->id;
          $ins->empleado_id = $re->empleado_id;
          $ins->remuneracion = $re->remuneracion;
          $ins->referencia = $re->referencia;
          $ins->ingresos = $re->ingresos;
          $ins->egresos = $re->egresos;
          $ins->indice = $re->indice;
          $ins->create();
        }
        foreach ($datos->relRubros as $rb) {
          $ins = new RolesRubros();
          $ins->rol_id = $rol->id;
          $ins->tipo = $rb->tipo;
          $ins->origen = $rb->origen;
          $ins->referencia = $rb->referencia;
          $ins->descripcion = $rb->descripcion;
          $ins->valor = $rb->valor;
          $ins->orden = $rb->orden;
          $ins->denominacion = $rb->denominacion;
          $ins->empleado_id = $rb->empleado_id;
          $ins->ingreso = $rb->ingreso;
          $ins->egreso = $rb->egreso;
          $ins->create();
        }
        $ret->res = true;
        $ret->cid = $rol->id;
        $ret->msj = "Se actualizo correctamente los datos del registro";
        $this->response->setStatusCode(200, $ret->msj);
      } else {
        $msj = "No se puede actualizar los datos: " . "\n";
        foreach ($rol->getMessages() as $m) {
          $msj .= $m . "\n";
        }
        $ret->res = false;
        $ret->cid = -1;
        $ret->msj = $msj;
      }
    } else {
      $rol = new Roles();
      $rol->fecha = $datos->fecha;
      $rol->subscripcion_id	= $datos->subscripcion_id;
      $rol->empresa_id = $datos->empresa_id;
      $rol->anio = $datos->anio;
      $rol->mes	= $datos->mes;
      $rol->desde	= $datos->desde;
      $rol->hasta	= $datos->hasta;
      $rol->cuenta = $datos->cuenta;
      $rol->referencia = $datos->referencia;
      $rol->fecha	= $datos->fecha;
      $rol->contabilizar = $datos->contabilizar;
      $rol->pagado = $datos->pagado;
      $rol->descripcion = $datos->descripcion;
      $rol->estado = $datos->estado;
      if ($rol->create()) {
        foreach ($datos->relEmpleados as $re) {
          $ins = new RolesEmpleados();
          $ins->rol_id = $rol->id;
          $ins->empleado_id = $re->empleado_id;
          $ins->remuneracion = $re->remuneracion;
          $ins->referencia = $re->referencia;
          $ins->ingresos = $re->ingresos;
          $ins->egresos = $re->egresos;
          $ins->indice = $re->indice;
          $ins->create();
        }
        foreach ($datos->relRubros as $rb) {
          $ins = new RolesRubros();
          $ins->rol_id = $rol->id;
          $ins->tipo = $rb->tipo;
          $ins->origen = $rb->origen;
          $ins->referencia = $rb->referencia;
          $ins->descripcion = $rb->descripcion;
          $ins->valor = $rb->valor;
          $ins->orden = $rb->orden;
          $ins->denominacion = $rb->denominacion;
          $ins->empleado_id = $rb->empleado_id;
          $ins->ingreso = $rb->ingreso;
          $ins->egreso = $rb->egreso;
          $ins->create();
        }
        $ret->res = true;
        $ret->cid = $rol->id;
        $ret->msj = "Se registro correctamente el nuevo rol";  
        $this->response->setStatusCode(201, $ret->msj);
      } else {
        $msj = "No se pudo crear el nuevo registro: " . "\n";
        foreach ($rol->getMessages() as $m) {
          $msj .= $m . "\n";
        }
        $ret->res = false;
        $ret->cid = 0;
        $ret->msj = $msj;
      }
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function rolesLiquidarAction() {
    $id = $this->dispatcher->getParam('id');
    $rol = RolesMin::findFirstById($id);
    $ret = (object) [
      'res' => false,
      'cid' => -1,
      'msj' => 'Los datos no se pudieron procesar'
    ];
    $this->response->setStatusCode(406, 'Datos invalidos');
    if ($rol->id > 0) {
      $movs = RolesRubros::find([
        'conditions' => 'rol_id = ' . $id . " AND origen = 3"
      ]);
      foreach ($movs as $m) {
        $movMod = Movimientos::findFirstById($m->referencia);
        $est = 1;
        if ($movMod->cuotas_numero > 1) {
          $movMod->cuotas_ejecutadas += 1;
          if ($movMod->cuotas_numero > $movMod->cuotas_ejecutadas) {
            $est = 0;
          }
        }
        $movMod->estado = $est;
        $movMod->update();
      }

      $phql = 'SELECT SUM(ingreso) as ings, SUM(egreso) as egrs FROM Pointerp\Modelos\Nomina\RolesRubros 
        WHERE rol_id = ' . $id;
      $qry = new Query($phql, Di::getDefault());
      $rws = $qry->execute();
      if ($rws->count() === 1) {
        $sumas = $rws->getFirst();
        try {
          $ings = doubleval($sumas['ings']);
          $egrs = doubleval($sumas['egrs']);
          $rol->pagado = $ings - $egrs;
        } catch(Exception $ex) {}
      }

      $rol->estado = 1;
      if($rol->update()) {
        $ret->res = true;
        $ret->cid = $id;
        $ret->msj = "Se completó exitosamente la liquidacion del rol";
        $this->response->setStatusCode(200, 'Ok');
      }
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function rolesModificarEstadoAction() {
    $id = $this->dispatcher->getParam('id');
    $est = $this->dispatcher->getParam('estado');
    $mov = Roles::findFirstById($id);
    if ($mov != false) {
      $mov->estado = $est;
      if($mov->update()) {
        $msj = "La operacion se ejecuto exitosamente";
        $this->response->setStatusCode(200, 'Ok');
      } else {
        $this->response->setStatusCode(404, 'Error');
        $msj = "No se puede actualizar los datos: " . "\n";
        foreach ($mov->getMessages() as $m) {
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

  public function rubrosPeriodoAction() {
    $todosLosMeses = 0;
    $mesEspecifico = 1;
    $valorFraccion = 0;
    $bsueldo = 1;
    $bseguro = 2;

    $emp = $this->dispatcher->getParam('emp');
    $sub = $this->dispatcher->getParam('sub');
    $año = $this->dispatcher->getParam('anio');
    $mes = $this->dispatcher->getParam('mes');

    $smesDesde = $mes < 10 ? "0" . strval($mes) : strval($mes);
    $desde = date('Y-m-d', strtotime(strval($año) . "-" . $smesDesde . "-01"));
    $d = new \DateTime($desde); 
    $hasta = $d->format('Y-m-t');
    $retorno = "...";
    $rubros = [];
    $empleados = [];

    // traer RBU
    $rbus = Registros::find([
      'conditions' => "subscripcion_id = " . $sub . " AND tabla_id = 1",
      'order' => 'empresa_id'
    ]);
    $rbu = $rbus->count() > 0 ? $rbus[0]->valor : 0;

    // traer empleados
    $emps = Empleados::find([
      'conditions' => 'empresa_id = :emp: AND subscripcion_id = :sub: AND estado = 0',
      'bind' => [ 'emp' => $emp, 'sub' => $sub ],
      'order' => 'nombres'
    ]);
    if ($emps->count() > 0) {
      // sueldos
      foreach ($emps as $e) {
        $rsueldo = (object) [
          'id' => 0,
          'rol_id' => 0,
          'tipo'	=> 0,
          'origen'	=> 1,
          'referencia'	=> 0,
          'descripcion'	=> 'SUELDO',
          'valor'	=> 0,
          'orden'	=> 0,
          'denominacion' => 'SUELDO',
          'empleado_id'	=> $e->id,
          'ingreso'	=> $e->sueldo,
          'egreso' => 0
        ];
        $rolemp = (object) [
          'id' => 0,
          'rol_id'	=> 0,
          'empleado_id'	=> $e->id,
          'remuneracion' => $e->sueldo,
          'referencia' => 0,
          'ingresos' => 0,
          'egresos' => 0,
          'indice' => 0,
          'relEmpleado' => $e
        ];
        array_push($rubros, $rsueldo);
        array_push($empleados, $rolemp);
      }
    }

    // otros rubros
    $condicionrub = "empresa_id = " . $emp . " AND subscripcion_id = " . $sub;
    $condicionrub .= " AND estado = 0";
    $rubs = Rubros::find([
      'conditions' => $condicionrub
    ]);
    foreach ($rubs as $r) {
      $agregar = $r->relPeriodo->valor == $todosLosMeses || 
        ($r->relPeriodo->valor == $mesEspecifico && $r->referencia == $mes);      
      if ($agregar) {
        foreach ($emps as $er) {
          $val = $r->valor;
          if ($r->relFormula->valor == $valorFraccion) {
            $base = $r->base_indice == $bsueldo ? $er->sueldo : ($r->base_indice == $bseguro ? $er->sueldo_seguro : $rbu);
            if ($base > 0 && $val > 0) {
              $val = (doubleval($base) * doubleval($val)) / 100;
            } else {
              $val = 0;
            }
            if ($r->divisible == 1 && $r->relPeriodo->valor == $mesEspecifico) {
              // calcular dias completados
              if ($er->entrada_fecha == null) {
                $val = 0;
              } else {
                $fechaAtras = date('Y-m-d', strtotime('-' . $r->base_valor . ' days', strtotime($hasta)));                
                if ($er->entrada_fecha > $fechaAtras) {
                  $fh = new \DateTime($hasta);
                  $dif = $fh->diff(new \DateTime($er->entrada_fecha));
                  if ($dif->days) {
                    $fraccion = $dif->days / doubleval($r->base_valor);
                    $retorno = $fraccion;
                    $val = $val * $fraccion;
                  }
                }
              }
            }
          }
          if ($val > 0) {
            $ing = $r->relOrigen->valor > 0 ? $val : 0;
            $egr = $r->relOrigen->valor < 0 ? $val : 0;
            $ins = (object) [
              'id' => 0,
              'rol_id' => 0,
              'tipo'	=> 0,
              'origen'	=> 2,
              'referencia'	=> $r->id,
              'descripcion'	=> 'RUBROS PREDEFINIDOS',
              'valor'	=> 0,
              'orden'	=> 0,
              'denominacion' => $r->denominacion,
              'empleado_id'	=> $er->id,
              'ingreso'	=> $ing,
              'egreso' => $egr
            ];
            array_push($rubros, $ins);
          }
        }        
      }
    }

    // movimientos
    $condicionmov = "empresa_id = " . $emp . " and subscripcion_id = " . $sub;
    $condicionmov .= " AND tipo = 0 AND estado = 0";
    $movs = Movimientos::find([
      'conditions' => $condicionmov,
      'order' => 'fecha'
    ]);
    foreach ($movs as $mov) {
      $val = $mov->valor;
      $cuotaNo = "";
      if ($mov->cuotas_inicio != null && $mov->cuotas_inicio <= $hasta) {
        if ($mov->cuotas_numero > 1) {
          $val = $val / $mov->cuotas_numero;
          $cuotaNo = " (" . strval($mov->cuotas_ejecutadas + 1) . "/" . strval($mov->cuotas_numero) . ")";
        }
      }
      $ing = $mov->relOrigen->valor > 0 ? $val : 0;
      $egr = $mov->relOrigen->valor < 0 ? $val : 0;
      $rmov = (object) [
        'id' => 0,
        'rol_id' => 0,
        'tipo'	=> 0,
        'origen'	=> 3,
        'referencia'	=> $mov->id,
        'descripcion'	=> $mov->descripcion,
        'valor'	=> 0,
        'orden'	=> 0,
        'denominacion' => 'TRNS. #' . strval($mov->numero) . " (" . $mov->descripcion . $cuotaNo . ")",
        'empleado_id'	=> $mov->empleado_id,
        'ingreso'	=> $ing,
        'egreso' => $egr
      ];
      array_push($rubros, $rmov);
    }

    $res = (object) [
      'empleados' => $empleados,
      'rubros' => $rubros,
    ];
    if ($rubs->count() > 0) {
      $this->response->setStatusCode(200, 'Ok ' . json_encode($retorno));
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }
  #endregion

  #region liquidaciones

  #endregion
}
