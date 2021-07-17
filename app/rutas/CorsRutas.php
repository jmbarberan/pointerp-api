<?php

namespace Pointerp\Rutas;

class CorsRutas extends \Phalcon\Mvc\Router\Group
{
    public function initialize()
    {
        $controlador = 'seguridad';
        $this->setPaths(['namespace' => 'Pointerp\Controladores',]);
        $this->setPrefix('/api/v4');

        $this->addOptions('/{catch:(.*)}', [
            'controller' => 'seguridad',
            'action'     => 'prevuelo',
        ]);
    }
}