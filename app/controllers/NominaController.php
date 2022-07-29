<?php

namespace Pointerp\Controladores;

use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\Nomina\Registros;
use Pointerp\Modelos\Nomina\Cargos;
use Pointerp\Modelos\Nomina\Empleados;

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
    if ($est > 0) {
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
  #endregion

  #region Empleados
  public function empleadoPorIdAction() {
    $id = $this->dispatcher->getParam('id');
    $res = Empleados::findFirstById($id);
    if (count($res) > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function empleadosBuscarAction() {
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
    $condicion = 'empresa_id = :emp: AND subscripcion_id = :sub:';
    if ($atrib != 'nombres') {
      $condicion .= $atrib . ' = :fil:';
    } else {
      $filtroSP = strtoupper($filtroSP);
      $filtro = '%' . str_replace(' ' , '%', $filtroSP) . '%';
      $condicion .= 'UPPER('.$atrib.') LIKE :fil:';
    }
    if ($estado == 0) { // Estado = 1: motrar todos
        $condicion .= ' AND estado = 0';
    }
    $rows = Empleados::find([
      'conditions' => $condicion,
      'bind' => [ 'fil' => $filtro, 'emp' => $emp, 'sub' => $sub ]
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

  public function empleadoRegistradoAction() {
    $ced = $this->dispatcher->getParam('ced');
    $nom = $this->dispatcher->getParam('nom');
    $id = $this->dispatcher->getParam('id');
    $sub = $this->dispatcher->getParam('sub');
    $emp = $this->dispatcher->getParam('emp');
    $nom = str_replace('%20', ' ', $nom);
    $params = [ 'nom' => $nom, 'sub' => $sub, 'emp' => $emp ];
    $condicion = 'nombres = :nom: AND subscripcion_id = :sub: AND empresa_id = :emp';
    if (strlen($ced) >= 10) {
      $condicion = 'cedula = :ced: OR ' . $condicion;
      $params += [ 'ced' => $ced ];
    }
    $rows = Empleados::find([
      'conditions' => $condicion,
      'bind' => $params
    ]);
    $existe = false;
    $res = 'Se puede registrar los nuevos datos';
    if ($rows->count() > 0) {      
      $existe = true;
      $res = 'Estos datos ya estan registrados como ' . $rows[0]->nombres;
      $this->response->setStatusCode(406, 'Not Acceptable');
    }
    if (!$existe) {
      $this->response->setStatusCode(200, 'Ok');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function empleadoPorCedulaAction() {
    $ced = $this->dispatcher->getParam('ced');
    $sub = $this->dispatcher->getParam('sub');
    $emp = $this->dispatcher->getParam('emp');
    $ret = (object) [
      'res' => false,
      'cid' => 0,
      'data' => "",
      'msj' => 'No se encontro esta cedula'
    ];
    $rows = Empleados::find([
      'conditions' => 'cedula = :ced: AND subscripcion_id = :sub: AND empresa_id = :emp:',
      'bind' => [ 'ced' => $ced, 'sub' => $sub, 'emp' => $emp ]
    ]);
    if ($rows->count() > 0) {
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

  public function empleadoCedulaRegistradaAction() {
    $ced = $this->dispatcher->getParam('ced');
    $id = $this->dispatcher->getParam('id');
    $rows = Clientes::find([
      'conditions' => 'identificacion = :ced: and id != :id:',
      'bind' => [ 'id' => $id, 'ced' => $ced ]
    ]);
    $ret = (object) [
      'res' => false,
      'cid' => 0,
      'data' => "",
      'msj' => 'No se encontro esta cedula'
    ];
    if ($rows->count() > 0) {
      $ret->res = true;
      $ret->cid = $rows[0]->id;
      $ret->data = $rows[0];
      $ret->msj = 'Este numero de cedula ya esta registrado';
      $this->response->setStatusCode(406, 'Not Acceptable');
    } else {
      $this->response->setStatusCode(200, 'Ok');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function empleadoCrearAction() {
    $datos = $this->request->getJsonRawBody();
    $con = new Empleados();
    $res = $this->guardarDatos($con, $datos, true);
    $msj = 'Los datos se registraron correctamente';
    if (false === $res) {
        $this->response->setStatusCode(500, 'Internal Server Error');
        $msj = 'No se puede registrar los datos';
    } else {
        $this->response->setStatusCode(201, 'Created');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode("Paciente creado"));
    $this->response->send();
  }

  public function empleadoGuardarAction() {
    //$msg = "Procesando registro";
    try {
      $datos = $this->request->getJsonRawBody();
      $ret = (object) [
        'res' => false,
        'cid' => $datos->id,
        'msj' => 'Los datos no se pudieron procesar'
      ];
      $this->response->setStatusCode(406, 'Not Acceptable');
      if ($datos->id > 0) {
        // Traer paciente por id
        $pac = Empleados::findFirstById($datos->id);
        if (strlen($datos->fecha_nacimiento) > 0) $pac->fecha_nacimiento = $datos->fecha_nacimiento;
        $pac->sexo = $datos->sexo;
        $pac->estado_civil = $datos->estado_civil;
        $pac->grupo_sanguineo = $pac->grupo_sanguineo;
        $pac->estado = $pac->estado;
        if($pac->update()) {
          // Actualizar datos de cliente
          $cli = Clientes::findFirstById($datos->relCliente->id);
          if (strlen($datos->relCliente->identificacion) > 0) $cli->identificacion = $datos->relCliente->identificacion;
          if ($datos->relCliente->identificacion_tipo > 0) $cli->identificacion_tipo = $datos->relCliente->identificacion_tipo;
          if (strlen($datos->relCliente->direccion) > 0) $cli->direccion = $datos->relCliente->direccion;
          if (strlen($datos->relCliente->telefonos) > 0) $cli->telefonos = $datos->relCliente->telefonos;
          if (strlen($datos->relCliente->representante_nom) > 0) $cli->representante_nom = $datos->relCliente->representante_nom;
          if (strlen($datos->relCliente->representante_ced) >0) $cli->representante_ced = $datos->relCliente->representante_ced;
          if (strlen($datos->relCliente->email) >0) $cli->email = $datos->relCliente->email;
          $cli->nombres = $datos->relCliente->nombres;
          if ($cli->update()) {
            $ret->res = true;
            $ret->cid = $datos->id;
            $ret->msj = "Se actualizo correctamente los datos del paciente";
            $this->response->setStatusCode(200, 'Ok');
          } else {
            $msj = "Los datos se actualizaron parcialmente" . "\n";
            foreach ($cli->getMessages() as $m) {
              $msj .= $m . "\n";
            }
            $ret->res = false;
            $ret->cid = $datos->id;
            $ret->msj = $msj;
          }
          
        } else {
          $msj = "No se puede actualizar los datos: " . "\n";
          foreach ($pac->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = $datos->id;
          $ret->msj = $msj;
        }
      } else {
        // Buscar o crear cliente
        $cliret = $this->clienteBuscarCrear($datos->relCliente);
        if ($cliret->res) {
          // Crear nuevo paciente
          $pac = new Pacientes();
          $pac->id = 0;
          $pac->cliente_id = $cliret->cid;
          $pac->foto = '';
          $pac->fecha_nacimiento = $datos->fecha_nacimiento;
          $pac->sexo = $datos->sexo;
          $pac->estado_civil = $datos->estado_civil;
          $pac->grupo_sanguineo = $datos->grupo_sanguineo;
          $pac->alergias = '';
          $pac->antecedentes_familiares = '';
          $pac->antecedentes_personales = '';
          $pac->estado = 0;
          if ($pac->create()) {
            $ret->res = true;
            $ret->cid = $pac->id;
            $ret->msj = "Se registro correctamente el nuevo paciente";
            $this->response->setStatusCode(201, 'Created');  
          } else {
            $msj = "No se pudo crear el nuevo paciente: " . "\n";
            foreach ($pac->getMessages() as $m) {
              $msj .= $m . "\n";
            }
            $ret->res = false;
            $ret->cid = 0;
            $ret->msj = $msj;
          }
        } else {
          $ret->res = false;
          $ret->cid = 0;
          $ret->msj = $cliret->msj;
        }
      }
    } catch (Exception $e) {
      $ret->res = false;
      $ret->cid = 0;
      $ret->msj = $e->getMessage();
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function pacienteModificarEstadoAction() {
    $id = $this->dispatcher->getParam('id');
    $est = $this->dispatcher->getParam('est');
    $res = Pacientes::findFirstById($id);
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
      $msj = "No se encontro el servicio";
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($msj));
    $this->response->send();
  }

  private function clienteBuscarCrear($cli) {    
    $ced = $cli->identificacion;
    $nom = $cli->nombres;
    $params = [];
    $params += [ 'nom' => $nom ];
    $condicion = 'nombres = :nom:';
    if (strlen($ced) >= 10) {
      $condicion = 'identificacion = :ced: OR ' . $condicion;
      $params += [ 'ced' => $ced ];
    }
    $rows = Clientes::find([
      'conditions' => $condicion,
      'bind' => $params
    ]);
    $ret = (object) [
      'res' => false,
      'cid' => -1,
      'msj' => 'Cliente no procesado'
    ];
    if ($rows->count() > 0) {
      $id = $rows[0]->id;
      $enc = Clientes::findFirstById($id);
      $mods = 0;
      if ($enc != null) {
        $enc->nombres = $cli->nombres;
        if (strlen($cli->identificacion)) { $enc->identificacion = $cli->identificacion; $mods++; }
        if ($cli->identificacion_tipo > 0) { $enc->identificacion_tipo = $cli->identificacion_tipo; $mods++; }
        if (strlen($cli->direccion)) { $enc->direccion = $cli->direccion; $mods++; }
        if (strlen($cli->telefonos)) { $enc->telefonos = $cli->telefonos; $mods++; }
        if (strlen($cli->representante_nom)) { $enc->representante_nom = $cli->representante_nom; $mods++; }
        if (strlen($cli->representante_ced)) { $enc->representante_ced = $cli->representante_ced; $mods++; }
        if (strlen($cli->email)) { $enc->email = $cli->email; $mods++; }
        if ($mods > 0) {
          if ($enc->update()) {
            $ret->res = true;
            $ret->cid = $enc->id;
            $ret->msj = 'Se actualizo correctamente los datos del cliente';
          } else {
            $msj = "No se pudo actualizar:" . "\n";
            foreach ($enc->getMessages() as $m) {
              $msj .= $m . "\n";
            }
            $ret->res = false;
            $ret->cid = $enc->id;
            $ret->msj = $msj;
          }
        } else {
          $ret->res = true;
          $ret->cid = $enc->id;
          $ret->msj = 'No se requieren actualizaciones';
        }
      }
    } else {
      // Creara y devolver el id creado
      // Traer codigo automatico Select valor from registros where tabla_id = 2 and indice = 1
      $cod = "000";
      $rid = 0;
      $rows = Registros::find([
        'conditions' => 'tabla_id = 2 and indice = 1'
      ]);
      if ($rows->count() > 0) {
        $rid = $rows[0]->id;
        $num = $rows[0]->valor + 1;
        $cod .= $num;
      }
      $nuevo = new Clientes();
      $nuevo->empresa_id = $cli->empresa_id;
      $nuevo->codigo = $cod;
      $nuevo->identificacion = $cli->identificacion;
      $nuevo->identificacion_tipo = $cli->identificacion_tipo;
      $nuevo->nombres = $cli->nombres;
      $nuevo->direccion = $cli->direccion;
      $nuevo->telefonos = $cli->telefonos;
      $nuevo->email = $cli->email;
      $nuevo->representante_nom = $cli->representante_nom;
      $nuevo->representante_ced = $cli->representante_ced;
      $nuevo->cupo = 0;
      $nuevo->estado = 0;
      if($nuevo->create()) {
        $reg = Registros::findFirstById($rid);
        if ($reg != null) {
          $reg->valor++;
          $reg->update();
        }
        $ret->res = true;
        $ret->cid = $nuevo->id;
        $ret->msj = 'Cliente creado exitosamente';
      } else {
        $msj = "";
        foreach ($nuevo->getMessages() as $m) {
          $msj .= $m . "\n";
        }
        $ret->res = false;
        $ret->cid = 0;
        $ret->msj = $msj;
      }
    }
    return $ret;
  }

  #endregion
}
