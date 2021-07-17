<?php

namespace Pointerp\Rutas;

class SeguridadRutas extends \Phalcon\Mvc\Router\Group
{
    public function initialize()
    {
        $controlador = 'seguridad';
        $this->setPaths(['namespace' => 'Pointerp\Controladores',]);
        $this->setPrefix('/api/v4/seguridad');

        $this->addGet('/usuarios/lista/{estado}', [
            'controller' => $controlador,
            'action'     => 'usuariosTodos',
        ]);

        $this->addGet('/usuarios/{id}', [
            'controller' => $controlador,
            'action'     => 'usuarioPorId',
        ]);

        $this->addGet('/roles', [
            'controller' => $controlador,
            'action'     => 'rolesTodos',
        ]);

        $this->addPost('/credenciales/validar', [
            'controller' => $controlador,
            'action'     => 'credencialesValidar',
        ]);

        $this->addPut('usuario/credenciales/cambiar', [
            'controller' => $controlador,
            'action'     => 'cambiarClave',
        ]);

        $this->addPost('/usuarios/guardar', [
            'controller' => $controlador,
            'action'     => 'usuarioGuardar',
        ]);

        $this->addPut('/salir', [
            'controller' => $controlador,
            'action'     => 'cerrarSesion',
        ]);

        $this->addGet('/privilegios/rol/{rol}/funcion/{funcion}', [
            'controller' => $controlador,
            'action'     => 'privilegiosRolFuncion',
        ]);

        $this->addGet('/privilegios/rol/{rol}', [
            'controller' => $controlador,
            'action'     => 'privilegiosRol',
        ]);

        $this->addGet('/autorizaciones/{id}', [
            'controller' => $controlador,
            'action'     => 'autorizacionPorId',
        ]);

        $this->addGet('/autorizaciones/estado/{estado}', [
            'controller' => $controlador,
            'action'     => 'autorizaciones',
        ]);

        $this->addGet('/autorizaciones/usuario/{usuario}/estado/{estado}', [
            'controller' => $controlador,
            'action'     => 'autorizacionesUsuario',
        ]);

        $this->addGet('/autorizaciones/validar/funcion/{funcion}/usuario/{usuario}/comando/{comando}', [
            'controller' => $controlador,
            'action'     => 'autorizacionValidar',
        ]);

        $this->addPut('/autorizaciones/conceder', [
            'controller' => $controlador,
            'action'     => 'autorizacionConceder',
        ]);

        $this->addPut('/autorizaciones/denegar', [
            'controller' => $controlador,
            'action'     => 'autorizacionDenegar',
        ]);

        $this->addPut('/autorizaciones/ejecutar', [
            'controller' => $controlador,
            'action'     => 'autorizacionEjecutar',
        ]);

        $this->addGet('/funciones/{funcion}/comandos', [
            'controller' => $controlador,
            'action'     => 'funcionComandos',
        ]);
        $this->addGet('/prueba/{texto}', [
            'controller' => $controlador,
            'action'     => 'prueba',
        ]);
    }
}