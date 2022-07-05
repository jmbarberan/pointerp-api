<?php
//declare(strict_types=1);

namespace Pointerp\Controladores;

use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use Pointerp\Modelos\Claves;
use Pointerp\Modelos\Usuarios;
use Pointerp\Modelos\Autorizaciones;
use Pointerp\Modelos\Privilegios;
use Pointerp\Modelos\Roles;

class SeguridadController extends ControllerBase
{
    public function usuariosTodosAction() {
        $this->view->disable();
        $estado = $this->dispatcher->getParam('estado');
        if ($estado == 9) {
            $res = Usuarios::find([
                'order' => 'Nombres',
            ]);
        } else {
            $res = Usuarios::find([
                'conditions' => 'Estado != 2',
                'order' => 'Nombres'
            ]);
        }

        if ($res->count() > 0) {
            $this->response->setStatusCode(200, 'Ok');
        } else {
            $this->response->setStatusCode(404, 'Not found');
        }
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setJsonContent(mb_convert_encoding($res[1]->Nombres, 'UTF-8', 'UTF-8'));
        $this->response->send();
    }

    public function usuarioPorIdAction() {
        $id = $this->dispatcher->getParam('id');
        $res = Usuarios::findFirstById($id);
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

    public function usuarioModificarEstado() {
        $id = $this->dispatcher->getParam('id');
        $est = $this->dispatcher->getParam('estado');
        $usr = Usuarios::findFirstById($id);
        $res = 'No se ha podido eliminar el usuario';
        $this->response->setStatusCode(404, 'Not found');
        if ($usr != null) {
            $usr->Estado = $est;
            $res = $usr->save();
            if ($res != false) {
                $res = 'El usuario se elimino exitosamente';
                $this->response->setStatusCode(200, 'Ok');
            } else {
                $res = 'El usuario no se pudo eliminar';
            }
        } else {
            $res = 'El usuario no existe';
        }
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($res));
        $this->response->send();
    }

    public function cambiarClaveAction() {
        $cred = $this->request->getJsonRawBody();
        $id = $cred->Id;
        $cve = $cred->Clave;
        $usr = Usuarios::findFirstById($id);
        $res = 'No se ha podido actualizar la contraseña';
        $this->response->setStatusCode(404, 'Not found');
        if ($usr != null) {
            $usr->Clave = $cve;
            $res = $usr->save();
            if ($res != false) {
                $res = 'La contraseña se actualizo exitosamente';
                $this->response->setStatusCode(200, 'Ok');
            } else {
                $res = 'La contraseña no se pudo actualizar';
            }
        } else {
            $res = 'El usuario no existe';
        }
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($res));
        $this->response->send();
    }

    public function usuarioGuardarAction() {
        $datos = $this->request->getJsonRawBody();
        $res = 'No se ha podido guardar los cambios';
        $this->response->setStatusCode(404, 'Not found');
        $usr = false;
        if ($datos->Id > 0) {
            $usr = Usuarios::findFirstById($datos->Id);
        } else {
            $usr = new Usuarios();
        }
        if ($usr != false) {
            $usr->Clave = $datos->Clave;
            $usr->Codigo = $datos->Codigo;
            $usr->Nombres = $datos->Nombres;
            //$usr->rol_id = $datos->rol_id;
            if ($datos->Id > 0) {
                if ($usr->update()) {
                    $res = 'Los datos se actualizaron exitosamente';
                    $this->response->setStatusCode(200, 'Ok');
                } else {
                    $res = 'Los datos no se pudieron actualizar ';
                    foreach ($usr->getMessages() as $m) {
                        $res .= $m . "\n";
                    }
                    $this->response->setStatusCode(406, 'Error');
                }
            } else {
                $usr->estado = 0;
                if ($usr->create()) {
                    $res = 'Los datos se actualizaron exitosamente';
                    $this->response->setStatusCode(200, 'Ok');
                } else {
                    $res = 'Los datos no se pudieron actualizar ';
                    foreach ($usr->getMessages() as $m) {
                        $res .= $m . "\n";
                    }
                    $this->response->setStatusCode(406, 'Error');
                }
            }
        } else {
            $res = 'El usuario no existe';
        }
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($res));
        $this->response->send();
    }

    public function credencialesValidarAction() {
        $cred = $this->request->getJsonRawBody();
        $di = Di::getDefault();
        $phql = 'SELECT * FROM Pointerp\Modelos\Usuarios 
            WHERE Codigo = "%s" AND Clave = "%s"';
        $qry = new Query(sprintf($phql, $cred->usr, $cred->cla), $di);
        $rws = $qry->execute();
        $this->response->setStatusCode(401, 'Unauthorized');
        $rus = 'El usuario y/o contraseña no son validos';
        if ($rws->count() === 1) {
            $rus = $rws->getFirst();
            $this->response->setStatusCode(202, 'Accepted');
        }
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($rus));
        $this->response->send();
    }

    public function rolesTodosAction() {
        $this->view->disable();
        $res = Roles::find();

        if ($res->count() > 0) {
            $this->response->setStatusCode(200, 'Ok');
        } else {
            $this->response->setStatusCode(404, 'Not found');
        }

        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($res));
        $this->response->send();
    }

    public function cerrarSesionAction() {
        /*$datos = $this->request->getJsonRawBody();
        $token = $datos->token;
        $rws = Claves::find(
            [
                'conditions'  => 'clave = :tkn:',
                'bind'        => [
                    'tkn' => $token,
                ],
            ]
        );
        $this->response->setStatusCode(404, 'Not Found');
        $res = 'No se encontro la clave de acceso';
        if ($rws->count() === 1) {
            $cve = $rws->getFirst();
            $cve->Estado = 2;
            $res = $cve->save();
            if ($res === true) {*/
                $res = 'Sesion cerrada exitosamente';
                $this->response->setStatusCode(200, 'Ok');
            /*}
        }*/
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($res));
        $this->response->send();
    }

    public function privilegiosUsuarioAction() {
        $prvs = Privilegios::find([
            'conditions'  => 'Usuario = :usr:',
            'bind'        => [ 'usr' => $this->dispatcher->getParam('usuario'), ],
            'order'       => 'FuncionId',
        ]);

        $this->response->setStatusCode(404, 'Not Found');
        if ($prvs->count() > 0) {
            $this->response->setStatusCode(200, 'Ok');
        }

        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($prvs));
        $this->response->send();
    }

    public function privilegiosRolFuncionAction() {
        $rol = $this->dispatcher->getParam('rol');
        $fun = $this->dispatcher->getParam('funcion');
        $prvs = Privilegios::find([
            'conditions'  => 'UsuarioId = :rol: AND FuncionId = :fun:',
            'bind'        => [
                'rol' => $rol,
                'fun' => $fun,
            ],
        ]);

        $this->response->setStatusCode(404, 'Not Found');
        $res = 'No tiene privilegios para la operacion solicitada';
        if ($prvs->count() > 0) {
            $res = $prvs;
            $this->response->setStatusCode(200, 'Ok');
        }

        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($res));
        $this->response->send();
    }

    public function privilegiosRolAction() {
        $rol = $this->dispatcher->getParam('rol');
        $prvs = Privilegios::find([
            'conditions'  => 'UsuarioId = :rol:',
            'bind'        => [
                'rol' => $rol,
            ],
        ]);

        $this->response->setStatusCode(404, 'Not Found');
        $res = 'No tiene privilegios para la operacion solicitada';
        if ($prvs->count() > 0) {
            $res = $prvs;
            $this->response->setStatusCode(200, 'Ok');
        }

        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($res));
        $this->response->send();
    }

    public function funcionComandosAction() {
        $fun = $this->dispatcher->getParam('funcion');
        $phql = 'SELECT * FROM Pointerp\Modelos\Comandos 
            WHERE FuncionId = %d';
        $qry = new Query(sprintf($phql, $fun), Di::getDefault());
        $res = $qry->execute();
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($res));
        $this->response->send();
    }

    public function autorizacionesAction() {
        $est = $this->dispatcher->getParam('estado');
        $opciones = [
            'conditions' => 'estado = :est:',
            'bind'        => [
                'est' => $est,
            ],
            'order' => 'solicitud'
        ];
        /*if ($est == 9) {
            $opciones = [
                'order' => 'solicitud',
            ];
        }*/
        /*$auts = Autorizaciones::find($opciones);
        $this->response->setStatusCode(404, 'Not Found');*/
        $res = 'No se encontraron registros';
        /*if ($auts->count() > 0) {
            $res = [];
            foreach($auts as $a) {
                $p = $a;
                $p->cargarReferencia();
                $res[] = $p;
            }*/
            $this->response->setStatusCode(200, 'Ok');
        //}
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($res));
        $this->response->send();
    }

    public function autorizacionPorIdAction() {
        $id = $this->dispatcher->getParam('id');
        //$aut = Autorizaciones::findById($id);

        $this->response->setStatusCode(404, 'Not Found');
        $res = 'No exite el recurso solicitado';
        /*if ($aut->count() > 0) {
            $res = $aut->getFirst();
            $res->cargarReferencia();
            $this->response->setStatusCode(200, 'Ok');
        }*/
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($res));
        $this->response->send();
    }

    public function autorizacionesUsuarioAction() {
        $usr = $this->dispatcher->getParam('usuario');
        $est = $this->dispatcher->getParam('estado');
        /*$condicion = 'UsuarioId = :usr:';
        if ($est != 9) {
            $condicion .= ' and Estado = ' . $est;
        }
        $opciones = [
            'conditions' => $condicion,
            'bind'       => [
                'usr' => $usr,
            ],
            'order' => 'Solicitud'
        ];
        $auts = Autorizaciones::find($opciones);*/
        $this->response->setStatusCode(404, 'Not Found');
        $res = 'No se encontraron registros';
        /*if ($auts->count() > 0) {
            $res = [];
            foreach($auts as $a) {
                $p = $a;
                $p->cargarReferencia();
                $res[] = $p;
            }
            $this->response->setStatusCode(200, 'Ok');
        }*/
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($res));
        $this->response->send();
    }

    public function autorizacionCrearAction() {
        $datos = $this->request->getJsonRawBody();
        /*$aut = new Autorizaciones();
        $aut->funcion_id = $datos->funcion_id;
        $aut->usuario_id = $datos->usuario_id;
        $aut->comando_id = $datos->comando_id;
        $aut->supervisor = $datos->supervisor;
        $aut->entidad = $datos->entidad;
        $aut->referencia = $datos->referencia;
        $aut->ejecucion = $datos->ejecucion;
        $aut->resolucion = $datos->resolucion;
        $aut->solicitud = $datos->solicitud;
        $res = $con->create();*/
        $msj = 'Los datos se registraron correctamente';
        /*if ($res === false) {
            $this->response->setStatusCode(500, 'Internal Server Error');
            $msj = 'No se puede registrar los datos';
        } else {*/
            $this->response->setStatusCode(201, 'Created');
        //}
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($msj));
        $this->response->send();
    }

    public function autorizacionValidarAction() {
        $di = Di::getDefault();
        $fun = $this->dispatcher->getParam('funcion');
        $usr = $this->dispatcher->getParam('usuario');
        $phql = 'SELECT * FROM Pointerp\Modelos\Autorizaciones 
            WHERE usuario_id = %d AND funcion_id = %d AND estado <= 1';
        $qry = new Query(sprintf($phql, $usr, $fun), $di);
        $rws = $qry->execute();
        $this->response->setStatusCode(404, 'Not Found');
        $res = 'Debe solicitar autorizacion para la operacion';
        if ($rws->count() > 0) {
            $res = $rws->getFirst();
            if ($res->estado === 0) {
                $this->response->setStatusCode(401, 'Unauthorized');
                $res = 'La autorizacion solicitada no ha sido respondida';
            } elseif ($res->estado === 1) {
                $this->response->setStatusCode(200, 'Ok');
            } 
        }
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($res));
        $this->response->send();
    }

    public function autorizacionSolicitarAction() {
        // Estado = 0 (Pendiente)
        $hoy = new \DateTime();
        $sol = $this->request->getJsonRawBody();
        /*$fun = $sol->funcion;
        $cmd = $sol->comando;
        $usr = $sol->usuario;
        $ent = $sol->entidad;
        $rfr = $ref->referencia;
        $phql = 'SELECT * FROM Pointerp\Modelos\Autorizaciones 
            WHERE usuario_id = %d AND funcion_id = %d AND comando_id = %d AND estado <= 1';
        $qry = new Query(sprintf($phql, $usr, $fun, $cmd), $di);
        $rws = $qry->execute();
        if ($rws->count() > 0) {*/
            $this->response->setStatusCode(401, 'Unauthorized');
            /*$res = 'Ya existe una autorizacion en tramite para la operacion solicitada';
        } else {
            $aut = new Autorizaciones();
            $aut->funcion_id = $fun;
            $aut->usuario_id = $usr;
            $aut->comando_id = $cmd;
            $aut->entidad = $ent;
            $aut->referencia = $rfr;
            $aut->solicitud = $hoy->format('Y-m-d H:i:s');
            $aut->estado = 0;
            $res = $aut->create();
            $this->response->setStatusCode(200, 'Ok');*/
            $msj = 'Se ha enviado exitosamente la solicitud de autorizacion';
            /*if ($res === false) {
                $this->response->setStatusCode(500, 'Error');
                $msj = 'No se ha podido enviar la solicitud de autorizacion';
            }
        }*/
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($msj));
        $this->response->send();
    }

    public function autorizacionConcederAction() {
        // Estado = 1 (Concedido)
        $sol = $this->request->getJsonRawBody();
        $pid = $sol->id;
        $sup = $sol->supervisor;
        $res = $this->alterarEstadoAutorizacion($pid, 1, $sup);
        $this->response->setStatusCode(404, 'Not Found');
        $msj = 'No se encontro la autorizacion solicitado';
        if ($res) {
            $this->response->setStatusCode(200, 'Ok');
            $msj = 'La autorizacion se ha concedido exitosamente';
        }
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($msj));
        $this->response->send();
    }

    public function autorizacionDenegarAction() {
        // Estado = 2 (Denegado)
        $sol = $this->request->getJsonRawBody();
        $pid = $sol->id;
        $sup = $sol->supervisor;
        $res = $this->alterarEstadoAutorizacion($pid, 2, $sup);
        $this->response->setStatusCode(404, 'Not Found');
        $msj = 'No se encontro la autorizacion solicitada';
        if ($res) {
            $this->response->setStatusCode(200, 'Ok');
            $msj = 'La autorizacion solicitada se han denegado';
        }
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($msj));
        $this->response->send();
    }

    public function autorizacionEjecutarAction() {
        // Estado = 3 (Ejecutado)
        $sol = $this->request->getJsonRawBody();
        $pid = $sol->id;
        $res = $this->alterarEstadoAutorizacion($pid, 3, 0);
        $this->response->setStatusCode(404, 'Not Found');
        $msj = 'No se encontro la solicitud de autorizacion';
        if ($res) {
            $this->response->setStatusCode(200, 'Ok');
            $msj = 'La autorizacion se ha registrado como ejecutada';
        }
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($msj));
        $this->response->send();
    }

    private function alterarEstadoAutorizacion($id, $est, $sup) {
        $aut = Autorizaciones::findFirstById($id);
        if ($aut != null) {
            $hoy = new \DateTime();
            $aut->estado = $est;
            if ($sup > 0) {
                $aut->supervisor = $sup;
            } 
            if ($est >= 2) {   
                $aut->resolucion = $hoy->format('Y-m-d H:i:s');
            } else {
                $aut->ejecucion = $hoy->format('Y-m-d H:i:s');
            }
            return $aut->save();
        }
    }
    
    private function crearToken($usr) {
        $di = Di::getDefault();
        $config = $di->getConfig();
        $token = openssl_random_pseudo_bytes($config->entorno->tokenSize);
        $token = bin2hex($token);
        $hoy = new \DateTime();
        $cve = new Claves();
        $cve->clave   = $token;
        $cve->usuario = $usr;
        $cve->emision = $hoy->format('Y-m-d H:i:s');
        $cve->validez = $config->entorno->tokenDuracion;
        $cve->estado = 0;
        //$result = $cve->create();

        /*if ($result === false) {
            $token = 'Error no se pudo registrar la clave';
        }*/
        return ['token' => $token, 'clave' => $cve];
    }

    public function prevueloAction() {
        // No hace nada
    }

    public function pruebaAction() {
        $txt = $this->dispatcher->getParam('texto');
        $this->response->setStatusCode(200, 'Ok');
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode('code:' . md5($txt)));
        $this->response->send();
    }


}