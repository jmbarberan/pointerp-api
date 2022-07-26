<?php

namespace Pointerp\Rutas;

class CxcRutas extends \Phalcon\Mvc\Router\Group
{
  public function initialize()
  {
    $controlador = 'cxc';
    $this->setPaths(['namespace' => 'Pointerp\Controladores',]);
    $this->setPrefix('/api/v4/cxc');

    $this->addGet('/comprobantes/sucursal/{sucursal}/cliente/{cliente}/cuentacorriente', [
      'controller' => $controlador,
      'action'     => 'cuentaCorriente',
    ]);
  }
}