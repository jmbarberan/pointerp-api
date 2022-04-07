<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;
use Pointerp\Modelos\Roles;

class Usuarios extends Modelo
{
    public function initialize()
    {
        $this->setSource('usuarios');
        $this->hasOne('RolId', Roles::class, 'Id', [
            'reusable' => true, // cache
            'alias'    => 'relRol',
          ]);
    }

    public function jsonSerialize () : array {
        $dat = $this->ToUnicodeArray();        
        if ($this->relRol != null) {
          $res['relRol'] = $this->relRol->ToUnicodeArray();
        }
        return $res;
    }
}