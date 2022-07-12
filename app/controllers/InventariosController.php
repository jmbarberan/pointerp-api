<?php

namespace Pointerp\Controladores;

use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\Maestros\Productos;
use Pointerp\Modelos\Maestros\ProductosPrecios;
use Pointerp\Modelos\Maestros\ProductosImposiciones;
use Pointerp\Modelos\Inventarios\Bodegas;
use Pointerp\Modelos\Inventarios\Compras;
use Pointerp\Modelos\Inventarios\ComprasItems;
use Pointerp\Modelos\Inventarios\ComprasImpuestos;
use Pointerp\Modelos\Inventarios\Kardex;
use Pointerp\Modelos\Inventarios\Movimientos;
use Pointerp\Modelos\Inventarios\MovimientosItems;
use Pointerp\Modelos\Inventarios\Existencias;
use Pointerp\Modelos\Maestros\Registros;

class InventariosController extends ControllerBase  {

  #region PRODUCTOS
  public function productoPorIdAction() {
    $this->view->disable();
    $id = $this->dispatcher->getParam('id');
    $res = Productos::findFirstById($id);

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

  public function productosBuscarAction() {
    $this->view->disable();
    $tipoBusca = $this->dispatcher->getParam('tipo');
    $estado = $this->dispatcher->getParam('estado');
    $filtro = $this->dispatcher->getParam('filtro');
    $empresa = $this->dispatcher->getParam('emp');
    $atrib = $this->dispatcher->getParam('atrib');
    $filtro = str_replace('%20', ' ', $filtro);
    $filtro = str_replace('%C3%91' , 'Ñ',$filtro);
    $filtro = str_replace('%C3%B1' , 'ñ',$filtro);
    if ($atrib == 0) {
      if ($tipoBusca == 0) {
        $filtro .= '%';
      } else {
        $filtroSP = str_replace('  ', ' ',trim($filtro));
        $filtro = '%' . str_replace(' ' , '%',$filtroSP) . '%';
      }
    }

    $campo = "Nombre like '" . $filtro . "'";
    if($atrib == 1) {
      $campo = "Codigo = '" . $filtro . "'";
    };

    $condicion = "EmpresaId = " . $empresa . " AND " . $campo;
    if ($estado == 0) {
        $condicion .= ' AND Estado = 0';
    }
    
    $res = Productos::find([
      'conditions' => $condicion,
      'order' => 'Nombre'
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

  public function productosEmpresaEstadoAction() {
    $this->view->disable();
    $estado = $this->dispatcher->getParam('estado');
    $empresa = $this->dispatcher->getParam('empresa');
    $res = Productos::find([
      'conditions' => 'EmpresaId = :emp: AND Estado = :est:',
      'bind' => [ 'emp' => $empresa, 'est' => $estado ],
      'order' => 'Nombre'
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

  public function productoRegistradoAction() {
    $cod = $this->dispatcher->getParam('cod');
    $nom = $this->dispatcher->getParam('nom');
    $id = $this->dispatcher->getParam('id');
    $nom = str_replace('%20', ' ', $nom);
    $rows = Productos::find([
      'conditions' => 'Id != :id: AND (Codigo = :cod: OR Nombre = :nom:)',
      'bind' => [ 'nom' => $nom, 'cod' => $cod, 'id' => $id ]
    ]);
    $existe = false;
    $res = 'Se puede registrar los nuevos datos';
    if ($rows->count() > 0) {
      $res = 'Estos datos ya estan registrados busquelo como ' . $rows[0]->nombres;
      $this->response->setStatusCode(406, 'Not Acceptable');
    } else {
      $this->response->setStatusCode(200, 'Ok');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function productoGuardarAction() {
    try {
      
      $datos = $this->request->getJsonRawBody();
      $ret = (object) [
        'res' => false,
        'cid' => $datos->Id,
        'msj' => 'Los datos no se pudieron procesar'
      ];
      $nom = $datos->Nombre;
      if (substr($nom, 0, 4 ) === "@1_7") {
        $nom = str_replace('@1_7', 'UNIO', $nom);
      }
      if ($datos->Id > 0) {
        // Traer medico por id para actualizar
        $prd = Productos::findFirstById($datos->Id);
        $prd->Codigo = $datos->Codigo;
        $prd->Nombre = $datos->Nombre;
        $prd->Barcode = $datos->Barcode;
        $prd->Grupo = $datos->Grupo;
        $prd->Descripcion = $datos->Descripcion;
        $prd->Medida = $datos->Medida;
        $prd->Tipo = $datos->Tipo;
        $prd->UltimoCosto = $datos->UltimoCosto;
        $prd->EmpresaId = $datos->EmpresaId;
        $prd->Exitencia = $datos->Exitencia;
        $prd->Adicional = $datos->Adicional;
        $prd->EmbalajeTipo = $datos->EmbalajeTipo;
        $prd->EmbalejeCantidad = $datos->EmbalejeCantidad;
        $prd->EmbalajeUnidad = $datos->EmbalajeUnidad;
        $prd->EmbalajeVolumen = $datos->EmbalajeVolumen;
        $prd->EspecieId = $datos->EspecieId;
        $prd->Marca = $datos->Marca;
        $prd->Precio = $datos->Precio;
        $prd->PrecioOrigen = $datos->PrecioOrigen;
        $prd->Estado = $datos->Estado;
        if($prd->update()) {
          // Eliminar lineas anteriores de precios
          foreach ($datos->relPreciosEliminados as $peli) {
            $precioeli = ProductosPrecios::findById($peli->Id);
            $precioeli->delete();
          }
          // Crear lineas nuevas de precios y actualizar actualizadas
          foreach($datos->relPrecios as $pre) {
            if ($pre->Id > 0) {
              $insp = ProductosPrecios::findFirstById($pre->Id);
              $insp->ProductoId = $pre->ProductoId;
              $insp->Precio = $pre->Precio;
              $insp->VolumenCondicion = $pre->VolumenCondicion;
              $insp->MinimoCondicion = $pre->MinimoCondicion;
              $insp->update();
            } else {
              $insp = new ProductosPrecios();
              $insp->ProductoId = $pre->ProductoId;
              $insp->Precio = $pre->Precio;
              $insp->VolumenCondicion = $pre->VolumenCondicion;
              $insp->MinimoCondicion = $pre->MinimoCondicion;
              $insp->create();
            }
          }
          // Traer imposisiones existentes
          $pivas = ProductosImposiciones::find([
            'conditions' => 'ProductoId = :pid:',
            'bind' => [ 'pid' => $datos->Id ]
          ]);
          if ($pivas->count() > 0) {
            // Si hay registro de iva en db
            if ($datos->relImposiciones && count($datos->relImposiciones) <= 0) { // Eliminar
              $prdimp = reset($pivas);
              $piv = ProductosImposiciones::findFirstById($prdimp->Id);
              if ($piv != undefined) {
                $piv->delete();
              }
            }
          } else {
            // no hay resgistro de iva db
            if ($datos->relImposiciones && count($datos->relImposiciones) > 0) {
              $prdimp = reset($datos>relImposiciones);
              $piv = new ProductosImposiciones();
              $piv->ProductoId = $datos->Id;
              $piv->ImpuestoId = $prdimp->ImpuestoId;
              $piv->create();
            }
          }
          $ret->res = true;
          $ret->cid = $datos->Id;
          $ret->msj = "Se actualizo correctamente los datos del Producto";
          $this->response->setStatusCode(200, 'Ok');
        } else {
          $this->response->setStatusCode(500, 'Error');
          $msj = "No se puede actualizar los datos: " . "\n";
          foreach ($prd->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = $datos->Id;
          $ret->msj = $msj;
        }
      } else {
        // Crear producto nuevo
        // Buscar codigo numerico
        $newcod = $datos->Codigo;
        if (strlen($datos->Codigo) <= 0) {
          $di = Di::getDefault();
          $phql = 'SELECT MAX(Codigo) as maxcod FROM Pointerp\Modelos\Maestros\Productos 
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

        $prd = new Productos();
        $prd->Codigo = $newcod; //strval($num);
        $prd->Nombre = utf8_decode($datos->Nombre);
        $prd->Barcode = $datos->Barcode;
        $prd->Grupo = $datos->Grupo;
        $prd->Descripcion = $datos->Descripcion;
        $prd->Medida = $datos->Medida;
        $prd->Tipo = $datos->Tipo;
        $prd->UltimoCosto = $datos->UltimoCosto;
        $prd->EmpresaId = $datos->EmpresaId;
        $prd->Exitencia = $datos->Exitencia;
        $prd->Adicional = $datos->Adicional;
        $prd->EmbalajeTipo = $datos->EmbalajeTipo;
        $prd->EmbalejeCantidad = $datos->EmbalejeCantidad;
        $prd->EmbalajeUnidad = $datos->EmbalajeUnidad;
        $prd->EmbalajeVolumen = $datos->EmbalajeVolumen;
        $prd->EspecieId = $datos->EspecieId;
        $prd->Marca = $datos->Marca;
        $prd->Precio = $datos->Precio;
        $prd->PrecioOrigen = $datos->PrecioOrigen;
        $prd->Estado = 0;
        if ($prd->create()) {
          // Crear lineas de precios nuevas e imposiciones
          $ret->res = true;
          $ret->cid = $prd->Id;          
          $ret->msj = "Se registrara las imposiciones";
          // Crear imposiciones
          foreach ($datos->relImposiciones as $mi) {
            $ins = new ProductosImposiciones();
            $ins->ProductoId = $prd->Id;
            $ins->ImpuestoId = $mi->ImpuestoId;
            $ins->create();
          }
          $ret->msj = "Se registrara los precios";
          // Crear precios
          foreach ($datos->relPrecios as $pi) {
            if ($pi->Precio > 0) {
              $insp = new ProductosPrecios();
              $insp->ProductoId = $prd->Id;
              $insp->Precio = $pi->Precio;
              $insp->MinimoCondicion = $pi->MinimoCondicion;
              $insp->VolumenCondicion = $pi->VolumenCondicion;
              $insp->create();
            }
            
          }
          $ret->msj = "Se registro correctamente el nuevo producto";
          $this->response->setStatusCode(201, 'Created');  
        } else {
          $this->response->setStatusCode(500, 'Error');  
          $msj = "No se pudo crear el nuevo producto: " . "\n";
          foreach ($prd->getMessages() as $m) {
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
  
  public function productoModificarEstadoAction() {
    $id = $this->dispatcher->getParam('id');
    $est = $this->dispatcher->getParam('estado');
    $res = Productos::findFirstById($id);
    $this->response->setStatusCode(406, 'Not Acceptable');
    if ($res != null) {
      $res->Estado = $est;
      if($res->update()) {
        $msj = "Operacion ejecutada exitosamente";
        $this->response->setStatusCode(200, 'Ok');
      } else {
        $this->response->setStatusCode(500, 'Error');
        foreach ($res->getMessages() as $m) {
          $msj .= $m . "\n";
        }
      }
    } else {
      $res = [];
      $msj = "No se encontro el Producto";
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($msj));
    $this->response->send();
  }
  #endregion

  #region KARDEX
  public function bodegasPorEstadoAction() {
    $this->view->disable();
    $est = $this->dispatcher->getParam('estado');
    $emp = $this->dispatcher->getParam('empresa');
    $ops = [];
    if ($est == 0) { 
      $ops += [ 'conditions' => 'Estado = :est: AND EmpresaId = :emp:' ];
      $ops += [ 'bind' => ['est' => $est, 'emp' => $emp] ];
    }
    //$ops += [ 'order' => 'Nombre' ];
    $res = Bodegas::find($ops);
    if ($res->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }
  
  // TODO Traer la existencia desde la vista ExistenciaDatos
  public function exitenciasProductoAction() {    
    $di = Di::getDefault();
    $id = $this->dispatcher->getParam('id');
    $bod = $this->dispatcher->getParam('bodega');
    $condicion = 'e.ProductoId = ' . $id . ' ';
    if ($bod > 0) {
      $condicion .= 'AND e.BodegaId = ' . $bod . ' ';
    }
    
    $phql = 'Select e.ProductoId, e.BodegaId, b.Denominacion, SUM(e.Saldo) as Saldo 
      from Pointerp\Modelos\Inventarios\Existencias e 
      left join Pointerp\Modelos\Maestros\Productos p on e.ProductoId = p.Id
      left join Pointerp\Modelos\Inventarios\Bodegas b on e.BodegaId = b.Id
      Where ' . $condicion .
      'group by ProductoId, BodegaId, Denominacion';    
      
    $qry = new Query($phql, $di);
    $rws = $qry->execute();
    
    //$rex = array_merge($res);
    if (count($rws) > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rws));
    $this->response->send();
  }

  public function exitenciasTodosAction() {
    $di = Di::getDefault();
    $bod = $this->dispatcher->getParam('bodega');
    $zeros = $this->dispatcher->getParam('zeros');
    $condicion = 'e.BodegaId = ' . $bod . ' ';
    
    $phql = 'Select e.BodegaId, e.ProductoId, p.Nombre, p.Codigo, p.Medida, SUM(e.Saldo) as Saldo 
      from Pointerp\Modelos\Inventarios\Existencias e 
      left join Pointerp\Modelos\Maestros\Productos p on e.ProductoId = p.Id
      Where ' . $condicion .
      'group by BodegaId, ProductoId, Nombre, Codigo, Medida';
    if ($zeros == 0) {
        $phql .= ' HAVING Saldo != 0';
    }
      
    $qry = new Query($phql, $di);
    $rws = $qry->execute();
    $res = [];

    foreach ($rws as $exis) {
      $eay = (array) $exis;
      array_walk_recursive($eay, function(&$item) {
        try {
          if (is_string($item)) {
            $item = utf8_encode($item);
          }
        } catch (Exception $e) { }
      });
      array_push($res, $eay);
    }

    //$rex = array_merge($res);
    if (count($rws) > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function productosEnCeroAction() {
    $di = Di::getDefault();
    $bod = $this->dispatcher->getParam('bodega');
    $qry = new Query('SELECT p.* 
      FROM Pointerp\Modelos\Maestros\Productos p 
      Where id not in (
        Select ProductoId from Pointerp\Modelos\Inventarios\Kardex
      )', $di 
    );
    $res  =  $qry->execute();
    if ($res->count() > 0) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $this->response->setStatusCode(404, 'Not found');
    }

    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }
  #endregion

  #region MOVIMIENTOS
  public function movimientosBuscarAction() {
    $this->view->disable();
    $bod = $this->dispatcher->getParam('bodega');
    $tipoBusca = $this->dispatcher->getParam('tipobusca');
    $tipoMov = $this->dispatcher->getParam('tipo');
    $filtro = $this->dispatcher->getParam('filtro');
    $estado = $this->dispatcher->getParam('estado');
    $clase = $this->dispatcher->getParam('clase');
    $desde = $this->dispatcher->getParam('desde');
    $hasta = $this->dispatcher->getParam('hasta');
    $condicion = "Tipo = " . $tipoMov . " AND ";
    $res = [];
    if ($clase < 2) {
      $condicion .= "Fecha >= '" . $desde . "' AND Fecha <= '" . $hasta . "'";      
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
          $condicion .= " Descripcion like '" . $filtro . "'";
        } else {
          $condicion .= 'Numero = ' . $filtro;
        }
      }
    }
    if (strlen($condicion) > 0) {
      $condicion .= ' AND ';
      $condicion .= 'Estado = 0';
      $res = Movimientos::find([
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

  public function movimientoGuardarAction() {
    try {
      $datos = $this->request->getJsonRawBody();
      $ret = (object) [
        'res' => false,
        'cid' => $datos->Id,
        'msj' => 'Los datos no se pudieron procesar'
      ];
      $this->response->setStatusCode(406, 'Not Acceptable');
      //$signo = $this->signoPorTipo($datos->Tipo);
      $datos->Fecha = str_replace('T', ' ', $datos->Fecha);
      $datos->Fecha = str_replace('Z', '', $datos->Fecha);
      if ($datos->Id > 0) {
        // Traer movimiento por id y acualizar
        $mov = Movimientos::findFirstById($datos->Id);
        // Traer los items anteriores, /*reversar el inventario*/ y eliminar estos items 
        foreach ($mov->relItems as $mie) {
          /*if ($signo != 0 && !is_null($mie->relProducto->relTipo) && $mie->relProducto->relTipo->contenedor > 0) { // Signo 0 no afecta el inventario
            $this->afectarMovimientoInventario($mie, $mov->BodegaId, -1, $signo);
          }*/
          $eli = MovimientosItems::findFirstById($mie->Id);
          if ($eli != false) {
            $eli->delete();
          }
        }
        $mov->Fecha = $datos->Fecha;
        $mov->BodegaId = $datos->BodegaId;
        $mov->Referencia = $datos->Referencia;
        $mov->SucursalId = $datos->SucursalId;
        $mov->Descripcion = $datos->Descripcion;
        $mov->Concepto = $datos->Concepto;
        $mov->Estado = $datos->Estado;
        if($mov->update()) {
          $ret->res = true;
          $ret->cid = $datos->Id;
          // crear los items actualues
          foreach ($datos->relItems as $mi) {
            /* Afectar el inventario YA NO
            if ($signo != 0 && !is_null($mi->relProducto->relTipo) && $mi->relProducto->relTipo->Contenedor > 0) { // Signo 0 no afecta el inventario
              $this->afectarMovimientoInventario($mi, $mov->BodegaId, 0, $signo);
            }*/
            $ins = new MovimientosItems();
            $ins->KardexId = $mov->Id;
            $ins->ProductoId = $mi->ProductoId;
            $ins->Cantidad = $mi->Cantidad;
            $ins->Costo = $mi->Costo;
            $ins->create();
          }
          $ret->msj = "Se actualizo correctamente los datos del registro";
          $this->response->setStatusCode(200, 'Ok');
        } else {
          $msj = "No se puede actualizar los datos: " . "\n";
          foreach ($mov->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = $datos->Id;
          $ret->msj = $msj;
        }
      } else {
        // Crear movimiento nuevo
        $num = $this->ultimoNumeroMovimiento($datos->Tipo, $datos->SucursalId);
        $mov = new Movimientos();
        $mov->Numero = $num + 1;
        $mov->Tipo = $datos->Tipo;
        $mov->Fecha = $datos->Fecha;
        $mov->BodegaId = $datos->BodegaId;
        $mov->Referencia = $datos->Referencia;
        $mov->SucursalId = $datos->SucursalId;
        $mov->Descripcion = $datos->Descripcion;
        $mov->Concepto = $datos->Concepto;
        $mov->Estado = 0;
        if ($mov->create()) {
          $ret->res = true;
          $ret->cid = $mov->Id;
          $ret->msj = "Se registro correctamente el nuevo movimiento";  
          // Crear items /*y afectar el inventario*/
          foreach ($datos->relItems as $mi) {
            /*if ($signo != 0 && !is_null($mi->relProducto->relTipo) && $mi->relProducto->relTipo->Contenedor > 0) { // Signo 0 no afecta el inventario
              $this->afectarMovimientoInventario($mi, $mov->BodegaId, 0, $signo);
            }*/
            $ins = new MovimientosItems();
            $ins->KardexId = $mov->Id;
            $ins->ProductoId = $mi->ProductoId;
            $ins->Cantidad = $mi->Cantidad;
            $ins->Costo = $mi->Costo;
            $ins->create();
          }
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

  private function afectarMovimientoInventario($item, $bod, $origen, $signo) {
    /*$res = Kardex::find([
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
        $msj = "Se actualizo";
      } else {
        $msj = "No se pudo crear el nuevo kardex: " . "\n";
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
        $msj = "Todo bien";
      } else {
        $msj = "No se pudo crear el nuevo kardex: " . "\n";
        foreach ($kdxn->getMessages() as $m) {
          $msj .= $m . "\n";
        }
      }
    }*/
    return true; //$msj;
  }

  private function ultimoNumeroMovimiento($tipo, $suc) {
    return Movimientos::maximum([
      'column' => 'Numero',
      'conditions' => 'Tipo = ' . $tipo . ' AND SucursalId = ' . $suc
    ]) ?? 0;
  }

  private function signoPorTipo($tipo) {
    $this->view->disable();
    $res = Registros::find([
        'conditions' => 'TablaId = 20 AND Indice = :tipo:',
        'bind' => ['tipo' => $tipo]
    ]);
    if ($res->count() > 0) {
      $si = $res[0];
      return $si->Valor;
    } else {
      return 9;
    }
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

  #region Compras
  public function comprasBuscarAction() {
    $this->view->disable();
    $bod = $this->dispatcher->getParam('sucursal');
    $tipoBusca = $this->dispatcher->getParam('tipobusca');
    $tipoMov = $this->dispatcher->getParam('tipo');
    $filtro = $this->dispatcher->getParam('filtro');
    $estado = $this->dispatcher->getParam('estado');
    $clase = $this->dispatcher->getParam('clase');
    $desde = $this->dispatcher->getParam('desde');
    $hasta = $this->dispatcher->getParam('hasta');
    $condicion = "Tipo = " . $tipoMov . " AND SucursalId = " . $bod. " AND ";
    $res = [];
    if ($clase < 2) {
      $condicion .= "Fecha >= '" . $desde . " 0:00:00' AND Fecha <= '" . $hasta . " 23:59:59'"; 
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
          $condicion .= " Notas like '" . $filtro . "'";
        } else {
          $condicion .= 'Numero = ' . $filtro;
        }
      }
    }
    if ($estado == 0) {
      if (strlen($condicion) > 0) {
        $condicion .= ' AND ';      
        $condicion .= 'Estado != 2';
      }
    }
    $res = Compras::find([
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

  public function compraGuardarAction() {
    try {
      $datos = $this->request->getJsonRawBody();
      $ret = (object) [
        'res' => false,
        'cid' => $datos->Id,
        'com' => null,
        'msj' => 'Los datos no se pudieron procesar'
      ];
      $this->response->setStatusCode(406, 'Not Acceptable');
      $datos->Fecha = str_replace('T', ' ', $datos->Fecha);
      $datos->Fecha = str_replace('Z', '', $datos->Fecha);
      if ($datos->Id > 0) {
        // Traer movimiento por id y acualizar
        $mov = Compras::findFirstById($datos->Id);
        $mov->Tipo = $datos->Tipo;
        $mov->Numero = $datos->Numero;
        $mov->Especie = $datos->Especie;
        $mov->ProveedorId = $datos->ProveedorId;
        $mov->Fecha = $datos->Fecha; 
        $mov->Notas = $datos->Notas;
        $mov->PorcentajeDescuento = $datos->PorcentajeDescuento;
        $mov->BodegaId = $datos->BodegaId;
        $mov->Plazo = $datos->Plazo;
        $mov->Subtotal = $datos->Subtotal;
        $mov->SubtotalEx = $datos->SubtotalEx;
        $mov->PorcentajeCompra = $datos->PorcentajeCompra;
        $mov->Descuento = $datos->Descuento;
        $mov->Recargo = $datos->Recargo; 
        $mov->Flete = $datos->Flete; 
        $mov->Impuestos = $datos->Impuestos;
        $mov->Pagos = $datos->Pagos;
        $mov->CostoFinal = $datos->CostoFinal;
        $mov->SucursalId = $datos->SucursalId;
        $mov->Estado = $datos->Estado;
        if($mov->update()) {
          $ret->res = true;
          $ret->cid = $datos->Id;
          // Quitar items eliminados
          foreach ($datos->itemsEliminados as $mie) {          
            $eli = ComprasItems::findFirstById($mie->Id);
            if ($eli != false) {
              $eli->delete();
            }
          }          
          // crear los items actualues
          foreach ($datos->relItems as $mi) {            
            $ins = null;
            if ($mi->Id > 0) {
              $ins = ComprasItems::findFirstById($mi->Id);
            } else {              
              $ins = new ComprasItems();
              $ins->CompraId = $mov->Id;
            }
            if ($ins != null) {
              $ins->ProductoId = $mi->ProductoId;
              $ins->Cantidad = $mi->Cantidad;
              $ins->Costo = $mi->Costo;
              $ins->Descuento = $mi->Descuento;
              $ins->Bodega = $mi->Bodega;
              $ins->Adicional = $mi->Adicional;
              $ins->Bultos = $mi->Bultos;
              $ins->Lote = $mi->Lote;
              $ins->Expiracion = $mi->Expiracion;
              $ins->BultoCosto = $mi->BultoCosto;
              if ($mi->Id > 0) {
                $ins->update();
              } else {
                $ins->create();
              }
            }
          }
          // Procesar items de impuestos          
          foreach ($datos->relImpuestos as $imp) {
            $ins = ComprasImpuestos::findFirst([
              'conditions' => 'CompraId = ' . $mov->Id . ' AND ImpuestoId = ' . $imp->ImpuestoId
            ]);
            if ($ins != null) {
              $ins->Porcentaje = $imp->Porcentaje;
              $ins->Base = $imp->Base;
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
              $o = new ComprasImpuestos();
              $o->Id = 0;
              $o->CompraId = $mov->Id;
              $o->ImpuestoId = $imp->ImpuestoId;
              $o->Porcentaje = $imp->Porcentaje;
              $o->Base = $imp->Base;
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
          if ($ret->res) {
            $ret->msj = "Se actualizo correctamente los datos de la transaccion";
            $this->response->setStatusCode(200, 'Ok');
          } else {
            $this->response->setStatusCode(500, 'Error');
          }
        } else {
          $msj = "No se puede actualizar los datos: ";
          foreach ($mov->getMessages() as $m) {            
            $msj .= $m . " ";
          }
          $ret->res = false;
          $ret->cid = $datos->Id;
          $ret->msj = $msj;
        }
      } else {
        // Crear movimiento nuevo
        $num = $this->ultimoNumeroCompra($datos->Tipo, $datos->SucursalId);
        $mov = new Compras();
        $mov->Numero = $num + 1;
        $mov->Tipo = $datos->Tipo;
        $mov->Especie = $datos->Especie;
        $mov->ProveedorId = $datos->ProveedorId;
        $mov->Fecha = $datos->Fecha; 
        $mov->Notas = $datos->Notas;
        $mov->PorcentajeDescuento = $datos->PorcentajeDescuento;
        $mov->BodegaId = $datos->BodegaId;
        $mov->Plazo = $datos->Plazo;
        $mov->Subtotal = $datos->Subtotal;
        $mov->SubtotalEx = $datos->SubtotalEx;
        $mov->PorcentajeCompra = $datos->PorcentajeCompra;
        $mov->Descuento = $datos->Descuento;
        $mov->Recargo = $datos->Recargo; 
        $mov->Flete = $datos->Flete; 
        $mov->Impuestos = $datos->Impuestos;
        $mov->Pagos = $datos->Pagos;
        $mov->CostoFinal = $datos->CostoFinal;
        $mov->SucursalId = $datos->SucursalId;
        $mov->Estado = 0;
        if ($mov->create()) {
          $ret->res = true;
          $ret->cid = $mov->Id;
          $ret->num = $mov->Numero;
          $ret->msj = "Transaccion registrada exitosamente";  
          // Crear items /*y afectar el inventario*/
          foreach ($datos->relItems as $mi) {
            $ins = new ComprasItems();
            $ins->CompraId = $mov->Id;
            $ins->ProductoId = $mi->ProductoId;
            $ins->Cantidad = $mi->Cantidad;
            $ins->Costo = $mi->Costo;
            $ins->Descuento = $mi->Descuento;
            $ins->Bodega = $mi->Bodega;
            $ins->Adicional = $mi->Adicional;
            $ins->Bultos = $mi->Bultos;
            $ins->Lote = $mi->Lote;
            $ins->Expiracion = $mi->Expiracion;
            $ins->BultoCosto = $mi->BultoCosto;
            $ins->create();
          }
          foreach ($datos->relImpuestos as $im) {
            $ins = new ComprasImpuestos();
            $ins->CompraId = $mov->Id;
            $ins->ImpuestoId = $im->ImpuestoId;
            $ins->Porcentaje = $im->Porcentaje;
            $ins->Base = $im->Base;
            $ins->Valor = $im->Valor;
            $ins->create();
          }
          $this->response->setStatusCode(201, 'Created');
        } else {
          $msj = "No se pudo crear el nuevo registro: " . "\n";
          foreach ($mov->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->cid = 0;
          $ret->num = 0;
          $ret->msj = $msj;
        }
      }
      $ret->com = Compras::findFirstById($ret->cid);
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

  private function ultimoNumeroCompra($tipo, $suc) {
    return Compras::maximum([
      'column' => 'Numero',
      'conditions' => 'Tipo = ' . $tipo . ' AND SucursalId = ' . $suc
    ]) ?? 0;
  }

  public function compraPorIdAction() {
    $id = $this->dispatcher->getParam('id');
    $res = Compras::findFirstById($id);
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

  public function compraModificarEstadoAction() {
    $id = $this->dispatcher->getParam('id');
    $est = $this->dispatcher->getParam('estado');
    $mov = Compras::findFirstById($id);
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
}