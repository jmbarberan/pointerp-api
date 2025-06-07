<?php

namespace Pointerp\Controladores;

use Exception;
use Phalcon\Di\Di;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Pointerp\Modelos\Empresas;
use Pointerp\Modelos\Maestros\Productos;
use Pointerp\Modelos\Maestros\ProductosImagenes;
use Pointerp\Modelos\Maestros\ProductosPrecios;
use Pointerp\Modelos\Maestros\ProductosImposiciones;
use Pointerp\Modelos\Inventarios\Bodegas;
use Pointerp\Modelos\Inventarios\Compras;
use Pointerp\Modelos\Inventarios\ComprasItems;
use Pointerp\Modelos\Inventarios\ComprasImpuestos;
use Pointerp\Modelos\Inventarios\Movimientos;
use Pointerp\Modelos\Inventarios\MovimientosItems;
use Pointerp\Modelos\Maestros\Registros;
use Pointerp\Modelos\SubscripcionesEmpresas;
use Pointerp\Modelos\EmpresaParametros;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;


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
    try {
      $page = $this->request->getQuery('page', null, 0);
      $limit = $this->request->getQuery('limit', null, 0);
      $order = $this->request->getQuery('order', null, 'Nombre');
      $orderDir = $this->request->getQuery('dir', null, '');
    } catch (Exception $ex) {
      $page = 0;
      $limit = 0;
      $order = 'Nombre';
      $orderDir = '';
    }
    $tipoBusca = $this->dispatcher->getParam('tipo');
    $estado = $this->dispatcher->getParam('estado');
    $filtro = $this->dispatcher->getParam('filtro');
    $emp = $this->dispatcher->getParam('emp');
    $atrib = $this->dispatcher->getParam('atrib');
    $filtro = str_replace('%20', ' ', $filtro);
    $filtro = str_replace('%C3%91' , 'Ñ',$filtro);
    $filtro = str_replace('%C3%B1' , 'ñ',$filtro);

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

    if ($atrib == 0) {
      if ($tipoBusca == 0) {
        $filtro .= '%';
      } else {
        $filtroSP = str_replace('  ', ' ',trim($filtro));
        $filtro = '%' . str_replace(' ' , '%',$filtroSP) . '%';
      }
    }

    $campo = "Nombre like '{$filtro}'";
    if($atrib == 1) {
      $campo = "Codigo = '{$filtro}'";
    };

    $condicion .= $campo;
    if ($estado == 0) {
        $condicion .= ' AND Estado = 0';
    }

    $hasData = false;
    $prods = [];
    if ($page > 0 && $limit > 0) {
      
      $paginator = new PaginatorModel([
        "model"      => Productos::class,
        "parameters" => [
          'conditions' => $condicion,
          'order' => ($order ?? 'Nombre') . " {$orderDir}"
        ],
        "limit"      => $limit,
        "page"       => $page,
      ]);
      $pageData = $paginator->paginate();
      $prods = (object) [
        'completo' => true,
        'total' => $pageData->getTotalItems(),
        'items' => $pageData->getItems()
      ];
      $hasData = $pageData->getTotalItems() > 0;      
    } else {
      $prods = Productos::find([
        'conditions' => $condicion,
        'order' => $order ?? 'Nombre'
      ]);
      $hasData = $prods->count() > 0;
    }
    

    if ($hasData) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($prods));
    $this->response->send();
  }

  public function productosParaCacheAction() {
    $cmd = "Select r.Id as prcId, r.Precio, r.VolumenCondicion," .
    "p.Id, p.Codigo, p.Barcode, p.Nombre, p.Grupo, p.Descripcion, " . 
    "p.Medida, p.Tipo, p.UltimoCosto, p.EmpresaId, p.Adicional," .
    "p.EmbalejeCantidad, p.PrecioOrigen, p.Estado," .
    "i.Id as impId, ImpuestoId, i.ProductoId," .
    "m.Id as ivaId, m.Nombre as ivaNombre, m.Porcentaje, m.Actualizado, p.EmbalajeVolumen " .
    "from Pointerp\Modelos\Maestros\ProductosPrecios r " .
    "right join Pointerp\Modelos\Maestros\Productos p on r.ProductoId = p.Id " .
    "left join Pointerp\Modelos\Maestros\ProductosImposiciones i on i.ProductoId = p.Id " .
    "left join Pointerp\Modelos\Maestros\Impuestos m on i.ImpuestoId = m.Id " .
    "order by r.ProductoId";

    $qry = new Query($cmd, Di::getDefault());
    $rws = $qry->execute();
    if ($rws->count() > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rws));
    $this->response->send();

  }

  public function productoSeleccionarAction() {
    $eliminados = $this->dispatcher->getParam('estado');
    $filtro = $this->dispatcher->getParam('filtro');
    $empresa = $this->dispatcher->getParam('emp');
    $buscaExt = $this->dispatcher->getParam('extendida');
    $condicion = '';
    if ($this->subscripcion['exclusive'] === 1) {
      $condicion = $this->subscripcion['sharedemps'] === 1 ? '' : 'EmpresaId = ' . $empresa . ' AND ';
    } else {
      $emps = SubscripcionesEmpresas::find([
        'conditions' => 'subscripcion_id = ' . $this->subscripcion['id']
      ]);
      $condicion = '';
      foreach ($emps as $e) {
        $condicion .= strlen($condicion) > 0 ? ', ' . $e->empresa_id : $e->empresa_id;
      }
      $condicion = (strlen($condicion) > 0 ? 'EmpresaId in (' . $condicion . ')' : 'EmpresaId = ' . $empresa);
      $condicion .= ' AND ';
    }

    $res = null;
    if (strpos($filtro, '%20') === false) {
      $campo = "Codigo = '" . $filtro . "'";
      $condicionc = $condicion . $campo;
      if ($eliminados == 0) {
          $condicionc .= ' AND Estado = 0';
      }
      $res = Productos::find([
        'conditions' => $condicionc,
        'order' => 'Nombre'
      ]);
    }
    
    if ($res === null || $res->count() <= 0 ) {
      $filtro = str_replace('%20', ' ', $filtro);
      $filtro = str_replace('%C3%91' , 'Ñ',$filtro);
      $filtro = str_replace('%C3%B1' , 'ñ',$filtro);
      $filtroSP = str_replace('  ', ' ',trim($filtro));
      $filtro = str_replace(' ' , '%',$filtroSP) . '%';
      if ($buscaExt == 1) {
        $filtro = '%' . $filtro;
      }
      $condicion .= "Nombre like '" . $filtro . "'";
      if ($eliminados == 0) {
          $condicion .= ' AND Estado = 0';
      }
      $res = Productos::find([
        'conditions' => $condicion,
        'order' => 'Nombre'
      ]);
    }

    if ($res->count() > 0) {
      $this->response->setStatusCode(200, 'Ok');
    } else {
      $this->response->setStatusCode(404, 'Not found => ' . $res->count());
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
      $soloIva = $this->request->getQuery('soloiva', null, false);
      $datos = $this->request->getJsonRawBody();
      $ret = (object) [
        'res' => false,
        'cid' => $datos->Id,
        'msj' => 'Los datos no se pudieron procesar'
      ];
      /*$nom = $datos->Nombre;
      if (substr($nom, 0, 4 ) === "@1_7") {
        $nom = str_replace('@1_7', 'UNIO', $nom);
      }*/
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

          if ($soloIva) {
            $param = EmpresaParametros::findFirst([
              "conditions" => "Tipo = 1 and EmpresaId = {$prd->EmpresaId}"
            ]);
            $filaImpos = reset($datos->relImposiciones);
            $impuestoId = $filaImpos->ImpuestoId;
            if (isset($param)) {
              $impuestoId = $param->RegistroId;
            }
            $pivas = ProductosImposiciones::find([
              'conditions' => 'ProductoId = :pid: AND ImpuestoId != :imp:',
              'bind' => [ 'pid' => $datos->Id, 'imp' => $impuestoId ]
            ]);
            if ($pivas->count() > 0) {
              foreach ($pivas as $pivaItem) {
                $pivaItem->delete();
              }
            }
            $piv = new ProductosImposiciones();
            $piv->ProductoId = $filaImpos->ProductoId;
            $piv->ImpuestoId = $impuestoId;
            $piv->create();
          } else {
            // eliminar los ids q no estan en la lista y son mayor q cero
            $pivas = ProductosImposiciones::find([
              'conditions' => 'ProductoId = :pid:',
              'bind' => [ 'pid' => $datos->Id ]
            ]);

            foreach($datos->relImposiciones as $impos) {
              if ($impos->Id > 0) {
                $piv = ProductosImposiciones::findFirstById($impos->Id);
                if (isset($piv) && $piv != null) {
                  $piv->ImpuestoId = $impos->ImpuestoId;
                  $piv->update();
                }
              } else {
                $piv = new ProductosImposiciones();
                $piv->ProductoId = $datos->Id;
                $piv->ImpuestoId = $impos->ImpuestoId;
                $piv->create();
              }
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
        $prd->Nombre = mb_convert_encoding($datos->Nombre, "UTF-8", mb_detect_encoding($datos->Nombre));
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
          $impuestoId = 0;
          if ($soloIva) {
            $param = EmpresaParametros::find([
              "conditions" => "Tipo = 1 and EmpresaId = {$prd->EmpresaId}"
            ]);
            if (isset($param)) {
              $impuestoId = $param->RegistroId;
            }
          }
          foreach ($datos->relImposiciones as $mi) {
            $ins = new ProductosImposiciones();
            $ins->ProductoId = $prd->Id;
            $ins->ImpuestoId = $impuestoId;
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

  public function productoReplicarAction() {
    try {
      
      $body = $this->request->getJsonRawBody();
      $ret = (object) [
        'res' => false,
        'cid' => $body->id,
        'msj' => 'No se pudo procesar el producto'
      ];

      $empresaId = $body->empresa;
      if ($body->id > 0) {
        $datos = Productos::findFirstById($body->id);
        if ($datos != null) {
          if ($empresaId <= 0) {
            $emp = Empresas::findFirst([
              'conditions' => "Id != " . $datos->EmpresaId
            ]);
            if ($emp != null) {
              $empresaId = $emp->Id;
            } else {
              $empresaId = 1;
            }
          }

          if ($empresaId > 0) {
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
            $prd->Nombre = mb_convert_encoding($datos->Nombre, "UTF-8", mb_detect_encoding($datos->Nombre));
            $prd->Barcode = $datos->Barcode;
            $prd->Grupo = $datos->Grupo;
            $prd->Descripcion = $datos->Descripcion;
            $prd->Medida = $datos->Medida;
            $prd->Tipo = $datos->Tipo;
            $prd->UltimoCosto = $datos->UltimoCosto;
            $prd->EmpresaId = $empresaId;
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
              $ret->msj = "Se replico correctamente el producto";
              $this->response->setStatusCode(201, 'Created');  
            } else {
              $this->response->setStatusCode(500, 'Error');  
              $msj = "No se pudo replicar el producto: " . "\n";
              foreach ($prd->getMessages() as $m) {
                $msj .= $m . "\n";
              }
              $ret->res = false;
              $ret->cid = 0;
              $ret->msj = $msj;
            }
          } else {
            $ret->msj = "La empresa no es válida";
          }
        } else {
          $ret->msj = "No se encontró el producto a replicar";
        }
      } else {
        $ret->msj = "El producto no es válido";
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
        $msj = "";
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

  public function imagenProductoPorIdAction() {
    $id = $this->dispatcher->getParam('id');
    $this->response->setStatusCode(404, 'Not found');

    $img = ProductosImagenes::findFirst($id);
    if (isset($img)) {
      $filePath = $img->ImagenRuta;
      if (file_exists($filePath)) {
        $mimeType = mime_content_type($filePath);
        $this->response->setContentType($mimeType);
        $this->response->setContent(file_get_contents($filePath));
        $this->response->setStatusCode(200, 'Ok');
      }
    }
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
  
  public function exitenciasProductoAction() {    
    $id = $this->dispatcher->getParam('id');
    $bod = $this->dispatcher->getParam('bodega');
    
    $rws = $this->exisenciaProductoBodega($id, $bod);    
    
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
    
    $phql = 'Select e.BodegaId, e.ProductoId, p.Nombre, p.Codigo, p.Medida, SUM(e.Saldo) as Saldo 
      from Pointerp\Modelos\Inventarios\Existencias e 
      left join Pointerp\Modelos\Maestros\Productos p on e.ProductoId = p.Id
      Where e.BodegaId = :bod:' .
      'group by BodegaId, ProductoId, Nombre, Codigo, Medida';
    if ($zeros == 0) {
        $phql .= ' HAVING Saldo != 0';
    }
      
    $qry = new Query($phql, $di);
    $rws = $qry->execute(['bod' => $bod]);
    $res = [];

    foreach ($rws as $exis) {
      $eay = (array) $exis;
      array_walk_recursive($eay, function(&$item) {
        try {
          if (is_string($item)) {
            $item = mb_convert_encoding($item, "UTF-8", mb_detect_encoding($item));
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

  private function exisenciaProductoBodega($prd, $bod) {
    $params = [ 'prd' => $prd ];
    $andCondition = '';
    if ($bod != 0) {
      $andCondition = 'AND e.BodegaId = :bod: ';
      $params['bod'] = $bod;
    }
    $phql = 'Select e.ProductoId, e.BodegaId, b.Denominacion, SUM(e.Saldo) as Saldo 
      from Pointerp\Modelos\Inventarios\Existencias e 
      left join Pointerp\Modelos\Maestros\Productos p on e.ProductoId = p.Id
      left join Pointerp\Modelos\Inventarios\Bodegas b on e.BodegaId = b.Id
      Where e.ProductoId = :prd: ' . $andCondition .
      'group by ProductoId, BodegaId, Denominacion';    
      
    $qry = new Query($phql, Di::getDefault());
    return $qry->execute($params);
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
      $condicion .= "Fecha >= '" . $desde . "' AND Fecha <= '" . $hasta . " 23:59:59'";      
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

  public function ajustarExistenciasFisicoAction() {
    $respuesta = (object) [
      'result' => false,
      'mensaje' => "La operacion no se pudo ejecutar correctamente",
      'data' => null,
    ];
    $this->view->disable();
    $fisicoId = $this->dispatcher->getParam('id'); // $params->id;

    $movFisico = Movimientos::findFirstById($fisicoId);
    if ($movFisico == false) {
      $respuesta->mensaje = "No se encontro el inventario fisico";
      $this->response->setStatusCode(404, 'Not found');
      $this->response->setContentType('application/json', 'UTF-8');
      $this->response->setContent(json_encode($respuesta));
      $this->response->send();
      return;
    } else {
      if ($movFisico->Estado == 1) {
        $respuesta->mensaje = "El Inventario fisico ya fue ajustado";
        $this->response->setStatusCode(422, 'Invalid');
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($respuesta));
        $this->response->send();
        return;
      }
    }
    $itemsAjustar = MovimientosItems::find(['conditions' => "KardexId = $fisicoId"]);
    $itemsSobrantes = [];
    $itemsFaltantes = [];
    foreach ($itemsAjustar as $item) {
      $prdExistencia = 0;
      $rwExiste = $this->exisenciaProductoBodega($item->ProductoId, $movFisico->BodegaId);
      if ($rwExiste->count() > 0) {
        $prdExistencia = $rwExiste[0]->Saldo;
      }
      $prd = (object) [
        'id' => $item->ProductoId,
        'cantidad' => 0
      ];
      if ($prdExistencia < $item->Cantidad) {
        $prd->cantidad = $item->Cantidad - $prdExistencia;
        $itemsSobrantes[] = $prd;
      } else {
        if ($prdExistencia != $item->Cantidad) {
          $prd->cantidad = $prdExistencia - $item->Cantidad;
          $itemsFaltantes[] = $prd;
        }
      }
    }

    try {
      $manager = new TransactionManager();
      $transaction = $manager->get();
      $completa = true;
      if (count($itemsSobrantes) > 0) {      
        $movAjusteSobrantes = new Movimientos();
        $movAjusteSobrantes->BodegaId = $movFisico->BodegaId;
        $movAjusteSobrantes->SucursalId = $movFisico->SucursalId;
        $movAjusteSobrantes->Fecha = date('Y-m-d H:i:s');      
        $movAjusteSobrantes->Tipo = 9;
        $movAjusteSobrantes->Numero = $this->ultimoNumeroMovimiento($movAjusteSobrantes->Tipo, $movFisico->SucursalId) + 1;
        $movAjusteSobrantes->Estado = 0;
        $movAjusteSobrantes->Concepto = 0;
        $movAjusteSobrantes->Referencia = 0;
        $movAjusteSobrantes->Descripcion = "Ajuste de sobrantes de existencias fisico # {$movFisico->Numero}";
        if ($movAjusteSobrantes->save()) {
          foreach ($itemsSobrantes as $item) {
            $itemSobrante = new MovimientosItems();
            $itemSobrante->KardexId = $movAjusteSobrantes->Id;
            $itemSobrante->ProductoId = $item->id;
            $itemSobrante->Cantidad = $item->cantidad;
            $itemSobrante->Costo = 0;
            if (!$itemSobrante->save()) {
              $completa = false;
              $transaction->rollback();
            }
          }
        } else {
          $completa = false;
          $transaction->rollback();
        }
      }

      if (count($itemsFaltantes) > 0) {
        $movAjusteFaltantes = new Movimientos();
        $movAjusteFaltantes->BodegaId = $movFisico->BodegaId;
        $movAjusteFaltantes->SucursalId = $movFisico->SucursalId;
        $movAjusteFaltantes->Fecha = date('Y-m-d H:i:s');
        $movAjusteFaltantes->Tipo = 10;
        $movAjusteFaltantes->Numero = $this->ultimoNumeroMovimiento($movAjusteFaltantes->Tipo, $movFisico->SucursalId) + 1;
        $movAjusteFaltantes->Estado = 0;
        $movAjusteFaltantes->Concepto = 0;
        $movAjusteFaltantes->Referencia = 0;
        $movAjusteFaltantes->Descripcion = "Ajuste de faltantes de existencias fisico # {$movFisico->Numero}";
        if ($movAjusteFaltantes->save()) {
          foreach ($itemsFaltantes as $item) {
            $itemFaltante = new MovimientosItems();
            $itemFaltante->KardexId = $movAjusteFaltantes->Id;
            $itemFaltante->ProductoId = $item->id;
            $itemFaltante->Cantidad = $item->cantidad;
            $itemFaltante->Costo = 0;
            if (!$itemFaltante->save()) {
              $completa = false;
              $transaction->rollback();
            }
          }
        } else {
          $completa = false;
          $transaction->rollback();
        }
      }

      if ($completa)
      {
        $movFisico->Estado = 1;
        if ($movFisico->save()) {
          $transaction->commit();
          $this->response->setStatusCode(201, 'Ok');
          $respuesta->result = true;
          $respuesta->mensaje = "El inventario fisico fue ajustado exitosamente";
        } else {
          $transaction->rollback();
        }
      }
    } catch (Exception $e) {
      $this->response->setStatusCode(500, 'Error');
      $respuesta->mensaje = "Se produjo un error al intentar ajustar";
      $respuesta->data = $e->getMessage();
    }

    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($respuesta));
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
          // crear los items nuevos y modificar los actuales
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