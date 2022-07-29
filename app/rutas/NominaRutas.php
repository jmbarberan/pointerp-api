<?php

namespace Pointerp\Rutas;

class NominaRutas extends \Phalcon\Mvc\Router\Group
{
  public function initialize()
  {
    $controlador = 'nomina';
    $this->setPaths(['namespace' => 'Pointerp\Controladores',]);
    $this->setPrefix('/api/v4/nomina');

    $this->addGet('/tablas/{tabla}/registros/sub/{sub}/emp/{emp}', [
      'controller' => $controlador,
      'action'     => 'registrosPorTabla',
    ]);
    $this->addGet('/tablas/{tabla}/registro/indice/{indice}/sub/{sub}/emp/{emp}', [
      'controller' => $controlador,
      'action'     => 'registroPorTablaIndice',
    ]);
    $this->addGet('/tablas/registros/{id}', [
      'controller' => $controlador,
      'action'     => 'registroPorId',
    ]);
  }
}