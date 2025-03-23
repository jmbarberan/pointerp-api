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

  public function enviarComprobantePorEmail($ventaId) {
    $valido = false;
    $error = "";
    $venta = Ventas::findFirst($ventaId);
    $di = Di::getDefault();
    $config = $di->get('config');
    $subscripcion = $config->entorno->subscripcion;
    if (isset($venta)) {      
      $clienteData = $venta->relCliente;
      if (!isset($clienteData)) {
        $clienteData = Clientes::findFirst($venta->ClienteId);
      }
      if (isset($clienteData->email)) {
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
        $htmlParaPdf = '<h1>Factura</h1><p>Este es un contenido para el PDF de impresión.</p>'; // viene de la plantilla
      } else {
        $error = "El cliente no tiene correo registrado";
      }
    } else {
      $error = "El numero de comprobante no existe";
    }
    
    if ($valido) {
      $mail = new PHPMailer(true);
      try {
          // Configuración de Gmail
          $mail->isSMTP();
          $mail->Host = $subscripcionData->email_host;
          $mail->SMTPAuth = true;
          //$mail->SMTPSecure = "tls";
          $mail->Username = $subscripcionData->email_user;
          $mail->Password = $subscripcionData->email_pass;
          /*if ($subscripcionData->email_tls == "1") {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
          }*/
          $mail->Port = $subscripcionData->email_port;

          // Configuración del correo
          $mail->setFrom($subscripcionData->email_dir, $empresa->NombreComercial); // correo de la empresa
          $mail->addAddress($destinatario); // Correo del cliente
          $mail->isHTML(true); // Activar contenido HTML
          $mail->Subject = $asunto;
          $mail->Body = $contenidoHtml;

          // Adjuntar archivo XML
          $mail->addStringAttachment($xmlString, 'factura.xml', 'base64', 'application/xml');

          // Crear pdf RIDE
          $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
					$pdf->SetTitle('Invoice');
					$pdf->SetSubject('Invoice');
					$pdf->SetKeywords('Invoice');
					$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
					$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
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
					$barcodePNG = $bc->getBarcodePngData(2, 60, array(0,0,0));
					$pathPng = __DIR__ . DIRECTORY_SEPARATOR . $barcodeFile;
					if ($barcodePNG !== false) {
							file_put_contents($pathPng, $barcodePNG);
					}

					
          $logoStyle = $subscripcionData->logo_style;
          $logoFile = $subscripcionData->logo_file;
					$empObligadoContabilidad = $empresa->ObligadoContabilidad > 0 ? "SI" : "NO";

          $total = $venta->Subtotal + $venta->Subtotal0 + $venta->Impuestos;
          $total = number_format($venta->Total, 2, ".", ",");
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
						$ambiente = $oAmbiente->Denonimacion;
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
						$regimen = $oRegimen->Denonimacion;
					}
          $formaPago = "Sin utlizacion del sistema financiero";
					$oFormaPago = Registros::findFirst([
						"conditions" => "Id = {$venta->CERespuestaId}"
					]);
					if (isset($oFormaPago)) {	
						$formaPago = $oFormaPago->Denonimacion;
					}

          $htmlItems = "";
          foreach ($venta->relDetalles as $detalle) {
            $htmlItems .= "<tr class=\"item\">
              <td>{$detalle->relProducto->Codigo}</td>
              <td class=\"descripcion-producto\">{$detalle->relProducto->Nombre}</td>
              <td class=\"valor\">{$detalle->Cantidad}</td>
              <td class=\"valor\">{$detalle->Precio}</td>
              <td class=\"valor\">0.00</td>
              <td class=\"valor\">{$detalle->Total}</td>
            </tr>"."\n";
          }
          $tbl = <<<EOD
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
				font-size: 14px;
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
									Telefonos: $sucursal->Telefonos<br/>
									Obligado a llevar contabilidad: $empObligadoContabilidad<br/>
									$regimen<br/>
								</td>

								<td>
									<div class="codebar">
										<img src="barcodeFile" alt="Codebar" class="barcode" />
										<span>$venta->CEClaveAcceso</span>
									</div>
								</td>
							</tr>
						</table>
					</td>
				</tr>

				<tr class="heading" style="background-color: #e0e0e0;">
					<td colspan="6" class="descripcion">Razon social / Nombres: <span class="destacado">$venta->relCliente->Nombres</span></td>
				</tr>

				<tr class="details">
					<td colspan="3">Cedula / R.U.C: <span class="destacado">$venta->relCliente->Identificacion</span></td>
					<td colspan="3">Telefonos: <span class="destacado">$venta->relCliente->Telefonos</span></td>
				</tr>

				<tr class="details">
					<td colspan="4" class="descripcion">Direccion: <span class="destacado">$venta->relCliente->Direccion</span></td>
					<td colspan="2">Guia de remision:</td>
				</tr>

				<tr class="details">
					<td colspan="6" class="descripcion">Correo electronico: <span class="destacado">$venta->relCliente->Email</span></td>
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
					<td>$venta->Subtotal</td>
				</tr>
				<tr class="total">
					<td colspan="4">
						<span class="destacado">Forma de pago: </span>$formaPago
					</td>
					<td>Subtotal 0%</td>
					<td>$venta->SubtotalEx</td>
				</tr>
				<tr class="total">
					<td colspan="4">
						<span class="destacado">Numero control: </span>$venta->Numero
					</td>
					<td>Descuento</td>
					<td>$0.00</td>
				</tr>
				<tr class="total">
					<td colspan="4"></td>
					<td>IVA 15%</td>
					<td>$venta->Impuestos</td>
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
EOD;

          $pdf->writeHTML($tbl, true, false, false, false, '');

          $pdfOutput = $pdf->Output('filename.pdf', 'S');
          // Adjuntar el PDF generado
          $mail->addStringAttachment($pdfOutput, 'factura.pdf', 'base64', 'application/pdf');

          // Enviar correo
          if ($mail->send()) {
            return "";
          } else {
            return "Error al enviar el correo: {$mail->ErrorInfo}";
          }
      } catch (Exception $e) {
          return "Error al enviar el correo: {$e->getMessage()}";
      }
    }
  }
}  