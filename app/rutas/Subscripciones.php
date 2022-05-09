<?php

namespace Pointerp\Rutas;

class SubscripcionesRutas extends \Phalcon\Mvc\Router\Group
{
  public function initialize()
  {
    $controlador = 'subscripciones';
    $this->setPaths(['namespace' => 'Pointerp\Controladores',]);
    $this->setPrefix('/api/v4/subscripciones');

    $this->addPost('/subscripciones/data', [
      'controller' => $controlador,
      'action'     => 'conexionPorCodigo',
    ]);
  }
}