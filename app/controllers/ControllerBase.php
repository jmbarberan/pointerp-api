<?php

namespace Pointerp\Controladores;

use Phalcon\Di;
use Phalcon\Mvc\Controller;

class ControllerBase extends Controller
{
    public function beforeExecuteRoute($dispatcher) {
        //$config = Di::getDefault()->getConfig();
        $this->view->disable();
        //$this->response->setHeader('Access-Control-Allow-Origin', $config->cors->origen);
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET,PATCH,PUT,POST,DELETE,OPTIONS');
        $this->response->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Range, Content-Disposition, Content-Type, Authorization, Cache-control, Pragma');
        $this->response->setHeader('Access-Control-Allow-Credentials', 'true');
        $this->response->setContentType('application/json', 'UTF-8');
        if ($this->request->getMethod() === 'OPTIONS') {
            $this->response->setStatusCode(200, 'OK');
            $this->response->setContent(json_encode(['Resultado' => 'Prevuelo ejecutado satisfactoriamente']));
            $this->response->send();
            exit;
        }
    }

    public function afterExecuteRoute() 
    {
        /*if ($this->request->getMethod() != 'POST') {
            $config = Di::getDefault()->getConfig();
            $this->response->setHeader('Access-Control-Allow-Origin', $config->cors->origen);
            $this->response->setHeader('Access-Control-Allow-Methods', 'GET,PATCH,PUT,POST,DELETE,OPTIONS');
            $this->response->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Range, Content-Disposition, Content-Type, Authorization, Cache-control, Pragma');
            $this->response->setHeader('Access-Control-Allow-Credentials', 'true');
            if ($this->request->getMethod() === 'OPTIONS') {
                $this->response->setStatusCode(200, 'OK');
                $this->response->setContentType('application/json', 'UTF-8');
                $this->response->setContent(json_encode(['Resultado' => 'Prevuelo ejecutado satisfactoriamente']));
                $this->response->send();
                exit;
            }
        }*/
    }
}
