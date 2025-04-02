<?php

namespace Pointerp\Controladores;

use DateTime;
use Exception;
use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\Inventarios\Kardex;
use Pointerp\Modelos\Maestros\Impuestos;
use Pointerp\Modelos\Maestros\ProductosImposiciones;
use Pointerp\Modelos\Ventas\CajaMovimientos;
use Pointerp\Modelos\Ventas\Cajas;
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
use Phalcon\Paginator\Adapter\Model as PaginatorModel;

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

  public function ventasBuscarPaginadoAction() {
    $this->view->disable();
    $suc = $this->dispatcher->getParam('sucursal'); // Solo se consulta una sucursal
    $tipoBusca = $this->dispatcher->getParam('tipo');
    $filtro = $this->dispatcher->getParam('filtro');
    $estado = $this->dispatcher->getParam('estado');
    $clase = $this->dispatcher->getParam('clase');
    $desde = $this->dispatcher->getParam('desde');
    $hasta = $this->dispatcher->getParam('hasta');
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

    $condicion = "Tipo in (11, 12) AND SucursalId = :suc:";
    $params = [ 'suc' => $suc ];
    $ventas = (object) [
      'completo' => false,
      'total' => 0,
      'items' => []
    ];
    if ($clase < 3) {      
      if ($clase <= 1) {
        $condicion .= " AND Fecha >= :desde: AND Fecha <= :hasta:";
        $params = array_merge([ 'desde' => $desde, 'hasta' => $hasta ], $params);
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
          $condicion .= " AND `Notas` like :filtro:";
          $params = array_merge([ 'filtro' => $filtro ], $params);
        }
      }
    } else {
      $condicion .= " AND Numero = :filtro:";
      $params = array_merge([ 'filtro' => $filtro ], $params);
    }

    $condicion .= ' AND Estado = 0';

    $paginator = new PaginatorModel([
      "model"      => Ventas::class,
      "parameters" => [
        'conditions' => $condicion,
        'bind' => $params,
        'order' => ($order ?? 'Fecha') . " {$orderDir}"
      ],
      "limit"      => $limit,
      "page"       => $page,
    ]);
    $pageData = $paginator->paginate();
    $ventas = (object) [
      'completo' => true,
      'total' => $pageData->getTotalItems(),
      'items' => $pageData->getItems()
    ];
    
    if (count($ventas->items) > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ventas));
    $this->response->send();
  }

  public function ventasDiarioAction() {
    $this->view->disable();
    $suc = $this->dispatcher->getParam('sucursal'); // Solo se consulta una sucursal    
    $estado = $this->dispatcher->getParam('estado');    
    $desde = $this->dispatcher->getParam('desde');
    $hasta = $this->dispatcher->getParam('hasta');
    $res = [];
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

  public function ventasDiarioCEAction() {
    $this->view->disable();
    $suc = $this->dispatcher->getParam('sucursal');
    $estado = $this->dispatcher->getParam('estado');    
    $desde = $this->dispatcher->getParam('desde');
    $hasta = $this->dispatcher->getParam('hasta');
    $condicionEstado = '';
    if ($estado == 0) {
      $condicionEstado = " AND ven.Estado = {$estado}";
    }
    $sql = "Select cast(ven.CERespuestaTipo as UNSIGNED) as Secuencial, ven.Id, ven.Tipo, ven.Numero, ven.Fecha, ven.CEAutorizaFecha, ven.CEClaveAcceso,
      ven.Subtotal, ven.SubtotalEx, ven.Impuestos, ven.Descuento, ven.Recargo, ven.Flete, ven.Estado, cli.Nombres, cli.Identificacion, ven.Tipo
      from Pointerp\Modelos\Ventas\Ventas ven
      left join Pointerp\Modelos\Maestros\Clientes cli on ven.ClienteId = cli.Id
      where ven.Tipo in (11, 12) AND ven.SucursalId = {$suc}{$condicionEstado} AND ven.Fecha between '{$desde}' and '$hasta' and ven.CEAutorizaFecha is not null
      order by Secuencial";
    $qry = new Query($sql, Di::getDefault());
    $res = $qry->execute();
    
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
      $enviar = $this->request->getQuery('enviar', null, false);
      try {
        $version = $this->request->getQuery('version', null, 4);
      } catch (Exception $ex) {
        $version = 4;
      }
      $autorizar = $autorizar === 'true';
      $enviar = $enviar === 'true';
      $version = intVal($version);
      $ret = (object) [
        'res' => false,
        'cid' => $datos->Id,
        'cve' => '',
        'sec' => '',
        'ven' => null,
        'msj' => 'Los datos no se pudieron procesar',
        'num' => $datos->Numero,
        'aut' => ''
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
          $nuevoCliente->Referencias = '';
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
        $ven->CEAutorizaFecha = $datos->CEAutorizaFecha;
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
          // eliminar ventaimpuestos
          $phqle = "DELETE FROM Pointerp\Modelos\Ventas\VentasImpuestos WHERE VentaId = {$datos->Id}";
          $qrye = new Query($phqle, Di::getDefault());
          $qrye->execute();
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
            if ($datos->Tipo == FACTURA) {
              $impsItem = []; 
              if (isset($mi->relProducto->relImposiciones)) {
                $impsItem = $mi->relProducto->relImposiciones;
              } else {
                $impsItem = ProductosImposiciones::find([
                  "conditions" => "ProductoId = {$mi->ProductoId}"
                ]);
              }
              if (count($impsItem) > 0) {
                foreach ($mi->relProducto->relImposiciones as $imps)
                {
                  $valor = 0;
                  $porcentaje = 0;
                  if (isset($imps->relImpuesto))
                  {
                    $porcentaje = $imps->relImpuesto->Porcentaje;
                    $valor = (($mi->Cantidad * $mi->Precio) * $porcentaje) / 100;
                  }
                  else
                  {
                    $impuesto = Impuestos::FindById($imps->ImpuestoId);
                    if (isset($impuesto))
                      $porcentaje = $impuesto->Porcentaje;
                      $valor = (($mi->Cantidad * $mi->Precio) * $porcentaje) / 100;
                  }

                  if ($valor > 0) {
                    $vimp = new VentasImpuestos();
                    $vimp->ImpuestoId = $imps->ImpuestoId;
                    $vimp->Porcentaje = $porcentaje;
                    $vimp->Valor = round($valor, 2);
                    $vimp->base = round(($mi->Cantidad * $mi->Precio), 2);
                    array_push($datos->relImpuestos, $vimp);
                  }
                }
              }
            }
          }
          
          if ($datos->Tipo == FACTURA) {        
            foreach ($datos->relImpuestos as $imp) {
              $o = new VentasImpuestos();
              $o->Id = 0;
              $o->CompraId = $ven->Id;
              $o->ImpuestoId = $imp->ImpuestoId;
              $o->Porcentaje = $imp->Porcentaje;
              $o->base = $imp->base;
              $o->Valor = $imp->Valor;
              if (!$o->create()) {
                $msj = "No se puede actualizar los datos: \n";
                foreach ($o->getMessages() as $m) {
                  $msj .= "{$m} \n";
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
        if (!$vendoble) {
          if ($generarCA && $datos->Tipo == 11) {
            $res = $this->generarClaveAcceso($datos->SucursalId);
            $datos->CEClaveAcceso = $res->clave;
            $datos->CERespuestaTipo = $res->secuencial;
          }
          $respVta = $this->guardarVentaNueva($datos, 0, false, $version);
          $ret->num = $respVta->ven->Numero;
          $ret->ven = $respVta->ven;
          $ret->cid = $respVta->ven->Id;
          $ret->msj = "Factura guardada exitosamente";
          if ($respVta->res) {
            if ($autorizar) {
              require_once APP_PATH . '/library/ComprobantesElectronicos.php';
              try {
                $paramCert = EmpresaParametros::findFirst([
                  'conditions' => "Tipo = 19 AND EmpresaId = {$respVta->ven->relSucursal->EmpresaId}"
                ]);
                \ComprobantesElectronicos::cargarCertificado($paramCert->Denominacion, $paramCert->Extendido);
                $result = \ComprobantesElectronicos::autorizarFactura($respVta->ven);
                if (isset($result)) {
                  $ventaGuardada = Ventas::findFirstById($respVta->ven->Id);
                  if ($result->respuesta == true) {
                    if (isset($ventaGuardada) && isset($result->comprobante)) {
                      $ahora = new DateTime();
                      $ret->aut = "{$result->comprobante->estado} - Clave de acceso: {$result->comprobante->numeroAutorizacion} | Fecha de autorizacion: {$result->comprobante->fechaAutorizacion}";
                      $ventaGuardada->CEAutorizacion = $ret->ven->CEClaveAcceso;
                      $ventaGuardada->CERespuestaMsj = $result->mensaje;
                      $ventaGuardada->CEAutorizaFecha = date_format(new DateTime(), 'Y-m-d H:i:s');
                      $ventaGuardada->CEContenido = "<autorizacion>" .
                        "<estado>{$result->comprobante->estado}</estado>" .
                        "<numeroAutorizacion>{$result->comprobante->numeroAutorizacion}</numeroAutorizacion>" .
                        "<fechaAutorizacion>{$result->comprobante->fechaAutorizacion}</fechaAutorizacion>" .
                        "<ambiente>{$result->comprobante->ambiente}</ambiente>" .
                        "<comprobante><![CDATA[{$result->comprobante->comprobante}]]></comprobante>" .
                        "</autorizacion>";
                      $ret->msj = "Factura autorizada";
                      if (!$ventaGuardada->update()) {
                        $ret->msj = "Error al actualizar la factura";
                        $msj = "No se pudo crear el nuevo registro: \n";
                        foreach ($ventaGuardada->getMessages() as $m) {
                          $msj .= "{$m} \n";
                        }
                        $m = 1;
                      }
                      if ($enviar) {
                        $ventaEnviar = Ventas::findFirstById($respVta->ven->Id);
                        $fc = new FirmaElectronicaController();
                        $respEnvio = $fc->enviarCorreoComprobante($ventaEnviar);
                        if ($respEnvio->res) {
                          $ret->msj .= " y enviada por correo exitosamente";
                        } else {
                          $ret->msj .= ", pero no se pudo enviar por correo";
                        }
                      }
                    }
                  } else {
                    if (isset($ventaGuardada)) {
                      $data = json_decode($result->mensaje, true);
                      $autMensaje = $data['mensajes']['mensaje']['mensaje'] ?? 'DEVUELTA';
                      $autInfoAdicional = $data['mensajes']['mensaje']['informacionAdicional'] ?? 'MOTIVO NO DETERMINADO';
                      $ret->aut = "{$result->proceso} - {$autMensaje}: {$autInfoAdicional}";
                      $ventaGuardada->CERespuestaMsj = "{$result->proceso} - {$autMensaje}: {$autInfoAdicional}";
                      $ventaGuardada->CEContenido = $result->comprobante ?? "";
                      if (!$ventaGuardada->update()) {
                        $ret->msj = "Error al actualizar la factura";
                        $msj = "No se pudo crear el nuevo registro: \n";
                        foreach ($ventaGuardada->getMessages() as $m) {
                          $msj .= "{$m} \n";
                        }
                        $m = 1;
                      }
                    }
                  }                  
                } else {
                  // respuesta nula
                }
              } catch (Exception $e) {
                $this->response->setStatusCode(500, 'Error');  
                $ret->msj = $e->getMessage();
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
      $fechaComparar = (new DateTime($datos->Fecha))->format('Y-m-d H:i:s');
      $cmd = "SELECT Id FROM Pointerp\Modelos\Ventas\Ventas 
        WHERE Tipo = :tipo: 
        AND SucursalId = :sucursalId: 
        AND Fecha = :fecha:";
      $qry = new Query($cmd, Di::getDefault());
      $rws = $qry->execute([
          'tipo' => $datos->Tipo,
          'sucursalId' => $datos->SucursalId,
          'fecha' => $fechaComparar
      ]);
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
      $respVta = $this->guardarVentaNueva($datos, $cobrado, false);
      if ($respVta->res) {
        $vta = $respVta->ven;
        $cobroNum = $this->ultimoNumeroCobro(16, $datos->SucursalId) + 1;
        $cobro = new Comprobantes();
        $cobro->Tipo = 16; // (int)EntidadesEnum.EnCobro
        $cobro->Fecha = date_format(new DateTime(),"Y-m-d H:i:s");
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
            $cobitem->Fecha = date_format(new DateTime(),"Y-m-d H:i:s");
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
                $msj .= "{$m}\n";
              }
              $ret->msj = $msj;
            }
          } else {
            $msj = "No se pudo crear el nuevo Documento: " . "\n";
            foreach ($doc->getMessages() as $m) {
              $msj .= "{$m}\n";
            }
            $ret->msj = $msj;
          }
        } else {
          $msj = "No se pudo crear el nuevo cobro:\n";
          foreach ($cobro->getMessages() as $m) {
            $msj .= "{$m}\n";
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
        $resItems = VentasItems::find([
          "conditions" => "VentaId = {$res->Id}"
        ]);
        $res->relItems = $resItems;
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
    $id = $this->dispatcher->getParam('sendEmail',  false);
    $ven = Ventas::findFirstById($id);
    if ($ven != false) {
      require_once APP_PATH . '/library/ComprobantesElectronicos.php';
      try {
        $paramCert = EmpresaParametros::findFirst([
          'conditions' => "Tipo = 19 AND EmpresaId = {$ven->relSucursal->EmpresaId}"
        ]);
        \ComprobantesElectronicos::cargarCertificado($paramCert->Denominacion, $paramCert->Extendido);
        $result = \ComprobantesElectronicos::autorizarFactura($ven);
        if (isset($result)) {          
          if ($result->respuesta == true) {
            if (isset($result->comprobante)) {
              $ven->CEAutorizacion = $ven->CEClaveAcceso;
              $ven->CERespuestaMsj = $result->mensaje;
              $ven->CEAutorizaFecha = date_format(new DateTime(), 'Y-m-d H:i:s');
              $ven->CEContenido = "<autorizacion>" .
                "<estado>{$result->comprobante->estado}</estado>" .
                "<numeroAutorizacion>{$result->comprobante->numeroAutorizacion}</numeroAutorizacion>" .
                "<fechaAutorizacion>{$result->comprobante->fechaAutorizacion}</fechaAutorizacion>" .
                "<ambiente>{$result->comprobante->ambiente}</ambiente>" .
                "<comprobante><![CDATA[{$result->comprobante->comprobante}]]></comprobante>" .
                "</autorizacion>";
              if ($ven->update()) {
                $msj = "Factura autorizada";
                $fc = new FirmaElectronicaController();
                $respEnvio = $fc->enviarCorreoComprobante($ven);
                if ($respEnvio->res) {
                  $msj .= " y enviada por correo exitosamente";
                } else {
                  $msj = ", pero no se pudo enviar por correo";
                }
              }
            }
          } else {
            if (isset($ven)) {
              $data = json_decode($result->mensaje, true);
              $autMensaje = $data['mensajes']['mensaje']['mensaje'] ?? 'DEVUELTA';
              $autInfoAdicional = $data['mensajes']['mensaje']['informacionAdicional'] ?? 'MOTIVO NO DETERMINADO';
              $ven->CERespuestaMsj = "{$result->proceso} - {$autMensaje}: {$autInfoAdicional}";
              $ven->CEContenido = $result->comprobante ?? "";
            }
          }
          $ven->update();
        }
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

  public function ventaGenerarSecuencialCEAction() {
    $sucursal = $this->dispatcher->getParam('sucursal');
    $num = $this->generarSecuencialCE($sucursal);
    $this->response->setStatusCode(200, 'Ok');
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($num));
    $this->response->send();
  }

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

  public function ultimoNumeroVentaCEAction($tipo, $suc) {
    try {
      $sql = "Select cast(ven.CERespuestaTipo as UNSIGNED) as Secuencial, ven.Id, ven.Tipo, ven.Numero, ven.Fecha, ven.CEAutorizaFecha, ven.CEClaveAcceso,
        ven.Subtotal, ven.SubtotalEx, ven.Impuestos, ven.Descuento, ven.Recargo, ven.Flete, ven.Estado, cli.Nombres, cli.Identificacion, ven.CERespuestaMsj
        from Pointerp\Modelos\Ventas\Ventas ven
        left join Pointerp\Modelos\Maestros\Clientes cli on ven.ClienteId = cli.Id
        where ven.Tipo IN (11, 12) AND ven.SucursalId = {$suc} AND 
        (ven.CEClaveAcceso is not null AND TRIM(ven.CEClaveAcceso) != '') AND 
		    (ven.CERespuestaTipo is not null AND TRIM(ven.CERespuestaTipo) != '' AND cast(ven.CERespuestaTipo as UNSIGNED) > 0)
        order by Secuencial";
      $qry = new Query($sql, Di::getDefault());
      $res = $qry->execute();
      
      if ($res->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
      } else {
        $this->response->setStatusCode(404, 'Not found');
      }
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

  private function guardarVentaNueva($datos, $cobrado, $min, $version = 4) {
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
            if ($datos->Tipo == FACTURA) {
              if ($version == 4) {
                if (!isset($mi->relProducto->relImposiciones) || count($mi->relProducto->relImposiciones) > 0) {
                  $mi->relProducto->relImposiciones = ProductosImposiciones::find([
                    "conditions" => "ProductoId = ${$mi->ProductoId}"
                  ]);
                }
                foreach ($mi->relProducto->relImposiciones as $imps)
                {
                  $valor = 0;
                  $porcentaje = 0;
                  if (isset($imps->relImpuesto))
                  {
                    $porcentaje = $imps->relImpuesto->Porcentaje;
                    $valor = (($mi->Cantidad * $mi->Precio) * $porcentaje) / 100;
                  }
                  else
                  {
                    $impuesto = Impuestos::FindById($imps->ImpuestoId);
                    if (isset($impuesto))
                      $porcentaje = $impuesto->Porcentaje;
                      $valor = (($mi->Cantidad * $mi->Precio) * $porcentaje) / 100;
                  }
                  if ($valor > 0) {
                    $vimp = new VentasImpuestos();
                    $vimp->VentaId = $ven->Id;
                    $vimp->ImpuestoId = $imps->ImpuestoId;
                    $vimp->Porcentaje = $porcentaje;
                    $vimp->Valor = round($valor, 2);
                    $vimp->base = round(($mi->Cantidad * $mi->Precio), 2);
                    array_push($datos->relImpuestos, $vimp);
                  }
                }
              } else {
                $impuesto = Impuestos::findFirst([
                  "conditions" => "Porcentaje = 0 AND Estado = 0"
                ]);

                $impuestoVigenteId = 0;
                $empresaParam = EmpresaParametros::findFirst([
                  "conditions" => "EmpresaId = {$ven->relSucursal->EmpresaId} AND Tipo = 1 AND Referencia = 11"
                ]);
                if (isset($empresaParam)) {
                  $impuestoVigenteId = $empresaParam->RegistroId;
                }
                $impuestoId = $mi->relProducto->Existencia > 0 ? $impuestoVigenteId : $mi->relProducto->Marca;  
                if ($impuestoId > 0) {
                  $impuestoFind = Impuestos::findFirst([
                    "conditions" => "Activo = 1 ORDER BY Porcentaje DESC LIMIT 1"
                  ]);
                  if (isset($impuestoFind)) {
                    $impuesto = $impuestoFind;
                  }
                }
                if (isset($impuesto)) {
                  $porcentaje = $impuesto->Porcentaje;
                  $valor = 0;
                  if ($porcentaje > 0) {
                    $valor = (($mi->Cantidad * $mi->Precio) * $porcentaje) / 100;  
                  }
                  $vimp = new VentasImpuestos();
                  if ($valor > 0) {
                    $vimp = new VentasImpuestos();
                    $vimp->VentaId = $ven->Id;
                    $vimp->ImpuestoId = $imps->ImpuestoId;
                    $vimp->Porcentaje = $porcentaje;
                    $vimp->Valor = round($valor, 2);
                    $vimp->base = round(($mi->Cantidad * $mi->Precio), 2);
                    array_push($datos->relImpuestos, $vimp);
                  }
                }
              }
            }  
          }
          if ($datos->Tipo == FACTURA) {
            foreach ($datos->relImpuestos as $im) {
              $ins = new VentasImpuestos();
              $ins->VentaId = $ven->Id;
              $ins->ImpuestoId = $im->ImpuestoId;
              $ins->Porcentaje = $im->Porcentaje;
              $ins->base = $im->base;
              $ins->Valor = $im->Valor;
              $ins->create();
            }
          }
          $ret->ven = $ven;
          $ret->cve = $datos->CEClaveAcceso;
          $ret->sec = $datos->CERespuestaTipo;
        } else {
          $msj = "No se pudo crear el nuevo registro: \n";
          foreach ($ven->getMessages() as $m) {
            $msj .= "{$m} \n";
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
    // Secuencial
    $sucursal = Sucursales::findFirstById($sucursalId);
    $empresa = Empresas::findFirstById($sucursal->EmpresaId);
    $serie = $sucursal->Codigo . trim($sucursal->Descripcion);
    $numSig = $this->generarSecuencialCE($sucursalId);

    $tipoDatos = (object) [
      'tipoDocumento' => '01', // 01: FACTURA
      'tipoEmision' => '1',  // OFFLINE UNICO VALOR VALIDO
      'ambiente' => '1' // 1: PRUEBAS; 2: PRODUCCION
    ];

    // Ambiente
    $ruc = "9999999999999";
    if (isset($empresa)) {
      $ruc = $empresa->Ruc;
      $reg = Registros::findFirstById($empresa->TipoAmbiente);
      if (isset($reg))
        $tipoDatos->ambiente = $reg->Codigo;
    }

    // Clave de acceso
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

    $retorno = (object) [
      'clave' => $clave,
      'secuencial' => $numSig
    ];

    return $retorno;
  }

  private function generarSecuencialCE($sucursalId) {
    $sucursal = Sucursales::findFirstById($sucursalId);
    $empresa = Empresas::findFirstById($sucursal->EmpresaId);
    $paramFaEmpresa = EmpresaParametros::findFirst([
      'conditions' => "Tipo = 1 AND Referencia = 11 AND EmpresaId = {$empresa->Id}" 
    ]);

    $numSig = intval($paramFaEmpresa->Indice);
    $numSig++;
    $existe = true;
    while($existe) {
      $rows = Ventas::find([
        'conditions' => 'Tipo = 11 AND CERespuestaTipo = :num: AND SucursalId = :suc:',
        'bind' => [
            'num' => $numSig,
            'suc' => $sucursalId
          ]
      ]);
      if ($rows->count() > 0) {
        $numSig++;
      } else {
        $existe = false;
      }
    }

    $paramFaEmpresa->Indice = $numSig;
    $paramFaEmpresa->update();

    return $numSig;
  }

  #endregion

  #region cajas
  public function cajasPorEstadoAction() {
    $this->view->disable();
    $estado = $this->dispatcher->getParam('estado');
    $condiciones = '';
    if ($estado == 0) {
      $condiciones = 'Estado = 0';
    }

    $rows = Cajas::find([
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

  public function cajaGuardarAction() {
    $datos = $this->request->getJsonRawBody();
    $ret = (object) [
      'res' => false,
      'cid' => $datos->Id,
      'msj' => 'Los datos no se pudieron procesar'
    ];
    try {
      $newCaja = new Cajas();
      if ($datos->Id > 0) {
        $newCaja = Cajas::findFirstById($datos->Id);
      }
      $newCaja->Codigo = $datos->Codigo;
      $newCaja->Descripcion = $datos->Descripcion;
      $newCaja->Saldo = $datos->Saldo;
      $newCaja->Cierre = $datos->Cierre;
      $newCaja->Estado = $datos->Estado;
      $newCaja->EmpresaId = $datos->EmpresaId;
      $newCaja->Referencia = $datos->Referencia;
      if ($datos->Id > 0) {
        if (!$newCaja->update()) {
          $this->response->setStatusCode(500, 'Error');  
          $msj = "No se pudo guadar la caja: " . "\n";
          foreach ($newCaja->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = 0;
          $ret->msj = $msj;
        }
      } else {
        if (!$newCaja->create()) {
          $this->response->setStatusCode(500, 'Error');  
          $msj = "No se pudo crear la nueva caja: " . "\n";
          foreach ($newCaja->getMessages() as $m) {
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

  public function cajaModificarEstadoAction() {
    $estado = $this->dispatcher->getParam('estado');
    $id = $this->dispatcher->getParam('id');
    $result = (Object) [
      "completo" => false,
      "mensaje" => "La operación no se pudo completar"
    ];
    $this->response->setStatusCode(422, 'Unprocessable Content');
    $caja = Cajas::findFirstById($id);
    if ($caja) {
      $caja->Estado = $estado;
      if($caja->update()) {
        $result->completo = true;
        $result->mensaje = "Registro actualizado exitosamente";
        $this->response->setStatusCode(201, 'Ok');
      } else {
        $result->mensaje = "Error al intentar modificar la caja";
      }
    } else {
      $result->mensaje = "No se encontro la caja";
      $this->response->setStatusCode(404, 'Not found');
    }

    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($result));
    $this->response->send();
  }

  // Vales
  public function valesBuscarAction() {
    $this->view->disable();
    $suc = $this->dispatcher->getParam('sucursal'); // Solo se consulta una sucursal
    $tipoBusca = $this->dispatcher->getParam('tipo');
    $filtro = $this->dispatcher->getParam('filtro');
    $estado = $this->dispatcher->getParam('estado');
    $clase = $this->dispatcher->getParam('clase');
    $desde = $this->dispatcher->getParam('desde');
    $hasta = $this->dispatcher->getParam('hasta'); 
    try {
      $page = $this->request->getQuery('page', null, 0);
      $limit = $this->request->getQuery('limit', null, 0);
      $order = $this->request->getQuery('order', null, 'Fecha');
      $orderDir = $this->request->getQuery('dir', null, '');
      $externo = $this->request->getQuery('ext', null, false);
    } catch (Exception $ex) {
      $page = 0;
      $limit = 0;
      $order = 'Nombres';
      $orderDir = '';
      $externo = false;
    }

    $condicion = "SucursalId = :suc:";
    $params = [ 'suc' => $suc ];
    $res = [];
    if ($clase < 3) {      
      if ($clase <= 1) {
        $condicion .= ' AND Fecha >= :desde: AND Fecha <= :hasta:';
        $params = array_merge([ 'desde' => $desde, 'hasta' => $hasta ], $params);
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
          $condicion .= " AND `Notas` like :filtro:";
          $params = array_merge([ 'filtro' => $filtro ], $params);
        }
      }
    } else {
      $condicion .= " AND Numero = :filtro:";
      $params = array_merge([ 'filtro' => $filtro ], $params);
    }

    if (strlen($condicion) > 0) {
      $condicion .= ' AND Estado = 0';
      $res = CajaMovimientos::find([
        'conditions' => $condicion,
        'bind' => $params,
        'order' => 'Fecha'
      ]);
    }

    $paginator = new PaginatorModel([
      "model"      => CajaMovimientos::class,
      "parameters" => [
        'conditions' => $condicion,
        'bind' => $params,
        'order' => ($order ?? 'Fecha') . " {$orderDir}"
      ],
      "limit"      => $limit,
      "page"       => $page,
    ]);
    $pageData = $paginator->paginate();
    $vales = (object) [
      'completo' => true,
      'total' => $pageData->getTotalItems(),
      'items' => $pageData->getItems()
    ];

    if ($pageData->getTotalItems() > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($vales));
    $this->response->send();
  }

  public function valeGuardarAction() {
    $datos = $this->request->getJsonRawBody();
    $ret = (object) [
      'res' => false,
      'cid' => $datos->Id,
      'msj' => 'Los datos no se pudieron procesar'
    ];
    try {
      $newCaja = new Cajas();
      if ($datos->Id > 0) {
        $newCaja = Cajas::findFirstById($datos->Id);
      }
      $newCaja->Codigo = $datos->Codigo;
      $newCaja->Descripcion = $datos->Descripcion;
      $newCaja->Saldo = $datos->Saldo;
      $newCaja->Cierre = $datos->Cierre;
      $newCaja->Estado = $datos->Estado;
      $newCaja->EmpresaId = $datos->EmpresaId;
      $newCaja->Referencia = $datos->Referencia;
      if ($datos->Id > 0) {
        if (!$newCaja->update()) {
          $this->response->setStatusCode(500, 'Error');  
          $msj = "No se pudo guadar la caja: " . "\n";
          foreach ($newCaja->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = 0;
          $ret->msj = $msj;
        }
      } else {
        if (!$newCaja->create()) {
          $this->response->setStatusCode(500, 'Error');  
          $msj = "No se pudo crear la nueva caja: " . "\n";
          foreach ($newCaja->getMessages() as $m) {
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

  public function valeModificarEstadoAction() {
    $estado = $this->dispatcher->getParam('estado');
    $id = $this->dispatcher->getParam('id');
    $result = (Object) [
      "completo" => false,
      "mensaje" => "La operación no se pudo completar"
    ];
    $this->response->setStatusCode(422, 'Unprocessable Content');
    $vale = CajaMovimientos::findFirstById($id);
    if ($vale) {
      $vale->Estado = $estado;
      if($vale->update()) {
        $result->completo = true;
        $result->mensaje = "Registro actualizado exitósamente";
        $this->response->setStatusCode(201, 'Ok');
      } else {
        $result->mensaje = "Error al intentar modificar el vale";
      }
    } else {
      $result->mensaje = "No se encontró el vale";
      $this->response->setStatusCode(404, 'Not found');
    }

    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($result));
    $this->response->send();
  }

  public function cuadreCajaMovimientosAction() {
    $caja = $this->request->getQuery('caja', null, 0);
    $sucursal = $this->request->getQuery('sucursal', null, 0);
    $desde = $this->request->getQuery('desde', null, null);
    $hasta = $this->request->getQuery('hasta', null, null);
    $referenciales = $this->request->getQuery('referenciales', null, false);
    
    $result = [];
    $condicionSucursal = '';
    $paramSucursal = [];
    if (true) {
      $condicionSucursal = " AND SucursalId == :sucursal:";
      $paramSucursal = [ 'sucursal' => $sucursal ];
    }
    
    // Vales de transferencia
    $sumaTransfers = CajaMovimientos::sum([
      'column'     => 'Valor', //$empresa = Empresas::findFirstById($sucursal->EmpresaId);
      'condition' => "Tipo = " . TRANSFERENCIA_CAJA . "{$condicionSucursal} AND CajaId == :caja: AND Estado == 0 AND Fecha BETWEEN :desde: AND :hasta:",
      'bind' => [ 
        'caja' => $caja,
        'desde' => $desde,
        'hasta' => $hasta,
        ...$paramSucursal
      ]
    ]);
    if ($sumaTransfers > 0) {
      $result[] = [
        "Descripcion" => "Transferencia de caja",
        "Valor" => $sumaTransfers
      ];
    }

    // Traer cobros en efectivo del periodo
    $cobrosContado = $this->modelsManager->createBuilder()
      ->columns([
        'SUM(ci.Valor)'
      ])
      ->from(['d' => 'ComprobanteDocumentos'])
      ->join('Comprobantes', 'd.ComprobanteId = c.Id', 'c')
      ->join('ComprobanteItems', 'd.ComprobanteId = ci.ComprobanteId', 'ci')
      ->join('Ventas', 'd.Referencia = v.Id', 'v')
      ->where('c.Tipo = :tipo:', ['tipo' => COBRO])
      ->andWhere('ci.Origen = :origen:', ['origen' => COBRO_EFECTIVO])
      ->andWhere('c.Estado = 0')
      ->andWhere('ci.Numero = :numero:', ['numero' => $caja])
      ->andWhere('c.SucursalId = :sucursalId:', ['sucursalId' => $sucursal])
      ->andWhere('c.Concepto != :concepto:', ['concepto' => ABONOS_EFECTIVO])
      ->andWhere('c.Fecha BETWEEN :desde: AND :hasta:', ['desde' => $desde, 'hasta' => $hasta])
      ->andWhere('Date(v.Fecha) = Date(c.Fecha)')
      ->getQuery()
      ->execute();

    if ($cobrosContado != null && $cobrosContado > 0) {
      $result[] = [
        "Descripcion" => "Ventas del periodo cobradas en efectivo",
        "Valor" => $cobrosContado
      ];
    }

    $cobrosCartera = $this->modelsManager->createBuilder()
      ->columns([
        'SUM(ci.Valor)'
      ])
      ->from(['d' => 'comprobantedocumentos'])
      ->join('comprobantes', 'd.ComprobanteId = c.Id', 'c')
      ->join('comprobanteitems', 'd.ComprobanteId = ci.ComprobanteId', 'ci')
      ->join('ventas', 'd.Referencia = v.Id', 'v')
      ->where('c.Tipo = :tipo:', ['tipo' => COBRO])
      ->andWhere('ci.Origen = :origen:', ['origen' => COBRO_EFECTIVO])
      ->andWhere('c.Estado = 0')
      ->andWhere('ci.Numero = :numero:', ['numero' => $caja])
      ->andWhere('c.SucursalId = :sucursalId:', ['sucursalId' => $sucursal])
      ->andWhere('c.Concepto != :concepto:', ['concepto' => ABONOS_EFECTIVO])
      ->andWhere('c.Fecha BETWEEN :desde: AND :hasta:', ['desde' => $desde, 'hasta' => $hasta])
      ->andWhere('Date(v.Fecha) < Date(c.Fecha)')
      ->getQuery()
      ->execute();

    if ($cobrosCartera != null && $cobrosCartera > 0) {
      $result[] = [
        "Descripcion" => "Ventas anteriores cobradas en efectivo",
        "Valor" => $cobrosCartera
      ];
    }
    
    // Traer abonos en efectivo a pedidos reservados del periodo
    $abonosPedidos = $this->modelsManager->createBuilder()
      ->columns([
        'SUM(ci.Valor)'
      ])
      ->from(['ci' => 'comprobanteitems'])
      ->join('comprobantes', 'ci.ComprobanteId = c.Id', 'c')
      ->where('c.Tipo = :tipo:', ['tipo' => COBRO])
      ->andWhere('ci.Origen = :origen:', ['origen' => COBRO_EFECTIVO])
      ->andWhere('c.Estado = 0')
      ->andWhere('ci.Numero = :numero:', ['numero' => $caja])
      ->andWhere('c.SucursalId = :sucursalId:', ['sucursalId' => $sucursal])
      ->andWhere('c.Concepto = :concepto:', ['concepto' => ABONOS_EFECTIVO])
      ->andWhere('c.Fecha BETWEEN :desde: AND :hasta:', ['desde' => $desde, 'hasta' => $hasta])
      ->getQuery()
      ->execute();
    if ($abonosPedidos != null && $abonosPedidos > 0) {
      $result[] = [
        "Descripcion" => "Abonos a pedidos reservados con efectivo del periodo",
        "Valor" => $abonosPedidos
      ];
    }

    // Traer cheques postfechados ejecutados en efectivo (cambiados por ventanilla o pagos en efectivo por el cliente)
    // (16 = EnCobro, 36 = CobroCheque, Base 1 = Ejecutado, Autorizacion 2 = Caja chica)
    $chequesEjecutados = $this->modelsManager->createBuilder()
      ->columns([
        'SUM(ci.Valor)'
      ])
      ->from(['ci' => 'comprobanteitems'])
      ->join('comprobantes', 'ci.ComprobanteId = c.Id', 'c')
      ->where('c.Tipo = :tipo:', ['tipo' => COBRO])
      ->andWhere('ci.Origen = :origen:', ['origen' => COBRO_CHEQUE])
      ->andWhere('ci.Base = 1')
      ->andWhere('ci.Autorizacion = 2')
      ->andWhere('c.Estado = 0')
      ->andWhere('c.SucursalId = :sucursalId:', ['sucursalId' => $sucursal])
      ->andWhere('c.Concepto = :concepto:', ['concepto' => ABONOS_EFECTIVO])
      ->andWhere('c.Fecha BETWEEN :desde: AND :hasta:', ['desde' => $desde, 'hasta' => $hasta])
      ->getQuery()
      ->execute();
    if ($chequesEjecutados != null && $chequesEjecutados > 0) {
      $result[] = [
        "Descripcion" => "Abonos a pedidos reservados con efectivo del periodo",
        "Valor" => $chequesEjecutados
      ];
    }
    /*
    from ci in Contexto.ComprobanteItems
        join c in Contexto.Comprobantes on ci.ComprobanteId equals c.Id
        where c.Tipo == (int)EntidadesEnum.EnCobro && ci.Origen == (int)EntidadesEnum.EnCobroCheque && ci.Base == 1 &&
        ci.Autorizacion == "2" && c.Estado == 0 && ci.Fraccion == pCaja &&
        c.SucursalId == psuc && ci.Ejecucion >= pFini && ci.Ejecucion <= pFcor
     */

    // Traer vales de caja (31 = EnValeCaja)
    // Traer Pago a proveedores (34 = EnPago; 31 = EnValeCaja)
    // Traer Otros Pagos en efectivo (46 = EnOtroPago; 31 = EnValeCaja)
    if ($referenciales) {

    }

    return $result;
  }

  /*public IEnumerable<CuadreCajaItem> CuadreCajaMovimientos(int pCaja, DateTime pFini, DateTime pFcor, int psuc, bool referenciales)
  {
    

    if (referenciales)
    {
        // Cobros con tarjeta de credito
        IEnumerable<CobroPeriodo> cobst =
            (from ci in Contexto.ComprobanteItems
             join c in Contexto.Comprobantes on ci.ComprobanteId equals c.Id
             where c.Tipo == (int)EntidadesEnum.EnCobro && ci.Origen == (int)EntidadesEnum.EnCobroTarjeta && c.Estado == 0 &&
             c.SucursalId == psuc && c.Fecha >= pFini && c.Fecha <= pFcor
             select new CobroPeriodo
             {
                 Id = ci.Id,
                 Valor = ci.Valor,
             }
        ).AsEnumerable();
        double? dt = cobst.Sum(s => s.Valor);
        if (dt != null && dt > 0)
        {
            CuadreCajaItem i = new CuadreCajaItem("Ventas del periodo cobradas con tarjeta", 0, 0, (double)dt);
            res.Add(i);
        }

        // Cobros con cheque
        IEnumerable<CobroPeriodo> cobsq =
            (from ci in Contexto.ComprobanteItems
             join c in Contexto.Comprobantes on ci.ComprobanteId equals c.Id
             where c.Tipo == (int)EntidadesEnum.EnCobro && ci.Origen == (int)EntidadesEnum.EnCobroCheque && c.Estado == 0 &&
             c.SucursalId == psuc && c.Fecha >= pFini && c.Fecha <= pFcor
             select new CobroPeriodo
             {
                 Id = ci.Id,
                 Valor = ci.Valor,
             }
        ).AsEnumerable();
        double? dq = cobsq.Sum(s => s.Valor);
        if (dq != null && dq > 0)
        {
            CuadreCajaItem i = new CuadreCajaItem("Ventas del periodo cobradas en cheques", 0, 0, (double)dq);
            res.Add(i);
        }

        // Cobros con deposito
        IEnumerable<CobroPeriodo> cobsDep =
            (from ci in Contexto.ComprobanteItems
             join c in Contexto.Comprobantes on ci.ComprobanteId equals c.Id
             where c.Tipo == (int)EntidadesEnum.EnCobro && ci.Origen == (int)EntidadesEnum.EnDeposito && c.Estado == 0 &&
             c.SucursalId == psuc && c.Fecha >= pFini && c.Fecha <= pFcor
             select new CobroPeriodo
             {
                 Id = ci.Id,
                 Valor = ci.Valor,
             }
        ).AsEnumerable();
        double? dep = cobsDep.Sum(s => s.Valor);
        if (dep != null && dep > 0)
        {
            CuadreCajaItem iDeps = new CuadreCajaItem("Ventas del periodo cobradas con depositos", 0, 0, (double)dep);
            res.Add(iDeps);
        }
    }

    // Traer cheques postfechados ejecutados en efectivo (cambiados por ventanilla o pagos en efectivo por el cliente)
    // (16 = EnCobro, 36 = CobroCheque, Base 1 = Ejecutado, Autorizacion 2 = Caja chica)
    IEnumerable<CobroPeriodo> cobsc = 
        (from ci in Contexto.ComprobanteItems
        join c in Contexto.Comprobantes on ci.ComprobanteId equals c.Id
        where c.Tipo == (int)EntidadesEnum.EnCobro && ci.Origen == (int)EntidadesEnum.EnCobroCheque && ci.Base == 1 &&
        ci.Autorizacion == "2" && c.Estado == 0 && ci.Fraccion == pCaja &&
        c.SucursalId == psuc && ci.Ejecucion >= pFini && ci.Ejecucion <= pFcor
        select new CobroPeriodo
        {
            Id = ci.Id,
            Valor = ci.Valor
        }
    ).AsEnumerable();
    double? dc = cobsc.Sum(s => s.Valor);
    if (dc != null && dc > 0)
    {
        CuadreCajaItem i = new CuadreCajaItem("Cheques de clientes cobrados en efectivo", (double)dc, 0);
        res.Add(i);
    }

    // Traer vales de caja (31 = EnValeCaja)
    IEnumerable<CobroPeriodo> vales =
        (from m in Contexto.CajaMovimientos
         join c in Contexto.Cajas on m.CajaId equals c.Id
         where m.Tipo == (int)EntidadesEnum.EnValeCaja && m.CajaId == pCaja && m.SucursalId == psuc && m.Estado == 0 &&
         m.Fecha >= pFini && m.Fecha <= pFcor
         select new CobroPeriodo
         {
             Id = m.Id,
             Valor = m.Valor
         }
        ).AsEnumerable();
    double? dv = vales.Sum(s => s.Valor);
    if (dv != null && dv > 0)
    {
        CuadreCajaItem i = new CuadreCajaItem("Vales de caja", 0, (double)dv);
        res.Add(i);
    }

    // Traer Pago a proveedores (34 = EnPago; 31 = EnValeCaja)
    IEnumerable<CobroPeriodo> pgsp =
        (from ci in Contexto.ComprobanteItems
         join c in Contexto.Comprobantes on ci.ComprobanteId equals c.Id
         where c.Tipo == (int)EntidadesEnum.EnPago && ci.Origen == (int)EntidadesEnum.EnValeCaja && ci.Numero == pCaja && c.SucursalId == psuc &&
         c.Estado == 0 && c.Fecha >= pFini && c.Fecha <= pFcor
         select new CobroPeriodo
         {
             Id = ci.Id,
             Valor = ci.Valor
         }
    ).AsEnumerable();
    double? dp = pgsp.Sum(s => s.Valor);
    if (dp != null && dp > 0)
    {
        CuadreCajaItem i = new CuadreCajaItem("Pagos a proveedores", 0, (double)dp);
        res.Add(i);
    }

    // Traer Otros Pagos en efectivo (46 = EnOtroPago; 31 = EnValeCaja)
    IEnumerable<CobroPeriodo> pgst =
        (from ci in Contexto.ComprobanteItems
         join c in Contexto.Comprobantes on ci.ComprobanteId equals c.Id
         where c.Tipo == (int)EntidadesEnum.EnOtroPago && ci.Origen == (int)EntidadesEnum.EnValeCaja && ci.Numero == pCaja && c.SucursalId == psuc &&
         c.Estado == 0 && c.Fecha >= pFini && c.Fecha <= pFcor
         select new CobroPeriodo
         {
             Id = ci.Id,
             Valor = ci.Valor
         }
    ).AsEnumerable();
    double? pt = pgst.Sum(s => s.Valor);
    if (pt != null && pt > 0)
    {
        CuadreCajaItem i = new CuadreCajaItem("Comprobantes de egreso", 0, (double)pt);
        res.Add(i);
    }
    
    return res;
  }*/

  // Ajustes de caja
  // Crear ajuste caja

  #endregion
}
