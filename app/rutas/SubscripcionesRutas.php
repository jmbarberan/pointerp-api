<?php

namespace Pointerp\Rutas;

class SubscripcionesRutas extends \Phalcon\Mvc\Router\Group
{
  public function initialize()
  {
    $controlador = 'subscripciones';
    $this->setPaths(['namespace' => 'Pointerp\Controladores',]);
    $this->setPrefix('/api/v4/subscripciones');

    $this->addPost('/data', [
      'controller' => $controlador,
      'action'     => 'conexionPorCodigo',
    ]);

    $this->addPost('/codigo/validar', [
      'controller' => $controlador,
      'action'     => 'codigoValido',
    ]);
  }
}