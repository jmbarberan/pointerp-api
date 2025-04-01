<?php

namespace Pointerp\Controladores;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Phalcon\Di;
use Pointerp\Modelos\EmpresaParametros;
use Pointerp\Modelos\Empresas;
use Pointerp\Modelos\Maestros\Clientes;
use Pointerp\Modelos\Maestros\Registros;
use Pointerp\Modelos\SubscripcionesEmpresas;
use Pointerp\Modelos\Sucursales;
use Pointerp\Modelos\Ventas\Ventas;
use TCPDF;
use TCPDFBarcode;

class FirmaElectronicaController extends ControllerBase  {

  public function clavePorIdAction() {
    $this->view->disable();
    $id = $this->dispatcher->getParam('id');
    $res = Registros::findFirstById($id);

    if ($res != null) {
        $this->response->setStatusCode(200, 'Ok');
    } else {
        $res = [];
        $this->response->setStatusCode(404, 'Not found');
    }
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($res));
    $this->response->send();
  }

  public function enviarComprobantePorEmailAction($ventaId) {
    $venta = Ventas::findFirst($ventaId);
    $respEnvio = $this->enviarCorreoComprobante($venta);
    $ret = (object) [
			'res' => $respEnvio->res,
			'cid' => $respEnvio->cid,
			'msj' => $respEnvio->msj,
			'det' => $respEnvio->det,
		];
		$codMsg = "Ok";
    if ($respEnvio->cod == 500) {
			$codMsg = "Error";
		} elseif ($respEnvio->cod == 404) {
			$codMsg = "Not found";
		} elseif ($respEnvio->cod == 402) {
			$codMsg = "Bad request";
		}
		$this->response->setStatusCode($respEnvio->cod, $codMsg);
    $this->response->setContentType('application/json', 'UTF-8');
    $this->response->setContent(json_encode($ret));
    $this->response->send();
  }

  public function enviarCorreoComprobante($venta) {
		$valido = false;
		$di = Di::getDefault();
		$config = $di->get('config');
		$subscripcion = $config->entorno->subscripcion;
		$ret = (object) [
			'res' => false,
			'msj' => 'Los datos no se pudieron procesar',
			'det' => '',
			'cod' => 500
		];
    if (isset($venta)) {
      $clienteData = $venta->relCliente;
      if (!isset($clienteData)) {
        $clienteData = Clientes::findFirst($venta->ClienteId);
      }
      if (isset($clienteData->Email)) {
        // verificar si el correo es valido TODO
        $empresa = Empresas::findFirst($clienteData->EmpresaId);
        $subscripcionData = SubscripcionesEmpresas::findFirst([
          'conditions' => "subscripcion_id = {$subscripcion} and empresa_id = {$clienteData->EmpresaId}"
        ]);
        // empresa de subscripcion
        $destinatario = $clienteData->Email; // viene del cliente
        $asunto = 'Se ha emitido un comprobante electronico';
        $contenidoHtml = "<h4>Estimado(a), {$clienteData->Nombres}</h4>" . 
          "<p>A continuación adjuntamos el Comprobante electrónico en formato XML y su interpretación en formato PDF de su FACTURA ELECTRÓNICO(A) que hemos generado por su compra en nuestro establecimiento</p>"; // Quemado
        $xmlString = $venta->CEContenido;
        // cargar de plantilla reemplazar valores
        //$htmlParaPdf = '<h1>Factura</h1><p>Este es un contenido para el PDF de impresión.</p>'; // viene de la plantilla
        $valido = true;
      } else {
        $ret->msj = "El cliente no tiene correo registrado";
        $ret->cod = 402;
      }
    } else {
      $ret->msj = "El numero de comprobante no existe";
      $ret->cod = 404;
    }
    
    if ($valido) {
      $mail = new PHPMailer(true);
      try {
        // Configuración de Gmail
        $mail->isSMTP();
        $mail->Host = $subscripcionData->email_host;
        $mail->SMTPAuth = true;
        $mail->Username = $subscripcionData->email_user;
        $mail->Password = $subscripcionData->email_pas;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $subscripcionData->email_port;
        $mail->isHTML(true);

        // Configuración del correo
        $mail->setFrom($subscripcionData->email_dir, $empresa->NombreComercial);
        $mail->addAddress($destinatario);
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $contenidoHtml;

        // Adjuntar archivo XML
        $mail->addStringAttachment($xmlString, 'comprobante.xml', 'base64', 'application/xml');

        // Crear pdf RIDE
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetTitle('Invoice');
        $pdf->SetSubject('Invoice');
        $pdf->SetKeywords('Invoice');
        $pdf->setHeaderFont([ PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN ]);
        $pdf->setFooterFont([ PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA ]);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(PDF_MARGIN_LEFT, 10, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFont('times', '', 12);
        $pdf->AddPage();

        $type = 'C128';
        $barcodeFile = "barcode{$venta->CEClaveAcceso}.png";
        $bc = new TCPDFBarcode($venta->CEClaveAcceso, $type);
        $barcodePNG = $bc->getBarcodePngData(2, 60, [0,0,0]);
        $pathPng = __DIR__ . DIRECTORY_SEPARATOR . $barcodeFile;
        if ($barcodePNG !== false) {
          file_put_contents($pathPng, $barcodePNG);
        }
        
        $logoStyle = $subscripcionData->logo_style;
        $logoFile = $subscripcionData->logo_file;
        $empObligadoContabilidad = $empresa->ObligadoContabilidad > 0 ? "SI" : "NO";

        $fSubtotal = number_format($venta->Subtotal, 2, ".", ",");
        $fSubtotalEx = number_format($venta->SubtotalEx, 2, ".", ",");
        $fImpuestos = number_format($venta->Impuestos, 2, ".", ",");
        $total = $venta->Subtotal + $venta->SubtotalEx + $venta->Impuestos;
        $total = number_format($total, 2, ".", ",");
        $sucursal = $venta->relSucursal;
        if (!isset($sucursal)) {
          $sucursal = Sucursales::findFirst($venta->SucursalId);
        }
        $secuencial = "{$sucursal->Codigo}-{$sucursal->Descripcion}-{$venta->Numero}";
        $ambiente = "Produccion";
        $oAmbiente = Registros::findFirst([
          "conditions" => "Id = {$empresa->TipoAmbiente}"
        ]);
        if (isset($oAmbiente)) {
          $ambiente = $oAmbiente->Denominacion;
        }
        $regimen = "";
        $oRegimen = EmpresaParametros::findFirst([
          "conditions" => 
            "EmpresaId = {$empresa->Id} and 
            Tipo = 1 and
            Referencia = 11 and
            Estado = 0"
        ]);
        if (isset($oRegimen)) {
          $regimen = $oRegimen->Denominacion;
        }
        $formaPago = "Sin utlizacion del sistema financiero";
        $oFormaPago = Registros::findFirst([
          "conditions" => "Id = {$venta->CERespuestaId}"
        ]);
        if (isset($oFormaPago)) {
          $formaPago = $oFormaPago->Denominacion;
        }

        $htmlItems = "";

        foreach ($venta->relItems as $detalle) {
          $fTotalItem = number_format(($detalle->Cantidad * $detalle->Precio), 2, ".", ",");
          $fPrecio = number_format($detalle->Precio, 2, ".", ",");
          $htmlItems .= "<tr class=\"item\">
            <td>{$detalle->relProducto->Codigo}</td>
            <td class=\"descripcion-producto\">{$detalle->relProducto->Nombre}</td>
            <td class=\"valor\">{$detalle->Cantidad}</td>
            <td class=\"valor\">{$fPrecio}</td>
            <td class=\"valor\">0.00</td>
            <td class=\"valor\">{$fTotalItem}</td>
          </tr>"."\n";
        }
        $htmlFactura = <<<EOD
<!DOCTYPE html>
<html lang="es">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>Invoice template</title>
		<style>
			.invoice-box {
				max-width: 800px;
				margin: auto;
				padding: 30px;
				font-size: 12px;
				line-height: 1.5;
				font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
				color: #555;
			}

			.invoice-box table {
				width: 100%;
				text-align: left;
			}

			.invoice-box table td {
				padding: 5px;
				vertical-align: top;
			}

			.invoice-box table tr.top table td {
				padding-bottom: 20px;
			}

			.invoice-box table tr.top table td.title {
				font-size: 45px;
				line-height: 45px;
				color: #333;
				width: 400px;
			}

			.invoice-box table tr.information table td {
				padding-bottom: 40px;
			}

			.invoice-box table tr.heading td {
				border-bottom: 2px solid #ddd;
                border-top: 2px solid #ddd;
				font-weight: bold;
			}

			.invoice-box table tr.item td {
				border-bottom: 1px solid #eee;
			}

			.invoice-box table tr.item.last td {
				border-bottom: 2px solid #eee;
			}            

			.invoice-box table tr.total td {
					font-weight: bold;
					text-align: right;
			}

			.invoice-number {
				font-weight: bold;
				margin: initial;
			}

			.last {
					border-bottom: 2px solid #eee;
			}	

			.infoadicional {
				border-bottom: 0px;
			}

      .logo { 
        $logoStyle 
      }

			.valor {
				text-align: right;
			}

      .decripcion-producto {
				width: 200px;
			}

			.destacado {
				font-weight: bold;
			}

			.descripcion {
				max-width: 200px;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
			
			.codebar {
				display: flex;
				flex-direction: column;
				align-items: center;
				width: 100%;
			}
			
			.codebar img {
					margin: auto;
					width: 308px;
			}

			.codebar span {
				display: block;
				max-width: 304px;
				word-wrap: break-word;
				text-align: center;
			}
		</style>
	</head>
	<body>
		<div class="invoice-box">
			<table cellpadding="0" cellspacing="0">
				<tr class="top">
					<td colspan="6">
						<table>
							<tr>
								<td class="title">
									<img class="logo" alt="Logo"
										src="$logoFile"
									/>
								</td>

								<td>
									R.U.C. : $empresa->RazonSocial
									<h2 class="invoice-number">Factura #: $secuencial</h2>
									Autorizacion: $venta->CEClaveAcceso<br>
									Fecha autorizacion: $venta->CEAutorizaFecha<br/>
									<br/>
									Ambiente: $ambiente<br/>
									Emision: Normal<br/>
								</td>
							</tr>
						</table>
					</td>
				</tr>

				<tr class="information">
					<td colspan="6">
						<table>
							<tr>
								<td>
									<span class="destacado">$empresa->RazonSocial</span><br/>
									Direccion: $sucursal->Direccion<br/>
									Telefonos: $sucursal->Telefono<br/>
									Obligado a llevar contabilidad: $empObligadoContabilidad<br/>
									$regimen<br/>
								</td>

								<td>
									<div class="codebar">
										<img src="{$pathPng}" alt="Codebar"/>
										<span>$venta->CEClaveAcceso</span>
									</div>
								</td>
							</tr>
						</table>
					</td>
				</tr>

				<tr class="heading" style="background-color: #e0e0e0;">
					<td colspan="6" class="descripcion">Razon social / Nombres: <span class="destacado">$clienteData->Nombres</span></td>
				</tr>

				<tr class="details">
					<td colspan="3">Cedula / R.U.C: <span class="destacado">$clienteData->Identificacion</span></td>
					<td colspan="3">Telefonos: <span class="destacado">$clienteData->Telefonos</span></td>
				</tr>

				<tr class="details">
					<td colspan="4" class="descripcion">Direccion: <span class="destacado">$clienteData->Direccion</span></td>
					<td colspan="2">Guia de remision:</td>
				</tr>

				<tr class="details">
					<td colspan="6" class="descripcion">Correo electronico: <span class="destacado">$clienteData->Email</span></td>
				</tr>

				<tr class="heading" style="background-color: #e0e0e0;">
					<td>C&oacute;digo</td>
					<td class="descripcion-producto">Descripci&oacute;n</td>
					<td class="valor">Cantidad</td>
					<td class="valor">Precio</td>
					<td class="valor">Descto.</td>
					<td class="valor">Subtotal</td>
				</tr>

				$htmlItems

				<tr class="total first">
					<td colspan="4"></td>
					<td>Subtotal</td>
					<td>$fSubtotal</td>
				</tr>
				<tr class="total">
					<td colspan="4">
						<span class="destacado">Forma de pago: </span>$formaPago
					</td>
					<td>Subtotal 0%</td>
					<td>$fSubtotalEx</td>
				</tr>
				<tr class="total">
					<td colspan="4">
						<span class="destacado">Numero control: </span>$venta->Numero
					</td>
					<td>Descuento</td>
					<td>0.00</td>
				</tr>
				<tr class="total">
					<td colspan="4"></td>
					<td>IVA 15%</td>
					<td>$fImpuestos</td>
				</tr>
				<tr class="total last">
					<td colspan="4"></td>
					<td>TOTAL</td>
					<td>$total</td>
				</tr>
			</table>
		</div>
	</body>
</html>
EOD; // end of html
        $pdf->writeHTML($htmlFactura, true, false, false, false, '');
        $pdfOutput = $pdf->Output('comprobante.pdf', 'S');
        $mail->addStringAttachment($pdfOutput, 'comprobante.pdf', 'base64', 'application/pdf');
        if ($mail->send()) {
          $ret->res = true;
          $ret->msj = "Correo enviado con exito";
          $ret->cod = 200;
        } else {
          $ret->msj = "Se produjo un error al enviar el correo";
          $ret->det = $mail->ErrorInfo;
          $$ret->cod = 500;
        }
        $mail->smtpClose();
        if ($barcodePNG !== false) {
          unlink($pathPng);
		    }
			} catch (Exception $e) {
				$ret->msj = "Se produjo un error al procesar el comprobante";
        $ret->det = $e->getMessage();
				$ret->cod = 500;
			}
		}
		return $ret;
  }
}  