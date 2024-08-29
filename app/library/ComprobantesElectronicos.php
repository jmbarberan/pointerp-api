<?php

use Pointerp\Modelos\EmpresaParametros;
use Pointerp\Modelos\Empresas;
use Pointerp\Modelos\Maestros\Impuestos;
use Pointerp\Modelos\Maestros\Registros;

require(APP_PATH . '/library/CryptoToolKit/CryptoToolKitInterface.php');
require(APP_PATH . '/library/CryptoToolKit/OpenSSL.php');
require(APP_PATH . '/library/Signature/SignatureInterface.php');
require(APP_PATH . '/library/Signature/DOMToolKit.php');
require(APP_PATH . '/library/Signature/XMLDsig.php');
require(APP_PATH . '/library/Signature/XAdESBES.php');
require(APP_PATH . '/library/XMLSecLibs.php');

use PacoP\XMLSecLibs\XMLSecLibs;
use PacoP\XMLSecLibs\CryptoToolKit\OpenSSL;

class ComprobantesElectronicos {

  private static $certificado = "";
  private static $password = "";

  public static function cargarCertificado($certPath, $certPass) {
    self::$certificado = $certPath;
    self::$password = $certPass;
  }

  public static function autorizarFactura($comprobante) {
    $ret = (object) [
      'respuesta' => false,
      'titulo' => '',
      'mensaje' => 'Los datos no se pudieron procesar',
      'comprobante' => null
    ];
    $ambiente = '1';
    $contribuyente = Empresas::findFirstById($comprobante->relCliente->EmpresaId);
    #region Ambiente
    if (isset($contribuyente)) {
      $reg = Registros::findFirstById($contribuyente->TipoAmbiente);
      if (isset($reg))
        $ambiente = $reg->Codigo;
    }
    $xmlFactura = self::crearXmlFactura($comprobante);    
    if (isset($xmlFactura)) {
      $type = 'http://uri.etsi.org/01903/v1.3.2#';
      $crypto = new OpenSSL(BASE_PATH . '/certs/' . self::$certificado, self::$password, "PKCS12");
      $xmlsec = new XMLSecLibs();
      $options = ['timezone' => 'America/Guayaquil'];
      $xmlsec->setDigestMethod('http://www.w3.org/2001/04/xmlenc#sha256');
      $xmlsec->setSignatureMethod('http://www.w3.org/2000/09/xmldsig#rsa-sha1');
      $xmlFirmado = $xmlsec->sign($xmlFactura, $type, $crypto, $options);
      if (isset($xmlFirmado)) {
        $respEnvio = self::enviar($xmlFirmado, $ambiente);
        if (isset($respEnvio)) {
          // recibido o devuelta
          $res = self::verificar($comprobante->CEClaveAcceso, $ambiente);
        }
        // guardar el resultado de la autorizacion sea aprobado o rechazado
      }
    }
    return $res;
  }

  private static function crearXmlFactura($comprobante) {
    $contribuyente = Empresas::findFirstById($comprobante->relCliente->EmpresaId);
    $tipoDatos = (object) [
      'tipoDocumento' => '01', // 01: FACTURA
      'tipoEmision' => '1',  // OFFLINE UNICO VALOR VALIDO
      'ambiente' => '1' // 1: PRUEBAS; 2: PRODUCCION
    ];
    #region Ambiente
    if (isset($contribuyente)) {
      $reg = Registros::findFirstById($contribuyente->TipoAmbiente);
      if (isset($reg))
        $tipoDatos->ambiente = $reg->Codigo;
    }
    // traer datos del impuesto vigente
    $paramFaEmpresa = EmpresaParametros::findFirst([
      'conditions' => "EmpresaId = {$comprobante->relCliente->EmpresaId} AND Tipo = 1"      
    ]);    
    // traer todos los impuestos a un arreglo indexado
    $impuestosAr = [];
    $impsQry = Impuestos::find([
      'conditions' => 'Estado = 0'
    ]);
    $impCero = Impuestos::findFirst([
      'conditions' => 'Porcentaje = 0'
    ]);
    foreach ($impsQry as $imp) {
      $impuestosAr[$imp->Id] = $imp;
    }
    $impuestoVigente = $impuestosAr[$paramFaEmpresa->RegistroId];
    $formaPago = "01";

    #region Tipo identificacion
    $tipoIdent = "07";    
    if ($comprobante->relCliente->IdentificacionTipo > 0)
    {
      $tipoReg = Registros::findFirst([
        "conditions" => "Id = {$comprobante->relCliente->IdentificacionTipo}"
      ]);
      if (isset($tipoReg)) {
        $tipoIdent = $tipoReg->Codigo;
      }
    }
    #endregion

    $xml = new DomDocument('1.0', 'UTF-8');
    // $xml->standalone         = false;
    $xml->preserveWhiteSpace = false;
    $factura = $xml->createElement('factura');
    $factura = $xml->appendChild($factura);
    $factura->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $factura->setAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
    $factura->setAttribute("id", "comprobante");    
    $factura->setAttribute("version", "1.0.0");

    // INFORMACION TRIBUTARIA.
      $infoTributaria = $xml->createElement('infoTributaria');
      $infoTributaria = $factura->appendChild($infoTributaria);
      $cbc = $xml->createElement('ambiente', $tipoDatos->ambiente);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('tipoEmision', $tipoDatos->tipoEmision); // 
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('razonSocial', $contribuyente->RazonSocial);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('nombreComercial', $contribuyente->NombreComercial);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('ruc', $contribuyente->Ruc);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('claveAcceso', $comprobante->CEClaveAcceso);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('codDoc', $tipoDatos->tipoDocumento);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('estab', $comprobante->relSucursal->Descripcion);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('ptoEmi', $comprobante->relSucursal->Codigo);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('secuencial', str_pad(trim($comprobante->CERespuestaTipo), 9, "0", STR_PAD_LEFT));
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('dirMatriz', $comprobante->relSucursal->Direccion);
      $cbc = $infoTributaria->appendChild($cbc);
      if (isset($paramFaEmpresa->Denominacion)) {
        $cbc = $xml->createElement('contribuyenteRimpe', $paramFaEmpresa->Denominacion);
        $cbc = $infoTributaria->appendChild($cbc);
      }

    // INFORMACION DE FACTURA.    
      $obligadoContabilidad = 1;
      $obligadoContabVal = $contribuyente->ObligadoContabilidad;
      if ($obligadoContabVal != null) {
        $obligadoContabilidad = $obligadoContabVal == 1 ? 'SI' : 'NO';
      }
      $compDate = new DateTime($comprobante->Fecha);
      $formatDate = $compDate->format('d/m/Y');
      $infoFactura = $xml->createElement('infoFactura');
      $infoFactura = $factura->appendChild($infoFactura);
      $cbc = $xml->createElement('fechaEmision', $formatDate);
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('dirEstablecimiento', $comprobante->relSucursal->Direccion);
      $cbc = $infoFactura->appendChild($cbc);
      if ($contribuyente->CEResolucio != null)
        $cbc = $xml->createElement('contribuyenteEspecial', $contribuyente->CEResolucion);
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('obligadoContabilidad', $obligadoContabilidad);
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('tipoIdentificacionComprador', $tipoIdent);
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('razonSocialComprador', $comprobante->relCliente->Nombres);
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('identificacionComprador', $comprobante->relCliente->Identificacion);
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('totalSinImpuestos', 
        number_format(doubleval($comprobante->Subtotal) + doubleval($comprobante->SubtotalEx) + doubleval($comprobante->Flete), 2, ".", ""));
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('totalDescuento', $comprobante->Descuento);
      $cbc = $infoFactura->appendChild($cbc);

      $totalConImpuestos = $xml->createElement('totalConImpuestos');
      $totalConImpuestos = $infoFactura->appendChild($totalConImpuestos);
      

      if ($comprobante->Subtotal > 0) {
        $totalImpuesto12 = $xml->createElement('totalImpuesto');
        $totalImpuesto12 = $totalConImpuestos->appendChild($totalImpuesto12);
        $cbc = $xml->createElement('codigo', $impuestoVigente->CodigoEmision);
        $cbc = $totalImpuesto12->appendChild($cbc);
        $cbc = $xml->createElement('codigoPorcentaje', $impuestoVigente->CodigoPorcentaje);
        $cbc = $totalImpuesto12->appendChild($cbc);
        $cbc = $xml->createElement('baseImponible', number_format($comprobante->Subtotal, 2, ".", ""));
        $cbc = $totalImpuesto12->appendChild($cbc);
        $cbc = $xml->createElement('tarifa', $impuestoVigente->Porcentaje);
        $cbc = $totalImpuesto12->appendChild($cbc);
        $cbc = $xml->createElement('valor', number_format($comprobante->Impuestos, 2, ".", ""));
        $cbc = $totalImpuesto12->appendChild($cbc);
      }

      if ($comprobante->SubtotalEx > 0) {
        $totalImpuesto0 = $xml->createElement('totalImpuesto');
        $totalImpuesto0 = $totalConImpuestos->appendChild($totalImpuesto0);
        $cbc = $xml->createElement('codigo', $impuestoVigente->CodigoEmision);
        $cbc = $totalImpuesto0->appendChild($cbc);
        $cbc = $xml->createElement('codigoPorcentaje', '0');
        $cbc = $totalImpuesto0->appendChild($cbc);
        $cbc = $xml->createElement('baseImponible', number_format($comprobante->SubtotalEx, 2, ".", ""));
        $cbc = $totalImpuesto0->appendChild($cbc);
        $cbc = $xml->createElement('tarifa', '0');
        $cbc = $totalImpuesto0->appendChild($cbc);
        $cbc = $xml->createElement('valor', '0');
        $cbc = $totalImpuesto0->appendChild($cbc);
      }

      $cbc = $xml->createElement('propina', '0');
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('importeTotal', number_format(doubleval($comprobante->Subtotal) + doubleval($comprobante->SubtotalEx) + doubleval($comprobante->Impuestos), 2, ".", ""));
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('moneda', 'DOLAR');
      $cbc = $infoFactura->appendChild($cbc);

      $pagos = $xml->createElement('pagos');
      $pagos = $infoFactura->appendChild($pagos);
      $pago = $xml->createElement('pago');
      $pago = $pagos->appendChild($pago);
      $cbc = $xml->createElement('formaPago', $formaPago);
      $cbc = $pago->appendChild($cbc);
      $cbc = $xml->createElement('total', number_format(doubleval($comprobante->Subtotal) + doubleval($comprobante->SubtotalEx) + doubleval($comprobante->Impuestos), 2, ".", ""));
      $cbc = $pago->appendChild($cbc);
      $cbc = $xml->createElement('unidadTiempo', "DIAS");
      $cbc = $pago->appendChild($cbc);      

    
      //DETALLES DE LA FACTURA.
      $detalles = $xml->createElement('detalles');
      $detalles = $factura->appendChild($detalles);

    foreach ($comprobante->relItems as $vi) {
      $subtotal = doubleval($vi->Cantidad) * doubleval($vi->Precio);
      $detalle = $xml->createElement('detalle');
      $detalle = $detalles->appendChild($detalle);
      $item = $xml->createElement('codigoPrincipal', $vi->relProducto->Codigo);
      $item = $detalle->appendChild($item);
      $item = $xml->createElement('descripcion', $vi->relProducto->Nombre);
      $item = $detalle->appendChild($item);
      $item = $xml->createElement('cantidad', number_format($vi->Cantidad, 0, ".", ""));
      $item = $detalle->appendChild($item);
      $item = $xml->createElement('precioUnitario', number_format($vi->Precio, 2, ".", ""));
      $item = $detalle->appendChild($item);
      $item = $xml->createElement('descuento', 0);
      $item = $detalle->appendChild($item);
      $item = $xml->createElement('precioTotalSinImpuesto', number_format($subtotal, 2, ".", ""));
      $item = $detalle->appendChild($item);

      $impuestoRegistrado = false;
      $crearConImpuesto = count($vi->relProducto->relImposiciones) > 0 && $impuestoVigente->Porcentaje > 0;
      $impuestos = $xml->createElement('impuestos');
      $impuestos = $detalle->appendChild($impuestos);
      if ($crearConImpuesto) {
        foreach($vi->relProducto->relImposiciones as $im) {
          $impuestoItem = $impuestosAr[$im->ImpuestoId];
          $impuesto = $xml->createElement('impuesto');
          $impuesto = $impuestos->appendChild($impuesto);
          $cbv = $xml->createElement('codigo', $impuestoItem->CodigoEmision);
          $cbv = $impuesto->appendChild($cbv);
          $cbv = $xml->createElement('codigoPorcentaje', $impuestoItem->CodigoPorcentaje);
          $cbv = $impuesto->appendChild($cbv);
          $cbv = $xml->createElement('tarifa', $impuestoItem->Porcentaje);
          $cbv = $impuesto->appendChild($cbv);
          $cbv = $xml->createElement('baseImponible', number_format($subtotal, 2, ".", ""));
          $cbv = $impuesto->appendChild($cbv);
          $cbv = $xml->createElement('valor', number_format(($subtotal * $impuestoItem->Porcentaje) / 100, 2, ".", ""));
          $cbv = $impuesto->appendChild($cbv);
          $impuestoRegistrado = true;
        }
      } 
      if (!$impuestoRegistrado) {
        $impuesto = $xml->createElement('impuesto');
        $impuesto = $impuestos->appendChild($impuesto);
        $cbc = $xml->createElement('codigo', $impCero->CodigoEmision);
        $cbc = $impuesto->appendChild($cbc);
        $cbc = $xml->createElement('codigoPorcentaje', $impCero->CodigoPorcentaje);
        $cbc = $impuesto->appendChild($cbc);
        $cbc = $xml->createElement('tarifa', $impCero->Porcentaje);
        $cbc = $impuesto->appendChild($cbc);
        $cbc = $xml->createElement('valor', '0');
        $cbc = $impuesto->appendChild($cbc);
      }
    }

    $xml->formatOutput = true;
    $strings_xml = $xml->saveXML();
    return $strings_xml;
  }

  public static function enviar($xmlComprobante, $ambiente) {
    $variable = $ambiente == '1' ? 'SUBDOMINIO_SRIPRB' : 'SUBDOMINIO_SRIPRO';
    $wsdlURL = getenv($variable) . getenv('URL_SRIFAC_ENV');
    try {
      $client = new SoapClient($wsdlURL);
      $params = [ 'xml' => $xmlComprobante ];
      $response = $client->__soapCall('validarComprobante', [ $params ]);
      //$responseXml = $response->RespuestaRecepcionComprobante;
      // file_put_contents("repuesta.xml", $responseXml);
      return [
        "completo" => true,
        "mensaje" => "Operacion completa",
        "respuesta" => $response->RespuestaRecepcionComprobante
      ];
    } catch (SoapFault $fault) {
      return (Object) [
        "completo" => false,
        "mensaje" => $fault->faultstring,
        "respuesta" => $fault 
      ];
    }
  }

  public static function verificar($claveAcceso, $ambiente) {
    $variable = $ambiente == '1' ? 'SUBDOMINIO_SRIPRB' : 'SUBDOMINIO_SRIPRO';
    $wsdlURL = getenv($variable) . getenv('URL_SRIFAC_VAL');
    try {
      $client = new SoapClient($wsdlURL);
      $params = [ 'claveAccesoComprobante' => $claveAcceso ];
      sleep(3);
      $response = $client->__soapCall("autorizacionComprobante", [ $params ]);
      return [
        "completo" => true,
        "mensaje" => "Operacion completa",
        "respuesta" => $response
      ];
    } catch (SoapFault $e) {
      return (Object) [
        "completo" => false,
        "mensaje" => $e->getMessage(),
        "respuesta" => $e
      ];
    }
  }
}

