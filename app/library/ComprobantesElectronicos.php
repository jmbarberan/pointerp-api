<?php

use Pointerp\Modelos\EmpresaParametros;
use Pointerp\Modelos\Empresas;
use Pointerp\Modelos\Maestros\Impuestos;
use Pointerp\Modelos\Maestros\Registros;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class ComprobantesElectronicos {

  private static $certificado = "";
  private static $password = "";

  public static function cargarCertificado($certPath, $certPass) {
    self::$certificado = $certPath;
    self::$password = $certPass;
  }

  private static function codigoAleatorio() {
    $f = new \DateTime();
    $h = $f->format("H");
    $t = $f->format("i");
    $s = $f->format("s");
    $y = $f->format("Y");
    $m = $f->format("m");
    $d = $f->format("d");
    $calf = intval($y) * intval($m) * intval($d);
    $calh = intval($h) * intval($t) * intval($s);
    $generado = rand(1, 101);
    return strval($calf) . strval($calh) . strval($generado);
  }

  public static function autorizarFactura($comprobante) {
    $xmlFactura = self::crearXmlFactura($comprobante);
    if (isset($xmlFactura)) {
      $xmlFirmado = self::firmarXml($xmlFactura, self::$certificado, self::$password);
      if (isset($xmlFirmado)) {
        $respEnvio = self::enviar($xmlFirmado);
        if (isset($respEnvio)) {
          // verificar y dar resultado
          //
        }
      }
    }
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
      /*$con = self::$db->fetchAll(
        "SELECT Id, Codigo, Denominacion " .
        "FROM registros " . 
        "Where id = " . 
      );
      if (count($con) > 0) {
        $con = reset($con);
        $tipoIdent = $con["Codigo"];
      }*/
      if (isset($tipoReg)) {
        $tipoIdent = $tipoReg->Codigos;
      }
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
      $cbc = $xml->createElement('razonSocial', $contribuyente->RazonSocial);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('nombreComercial', $contribuyente->NombreComercial);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('ruc', $contribuyente->Identificacion);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('claveAcceso', $comprobante->CEClaveAcceso);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('codDoc', $tipoDatos->tipoDocumento);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('estab', $comprobante->relSucursal->Descripcion);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('ptoEmi', $comprobante->relSucursal->Codigo);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('secuencial', $comprobante->CERespuestaTipo);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('dirMatriz', $comprobante->relSucursal->Direccion);
      $cbc = $infoTributaria->appendChild($cbc);
      if (isset($paramFaEmpresa->Denominacion)) {
        $cbc = $xml->createElement('regimenMicroempresas', $paramFaEmpresa->Denominacion);
        $cbc = $infoTributaria->appendChild($cbc);
      }

    // INFORMACION DE FACTURA.    
      $obligadoContabilidad = 1;
      $obligadoContabVal = $contribuyente->ObligadoContabilidad;
      if ($obligadoContabVal != null) {
        $obligadoContabilidad = $obligadoContabVal == 1 ? 0 : 1;
      }
      $infoFactura = $xml->createElement('infoFactura');
      $infoFactura = $factura->appendChild($infoFactura);
      $cbc = $xml->createElement('fechaEmision', $comprobante->Fecha);
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('dirEstablecimiento', $comprobante->relSucursal->Direccion);
      $cbc = $infoFactura->appendChild($cbc);
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
      $cbc = $xml->createElement('plazo', number_format($comprobante->Plazo, 2, ".", ""));
      $cbc = $pago->appendChild($cbc);
      $cbc = $xml->createElement('unidadTiempo', "DIAS");
      $cbc = $pago->appendChild($cbc);
      $cbc = $xml->createElement('total', number_format(doubleval($comprobante->Subtotal) + doubleval($comprobante->SubtotalEx) + doubleval($comprobante->Impuestos), 2, ".", ""));
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
      if ($impuestoRegistrado) {
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

  private static function firmarXml($xml, $certFile, $certPass) {
    // Cargar el archivo .p12
    $pkcs12 = file_get_contents($certFile);
    if (!openssl_pkcs12_read($pkcs12, $certs, $certPass)) {
        throw new Exception("Error al leer el archivo .p12");
    }
    $privateKey = $certs['pkey'];
    $publicCert = $certs['cert'];

    // Cargar el XML a firmar
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    // Crear una nueva firma
    $objDSig = new XMLSecurityDSig();
    $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
    // Firmar todos los elementos del XML
    $objDSig->addReference(
        $doc, 
        XMLSecurityDSig::SHA1, 
        [ 'http://www.w3.org/2000/09/xmldsig#enveloped-signature' ]
    );
    // Crear una nueva clave de seguridad (llave privada)
    $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, [ 'type' => 'private' ]);
    $objKey->loadKey($privateKey);
    // Agregar la firma al documento
    $objDSig->sign($objKey);
    // Agregar la clave pública al documento firmado
    $objDSig->add509Cert($publicCert);
    // Anexar la firma al documento
    $objDSig->appendSignature($doc->documentElement);
    // Devolver el XML firmado
    return $doc->saveXML();
  }
  private static function generarXades($xml, $params) {
    $sha1_factura = base64_encode(str_replace('<?xml version="1.0" encoding="UTF-8"?>\n', '', $xml));
    $xmlns = 'xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:etsi="http://uri.etsi.org/01903/v1.3.2#"';
    $Certificate_number = self::codigoAleatorio();
    $Signature_number = self::codigoAleatorio();
    $SignedProperties_number = self::codigoAleatorio();
    //numeros fuera de los hash:
    $SignedInfo_number = self::codigoAleatorio();
    $SignedPropertiesID_number = self::codigoAleatorio();
    $Reference_ID_number = self::codigoAleatorio();
    $SignatureValue_number = self::codigoAleatorio();
    $Object_number = self::codigoAleatorio();

    $SignedProperties = '';
    $SignedProperties .= '<etsi:SignedProperties Id="Signature' + $Signature_number + '-SignedProperties' + $SignedProperties_number + '">';  //SignedProperties
        $SignedProperties .= '<etsi:SignedSignatureProperties>';
            $SignedProperties .= '<etsi:SigningTime>';
            $SignedProperties .= (new \DateTime())->format('Y-m-t H:i:s');
            $SignedProperties .= '</etsi:SigningTime>';
            $SignedProperties .= '<etsi:SigningCertificate>';
                $SignedProperties .= '<etsi:Cert>';
                    $SignedProperties .= '<etsi:CertDigest>';
                        $SignedProperties .= '<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1">';
                        $SignedProperties .= '</ds:DigestMethod>';
                        $SignedProperties .= '<ds:DigestValue>';
                            $SignedProperties .= $params->certificateX509_der_hash;
                        $SignedProperties .= '</ds:DigestValue>';
                    $SignedProperties .= '</etsi:CertDigest>';
                    $SignedProperties .= '<etsi:IssuerSerial>';
                        $SignedProperties .= '<ds:X509IssuerName>';
                            $SignedProperties .= $params->issuerName;
                        $SignedProperties .= '</ds:X509IssuerName>';
                    $SignedProperties .= '<ds:X509SerialNumber>';
                        $SignedProperties .= $params->X509SerialNumber;
                    $SignedProperties .= '</ds:X509SerialNumber>';
                    $SignedProperties .= '</etsi:IssuerSerial>';
                $SignedProperties .= '</etsi:Cert>';
            $SignedProperties .= '</etsi:SigningCertificate>';
        $SignedProperties .= '</etsi:SignedSignatureProperties>';
        $SignedProperties .= '<etsi:SignedDataObjectProperties>';
            $SignedProperties .= '<etsi:DataObjectFormat ObjectReference="#Reference-ID-' + $Reference_ID_number + '">';
                $SignedProperties .= '<etsi:Description>';
                    $SignedProperties .= 'contenido comprobante'; // XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
                $SignedProperties .= '</etsi:Description>';
                $SignedProperties .= '<etsi:MimeType>';
                    $SignedProperties .= 'text/xml';
                $SignedProperties .= '</etsi:MimeType>';
            $SignedProperties .= '</etsi:DataObjectFormat>';
        $SignedProperties .= '</etsi:SignedDataObjectProperties>';
    $SignedProperties .= '</etsi:SignedProperties>';
    $SignedProperties_para_hash = str_replace('<etsi:SignedProperties', '<etsi:SignedProperties ' + $xmlns, $SignedProperties);
    $sha1_SignedProperties = base64_encode($SignedProperties_para_hash);
    
    $KeyInfo = '';        
    $KeyInfo .= '<ds:KeyInfo Id="Certificate' + $Certificate_number + '">';
        $KeyInfo .= '\n<ds:X509Data>';
            $KeyInfo .= '\n<ds:X509Certificate>\n';
                //CERTIFICADO X509 CODIFICADO EN Base64 
                $KeyInfo .= $params->certificado;
            $KeyInfo .= '\n</ds:X509Certificate>';
        $KeyInfo .= '\n</ds:X509Data>';
        $KeyInfo .= '\n<ds:KeyValue>';
            $KeyInfo .= '\n<ds:RSAKeyValue>';
                $KeyInfo .= '\n<ds:Modulus>\n';
                    //MODULO DEL CERTIFICADO X509
                    $KeyInfo .= $params->modulus;
                $KeyInfo .= '\n</ds:Modulus>';
                $KeyInfo .= '\n<ds:Exponent>';
                    $KeyInfo .= $params->exponent;
                $KeyInfo .= '</ds:Exponent>';
            $KeyInfo .= '\n</ds:RSAKeyValue>';
        $KeyInfo .= '\n</ds:KeyValue>';
    $KeyInfo .= '\n</ds:KeyInfo>';    
    $KeyInfo_para_hash = str_replace('<ds:KeyInfo', '<ds:KeyInfo ' + $xmlns, $KeyInfo);
    $sha1_certificado = base64_encode($KeyInfo .= $KeyInfo_para_hash);

    $SignedInfo = '';
    $SignedInfo .= '<ds:SignedInfo Id="Signature-SignedInfo' . $SignedInfo_number . '">';
        $SignedInfo .= '\n<ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315">';
        $SignedInfo .= '</ds:CanonicalizationMethod>';
        $SignedInfo .= '\n<ds:SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1">';
        $SignedInfo .= '</ds:SignatureMethod>';
        $SignedInfo .= '\n<ds:Reference Id="SignedPropertiesID' + $SignedPropertiesID_number + '" Type="http://uri.etsi.org/01903#SignedProperties" URI="#Signature' + $Signature_number + '-SignedProperties' + $SignedProperties_number + '">';
            $SignedInfo .= '\n<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1">';
            $SignedInfo .= '</ds:DigestMethod>';
            $SignedInfo .= '\n<ds:DigestValue>';
                //HASH O DIGEST DEL ELEMENTO <etsi:SignedProperties>';
                $SignedInfo += $sha1_SignedProperties;
            $SignedInfo .= '</ds:DigestValue>';
        $SignedInfo .= '\n</ds:Reference>';
        $SignedInfo .= '\n<ds:Reference URI="#Certificate' + $Certificate_number + '">';
            $SignedInfo .= '\n<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1">';
            $SignedInfo .= '</ds:DigestMethod>';
            $SignedInfo .= '\n<ds:DigestValue>';
                //HASH O DIGEST DEL CERTIFICADO X509
                $SignedInfo .= $sha1_certificado;
            $SignedInfo .= '</ds:DigestValue>';
        $SignedInfo .= '\n</ds:Reference>';
        $SignedInfo .= '\n<ds:Reference Id="Reference-ID-' + $Reference_ID_number + '" URI="#comprobante">';
            $SignedInfo .= '\n<ds:Transforms>';
                $SignedInfo .= '\n<ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature">';
                $SignedInfo .= '</ds:Transform>';
            $SignedInfo .= '\n</ds:Transforms>';
            $SignedInfo .= '\n<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1">';
            $SignedInfo .= '</ds:DigestMethod>';
            $SignedInfo .= '\n<ds:DigestValue>';
                //HASH O DIGEST DE TODO EL XML IDENTIFICADO POR EL id="comprobante" 
                $SignedInfo += $sha1_factura;
            $SignedInfo .= '</ds:DigestValue>';
        $SignedInfo .= '\n</ds:Reference>';
    $SignedInfo .= '\n</ds:SignedInfo>';
    
    $SignedInfo_para_firma = str_replace('<ds:SignedInfo', '<ds:SignedInfo ' + $xmlns, $SignedInfo);
  
    $xades_bes = '';
    $xades_bes .= '<ds:Signature ' . $xmlns . ' Id="Signature' . $Signature_number . '">';
        $xades_bes .= '\n' + $SignedInfo;
        $xades_bes .= '\n<ds:SignatureValue Id="SignatureValue' + $SignatureValue_number + '">\n';
            //VALOR DE LA FIRMA (ENCRIPTADO CON LA LLAVE PRIVADA DEL CERTIFICADO DIGITAL) 
            $xades_bes .= $params->firma_SignedInfo;
        $xades_bes .= '\n</ds:SignatureValue>';
        $xades_bes .= '\n' + $KeyInfo;
        $xades_bes .= '\n<ds:Object Id="Signature' + $Signature_number + '-Object' + $Object_number + '">';
            $xades_bes .= '<etsi:QualifyingProperties Target="#Signature' + $Signature_number + '">';
                //ELEMENTO <etsi:SignedProperties>';
                $xades_bes .= $SignedProperties;
            $xades_bes .= '</etsi:QualifyingProperties>';
        $xades_bes .= '</ds:Object>';
    $xades_bes .= '</ds:Signature>';
  }
  public static function enviar($xmlComprobante) {
    $wsdl = "https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl";
    
    try {
        // Crear el cliente SOAP
        $client = new SoapClient($wsdl);
        // Crear el parámetro de la solicitud
        $params = array('xml' => $xmlComprobante);
        // Llamar al método 'validarComprobante' del servicio web
        $response = $client->__soapCall('validarComprobante', array($params));
        // Retornar la respuesta del servicio web
        $responseXml = $response->RespuestaRecepcionComprobante;
        // Escribir la respuesta en un archivo XML
        file_put_contents("repuesta.xml", $responseXml);
    } catch (SoapFault $fault) {
        // Manejar errores
        return "Error: {$fault->faultcode}, {$fault->faultstring}";
    }
  }

  public static function verificar($claveAcceso) {
    return false;
  }

  private static function procesarFactura($comprobante, $pdb) {
    $emp = Empresas::findById($comprobante->relCliente->EmpresaId);
    $paramFaEmpresa = EmpresaParametros::findFirst([
      'conditions' => "EmpresaId = {$comprobante->relCliente->EmpresaId} and Tipo = 1"      
    ]);
    //self::$db = $pdb;
    /*$emp = self::$db->fetchAll(
      "SELECT Id, Ruc, RazonSocial, NombreComercial, CEResolucion, ObligadoContabilidad, CertificadoRuta, CertificadoClave, Soporte " .
      "FROM empresas " . 
      "Where Id = " . $comprobante->relCliente->EmpresaId
    );
    if (count($emp) > 0) {
      $emp = reset($emp);
    }*/
    $tipoDatos = (object) [
      'tipoDocumento' => '01', // 01: FACTURA
      'tipoEmision' => '1',  // OFFLINE UNICO VALOR VALIDO
      'ambiente' => '1' // 1: PRUEBAS; 2: PRODUCCION
    ];
    $empresaContribuyente = (object) [
      'empresaId' => $comprobante->relCliente->EmpresaId,
      'ruc' => $emp->Ruc,
      'razonSocial' => $emp->RazonSocial,
      'nombreComercial' => $emp->NombreComercial,
      'contribuyenteEspecial' => $emp->CEResolucion != null ? $emp->CEResolucion : "NO",
      'obligadoContabilidad' => intval($emp["ObligadoContabilidad"]) == 1 ? 0 : 1
    ];

    $rutapfx = $emp["CertificadoRuta"];
    $archivo = basename($rutapfx);
    $archivoCert = $_SERVER["DOCUMENT_ROOT"] . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR. $archivo;
    $pass = $emp["CertificadoClave"];
    if (isset($paramFaEmpresa) && isset($paramFaEmpresa->Extendido) && $paramFaEmpresa->Extendido != '0') {
      $pass = base64_decode($paramFaEmpresa->Extendido);
    }
    
    $xml = self::crearXmlFactura($comprobante, $tipoDatos, $empresaContribuyente);
    $frm = self::firmarXml($xml, $archivoCert, $pass);
    file_put_contents('fa_' . strval($comprobante->Numero) . '.xml', "\xEF\xBB\xBF".  $frm);
    
    return $frm;
  }

  private static function ExtraerCertificado($rutapfx, $pass, $code) {
    $res = [];
    $pfx = file_get_contents($rutapfx);
    $openSSL = openssl_pkcs12_read($pfx, $res, $pass);
    if(!$openSSL) {
        throw new Exception("Error: " . openssl_error_string());
    }
    // this is the CER FILE
    file_put_contents(APP_PATH . '\files\sign\CERT' . $code . '.cer', $res['pkey'].$res['cert'].implode('', $res['extracerts']));

    // this is the PEM FILE
    $cert = $res['cert'].implode('', $res['extracerts']);
    file_put_contents(APP_PATH . '\files\sign\KEY' . $code . '.pem', $cert);
  }  
}

