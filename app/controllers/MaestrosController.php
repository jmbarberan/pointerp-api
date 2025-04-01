<?php
//declare(strict_types=1);

namespace Pointerp\Controladores;

use Exception;
use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\ClientesSri;
use Pointerp\Modelos\Maestros\Impuestos;
use Pointerp\Modelos\SubscripcionesEmpresas;
use Pointerp\Modelos\Maestros\Clientes;
use Pointerp\Modelos\Maestros\Proveedores;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;

class MaestrosController extends ControllerBase  {
  
  #region Clientes
  public function clientePorIdAction() {
    $this->view->disable();
    $id = $this->dispatcher->getParam('id');
    $res = Clientes::findFirstById($id);

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
  public function clientesPorCedulaAction() {
    $this->view->disable();
    $filtroEmp = '';
    $paramsEmp = [];
    try {
      $emp = $this->request->getQuery('emp', null, 0);
      if ($emp > 0) {
        $filtroEmp = 'EmpresaId = :emp: AND ';
        $paramsEmp = [ 'emp' => $emp ];
      }
    } catch (Exception $ex) {
      $errorMsg = $ex;
    }
    $ced = $this->dispatcher->getParam('ced');
    $rows = Clientes::find([
      'conditions' => "{$filtroEmp} Identificacion = :ced: AND Estado != 2",
      'bind' => [ 'ced' => $ced, ...$paramsEmp ]
    ]);
    if ($rows->count() > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $rows = Clientes::find([
        'conditions' => 'Codigo = :ced: AND Estado != 2',
        'bind' => [ 'ced' => $ced ]
      ]);
      if ($rows->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
      } else {
        $nombres = $this->buscarCedulaExterno($ced);
        if ($nombres != '') {
          $cli = new Clientes();
          $cli->Id = 0;
          $cli->Cedula = $ced;
          $cli->Nombres = $nombres;
          $cli->Estado = 0;
        }
        $rows = [
          $cli
        ];
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
    try {
      $page = $this->request->getQuery('page', null, 0);
      $limit = $this->request->getQuery('limit', null, 0);
      $order = $this->request->getQuery('order', null, 'Nombres');
      $orderDir = $this->request->getQuery('dir', null, '');
      $externo = $this->request->getQuery('ext', null, false);
    } catch (Exception $ex) {
      $page = 0;
      $limit = 0;
      $order = 'Nombres';
      $orderDir = '';
      $externo = false;
    }

    $condicion = '';
    $params = [];
    $ex = $this->subscripcion['id'];
    if ($this->subscripcion['exclusive'] === 1) {
      if ($this->subscripcion['sharedemps'] != 1) {
        $condicion = 'EmpresaId = :emp:';
        $params = [ 'emp' => $emp ];
      }
    } else {
      $emps = SubscripcionesEmpresas::find([
        'conditions' => 'subscripcion_id = ' . $this->subscripcion['id']
      ]);
      foreach ($emps as $e) {
        $condicion .= strlen($condicion) > 0 ? ", {$e->empresa_id}" : strval($e->empresa_id);
      }
      $condicion = strlen($condicion) > 0 ? "EmpresaId in ({$condicion})" : '';
    }
    
    $condicion .= strlen($condicion) > 0 ? " AND " : "";
    if ($atrib != 'Nombres') {
      if (is_numeric($atrib)) {
        if ($atrib != 0) {
          $atribNom = $atrib == 1 ? 'Identificacion' : 'Codigo';
          $condicion .= "{$atribNom} = :fil:";  
        } else {
          $filtroSP = strtoupper($filtroSP);
          $filtro = '%' . str_replace(' ' , '%', $filtroSP) . '%';
          $condicion .= "UPPER(Nombres) LIKE :fil:";
        }
      } else {
        $condicion .= "{$atrib} = :fil:";
      }
    } else {    
      $filtroSP = strtoupper($filtroSP);
      $filtro = '%' . str_replace(' ' , '%', $filtroSP) . '%';
      $condicion .= "UPPER({$atrib}) LIKE :fil:";
    }
    $params = array_merge([ 'fil' => $filtro ], $params);
    if ($estado == 0) { // Estado = 1: motrar todos
        $condicion .= ' AND Estado = :est:';
        $params = array_merge([ 'est' => 0 ], $params);
    }

    /*$rows = Clientes::find([
      'conditions' => $condicion,
      'bind' => $params
    ]);
    if ($rows->count() > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      if ($atrib == 'Cedula') {
        $nombres = $this->buscarCedulaExterno($filtro);
        if ($nombres != '') {
          $cli = new Clientes();
          $cli->Id = 0;
          $cli->Cedula = $filtro;
          $cli->Nombres = $nombres;
          $cli->Estado = 0;
        }
        $rows = [
          $cli
        ];
      }
      $this->response->setStatusCode(404, 'Not found');
    }*/

    $hasData = false;
    $clis = [];
    if ($page > 0 && $limit > 0) {
      
      $paginator = new PaginatorModel([
        "model"      => Clientes::class,
        "parameters" => [
          'conditions' => $condicion,
          'bind' => $params,
          'order' => ($order ?? 'Nombres') . " {$orderDir}"
        ],
        "limit"      => $limit,
        "page"       => $page,
      ]);
      $pageData = $paginator->paginate();
      $clis = (object) [
        'completo' => true,
        'total' => $pageData->getTotalItems(),
        'items' => $pageData->getItems()
      ];
      $hasData = $pageData->getTotalItems() > 0;      
    } else {
      $clis = Clientes::find([
        'conditions' => $condicion,
        'bind' => $params,
        'order' => $order ?? 'Nombres'
      ]);
      $hasData = $clis->count() > 0;
    }
    

    if ($hasData) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($clis));
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

  public function clienteGuardarAction() {
    $datos = $this->request->getJsonRawBody();
    $ret = (object) [
      'res' => false,
      'cid' => $datos->Id,
      'msj' => 'Los datos no se pudieron procesar'
    ];
    try {
      $newcli = new Clientes();
      if ($datos->Id > 0) {
        $newcli = Clientes::findFirstById($datos->Id);
      }
      $newcli->EmpresaId = $datos->EmpresaId;
      $newcli->Codigo = $datos->Codigo;
      $newcli->Identificacion = $datos->Identificacion;
      $newcli->IdentificacionTipo = $datos->IdentificacionTipo;
      $newcli->Nombres = $datos->Nombres;
      $newcli->Representante = $datos->Representante;
      $newcli->Direccion = $datos->Direccion;
      $newcli->Telefonos = $datos->Telefonos;
      $newcli->Email = $datos->Email;
      $newcli->Ciudad = $datos->Ciudad;
      $newcli->CiudadId = $datos->CiudadId;
      $newcli->Referencias = $datos->Referencias ?? '';
      $newcli->Cupo = $datos->Cupo ?? 0;
      $newcli->Estado = $datos->Estado;
      if ($datos->Id > 0) {
        if ($newcli->update()) {
          $this->response->setStatusCode(201, 'Ok');  
          $ret->res = true;
          $ret->cid = $newcli->Id;
          $ret->msj = "Cliente guardado exitosamente";
        } else {
          $this->response->setStatusCode(500, 'Error');  
          $msj = "No se pudo actualizar el cliente: \n";
          foreach ($newcli->getMessages() as $m) {
            $msj .= "{$m} \n";
          }
          $ret->res = false;
          $ret->cid = 0;
          $ret->msj = $msj;
        }
      } else {
        $newcod = $datos->Codigo;
        if (strlen($datos->Codigo) <= 0) {
          $di = Di::getDefault();
          $phql = "SELECT MAX(Codigo) as maxcod FROM Pointerp\Modelos\Maestros\Clientes 
              WHERE Estado = 0 AND EmpresaId = {$datos->EmpresaId}";
          $qry = new Query($phql, $di);
          $rws = $qry->execute();
          if ($rws->count() === 1) {
            $rmax = $rws->getFirst();
            try {
              $num = intval($rmax['maxcod']);
            } catch (Exception $e) {
              $num = 0;
            }
          }
          
          $num = $num == 0 ? 1000 : $num + 1;
          $newcod = strval($num);
        }
        $newcli->Codigo = $newcod;
        if ($newcli->create()) {
          $this->response->setStatusCode(201, 'Ok');  
          $ret->res = true;
          $ret->cid = $newcli->Id;
          $ret->msj = "Cliente creado exitosamente";
        } else {
          $this->response->setStatusCode(500, 'Error');  
          $msj = "No se pudo crear el nuevo cliente: \n";
          foreach ($newcli->getMessages() as $m) {
            $msj .= "{$m} \n";
          }
          $ret->res = false;
          $ret->cid = 0;
          $ret->msj = $msj;
        }
      }
    } catch (Exception $ex) {
      $this->response->setStatusCode(500, 'Error');  
      $ret->res = false;
      $ret->cid = 0;
      $ret->msj = $ex->getMessage();
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function buscarCedulaSRIAction() {
    $ident = $this->dispatcher->getParam('identificacion');
    $nombre = $this->buscarCedulaExterno($ident);
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($nombre));
    $this->response->send();
  }

  private function buscarCedulaExterno($cedula) {
    $nombre = '';
    $url = getenv('URL_SRIQRY_BASE') . $cedula . getenv('URL_SRIQRY_PARAMS');
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $nombre = 'Error de red';
    }
    curl_close($ch);
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $nombre = 'Error de conversion';
    }
    $nombreComercial = isset($data['contribuyente']['nombreComercial']) ? $data['contribuyente']['nombreComercial'] : null;
    if ($nombreComercial !== null) {
        $nombre = $nombreComercial;
    }
    return $nombre;
  }

  public function clienteCambiarEstadoAction() {
    $result = (Object) [
      "completo" => false,
      "mensaje" => "La operación no se pudo completar"
    ];
    $id     = $this->request->getQuery('id', null, 0);
    $activo = $this->request->getQuery('activo', null, true);
    $this->response->setStatusCode(422, 'Unprocessable Content');
    $cliente = Clientes::findFirstById($id);
    if ($cliente) {
      $cliente->Estado = $activo == "true" ? 0 : 2;
      if($cliente->update()) {
        $result->completo = true;
        $result->mensaje = "Registro actualizado exitosamente";
        $this->response->setStatusCode(201, 'Ok');
      } else {
        $result->mensaje = "Error al intentar modificar el cliente";
      }
    } else {
      $result->mensaje = "No se encontro el cliente";
      $this->response->setStatusCode(404, 'Not found');
    }

    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($result));
    $this->response->send();
  }
  #endregion

  #region Proveedores
  public function proveedoresBuscarAction() {
    $estado = $this->dispatcher->getParam('estado');
    $filtro = $this->dispatcher->getParam('filtro');
    $atrib = $this->dispatcher->getParam('atrib');
    $emp = $this->dispatcher->getParam('emp');
    $filtroSP = str_replace('%20', ' ', $filtro);
    $filtroSP = str_replace('  ', ' ',trim($filtroSP));
    // eñes
    $filtroSP = str_replace('%C3%91' , 'Ñ',$filtroSP);
    $filtroSP = str_replace('%C3%B1' , 'ñ',$filtroSP);
    try {
      $page = $this->request->getQuery('page', null, 0);
      $limit = $this->request->getQuery('limit', null, 0);
      $order = $this->request->getQuery('order', null, 'Nombres');
      $orderDir = $this->request->getQuery('dir', null, '');
      $externo = $this->request->getQuery('ext', null, false);
    } catch (Exception $ex) {
      $page = 0;
      $limit = 0;
      $order = 'Nombres';
      $orderDir = '';
      $externo = false;
    }

    // Filtros
    $condicion = '';
    $params = [];
    $ex = $this->subscripcion['id'];
    if ($this->subscripcion['exclusive'] === 1) {
      if ($this->subscripcion['sharedemps'] != 1) {
        $condicion = 'EmpresaId = :emp:';
        $params = [ 'emp' => $emp ];
      }
    } else {
      $emps = SubscripcionesEmpresas::find([
        'conditions' => 'subscripcion_id = ' . $this->subscripcion['id']
      ]);
      foreach ($emps as $e) {
        $condicion .= strlen($condicion) > 0 ? ", {$e->empresa_id}" : strval($e->empresa_id);
      }
      $condicion = strlen($condicion) > 0 ? "EmpresaId in ({$condicion})" : '';
    }
    
    $condicion .= strlen($condicion) > 0 ? " AND " : "";
    if ($atrib != 'Nombre') {
      $condicion .= "{$atrib} = :fil:";
    } else {
      $filtroSP = strtoupper($filtroSP);
      $filtro = '%' . str_replace(' ' , '%', $filtroSP) . '%';
      $condicion .= "UPPER({$atrib}) LIKE :fil:";
    }
    $params = array_merge([ 'fil' => $filtro ], $params);
    if ($estado == 0) { // Estado = 1: motrar todos
        $condicion .= ' AND Estado = :est:';
        $params = array_merge([ 'est' => 0 ], $params);
    }

    $hasData = false;
    $paginator = new PaginatorModel([
      "model"      => Proveedores::class,
      "parameters" => [
        'conditions' => $condicion,
        'bind' => $params,
        'order' => ($order ?? 'Nombre') . " {$orderDir}"
      ],
      "limit"      => $limit,
      "page"       => $page,
    ]);
    $pageData = $paginator->paginate();
    $prvs = (object) [
      'completo' => true,
      'total' => $pageData->getTotalItems(),
      'items' => $pageData->getItems()
    ];
    $hasData = $pageData->getTotalItems() > 0;    
    

    if ($hasData) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($prvs));
    $this->response->send();
  }

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

  public function proveedorExisteAction() {
    $id = $this->dispatcher->getParam('id');
    $cedula = $this->dispatcher->getParam('cedula');
    $nombre = $this->dispatcher->getParam('nombre');

    $rows = Proveedores::find([
      'conditions' => 'Identificacion = :ced: OR Nombre = :nom: OR Id != :id:',
      'bind' => [ 'ced' => $cedula, 'nom' => $nombre, 'id' => $id ]
    ]);
    $this->response->setStatusCode(200, 'Ok');
    $result = [ 'existe' => $rows->count() > 0 ];
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($result));
    $this->response->send();
  }

  public function proveedorCambiarEstadoAction() {
    $estado = $this->dispatcher->getParam('estado');
    $id = $this->dispatcher->getParam('id');
    $result = (Object) [
      "completo" => false,
      "mensaje" => "La operación no se pudo completar"
    ];
    $this->response->setStatusCode(422, 'Unprocessable Content');
    $proveedor = Proveedores::findFirstById($id);
    if ($proveedor) {
      $proveedor->Estado = $estado;
      if($proveedor->update()) {
        $result->completo = true;
        $result->mensaje = "Registro actualizado exitosamente";
        $this->response->setStatusCode(201, 'Ok');
      } else {
        $result->mensaje = "Error al intentar modificar el cliente";
      }
    } else {
      $result->mensaje = "No se encontro el cliente";
      $this->response->setStatusCode(404, 'Not found');
    }

    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($result));
    $this->response->send();
  }

  public function proveedorGuardarAction() {
    $datos = $this->request->getJsonRawBody();
    $ret = (object) [
      'res' => false,
      'cid' => $datos->Id,
      'msj' => 'Los datos no se pudieron procesar'
    ];
    try {
      $newPrv = new Proveedores();
      if ($datos->Id > 0) {
        $newPrv = Proveedores::findFirstById($datos->Id);
      }
      $newPrv->EmpresaId = $datos->EmpresaId;
      $newPrv->Codigo = $datos->Codigo;
      $newPrv->Identificacion = $datos->Identificacion;
      $newPrv->IdentificacionTipo = $datos->IdentificacionTipo;
      $newPrv->Nombres = $datos->Nombres;
      $newPrv->Representante = $datos->Representante;
      $newPrv->Direccion = $datos->Direccion;
      $newPrv->Telefonos = $datos->Telefonos;
      $newPrv->Ciudad = $datos->Ciudad;
      $newPrv->CiudadId = $datos->CiudadId;
      $newPrv->Estado = $datos->Estado;
      if ($datos->Id > 0) {
        if (!$newPrv->update()) {
          $this->response->setStatusCode(500, 'Error');  
          $msj = "No se pudo actualizar el proveedor: " . "\n";
          foreach ($newPrv->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = 0;
          $ret->msj = $msj;
        }
      } else {
        $newcod = $datos->Codigo;
        if (strlen($datos->Codigo) <= 0) {
          $di = Di::getDefault();
          $phql = 'SELECT MAX(Codigo) as maxcod FROM Pointerp\Modelos\Maestros\Proveedores 
              WHERE Estado = 0 AND EmpresaId = ' . $datos->EmpresaId;
          $qry = new Query($phql, $di);
          $rws = $qry->execute();
          if ($rws->count() === 1) {
            $rmax = $rws->getFirst();
            try {
              $num = intval($rmax['maxcod']);
            } catch (Exception $e) {
              //$msjr = $msjr . "\n" . "Codigo: " . $rmax['maxcod'] . "\n" . $e->getMessage();
              $num = 0;
            }
          }
          
          if ($num == 0)
            $num = 1000;
          else
            $num += 1;

          $newcod = strval($num);
        }
        $newPrv->Codigo = $newcod;
        if (!$newPrv->create()) {
          $this->response->setStatusCode(500, 'Error');  
          $msj = "No se pudo crear el nuevo proveedor: " . "\n";
          foreach ($newPrv->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = 0;
          $ret->msj = $msj;
        }
      }
    } catch (Exception $ex) {
      $this->response->setStatusCode(500, 'Error');  
      $ret->res = false;
      $ret->cid = 0;
      $ret->msj = $ex->getMessage();
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }
  #endregion

  #region Impuestos
  public function impuestosPorEstadoAction() {
    $this->view->disable();
    $estado = $this->dispatcher->getParam('est');
    $condiciones = '';
    if ($estado == 0) {
      $condiciones = 'Estado = 0';
    }

    $rows = Impuestos::find([
      'conditions' => $condiciones,
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

  public function impuestoGuardarAction() {
    $datos = $this->request->getJsonRawBody();
    $ret = (object) [
      'res' => false,
      'cid' => $datos->Id,
      'msj' => 'Los datos no se pudieron procesar'
    ];
    try {
      $newImp = new Impuestos();
      if ($datos->Id > 0) {
        $newImp = Impuestos::findFirstById($datos->Id);
      }
      $newImp->Nombre = $datos->Nombre;
      $newImp->Porcentaje = $datos->Porcentaje;
      $newImp->CodigoEmision = $datos->CodigoEmision;
      $newImp->CodigoPorcentaje = $datos->CodigoPorcentaje;
      $newImp->Actualizado = date('Y-m-d H:i:s');
      if ($datos->Id > 0) {
        if (!$newImp->update()) {
          $this->response->setStatusCode(500, 'Error');  
          $msj = "No se pudo actualizar el impuesto: " . "\n";
          foreach ($newImp->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = 0;
          $ret->msj = $msj;
        }
      } else {
        if (!$newImp->create()) {
          $this->response->setStatusCode(500, 'Error');  
          $msj = "No se pudo crear el nuevo impuesto: " . "\n";
          foreach ($newImp->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = 0;
          $ret->msj = $msj;
        }
      }
    } catch (Exception $ex) {
      $this->response->setStatusCode(500, 'Error');  
      $ret->res = false;
      $ret->cid = 0;
      $ret->msj = $ex->getMessage();
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function impuestoModificarEstadoAction() {
    $estado = $this->dispatcher->getParam('estado');
    $id = $this->dispatcher->getParam('id');
    $result = (Object) [
      "completo" => false,
      "mensaje" => "La operación no se pudo completar"
    ];
    $this->response->setStatusCode(422, 'Unprocessable Content');
    $impuesto = Impuestos::findFirstById($id);
    if ($impuesto) {
      $impuesto->Estado = $estado;
      if($impuesto->update()) {
        $result->completo = true;
        $result->mensaje = "Registro actualizado exitosamente";
        $this->response->setStatusCode(201, 'Ok');
      } else {
        $result->mensaje = "Error al intentar modificar el impuesto";
      }
    } else {
      $result->mensaje = "No se encontro el impuesto";
      $this->response->setStatusCode(404, 'Not found');
    }

    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($result));
    $this->response->send();
  }
  #endregion

  #region Tablas

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
