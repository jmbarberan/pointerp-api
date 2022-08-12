<?php

namespace Pointerp\Controladores;

use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
//use Pointerp\Modelos\Maestros\Registros;
use Pointerp\Modelos\Subscripciones;
use Pointerp\Modelos\Tickets;

class SubscripcionesController extends ControllerBase  {

  public function conexionPorCodigoAction() {
    $cred = $this->request->getJsonRawBody();
    $di = Di::getDefault();
    $phql = 'SELECT * FROM Pointerp\Modelos\Subscripciones 
        WHERE usuario = "%s"';
    $qry = new Query(sprintf($phql, $cred->codigo), $di);
    $rws = $qry->execute();
    $this->response->setStatusCode(404, 'Not found');
    $rus['resultado'] = false;
    if ($rws->count() === 1) {
      $sub = $rws->getFirst();
      $rus['resultado'] = true;
      $rus['dbhost'] = $sub->dbhost;
      $rus['dbuser'] = $sub->dbuser;
      $rus['dbpass'] = $sub->dbpass;
      $rus['dbport'] = $sub->dbport;
      $this->response->setStatusCode(200, 'Ok');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($rus));
    $this->response->send();
  }

  public function codigoValidoAction() {
    $res = (object) [
      'res' => false,
      'cid' => 0,
      'nom' => '',
      'tipo' => 0,
      'logo' => '',
      'msj' => 'El codigo no es valido'
    ];
    $datos = $this->request->getJsonRawBody();
    $this->response->setStatusCode(404, 'Not Found');
    if (strlen($datos->codigo) > 0) {
      $sub = Subscripciones::findFirst([
        'conditions' => "clave = '" . trim(base64_decode($datos->codigo)) . "'"
      ]);
      if ($sub != null) {
        $this->response->setStatusCode(200, 'Ok');
        $res->res = true;
        $res->cid = $sub->id;
        $res->tipo = $sub->dbexclusive;
        $res->nom = $sub->nombre;
        $res->logo = $sub->logoruta;
        $res->msj = 'Codigo correcto';
      }
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function ticketReseteoSolicitarAction() {    
    $datos = $this->request->getJsonRawBody();
    $ret = (object) [
      'res' => false,
      'cid' => 0,
      'msj' => 'Los datos no se pudieron procesar'
    ];
    $this->response->setStatusCode(404, 'Not Found');
    if (strlen($datos->codigo) > 0) {
      $sub = Subscripciones::findFirst([
        'conditions' => "email = '" . base64_decode($datos->codigo) . "'"
      ]);
      if ($sub) {
        $para  = $sub->email;
        $titulo = 'Solicitud de reseteo de codigo en Viniapro';
        $nombre = $sub->nombre;
        $dt = new \DateTime("now");
        $tk = new Tickets();
        $tk->tipo = 1;
        $tk->subscripcion_id = $sub->id;
        $tk->fecha = date_format(new \DateTime(), 'Y-m-d H:i:s');
        $tk->token = uniqid();
        $tk->estado = 0;
        if ($tk->create()) {
          #region html del correo
          $htmlCorreo = '<html>';   
          $htmlCorreo .= '<head>';
          $htmlCorreo .= '  <title>Recordatorio de cumpleaños para Agosto</title>';
          $htmlCorreo .= '  <style>html,body { padding: 0; margin:0; }</style>';
          $htmlCorreo .= '</head>';
          $htmlCorreo .= '<body>';
          $htmlCorreo .= '  <div style="font-family:Arial,Helvetica,sans-serif; line-height: 1.5; font-weight: normal; font-size: 15px; color: #2F3044; min-height: 100%; margin:0; padding:0; width:100%; background-color:#edf2f7">';
          $htmlCorreo .= '    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin:0 auto; padding:0; max-width:600px">';
          $htmlCorreo .= '      <tbody>';
          $htmlCorreo .= '        <tr>';
          $htmlCorreo .= '          <td align="center" valign="center" style="text-align:center; padding: 40px">';
          $htmlCorreo .= '            <a href="https://app.viniapro.com" rel="noopener" target="_blank">';
          $htmlCorreo .= '              <img alt="Viniapro" src="https://viniapro.com/img/logo.8fc98385.png" style="width: 100px;" />';
          $htmlCorreo .= '            </a>';
          $htmlCorreo .= '          </td>';
          $htmlCorreo .= '        </tr>';
          $htmlCorreo .= '        <tr>';
          $htmlCorreo .= '          <td align="left" valign="center">';
          $htmlCorreo .= '            <div style="text-align:left; margin: 0 20px; padding: 40px; background-color:#ffffff; border-radius: 6px">';
          //                            <!--begin:Email content-->
          $htmlCorreo .= '              <div style="padding-bottom: 30px; font-size: 17px;">';
          $htmlCorreo .= '                <strong>Hola!</strong>';
          $htmlCorreo .= '              </div>';
          $htmlCorreo .= '              <div style="padding-bottom: 30px">Ha recibido este correo por que solicito resetear su codigo de acceso a la App de Viniapro. para realizar el reseteo haga click en el boton abajo:</div>';
          $htmlCorreo .= '              <div style="padding-bottom: 40px; text-align:center;">';
          $htmlCorreo .= '                <a href="https://app.viniapro.com/subscripciones/codigo/resetear/' . $tk->token . '" rel="noopener" style="text-decoration:none;display:inline-block;text-align:center;padding:0.75575rem 1.3rem;font-size:0.925rem;line-height:1.5;border-radius:0.35rem;color:#ffffff;background-color:#009EF7;border:0px;margin-right:0.75rem!important;font-weight:600!important;outline:none!important;vertical-align:middle" target="_blank">Resetear codigo</a>';
          $htmlCorreo .= '              </div>';
          $htmlCorreo .= '              <div style="padding-bottom: 30px">Este link de reseteo expira en 60 minutos. Si Ud. no hizo esta solicitud, solo ignore este mensaje.</div>';
          $htmlCorreo .= '              <div style="border-bottom: 1px solid #eeeeee; margin: 15px 0"></div>';
          $htmlCorreo .= '              <div style="padding-bottom: 50px; word-wrap: break-all;">';
          $htmlCorreo .= '                <p style="margin-bottom: 10px;">El boton no funciona? Intente pegar esta URL en su navegador:</p>';
          $htmlCorreo .= '                <a href="https://app.viniapro.com/subscripciones/codigo/resetear/' . $tk->token . '" rel="noopener" target="_blank" style="text-decoration:none;color: #009EF7">https://keenthemes.com/account/password/reset/07579ae29b6?email=max%40kt.com</a>';
          $htmlCorreo .= '              </div>';
          //                            <!--end:Email content-->
          $htmlCorreo .= '              <div style="padding-bottom: 30px">No necesita responder a este correo.</div>';
          $htmlCorreo .= '              <div style="padding-bottom: 10px">Saludos,';
          $htmlCorreo .= '              <br>El equipo de Viniapro.';
          $htmlCorreo .= '              <tr>';
          $htmlCorreo .= '                <td align="center" valign="center" style="font-size: 13px; text-align:center;padding: 20px; color: #6d6e7c;">';
          $htmlCorreo .= '                  <p>Guayaquil - Ecuador.</p>';
          $htmlCorreo .= '                  <p>Copyright ©';
          $htmlCorreo .= '                  <a href="https://viniapro.com" rel="noopener" target="_blank">Viniapro</a>.</p>';
          $htmlCorreo .= '                </td>';
          $htmlCorreo .= '              </tr></br></div>';
          $htmlCorreo .= '            </div>';
          $htmlCorreo .= '          </td>';
          $htmlCorreo .= '        </tr>';
          $htmlCorreo .= '      </tbody>';
          $htmlCorreo .= '    </table>';
          $htmlCorreo .= '  </div>';
          $htmlCorreo .= '</body>';
          $htmlCorreo .= '</html>';
          #endregion
          
          $cabeceras  = 'MIME-Version: 1.0' . "\r\n";
          $cabeceras .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
          $cabeceras .= 'To: ' .$nombre. ' <' .$para. '>' . "\r\n";
          $cabeceras .= 'From: Soporte Viniapro <support@viniapro.com>' . "\r\n";
      
          mail($para, $titulo, $htmlCorreo, $cabeceras);

          $this->response->setStatusCode(200, 'Ok');
          $ret->res = true;
          $ret->msj = "En unos miniutos recibirá un correo con las instrucciones para resetear el código";
        }
      } else {
        $ret->msj = 'El correo no se encuentra registrado' . $sub->email;
      }
    } else {
      $ret->msj = 'La información proporcionada no es válida';
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function ticketReseteoValidarAction() {
    // validar la autenticidad del ticket para resetear codigo de subscriptor
    $codigo = $this->dispatcher->getParam('codigo');
    $ret = (object) [
      'res' => false,
      'cid' => 0,
      'msj' => 'El codigo de reseteo es invalido o ha caducado'
    ];
    $this->response->setStatusCode(404, 'Not Found');

    $tk = Tickets::findFirst([
      'conditions' => "token = '" . $codigo . "'"
    ]);
    if ($tk != null) {
      if ($tk->estado == 0) {
        if(time() - strtotime($tk->fecha) < 3600 ) { // 2400
          $this->response->setStatusCode(200, 'Ok');
          $ret->res = true;
          //$ret->cid = $tk->id;
          $ret->msj = "El código de reseteo es valido";
        }
      }
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function codigoActualizarAction() {
    $datos = $this->request->getJsonRawBody();
    $ret = (object) [
      'res' => false,
      'cid' => 0,
      'msj' => 'No se pudo actualizar su codigo de acceso'
    ];
    $tk = Tickets::findFirst([
      'conditions' => "token = '" . $datos->token . "'"
    ]);
    if ($tk != null) {
      $sub = Subscripciones::findFirst([
        'conditions' => "clave = '" . $datos->clave . "' and id != " . $tk->subscripcion_id
      ]);
      if ($sub != null) {
        $ret->res = true;
        $ret->msj = "El codigo ya se encuentra registrado";
        $this->response->setStatusCode(406, 'Ok');
      } else {
        $tk->estado = 1;
        if($tk->update()) {
          $sub = Subscripciones::findFirstById($tk->subscripcion_id);
          $sub->clave = $datos->clave;
          if ($sub->update()) {
            $ret->res = true;
            $ret->msj = "Se actualizo correctamente el codigo de acceso";
            $this->response->setStatusCode(200, 'Ok');
          } else {
            $msj = "Los datos se actualizaron parcialmente, error:" . "\n";
            foreach ($cli->getMessages() as $m) {
              $msj .= $m . "\n";
            }
            $ret->res = false;
            $ret->msj = $msj;
          }        
        } else {
          $msj = "No se puede actualizar los datos, error: " . "\n";
          foreach ($pac->getMessages() as $m) {
            $msj .= $m . "\n";
          }
          $ret->res = false;
          $ret->msj = $msj;
        }
      }
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  // codigoExistente 
  // enviarReseteo
  // actualizarCodigo

}
