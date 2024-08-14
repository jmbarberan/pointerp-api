<?php

namespace Pointerp\Controladores;

use Exception;
use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\Inventarios\Kardex;
use Pointerp\Modelos\Ventas\Ventas;
use Pointerp\Modelos\Ventas\VentasMin;
use Pointerp\Modelos\Ventas\VentasItems;
use Pointerp\Modelos\Ventas\VentasImpuestos;
use Pointerp\Modelos\Maestros\Registros;
use Pointerp\Modelos\Cxc\Comprobantes;
use Pointerp\Modelos\Cxc\ComprobanteItems;
use Pointerp\Modelos\Cxc\ComprobanteDocumentos;
use Pointerp\Modelos\EmpresaParametros;
use Pointerp\Modelos\Empresas;
use Pointerp\Modelos\Maestros\Clientes;
use Pointerp\Modelos\Sucursales;

class VentasController extends ControllerBase  {

  #region comprobantes
  public function ventasBuscarAction() {
    $this->view->disable();
    $suc = $this->dispatcher->getParam('sucursal'); // Solo se consulta una sucursal
    $tipoBusca = $this->dispatcher->getParam('tipo');
    $filtro = $this->dispatcher->getParam('filtro');
    $estado = $this->dispatcher->getParam('estado');
    $clase = $this->dispatcher->getParam('clase');
    $desde = $this->dispatcher->getParam('desde');
    $hasta = $this->dispatcher->getParam('hasta');    
    $condicion = "Tipo in (11, 12) AND SucursalId = " . $suc;
    $res = [];
    if ($clase < 3) {      
      if ($clase <= 1) {
        $condicion .= " AND Fecha >= '" . $desde . "' AND Fecha <= '" . $hasta . "'";
      } else {
        if (strlen($filtro) > 1) {
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
          }
          $condicion .= " AND `Notas` like '" . $filtro . "'";
        }
      }
    } else {
      $condicion .= " AND Numero = " . $filtro;
    }

    if (strlen($condicion) > 0) {
      $condicion .= ' AND Estado = 0';
      $res = Ventas::find([
        'conditions' => $condicion,
        'order' => 'Fecha'
      ]);
    }

    if ($res->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function ventasDiarioAction() {
    $this->view->disable();
    $suc = $this->dispatcher->getParam('sucursal'); // Solo se consulta una sucursal    
    $estado = $this->dispatcher->getParam('estado');    
    $desde = $this->dispatcher->getParam('desde');
    $hasta = $this->dispatcher->getParam('hasta');
    $res = [];
    /*$desde .= " 0:00:00";
    $hasta .= " 23:59:59";*/
    $condicion = "Tipo in (11, 12) AND SucursalId = " . $suc . " AND Fecha >= '" . $desde . "' AND Fecha <= '" . $hasta . "'";
    if ($estado == 0) {
      $condicion .= " AND Estado = " . $estado;
    }
    $res = VentasMin::find([
      'conditions' => $condicion,
      'order' => 'Fecha'
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

  public function ventaGuardarAction() {
    try {
      $datos = $this->request->getJsonRawBody();
      $generarCA = $this->request->getQuery('generarCA', null, false);
      $autorizar = $this->request->getQuery('autorizar', null, false);
      $ret = (object) [
        'res' => false,
        'cid' => $datos->Id,
        'cve' => '',
        'sec' => '',
        'ven' => null,
        'msj' => 'Los datos no se pudieron procesar',
        'num' => $datos->Numero
      ];
      $this->response->setStatusCode(406, 'Not Acceptable');
      $datos->Fecha = str_replace('T', ' ', $datos->Fecha);
      $datos->Fecha = str_replace('Z', '', $datos->Fecha);
      // Si el cliente id es 0 crearlo
      if (isset($datos->relCliente)) {
        $cliente = $datos->relCliente;
        if ($datos->relCliente->Id == 0) {
          $nuevoCliente = new Clientes();
          $nuevoCliente->generarNuevoCodigo($cliente->EmpresaId);
          $nuevoCliente->EmpresaId = $cliente->EmpresaId;
          $nuevoCliente->Identificacion = $cliente->Identificacion;
          $nuevoCliente->Nombres = $cliente->Nombres;
          $nuevoCliente->Direccion = $cliente->Direccion;
          $nuevoCliente->Telefonos = $cliente->Telefonos;
          $nuevoCliente->Email = $cliente->Email;
          $nuevoCliente->IdentificacionTipo = $cliente->IdentificacionTipo;
          $nuevoCliente->Cupo = 0;
          $nuevoCliente->Estado = 0;
          if ($nuevoCliente->create()) {
            $datos->ClienteId = $nuevoCliente->Id;
          } else {
            $msj = "";
            foreach ($nuevoCliente->getMessages() as $m) {
              $msj .= $m . " ";
            }
            echo $msj;
          }
        }
      }
      if ($datos->Id > 0) {
        // Traer movimiento por id y acualizar
        $ven = Ventas::findFirstById($datos->id);
        $ven->Fecha = $datos->Fecha;
        $ven->SucursalId = $datos->SucursalId;
        $ven->BodegaId = $datos->BodegaId; // BodegaId
        $ven->Plazo = $datos->Plazo;
        $ven->ClienteId = $datos->ClienteId;
        $ven->VendedorId = $datos->VendedorId;
        $ven->Notas = $datos->Notas;
        $ven->PorcentajeDescuento = $datos->PorcentajeDescuento;
        $ven->PorcentajeVenta = $datos->PorcentajeVenta;
        $ven->Subtotal = $datos->Subtotal;
        $ven->SubtotalEx = $datos->SubtotalEx;
        $ven->Descuento = $datos->Descuento;
        $ven->Recargo = $datos->Recargo;
        $ven->Flete = $datos->Flete;
        $ven->Impuestos = $datos->Impuestos;
        $ven->Abonos = $datos->Abonos;
        $ven->AbonosPf = $datos->AbonosPf;
        $ven->Estado = $datos->Estado;
        $ven->Especie = $datos->Especie; // receta, servicio medico
        $ven->CEClaveAcceso = $datos->CEClaveAcceso;
        $ven->CEAutorizacion = $datos->CEAutorizacion;
        $ven->CEAutorizacionFecha = $datos->CEAutorizacionFecha;
        $ven->CEContenido = $datos->CEContenido;
        $ven->CEEtapa = $datos->CEEtapa;
        $ven->CERespuestaId = $datos->CERespuestaId;
        $ven->CERespuestaTipo = $datos->CERespuestaTipo;
        $ven->CERespuestaMsj = $datos->CERespuestaMsj;
        $ven->Operador = $datos->Operador;
        if($ven->update()) {
          $ret->res = true;
          $ret->cid = $datos->Id;
          // Quitar items eliminados
          foreach ($datos->itemsEliminados as $mie) {          
            $eli = VentasItems::findFirstById($mie->Id);
            if ($eli != false) {
              $eli->delete();
            }
          }          
          // crear los items nuevos y acuatualizar los modiifcados
          foreach ($datos->relItems as $mi) {            
            $ins = null;
            if ($mi->Id > 0) {
              $ins = VentasItems::findFirstById($mi->Id);
            } else {
              $ins = new VentasItems();
              $ins->VentaId = $ven->Id;
            }
            if ($ins != null) {
              $ins->Bodega = $mi->Bodega; // Bodega
              $ins->Cantidad = $mi->Cantidad;
              $ins->Precio = $mi->Precio;
              $ins->Descuento = $mi->Descuento;
              $ins->Adicional = $mi->Adicional;
              $ins->Despachado = $mi->Despachado;
              if ($mi->Codigo) {
                $ins->Codigo = $mi->Codigo;
              }
              $ins->Costo = $mi->Costo;
              if ($mi->Id > 0) {
                $ins->update();
              } else {
                $ins->create();
              }
            }
          }
          
          // Procesar items de impuestos          
          foreach ($datos->relImpuestos as $imp) {
            $ins = VentasImpuestos::findFirst([
              'conditions' => 'VentaId = ' . $ven->Id . ' AND ImpuestoId = ' . $imp->ImpuestoId
            ]);
            if ($ins != null) {
              $ins->Porcentaje = $imp->Porcentaje;
              $ins->base = $imp->base;
              $ins->Valor = $imp->Valor;
              if (!$ins->update()) {
                $msj = "No se puede actualizar los datos: ";
                foreach ($ins->getMessages() as $m) {            
                  $msj .= $m . " ";
                }
                $ret->res = false;
                $ret->msj = $msj;
              }
            } else {
              $o = new VentasImpuestos();
              $o->Id = 0;
              $o->CompraId = $ven->Id;
              $o->ImpuestoId = $imp->ImpuestoId;
              $o->Porcentaje = $imp->Porcentaje;
              $o->base = $imp->base;
              $o->Valor = $imp->Valor;
              if (!$o->create()) {
                $msj = "No se puede actualizar los datos: ";
                foreach ($o->getMessages() as $m) {
                  $msj .= $m . " ";
                }
                $ret->res = false;
                $ret->msj = $msj;
              }
            }
          }
          $ret->msj = "Se actualizo correctamente la transaccion";       
          $this->response->setStatusCode(200, 'Ok');
        } else {
          $msj = "No se puede actualizar los datos: " . "\n";
          foreach ($ven->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = $datos->id;
          $ret->msj = $msj;
        }
      } else {
        // Crear factura nueva
        $vendoble = false;
        try {
          $fechaComparar = str_replace("T", " ", $datos->Fecha);
          $cmd = "select Id from Pointerp\Modelos\Ventas\Ventas 
            where Tipo = {$datos->Tipo} and SucursalId = {$datos->SucursalId} and substr(cast(Fecha as char), 1, 19) = substr('{$fechaComparar}', 1, 19)";
          $qry = new Query($cmd, Di::getDefault());
          $rws = $qry->execute();
          $vendoble = $rws->count() > 0;
        }
        catch(Exception $ex) {
          $ret->msj = $ex;
        }

        if (!$vendoble) {
          if ($generarCA && $datos->Tipo == 11) {
            $res = $this->generarClaveAcceso($datos->SucursalId);
            $datos->CEClaveAcceso = $res->clave;
            $datos->CERespuestaTipo = $res->secuencial;
          }
          $ret = $this->guardarVentaNueva($datos, 0, false);
          if ($ret->res) {
            if ($autorizar) {
              require_once APP_PATH . '/library/ComprobantesElectronicos.php';
              try {
                $paramCert = EmpresaParametros::findFirst([
                  'conditions' => "Tipo = 19 AND EmpresaId = {$ret->ven->relCliente->EmpresaId}"
                ]);
                \ComprobantesElectronicos::cargarCertificado($paramCert->Denominacion, $paramCert->Extendido);
                \ComprobantesElectronicos::autorizarFactura($ret->ven);
              } catch (Exception $e) {
                $this->response->setStatusCode(500, 'Error');  
                $msj = $e->getMessage();
              }
            }
            $this->response->setStatusCode(201, 'Ok');
          }
        } else {
          $ret->msj = "El documento ya se encuentra registrado";
        } 
      }
    } catch (Exception $e) {
      $this->response->setStatusCode(500, 'Error');
      $ret->cid = 0;
      $ret->msj = $e->getMessage();
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function ventasListaGuardarAction() {
    $ret = (object) [
      'res' => true,
      'cid' => 0,
      'ven' => null,
      'msj' => 'Ventas sincronizadas correctamente',
      'num' => 0
    ];
    $ventas = $this->request->getJsonRawBody();

    foreach ($ventas as $venta) {
      $this->guardarVentaNueva($venta, 0, false);  
    }
    
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function ventaCrearCobrarAction() {
    $caja = $this->dispatcher->getParam('caja');
    $usuario = $this->dispatcher->getParam('usuario');
    $generarCA = $this->request->getQuery('generarCA', null, false);
    $datos = $this->request->getJsonRawBody();
    $ret = (object) [
      'res' => false,
      'cid' => 0,
      'cve' => '',
      'sec' => '',
      'ven' => null,
      'msj' => 'Error al crear',
      'num' => 0
    ];
    
    $vendoble = false;
    try {
      $fechaComparar = str_replace("T", " ", $datos->Fecha);
      $cmd = "select Id from Pointerp\Modelos\Ventas\Ventas 
        where Tipo = {$datos->Tipo} and SucursalId = {$datos->SucursalId} and substr(cast(Fecha as char), 1, 19) = substr('{$fechaComparar}', 1, 19)";
      $qry = new Query($cmd, Di::getDefault());
      $rws = $qry->execute();
      $vendoble = $rws->count() > 0;
    }
    catch(Exception $ex) {
      $ret->msj = $ex;
    }

    if (!$vendoble) {
      $cobrado = $datos->Subtotal + $datos->SubtotalEx + $datos->Impuestos + $datos->Descuento + $datos->Recargo + $datos->Flete;
      if ($generarCA && $datos->Tipo == 11) {
        $res = $this->generarClaveAcceso($datos->SucursalId);
        $datos->CEClaveAcceso = $res->clave;
        $datos->CERespuestaTipo = $res->secuencial;
      }
      $ret = $this->guardarVentaNueva($datos, $cobrado, false);
      if ($ret->res) {
        $vta = $ret->ven;
        $cobroNum = $this->ultimoNumeroCobro(16, $datos->SucursalId) + 1;
        $cobro = new Comprobantes();
        $cobro->Tipo = 16; // (int)EntidadesEnum.EnCobro
        $cobro->Fecha = date_format(new \DateTime(),"Y-m-d H:i:s");
        $cobro->Total = $cobrado;
        $cobro->SucursalId = $datos->SucursalId;
        $cobro->Especie = 0;
        $cobro->Numero = $cobroNum;
        $cobro->UsuarioId = $usuario;
        $cobro->Estado = 0;
        if ($cobro->create()) {
          $doc = new ComprobanteDocumentos();
          if ($vta->Tipo == 47) { // (int)EntidadesEnum.EnPedido
            $doc->Concepto = 52; // (int)EntidadesEnum.EnAbonosEfectivo
            $doc->Notas = "Abono a pedido reservado";
          } else {
            $doc->Notas = "Cobro de venta de contado";
          }
          $doc->ComprobanteId = $cobro->Id;
          $doc->Origen = $vta->Tipo;
          $doc->Referencia = $vta->Id;
          $doc->Rebajas = $cobrado;
          $doc->Recargos = 0;
          $doc->Soporte = 0;
          if($doc->create()) {
            $cobitem = new ComprobanteItems();
            $cobitem->ComprobanteId = $cobro->Id;
            $cobitem->Numero = $caja; // id de la caja
            $cobitem->Fecha = date_format(new \DateTime(),"Y-m-d H:i:s");
            $cobitem->Cuenta = " ";
            $cobitem->Autorizacion = " ";
            $cobitem->Nombres = " ";
            $cobitem->Codigo = " ";
            $cobitem->Valor = $cobrado;
            $cobitem->Descripcion = "";
            $cobitem->Origen = 35; // (int)EntidadesEnum.EnCobroEfectivo
            if(!$cobitem->create()) {
              $msj = "No se pudo crear el nuevo Item: " . "\n";
              foreach ($cobitem->getMessages() as $m) {
                $msj .= $m . "\n";
              }
              $ret->msj = $msj;
            }
          } else {
            $msj = "No se pudo crear el nuevo Documento: " . "\n";
            foreach ($doc->getMessages() as $m) {
              $msj .= $m . "\n";
            }
            $ret->msj = $msj;
          }
        } else {
          $msj = "No se pudo crear el nuevo cobro: " . "\n";
          foreach ($cobro->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->msj = $msj;
        }
      } else {
        $ret->res = true;
        $ret->msj = "Se creo correctamente el comprobante, pero no se pudo registrar el cobro";
      }
    } else {
      $ret->msj = "El documento ya se encuentra registrado";
    }
    
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function ventaPorIdAction() {
    $id = $this->dispatcher->getParam('id');
    $res = Ventas::findFirstById($id);
    if ($res) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $res = [];
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function ventaPorNumeroAction() {
    $tipo = $this->dispatcher->getParam('tipo');
    $num = $this->dispatcher->getParam('numero');
    $rows = Ventas::find([
      'conditions' => 'Tipo = :tip: AND Numero = :num:',
      'bind' => [ 'tip' => $tipo, 'num' => $num ]
    ]);
    if ($rows->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $rows = [];
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rows));
    $this->response->send();
  }

  public function ventaModificarEstadoAction() {
    $id = $this->dispatcher->getParam('id');
    $est = $this->dispatcher->getParam('estado');
    $ven = Ventas::findFirstById($id);
    if ($ven != false) {
      $ven->estado = $est;
      if($ven->update()) {
        $msj = "La operacion se ejecuto exitosamente";
        $this->response->setStatusCode(200, 'Ok');
      } else {
        $this->response->setStatusCode(404, 'Error');
        $msj = "No se puede actualizar los datos: " . "\n";
        foreach ($ven->getMessages() as $m) {
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

  public function ventaAutorizarAction() {
    $id = $this->dispatcher->getParam('id');
    $ven = Ventas::findFirstById($id);
    if ($ven != false) {
      require_once APP_PATH . '/library/ComprobantesElectronicos.php';
      try {
        $paramCert = EmpresaParametros::findFirst([
          'conditions' => "Tipo = 19 AND EmpresaId = {$ven->relCliente->EmpresaId}"
        ]);
        \ComprobantesElectronicos::cargarCertificado($paramCert->Denominacion, $paramCert->Extendido);
        // validar si tiene secuencial y clave de accceso y no tiene error: SECUENCIAL REGISTRADO
        \ComprobantesElectronicos::autorizarFactura($ven);
      } catch (Exception $e) {
        $this->response->setStatusCode(500, 'Error');  
        $msj = $e->getMessage();
      }
    } else {
      $msj = "No se encontro el registro";
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($msj));
    $this->response->send();
  }

  public function ventaActualizarEstadoCEAction() {
    $datos = $this->request->getJsonRawBody();
    $ven = Ventas::findFirstById($datos->Id);
    if ($ven != false) {
      $ven->CEClaveAcceso = $datos->CEClaveAcceso;
      $ven->CEAutorizacion = $datos->CEAutorizacion;
      $ven->CEAutorizaFecha = $datos->CEAutorizaFecha;
      $ven->CEContenido = $datos->CEContenido;
      $ven->CERespuestaId = $datos->CERespuestaId;
      $ven->CERespuestaTipo = $datos->CERespuestaTipo;
      $ven->CERespuestaMsj = $datos->CERespuestaMsj;
      if($ven->update()) {
        $msj = "La operacion se ejecuto exitosamente";
        $this->response->setStatusCode(200, 'Ok');
      } else {
        $this->response->setStatusCode(404, 'Error');
        $msj = "No se puede actualizar los datos: " . "\n";
        foreach ($ven->getMessages() as $m) {
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

  public function ventaCrearEliminadaAction() {
    $datos = $this->request->getJsonRawBody();
    try {      
      $ret = (object) [
        'res' => false,
        'cid' => $datos->Id,
        'ven' => null,
        'msj' => 'Los datos no se pudieron procesar',
        'num' => $datos->Numero
      ];

      $consumidorFinal = Registros::findFirst([
        "conditions" => "TablaId = 5 and Indice = 14"
      ]);
      $this->response->setStatusCode(406, 'Not Acceptable');

      $num = intval($this->ultimoNumeroVenta($datos->Tipo, $datos->SucursalId)) + 1;
      $ven = new Ventas();
      $ven->Numero = $num;
      $ven->Tipo = 11; // Factura
      $ven->Fecha = new \DateTime();
      $ven->SucursalId = $datos->SucursalId;
      $ven->BodegaId = $datos->BodegaId;
      $ven->Plazo = 0;
      $ven->ClienteId = $consumidorFinal->Contenedor;
      $ven->VendedorId = 0;
      $ven->Notas = "Eliminado por contabilidad";
      $ven->PorcentajeDescuento = 0;
      $ven->PorcentajeVenta = 0;
      $ven->Subtotal = 0;
      $ven->SubtotalEx = 0;
      $ven->Descuento = 0;
      $ven->Recargo = 0;
      $ven->Flete = 0;
      $ven->Impuestos = 0;
      $ven->Abonos = 0;
      $ven->AbonosPf = 0;
      $ven->Estado = 2;
      $ven->Especie = 0; // receta, servicio medico
      $ven->CEClaveAcceso = ""; // TODO generar fake con el secuencial especificado
      $ven->CEAutorizacion = "";
      $ven->CEAutorizaFecha = "";
      $ven->CEContenido = "";
      $ven->CEEtapa = 0;
      $ven->CERespuestaId = 0;
      $ven->CERespuestaTipo = $datos->Secuencial;
      $ven->CERespuestaMsj = "Eliminado por contabilidad";
      $ven->Comprobante = 0;
      $ven->Contado = 0;
      $ven->Operador = "Contador";
      if ($ven->create()) {
        $ret->res = true;
        $ret->cid = $ven->Id;
        $ret->num = $ven->Numero;
        $ret->msj = "Se registro correctamente la transaccion";  
        $ret->ven = Ventas::findFirstById($ret->cid);
      } else {
        $msj = "No se pudo crear el nuevo registro: " . "\n";
        foreach ($ven->getMessages() as $m) {
          $msj .= $m . "\n";
        }
        $ret->cid = 0;
        $ret->msj = $msj;
      }
    } catch (Exception $e) {
      $this->response->setStatusCode(500, 'Error');
      $ret->cid = 0;
      $ret->msj = $e->getMessage();
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }
  
  public function ventaPorSecuencialAction() {    
    $num = $this->dispatcher->getParam('numero');
    $rows = Ventas::find([
      'conditions' => 'Tipo in (11, 12) AND CERespuestaTipo = :num: AND CEClaveAcceso is not null',
      'bind' => [ 'num' => $num ]
    ]);
    if ($rows->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $rows = [];
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rows));
    $this->response->send();
  }

  public function ventaTraerSecuencialAction() {
    $tipo = $this->dispatcher->getParam('tipo');
    $sucursal = $this->dispatcher->getParam('sucursal');
    $num = intval($this->ultimoNumeroVenta($tipo, $sucursal)) + 1;
    $this->response->setStatusCode(200, 'Ok');
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($num));
    $this->response->send();
  }
  #endregion

  private function ultimoNumeroVenta($tipo, $suc) {
    try {
      return Ventas::maximum([
        'column' => 'Numero',
        'conditions' => 'Tipo = ' . $tipo . ' AND SucursalId = ' . $suc
      ]) ?? 0;
    } catch (Exception $ex) {
      return -1;
    }
  }

  private function ultimoNumeroCobro($tipo, $suc) {
    return Comprobantes::maximum([
      'column' => 'Numero',
      'conditions' => 'Tipo = ' . $tipo . ' AND SucursalId = ' . $suc
    ]) ?? 0;
  }

  private function guardarVentaNueva($datos, $cobrado, $min) {
    $ret = (object) [
      'res' => false,
      'cid' => 0,
      'cve' => '',
      'sec' => '',
      'ven' => null,
      'msj' => 'Error al crear',
      'num' => 0
    ];

    $guardar = true;
    $this->db->begin();
    try {
      if ($datos->ClienteId <= 0) {
        if (!isset($datos->ClienteNav)) {
          $resp = $this->crearCliente($datos->relCliente);
        } else {
          $resp = $this->crearCliente($datos->ClienteNav);
        }
        
        if (strlen($resp) > 0) {
          $ret->msj = "Error al crear el cliente: {$resp}";
          $guardar = false;
        }
      }
      if ($guardar) {
        $num = intval($this->ultimoNumeroVenta($datos->Tipo, $datos->SucursalId)) + 1;
        $ven = new Ventas();
        $ven->Numero = $num;
        $ven->Tipo = $datos->Tipo;
        $ven->Fecha = $datos->Fecha;
        $ven->SucursalId = $datos->SucursalId;
        $ven->BodegaId = $datos->BodegaId;
        $ven->Plazo = $datos->Plazo;
        $ven->ClienteId = $datos->ClienteId;
        $ven->VendedorId = $datos->VendedorId;
        $ven->Notas = $datos->Notas;
        $ven->PorcentajeDescuento = $datos->PorcentajeDescuento;
        $ven->PorcentajeVenta = $datos->PorcentajeVenta;
        $ven->Subtotal = $datos->Subtotal;
        $ven->SubtotalEx = $datos->SubtotalEx;
        $ven->Descuento = $datos->Descuento;
        $ven->Recargo = $datos->Recargo;
        $ven->Flete = $datos->Flete;
        $ven->Impuestos = $datos->Impuestos;
        $ven->Abonos = $cobrado;
        $ven->AbonosPf = $datos->AbonosPf;
        $ven->Estado = $cobrado > 0 ? 1 : $datos->Estado;
        $ven->Especie = $datos->Especie; // receta, servicio medico
        $ven->CEClaveAcceso = $datos->CEClaveAcceso;
        $ven->CEAutorizacion = $datos->CEAutorizacion;
        $ven->CEAutorizaFecha = $datos->CEAutorizaFecha;
        $ven->CEContenido = $datos->CEContenido;
        $ven->CEEtapa = $datos->CEEtapa;
        $ven->CERespuestaId = $datos->CERespuestaId;
        $ven->CERespuestaTipo = strval($datos->CERespuestaTipo);
        $ven->CERespuestaMsj = $datos->CERespuestaMsj;
        $ven->Comprobante = $datos->Comprobante;
        $ven->Contado = $cobrado > 0 ? 1 : 0;
        $ven->Operador = $datos->Operador;
        if ($ven->create()) {
          $ret->res = true;
          $ret->cid = $ven->Id;
          $ret->num = $ven->Numero;
          $ret->msj = "Se registro correctamente la nueva transaccion";  
          // Crear items
          foreach ($datos->relItems as $mi) {
            $ins = new VentasItems();
            $ins->VentaId = $ven->Id;
            $ins->ProductoId = $mi->ProductoId;
            $ins->Bodega = $mi->Bodega;
            $ins->Cantidad = $mi->Cantidad;
            $ins->Precio = $mi->Precio;
            $ins->Descuento = $mi->Descuento;
            $ins->Adicional = $mi->Adicional;
            $ins->Despachado = $mi->Despachado;
            if (isset($mi->Codigo)) {
              $ins->Codigo = $mi->Codigo;
            }
            $ins->Costo = $mi->Costo;
            $ins->create();
          }
          foreach ($datos->relImpuestos as $im) {
            $ins = new VentasImpuestos();
            $ins->VentaId = $ven->Id;
            $ins->ImpuestoId = $im->ImpuestoId;
            $ins->Porcentaje = $im->Porcentaje;
            $ins->base = $im->base;
            $ins->Valor = $im->Valor;
            $ins->create();
          }
          $ret->ven = $ven; //$min ? VentasMin::findFirstById($ret->cid) : Ventas::findFirstById($ret->cid);
          $ret->cve = $datos->CEClaveAcceso;
          $ret->sec = $datos->CERespuestaTipo;
        } else {
          $msj = "No se pudo crear el nuevo registro: " . "\n";
          foreach ($ven->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->cid = 0;
          $ret->msj = $msj;
        }
      }
      $this->db->commit();
    } catch (Exception $ex) {
      $this->db->rollback();
      $ret->cid = 0;
      $ret->msj = "Error: " . $ex->getMessage();
    }
    return $ret;
  }

  private function crearCliente($datos) {
    try {
      $newcli = new Clientes();
      $newcli->EmpresaId = $datos->EmpresaId;
      $newcli->Codigo = $datos->Codigo;
      $newcli->Identificacion = $datos->Identificacion;
      $newcli->IdentificacionTipo = $datos->IdentificacionTipo;
      $newcli->Nombres = $datos->Nombres;
      $newcli->Representante = $datos->Representante;
      $newcli->Direccion = $datos->Direccion;
      $newcli->Telefonos = $datos->Telefonos;
      $newcli->Ciudad = $datos->Ciudad;
      $newcli->CiudadId = $datos->CiudadId;
      $newcli->Estado = $datos->Estado;
      
      $newcod = $datos->Codigo;
      if (strlen($datos->Codigo) <= 0) {
        $di = Di::getDefault();
        $phql = 'SELECT MAX(Codigo) as maxcod FROM Pointerp\Modelos\Maestros\Clientes 
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
      $newcli->Codigo = $newcod;
      if (!$newcli->create()) {
        $this->response->setStatusCode(500, 'Error');  
        $msj = "No se pudo crear el nuevo cliente: " . "\n";
        foreach ($newcli->getMessages() as $m) {
          $msj .= $m . "\n";
        }
        return $msj;
      }
      return "";
    } catch (Exception $ex) {
      return $ex->getMessage();
    }
  }

  private function afectarInventario($item, $bod, $origen, $signo) {
    $res = Kardex::find([
      'conditions' => 'ProductoId = :pro: AND BodegaId = :bod:',
      'bind' => [ 'bod' => $bod, 'pro' => $item->ProductoId ]
    ]);
    $ing = 0;
    $egr = 0;
    if ($origen == 0) {
      // 1 Es una operacion de aplicacion
      if ($signo < 0) {
        // 1.1 Es operacion negativa egreso
        $egr = $item->cantidad;
      } else {
        // 1.2 Es operacion poitiva ingreso
        $ing = $item->cantidad;
      }
    } else {
      // 2 Es una operacion de reversion
      if ($signo < 0) {
        // 2.1 Es operacion negativa egreso
        $egr = $item->cantidad * -1;
      } else {
        // 2.2 Es operacion poitiva ingreso
        $ing = $item->cantidad * -1;
      }
    }
    $date = date('Y-m-d H:i:s');

    $msj = "No se proceso";
    if ($res->count() > 0) {
      $kdx = $res[0];
      $kdx->Ingresos = $kdx->Ingresos + $ing;
      $kdx->Egresos = $kdx->Egresos + $egr;
      $kdx->Actualizacion = date('Y-m-d H:i:s');
      if ($kdx->update()) {
        $msj = "Kardex actualizado";
      } else {
        $msj = "No se pudo actualizarar el kardex: " . "\n";
        foreach ($kdx->getMessages() as $m) {
          $msj .= $m . "\n";
        }
      }
    } else {
      $kdxn = new Kardex();
      $kdxn->ProductoId = $item->ProductoId;
      $kdxn->BodegaId = $bod;
      $kdxn->Ingresos = $ing;
      $kdxn->Egresos = $egr;
      $kdxn->Actualizacion = date('Y-m-d H:i:s');
      if ($kdxn->create()) {
        $msj = "Kardex registrado";
      } else {
        $msj = "No se pudo reistrar el kardex: " . "\n";
        foreach ($kdxn->getMessages() as $m) {
          $msj .= $m . "\n";
        }
      }
    }
    return $msj;
  }

  private function signoPorTipo($tipo) {
    $this->view->disable();
    $res = Registros::find([
        'conditions' => 'TablaId = 20 AND Indice = :tipo:',
        'bind' => ['tipo' => $tipo]
    ]);
    if ($res->count() > 0) {
      $si = $res[0];
      return $si->valor;
    } else {
      return 0;
    }
  }

  private function calcularDigitoVerificadorCadena($cadena) {
    $cadenaInversa = '';
    foreach (str_split($cadena) as $c) {
        $cadenaInversa = $c . $cadenaInversa;
    }

    $factor = 2;
    $res = 0;

    for ($i = 0; $i < strlen($cadenaInversa); $i++) {
        $factor = ($factor == 8) ? 2 : $factor;
        $producto = intval($cadenaInversa[$i]);
        $producto *= $factor;
        $factor++;
        $res += $producto;
    }

    $res = 11 - $res % 11;

    if ($res == 11) $res = 0;
    if ($res == 10) $res = 1;

    return $res;
  }

  private function codigoAleatorio() {
    $f = new \DateTime();
    $h = $f->format("H");
    $t = $f->format("i");
    $s = $f->format("s");
    $y = $f->format("Y");
    $m = $f->format("m");
    $d = $f->format("d");
    $calf = intval($y) * intval($m) * intval($d);
    $calh = intval($h) * intval($t) * intval($s);
    $generado = rand(1, 99);
    return strval($calf) . strval($calh) . strval($generado);
  }

  private function generarClaveAcceso($sucursalId) {
    #region Secuencial
    $sucursal = Sucursales::findFirstById($sucursalId);
    $empresa = Empresas::findFirstById($sucursal->EmpresaId);
    $serie = $sucursal->Codigo . trim($sucursal->Descripcion);
    $paramFaEmpresa = EmpresaParametros::findFirst([
      'conditions' => "Tipo = 1 AND Referencia = 11 AND EmpresaId = {$empresa->Id}" 
    ]);

    $tipoDatos = (object) [
      'tipoDocumento' => '01', // 01: FACTURA
      'tipoEmision' => '1',  // OFFLINE UNICO VALOR VALIDO
      'ambiente' => '1' // 1: PRUEBAS; 2: PRODUCCION
    ];

    #region Ambiente
    $ruc = "9999999999999";
    if (isset($empresa)) {
      $ruc = $empresa->Ruc;
      $reg = Registros::findFirstById($empresa->TipoAmbiente);
      if (isset($reg))
        $tipoDatos->ambiente = $reg->Codigo;
    }
    #endregion

    #region Clave de acceso
    
    $numSig = intval($paramFaEmpresa->Indice);
    $numSig = $numSig + 1;
    $fecha = new \DateTime();
    $codigoAleatorio = self::codigoAleatorio();
    if (strlen($codigoAleatorio) > 8) {
      $codigoAleatorio = substr($codigoAleatorio, -8);
    } else {
      if (strlen($codigoAleatorio < 8)) {
        $codigoAleatorio = str_pad($codigoAleatorio, 8, "1", STR_PAD_LEFT);
      }
    }
    $clave = $fecha->format("dmY") .
      $tipoDatos->tipoDocumento .
      $ruc .
      $tipoDatos->ambiente .
      $serie .
      str_pad(strval($numSig), 9, "0", STR_PAD_LEFT) .
      $codigoAleatorio .
      $tipoDatos->tipoEmision;
    $clave .= strval(self::calcularDigitoVerificadorCadena($clave));
    #endregion

    $paramFaEmpresa->Indice = $numSig;
    $paramFaEmpresa->update();

    $retorno = (object) [
      'clave' => $clave,
      'secuencial' => $numSig
    ];

    return $retorno;
  }
}
