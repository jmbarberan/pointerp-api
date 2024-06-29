<?php

/*include APP_PATH . '/library/FirmaElectronica.php';
use sasco\LibreDTE\FirmaElectronica;*/

/*include APP_PATH . '/library/Exception/XmlSignatureValidatorException.php';
include APP_PATH . '/library/xmlsign/Utils/XPath.php';
include APP_PATH . '/library/xmlsign/XmlSecEnc.php';
include APP_PATH . '/library/xmlsign/XmlSecurityDSig.php';
include APP_PATH . '/library/xmlsign/XmlSecurityKey.php';

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;*/

/*use Selective\XmlDSig\DigestAlgorithmType;
use Selective\XmlDSig\XmlSigner;*/

//require $_SERVER['DOCUMENT_ROOT'] . '/vendor/robrichards/xmlseclibs/src/XMLSecurityDSig.php';

use Pointerp\Modelos\EmpresaParametros;
use Pointerp\Modelos\Empresas;
use Pointerp\Modelos\Maestros\Impuestos;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class ComprobantesElectronicos {

  static $db = null;

  static function calcularDigitoVerificadorCadena($cadena) {
    $cadenaInversa = strrev($cadena);    
    $factor = 2;
    $res = 0;
    if (strlen($cadenaInversa) > 0) {
      for ($i = 0; $i < strlen($cadenaInversa); $i++)
      {
        $factor = $factor == 8 ? 2 : $factor;
        $producto = intval(substr($cadenaInversa, $i, 1));
        $producto *= $factor;
        $factor++;
        $res .=$producto;
      }

      $res = 11 - $res % 11;
      $res = $res == 11 ? 0 : $res;
      $res = $res == 10 ? 1 : $res;
    }
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
    $generado = rand(1, 101);
    return strval($calf) . strval($calh) . strval($generado);
  }

  static function crearXmlFactura($comprobante, $tipoDatos, $contribuyente) {
    // traer datos del impuesto vigente
    $paramFaEmpresa = EmpresaParametros::findFirst([
      'conditions' => "EmpresaId = {$comprobante->relCliente->EmpresaId} and Tipo = 1"      
    ]);    
    // traer todos los impuestos a un arreglo indexado
    $impuestosAr = array();
    $impsQry = Impuestos::find([
      'conditions' => 'Estado = 0'
    ]);
    foreach ($impsQry as $imp) {
      $impuestosAr[$imp->id] = $imp;
    }
    $impuestoVigente = $impuestosAr[$paramFaEmpresa->RegistroId];
    $formaPago = "01";

    #region Tipo identificacion
    $tipoIdent = "07";    
    if ($comprobante->relCliente->IdentificacionTipo > 0)
    {
      $con = self::$db->fetchAll(
        "SELECT Id, Codigo, Denominacion " .
        "FROM registros " . 
        "Where id = " . strval($comprobante->relCliente->IdentificacionTipo)
      );
      if (count($con) > 0) {
        $con = reset($con);
        $tipoIdent = $con["Codigo"];
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
      $conSec = self::$db->fetchAll(
        "SELECT Indice " .
        "FROM empresaparametros " . 
        "Where Tipo = 1 AND Referencia = 11 AND Estado = 0 AND EmpresaId = " . strval($contribuyente->empresaId)
      );
      if (count($conSec) > 0) {
        $conSec = reset($conSec);
        $ret = $conSec["Indice"];
      }
      $secuencial = $ret;
    }
    #endregion

    #region Clave de acceso
    //$tipoDocumento = $tipoDatos->tipoDocumento; // 01 = Factura, 04 = NOTA CREDITO, 05 = NOTA DEBITO, 06 = GUIA REMISION, 07 = RETENCION
    //$tipoEmision = $tipoDatos->tipoEmision; // Unico valor disponible en metodo OFFLINE
    $clave = "";
    if (!strlen($comprobante->CEClaveAcceso) > 0) {
      if (strpos($comprobante->CEContenido, "ERROR EN LA ESTRUCTURA DE LA CLAVE DE ACCESO") !== false)
        $clave = $comprobante->CEClaveAcceso;
    }
    if (strlen($clave) <= 0) {
      $fecha = new \DateTime($comprobante->Fecha);
      $clave = $fecha->format("dmY") .
        $tipoDatos->tipoDocumento .
        $contribuyente->ruc .
        $tipoDatos->ambiente .
        $serie .
        str_pad(strval($secuencial), 9, "0", STR_PAD_LEFT) .
        str_pad(self::codigoAleatorio(), 8, "1", STR_PAD_LEFT) .
        $tipoDatos->tipoEmision;
      $clave .= strval(self::calcularDigitoVerificadorCadena($clave));
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
      $cbc = $xml->createElement('codDoc', $tipoDatos->tipoDocumento);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('estab', $comprobante->relSucursal->Descripcion);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('ptoEmi', $comprobante->relSucursal->Codigo);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('secuencial', $secuencial);
      $cbc = $infoTributaria->appendChild($cbc);
      $cbc = $xml->createElement('dirMatriz', $comprobante->relSucursal->Direccion);
      $cbc = $infoTributaria->appendChild($cbc);
      if (isset($paramFaEmpresa->Denominacion)) {
        $cbc = $xml->createElement('regimenMicroempresas', $paramFaEmpresa->Denominacion);
        $cbc = $infoTributaria->appendChild($cbc);
      }

    // INFORMACION DE FACTURA.
      $infoFactura = $xml->createElement('infoFactura');
      $infoFactura = $factura->appendChild($infoFactura);
      $cbc = $xml->createElement('fechaEmision', $comprobante->Fecha);
      $cbc = $infoFactura->appendChild($cbc);
      $cbc = $xml->createElement('dirEstablecimiento', $comprobante->relSucursal->Direccion);
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
      //$numerolinea = 0;

      $subtotal = doubleval($vi->Cantidad) * doubleval($vi->Precio);
      $detalle = $xml->createElement('detalle');
      $detalle = $detalles->appendChild($detalle);
      $cbc = $xml->createElement('codigoPrincipal', $vi->relProducto->Codigo);
      $cbc = $detalle->appendChild($cbc);
      $cbc = $xml->createElement('descripcion', $vi->relProducto->Nombre);
      $cbc = $detalle->appendChild($cbc);
      $cbc = $xml->createElement('cantidad', number_format($vi->Cantidad, 0, ".", ""));
      $cbc = $detalle->appendChild($cbc);
      $cbc = $xml->createElement('precioUnitario', number_format($vi->Precio, 2, ".", ""));
      $cbc = $detalle->appendChild($cbc);
      $cbc = $xml->createElement('precioTotalSinImpuesto', number_format($subtotal, 2, ".", ""));
      $cbc = $detalle->appendChild($cbc);

      $impuestos = $xml->createElement('impuestos');
      $impuestos = $detalle->appendChild($impuestos);

      if (count($vi->relProducto->relImposiciones) == 0) {
        // sin impuestos
        $impuesto = $xml->createElement('impuesto');
        $impuesto = $impuestos->appendChild($impuesto);
        $cbc = $xml->createElement('codigo', $impuestoVigente->CodigoEmision);
        $cbc = $impuesto->appendChild($cbc);
        $cbc = $xml->createElement('codigoPorcentaje', '0');
        $cbc = $impuesto->appendChild($cbc);
        $cbc = $xml->createElement('tarifa', '0');
        $cbc = $impuesto->appendChild($cbc);
        $cbc = $xml->createElement('valor', '0');
        $cbc = $impuesto->appendChild($cbc);
      } else {
        foreach($vi->relProducto->relImposiciones as $im) {
          $impuestoItem = $impuestosAr[$im->ImpuestoId];
          $impuesto = $xml->createElement('impuesto');
          $impuesto = $impuestos->appendChild($impuesto);
          $cbc = $xml->createElement('codigo', $impuestoItem->CodigoEmision);
          $cbc = $impuesto->appendChild($cbc);
          $cbc = $xml->createElement('codigoPorcentaje', $impuestoItem->CodigoPorcentaje);
          $cbc = $impuesto->appendChild($cbc);
          $cbc = $xml->createElement('tarifa', $impuestoItem->Porcentaje);
          $cbc = $impuesto->appendChild($cbc);
          $cbc = $xml->createElement('baseImponible', number_format($subtotal, 2, ".", ""));
          $cbc = $impuesto->appendChild($cbc);
          $cbc = $xml->createElement('valor', number_format((($subtotal * $impuestoItem->Porcentaje) / 100), 2, ".", ""));
          $cbc = $impuesto->appendChild($cbc);
        }
      }
    }

    $xml->formatOutput = true;
    $strings_xml = $xml->saveXML();
    return $strings_xml;
  }

  static function firmarXml($xml, $file, $pass) {
    //return $fe->getData()['serialNumber'];
    //$rutapfx = __DIR__ . '/public/index.php'
    /*$rutapfx = $_SERVER["DOCUMENT_ROOT"] .DIRECTORY_SEPARATOR.
      'certs'.DIRECTORY_SEPARATOR. $pfx;*/
      //use XmlSigner;    
    /*$signer = new XmlSigner();
    $signer->loadPfxFile($pfx, $pin);
    return $signer->signXml($xml, 'sha1');*/
    //return $objSec;


    /*$doc = new DOMDocument();
    //$doc->load(dirname(__FILE__) . '/basic-doc.xml');
    $doc->loadXML($xml);

    $objDSig = new XMLSecurityDSig();
    $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
    $objDSig->addReference($doc, XMLSecurityDSig::SHA1, array('http://www.w3.org/2000/09/xmldsig#enveloped-signature'), array('force_uri' => true));
    $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
    // load private key 
    $objKey->loadKey($cert, TRUE);
    // if key has Passphrase, set it using $objKey->passphrase = <passphrase> " 
    $objDSig->sign($objKey);
    // Add associated public key 
    $objDSig->add509Cert(file_get_contents($cert));
    $objDSig->appendSignature($doc->documentElement);
    return $doc->saveXML();*/

    // Cargar el archivo .p12
    $pkcs12 = file_get_contents($file);
    if (!openssl_pkcs12_read($pkcs12, $certs, $pass)) {
        throw new Exception("Error al leer el archivo .p12");
    }
    $privateKey = $certs['pkey'];
    $publicCert = $certs['cert'];
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    $objDSig = new XMLSecurityDSig();
    $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
    // Firmar todos los elementos del XML
    $objDSig->addReference(
        $doc, 
        XMLSecurityDSig::SHA1, 
        array('http://www.w3.org/2000/09/xmldsig#enveloped-signature')
    );
    // Crear una nueva clave de seguridad (llave privada)
    $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type' => 'private'));
    $objKey->loadKey($privateKey);
    // Agregar la firma al documento
    $objDSig->sign($objKey);
    // Agregar la clave pública al documento firmado
    $objDSig->add509Cert($publicCert);
    $objDSig->appendSignature($doc->documentElement);
    return $doc->saveXML();

  }
  static function generarXades($xml, $params) {
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
  public static function enviar($p) {
    $p->firmardo;
    $url = $p->url;
    try {
      $client = new SoapClient($url, [ "trace" => 1 ] );
      $result = $client->ResolveIP( [ "ipAddress" => $url, "licenseKey" => "0" ] );
      print_r($result);
    } catch ( SoapFault $e ) {
      echo $e->getMessage();      
    }
  }

  public static function verificar($claveAcceso) {
    return false;
  }

  public static function procesarFactura($comprobante, $pdb) {
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
      'ambiente' => '2' // 1: PRUEBAS; 2: PRODUCCION
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

  public static function ExtraerCertificado($rutapfx, $pass, $code) {
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

