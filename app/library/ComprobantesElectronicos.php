<?php

use Selective\XmlDSig\DigestAlgorithmType;
use Selective\XmlDSig\XmlSigner;

class ComprobantesElectronicos {

  static function calcularDigitoVerificadorCadena($cadena) {
    $cadenaInversa = strrev($cadena);    
    $factor = 2;
    $res = 0;
    $conteo = 1;
    for ($i = 0; i < strlen($cadenaInversa); $i++)
    {
      $factor = $factor == 8 ? 2 : $factor;
      $producto = intval(substr($cadenaInversa, i, 1));
      $producto *= $factor;
      $conteo++;
      $factor++;
      $res += $producto;
    }

    $res = 11 - $res % 11;
    //if ($res == 11) $res = 0;
    $res = $res == 11 ? 0 : $res;
    //if ($res == 10) $res = 1;
    $res = $res == 10 ? 1 : $res;

    return $res;
  }

  static function codigoAleatorio() {
    $f = new \DateTime();
    $h = $f->format("H");
    $t = $f->format("i");
    $s = $f->format("s");
    $y = $f->format("Y");
    $m = $f->format("m");
    $d = $f->format("d");
    $calf = intval($y) * intval($m) * intval($d);
    $calh = intval($h) * intval($t) * intval($s);
    $generado = rand(1, 100 + 1);
    return strval($calf) . strval(calh) . strval(generado);
  }

  static function crearXml($comprobante, $tipoDatos, $contribuyente) {
    $codigoDocumento = ""; // Aleatorio
    $formaPago = "01";
    $empresaId = $contribuyente->EmpresaId;

    #region Tipo identificacion
    $tipoIdent = "07";    
    if ($comprobante->ClienteNav->IdentificacionTipo > 0)
    {
      $con = $db->fetchAll(
        "SELECT id, codigo, denominacion " .
        "FROM registros " . 
        "Where id = " . strval($comprobante->ClienteNav->IdentificacionTipo)
      );
      if (count($con) > 0) {
        $con = reset($con);
        $tipoIdent = $con->Codigo;
      }
    }
    #endregion    
    
    #region Secuencial
    $serie = $comprobante->relSucursal->Codigo . trim($comprobante->relSucursal->Descripcion);
    $secuencialExiste = false;
    try
    {
      $secuencialExiste = intval($comprobante->CERespuestaTipo) > 0;
    }
    catch (Exception $e) { }

    $secuencial = 1;
    if ($secuencialExiste) {
      if (!str_contains($comprobante->CEContenido, "SECUENCIAL REGISTRADO")) {
        $secuencial = 0;
      } else {
          $secuencial = intval($comprobante->CERespuestaTipo);
          $secuencial = $secuencial <= 0 ? 1 : $secuencial;
      }
    } else {
      $secuencial = 0;
    }
    if ($secuencial <= 0) {
      $ret = 1;
      $conSec = $db->fetchAll(
        "SELECT id, codigo, denominacion " .
        "FROM empresaparametros " . 
        "Where Tipo = 1 AND Referencia = 11 AND Estado = 0 AND EmpresaId = " . strval($tipoDatos->empresa->id)
      );
      if (count($conSec) > 0) {
        $conSec = reset($conSec);
        $ret = $conSec->Indice;
      }
      $secuencial = $ret;
    }
    #endregion

    #region Clave de acceso
    $tipoDocumento = $tipoDatos->tipoDocumento; // 01 = Factura, 04 = NOTA CREDITO, 05 = NOTA DEBITO, 06 = GUIA REMISION, 07 = RETENCION
    $tipoEmision = $tipoDatos->tipoEmision; // Unico valor disponible en metodo OFFLINE
    $clave = "";
    if (!strlen($comprobante->CEClaveAcceso) > 0) {
      if (!str_contains($comprobante->CEContenido, "ERROR EN LA ESTRUCTURA DE LA CLAVE DE ACCESO"))
        $clave = $comprobante->CEClaveAcceso;
    }
    if (strlen($clave) <= 0) {
      $fecha = new \DateTime($comprobante->Fecha);
      $clave = $fecha->format("dmY") .
        $tipoDatos->tipoDocumento .
        $tipoDatos->empresa->Ruc .
        $tipoDatos->ambiente .
        $serie .
        str_pad(strval($secuencial), 9, "0", STR_PAD_LEFT) .
        str_pad(codigoAleatorio(), 8, "1", STR_PAD_LEFT) .
        $tipoDatos->tipoEmision;
      $clave .= strval(calcularDigitoVerificadorCadena($clave));
    }
    #endregion

    $xml = new DomDocument('1.0', 'UTF-8');
    // $xml->standalone         = false;
    $xml->preserveWhiteSpace = false;
    $factura = $xml->createElement('factura');
    $factura = $xml->appendChild($factura);
    $factura->setAttribute("id", "comprobante");
    $factura->setAttribute("version", "1.0.0");

    // INFORMACION TRIBUTARIA.
      $infoTributaria = $xml->createElement('infoTributaria');
      $infoTributaria = $factura->appendChild($infoTributaria);
      $cbc = $xml->createElement('ambiente', $tipoDatos->ambiente);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('tipoEmision', $tipoDatos->tipoEmision); // 
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('razonSocial', $contribuyente->razonSocial);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('nombreComercial', $contribuyente->nombreComercial);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('ruc', $contribuyente->ruc);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('claveAcceso', $clave);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('codDoc', $codDocumento);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('estab', $contribuyente->establecimiento);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('ptoEmi', $contribuyente->puntoEmision);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('secuencial', $secuncial);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('dirMatriz', $contribuyente->direccion);
      $cbc = $infoTributaria->appendChild($cbc);
      /*$cbc = $xml->createElement('regimenMicroempresas', $contribuyente->regimen);
      $cbc = $infoTributaria->appendChild($cbc);*/

    // INFORMACIOO DE FACTURA.
      $infoFactura = $xml->createElement('infoFactura');
      $infoFactura = $factura->appendChild($infoFactura);
      $cbc = $xml->createElement('fechaEmision', $comprobante->fecha);
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('dirEstablecimiento', $contribuyente->direccion);
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('contribuyenteEspecial', $contribuyente->contribuyenteEspecial);
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('obligadoContabilidad', $contribuyente->obligadoContabilidad);
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('tipoIdentificacionComprador', $comprobante->relCliente->relIdentificaTipo->Codigo);
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
        $cbc = $xml->createElement('codigo', '2');
        $cbc = $totalImpuesto12->appendChild($cbc);
        $cbc = $xml->createElement('codigoPorcentaje', '2');
        $cbc = $totalImpuesto12->appendChild($cbc);
        $cbc = $xml->createElement('baseImponible', number_format($comprobante->Subtotal, 2, ".", ""));
        $cbc = $totalImpuesto12->appendChild($cbc);
        $cbc = $xml->createElement('tarifa', '12');
        $cbc = $totalImpuesto12->appendChild($cbc);
        $cbc = $xml->createElement('valor', number_format($comprobante->Impuestos, 2, ".", ""));
        $cbc = $totalImpuesto12->appendChild($cbc);
      }

      if ($comprobante->SubtotalEx > 0) {
        $totalImpuesto0 = $xml->createElement('totalImpuesto');
        $totalImpuesto0 = $totalConImpuestos->appendChild($totalImpuesto0);
        $cbc = $xml->createElement('codigo', '2');
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
      $cbc = $xml->createElement('importeTotal', number_format($comprobante->Total, 2, ".", ""));
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('moneda', 'DOLAR');
      $cbc = $infoFactura->appendChild($cbc);

      $pagos = $xml->createElement('pagos');
      $pagos = $infoFactura->appendChild($pagos);
      $pago = $xml->createElement('pago');
      $pago = $pagos->appendChild($pago);
      $cbc = $xml->createElement('formaPago', $formaPago);
      $cbc = $pago->appendChild($cbc);
      $cbc = $xml->createElement('plazo', number_format($comprobante->Plazo, 2, ".", ""));
      $cbc = $pago->appendChild($cbc);
      $cbc = $xml->createElement('unidadTiempo', "DIAS");
      $cbc = $pago->appendChild($cbc);
      $cbc = $xml->createElement('total', number_format($comprobante->Total, 2, ".", ""));
      $cbc = $pago->appendChild($cbc);      

    
      //DETALLES DE LA FACTURA.
      $detalles = $xml->createElement('detalles');
      $detalles = $factura->appendChild($detalles);

    foreach ($comprobante->itemsNav as $vi) {
      $numerolinea = 0;

      $detalle = $xml->createElement('detalle');
      $detalle = $detalles->appendChild($detalle);
      $cbc = $xml->createElement('codigoPrincipal', number_format($vi->ProductoNav->Codigo, 2, ".", ""));
      $cbc = $detalle->appendChild($cbc);
      $cbc = $xml->createElement('descripcion', number_format($vi->ProductoNav->Nombre, 2, ".", ""));
      $cbc = $detalle->appendChild($cbc);
      $cbc = $xml->createElement('cantidad', number_format($vi->Cantidad, 0, ".", ""));
      $cbc = $detalle->appendChild($cbc);
      $cbc = $xml->createElement('precioUnitario', number_format($vi->Precio, 2, ".", ""));
      $cbc = $detalle->appendChild($cbc);
      $cbc = $xml->createElement('precioTotalSinImpuesto', number_format($vi->Subtotal, 2, ".", ""));
      $cbc = $detalle->appendChild($cbc);

      $impuestos = $xml->createElement('impuestos');
      $impuestos = $detalle->appendChild($impuestos);

      if (count($vi->ProductoNav->ImposicionesNav) == 0) {
        // sin impuestos
        $impuesto = $xml->createElement('impuesto');
        $impuesto = $impuestos->appendChild($impuesto);
        $cbc = $xml->createElement('codigo', '2');
        $cbc = $impuesto->appendChild($cbc);
        $cbc = $xml->createElement('codigoPorcentaje', '0');
        $cbc = $impuesto->appendChild($cbc);
        $cbc = $xml->createElement('tarifa', '0');
        $cbc = $impuesto->appendChild($cbc);
        $cbc = $xml->createElement('valor', '0');
        $cbc = $impuesto->appendChild($cbc);
      } else {
        foreach($vi->ProductoNav->ImposicionesNav as $im) {
          $impuesto = $xml->createElement('impuesto');
          $impuesto = $impuestos->appendChild($impuesto);
          $cbc = $xml->createElement('codigo', '2');
          $cbc = $impuesto->appendChild($cbc);
          $cbc = $xml->createElement('codigoPorcentaje', '2');
          $cbc = $impuesto->appendChild($cbc);
          $cbc = $xml->createElement('tarifa', '12'); // TODO parametrizar del impuesto seleccionado
          $cbc = $impuesto->appendChild($cbc);
          $cbc = $xml->createElement('baseImponible', number_format($vi->Subtotal, 2, ".", ""));
          $cbc = $impuesto->appendChild($cbc);
          $cbc = $xml->createElement('valor', number_format((doubleval($vi->Subtotal) * 12) / 100), 2, ".", "");
          $cbc = $impuesto->appendChild($cbc);
        }
      }
    }

    $xml->formatOutput = true;
    $strings_xml       = $xml->saveXML();
    return false;
  }

  static function firmarXml($xml, $pfx, $pass) {
    //$rutapfx = __DIR__ . '/public/index.php'
    $rutapfx = $_SERVER["DOCUMENT_ROOT"] .DIRECTORY_SEPARATOR.
      'certs'.DIRECTORY_SEPARATOR. $pfx;
    $xmlSigner = new XmlSigner();
    $xmlSigner->loadPfxFile($rutapfx, $pass);
    return $xmlSigner->signXml($xml, 'sha1');
  }

  public static function enviar($p) {
    $p->firmardo;
    $url = $p->rutaPais;
    try {
      $client = new SoapClient($url, [ "trace" => 1 ] );
      $result = $client->ResolveIP( [ "ipAddress" => $argv[1], "licenseKey" => "0" ] );
      print_r($result);
    } catch ( SoapFault $e ) {
      echo $e->getMessage();      
    }
  }

  public static function verificar($claveAcceso) {
    return false;
  }

  public static function procesarComprobante($comprobante, $rutapfx, $pass) {
    $xml = crearXml($comprobante);
    // Traer nombre del archivo pfx y contraseña de la db
    $frm = firmarXml($xml, $rutapfx, $pass);
    $ret = enviarEc($frm);
    /**/
    return procesar($ret);
  }

}

?>