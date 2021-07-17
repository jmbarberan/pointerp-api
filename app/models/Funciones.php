<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Funciones extends Modelo {
    
    public function initialize()
    {
        $this->setSource('funciones');

        $this->hasOne('ModuloId', Modulos::class, 'Id', [
            'reusable' => true, // cache
            'alias'    => 'relModulo',
        ]);

        $this->hasMany('Id', Comandos::class, 'FuncionId',
            [
                'reusable' => true,
                'alias'    => 'relComandos'
            ]
        );
    }

    public function jsonSerialize () : array {
        $res = $this->toArray();
        if ($this->relComandos != null) {
            $res['relComandos'] = $this->relComandos->toArray();
        }
        if ($this->relModulo != null) {
            $res['relModulo'] = $this->relModulo->toArray();
        }
        return $res;
    }
}