<?php

namespace Pointerp\Controladores;

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
      $ret = (object) [
        'res' => false,
        'cid' => $datos->Id,
        'ven' => null,
        'msj' => 'Los datos no se pudieron procesar'
      ];
      $this->response->setStatusCode(406, 'Not Acceptable');
      $datos->Fecha = str_replace('T', ' ', $datos->Fecha);
      $datos->Fecha = str_replace('Z', '', $datos->Fecha); 
      // Si el cliente id es 0 crearlo
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
        // Crear factura nuevo
        $creadoRes = $this->guardarVentaNueva($datos, 0, false);
        $ret->res = $creadoRes->res;
        $ret->cid = $creadoRes->cid;
        $ret->num = $creadoRes->Numero;
        $ret->msj = $creadoRes->msj;
        $ret->ven = $creadoRes->ven;
        if ($ret->res) {
          $this->response->setStatusCode(201, 'Ok');
        }
      }
    } catch (\Exception $e) {
      $this->response->setStatusCode(500, 'Error');
      $ret->cid = 0;
      $ret->msj = $e->getMessage();
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function ventaCrearCobrarAction() {
    $caja = $this->dispatcher->getParam('caja');
    $usuario = $this->dispatcher->getParam('usuario');
    $datos = $this->request->getJsonRawBody();
    $cobrado = $datos->Subtotal + $datos->SubtotalEx + $datos->Impuestos + $datos->Descuento + $datos->Recargo + $datos->Flete;
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
    $db = DI::getDefault()->getDb();
    $id = $this->dispatcher->getParam('id');    
    $ven = Ventas::findFirstById($id);
    if ($ven != false) {
      require_once APP_PATH . '/library/ComprobantesElectronicos.php';
      //$ce = new \ComprobantesElectronicos();
      try {
        $msj = \ComprobantesElectronicos::procesarFactura($ven, $db);
        //$msj = "Procesado correctamente";
      } catch (\Exception $e) {
        $this->response->setStatusCode(500, 'Error');  
        $msj = $e->getMessage();
      }
      /*$ven->estado = $est;
      if($ven->update()) {
        $msj = "La operacion se ejecuto exitosamente";
        $this->response->setStatusCode(200, 'Ok');
      } else {
        $this->response->setStatusCode(404, 'Error');
        $msj = "No se puede actualizar los datos: " . "\n";
        foreach ($ven->getMessages() as $m) {
          $msj .= $m . "\n";
        }
      }*/
    } else {
      $msj = "No se encontro el registro";
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($msj));
    $this->response->send();
    echo var_dump($msj);
  }
  #endregion

  private function ultimoNumeroVenta($tipo, $suc) {
    return Ventas::maximum([
      'column' => 'Numero',
      'conditions' => 'Tipo = ' . $tipo . ' AND SucursalId = ' . $suc
    ]) ?? 0;
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
      'ven' => null,
      'msj' => 'Error al crear'
    ];

    $num = intval($this->ultimoNumeroVenta($datos->Tipo, $datos->SucursalId)) + 1;
    $ven = new Ventas();
    /*if (property_exists($datos, 'UCodigo'))
      $ven->Codigo = $datos->UCodigo;*/
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
    $ven->CERespuestaTipo = $datos->CERespuestaTipo;
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
        if ($mi->Codigo) {
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
      $ret->ven = $min ? VentasMin::findFirstById($ret->cid) : Ventas::findFirstById($ret->cid);
    } else {
      $msj = "No se pudo crear el nuevo registro: " . "\n";
      foreach ($ven->getMessages() as $m) {
        $msj .= $m . "\n";
      }
      $ret->cid = 0;
      $ret->msj = $msj;
    }
    return $ret;
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
}
