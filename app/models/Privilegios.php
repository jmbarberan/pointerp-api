<?php

namespace Pointerp\Modelos;

use Phalcon\Mvc\Model;

class Privilegios extends Modelo
{
    protected $funcionNav;

    public function getFuncionNav() {
        return $this->funcionNav;
    }

    public function setFuncionNav($fun) {
        $this->funcionNav = $fun;
    }
    
    public function initialize()
    {
        $this->setSource('privilegios');
        
        $this->hasOne('FuncionId', Funciones::class, 'Id', [
            'reusable' => true, // cache
            'alias'    => 'relFuncion',
        ]);
    }

    public function jsonSerialize () : array {
        $res = $this->toArray();
        if ($this->relFuncion != null) {   
            $res['relFuncion'] = $this->relFuncion->toArray();
            if ($this->relFuncion->relComandos != null) {
                $res['relFuncion']['relComandos'] = $this->relFuncion->relComandos->toArray();
            }
            if ($this->relFuncion->relModulo != null) {
                $res['relFuncion']['relModulo'] = $this->relFuncion->relModulo->toArray();
            }
        }
        return $res;
    }
}