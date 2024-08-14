<?php



use Pointerp\Modelos\EmpresaParametros;
use Pointerp\Modelos\Empresas;
use Pointerp\Modelos\Maestros\Impuestos;
use Pointerp\Modelos\Maestros\Registros;
/*use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;*/
/*use phpseclib3\File\X509;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Math\BigInteger;*/

require_once APP_PATH . '/library/sha256.php';

//include("../library/sha256.php");

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
      $xmlFirmado = self::crearFirmaXades($xmlFactura);
      if (isset($xmlFirmado)) {
        $respEnvio = self::enviar($xmlFirmado, $ambiente);
        if (isset($respEnvio)) {
          $res = self::verificar($comprobante->CEClaveAcceso, $ambiente);

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

  private static function crearFirmaXades($comprobante) {
    $comprobante = preg_replace('/\s+/', ' ', $comprobante);
    $comprobante = trim($comprobante);
    $comprobante = preg_replace('/(?<=\>)(\r?\n)/', '', $comprobante);
    $comprobante = trim($comprobante);
    $comprobante = preg_replace('/(?<=\>)(\s*)/', '', $comprobante);
    $comprobante = trim($comprobante);

    $certFileName = basename(self::$certificado);
    $certFile = $_SERVER["DOCUMENT_ROOT"] . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR. $certFileName;
    $pass = base64_decode(self::$password);

    $pkcs12 = file_get_contents($certFile);
    if (!openssl_pkcs12_read($pkcs12, $certs, $pass)) {
        throw new Exception("Error al leer el archivo certificado");
    }
    $cert = $certs['cert'];
    //$key = $certs['pkey'];

    $publicKey = openssl_x509_read($cert);
    $privateKey = openssl_pkey_get_private($certs['pkey']);
    $aInfoPublicKey = openssl_pkey_get_details(openssl_pkey_get_public($publicKey));
    $certificateX509_pem = $cert;
    $certificateX509 = $certificateX509_pem;
    $certificateX509 = substr($certificateX509, strpos($certificateX509, "\n") + 1);
    $certificateX509 = substr($certificateX509, 0, strpos($certificateX509, "\n-----END CERTIFICATE-----"));

    $certificateX509 = preg_replace('/\r?\n|\r/', '', $certificateX509);
    //$certificateX509 = preg_replace('/([^\0]{76})/', "$1\n", $certificateX509);
    
    $certificateX509_asn1 = openssl_x509_read($certificateX509_pem);
    
    $certificateX509_der = '';
    openssl_x509_export($certificateX509_asn1, $certificateX509_der, false);
    $certificateX509_der_hash = self::sha256_base64($certificateX509_der);

    /*$certificateX509_der = '';
    $certificateX509_der = openssl_x509_export($certificateX509_asn1, $certificateX509_der, OPENSSL_KEYTYPE_RSA);
                         //openssl_x509_export($certificateX509_asn1, $certificateX509_der, false);
    $certificateX509_der_hash = self::sha256_base64($certificateX509_der);*/
    
    $certData = openssl_x509_parse($certificateX509_asn1);
    //$serialInit = gmp_init($certData["serialNumberHex"], 16);
    $X509SerialNumber = strval(gmp_init($certData["serialNumberHex"], 16));
    $issuerRevArray = array_reverse((array)$certData['issuer']);
    $issuerName = implode(', ', array_map(function($issuerClave, $issuerValor) {
      return "{$issuerClave}={$issuerValor}";
    }, array_keys($issuerRevArray), $issuerRevArray));    
    $sha_Comprobante = self::sha256_base64(str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $comprobante));

    //$xmlns = 'xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:etsi="http://uri.etsi.org/01903/v1.3.2#"';    
    $Certificate_number = self::obtenerAleatorio();
    $Signature_number = self::obtenerAleatorio();
    $SignedProperties_number = self::obtenerAleatorio();
    //$SignedInfo_number = self::obtenerAleatorio();
    $Reference_ID_number = self::obtenerAleatorio();
    $SignatureValue_number = self::obtenerAleatorio();
    $Object_number = self::obtenerAleatorio();

    #region SIGNED PROPERTIES
    $SignedProperties = "<xades:SignedProperties Id='Signature{$Signature_number}-SignedProperties{$SignedProperties_number}'>";

    $SignedProperties .= '<xades:SignedSignatureProperties>';
    $SignedProperties .= '<xades:SigningTime>';
    $SignedProperties .= date('c');
    $SignedProperties .= '</xades:SigningTime>';
    $SignedProperties .= '<xades:SigningCertificate>';
    $SignedProperties .= '<xades:Cert>';
    $SignedProperties .= '<xades:CertDigest>';
    $SignedProperties .= '<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmlenc#sha256"></ds:DigestMethod>';
    $SignedProperties .= '<ds:DigestValue>';
    $SignedProperties .= htmlspecialchars($certificateX509_der_hash, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $SignedProperties .= '</ds:DigestValue>';
    $SignedProperties .= '</xades:CertDigest>';
    $SignedProperties .= '<xades:IssuerSerial>';
    $SignedProperties .= '<ds:X509IssuerName>';
    $SignedProperties .= $issuerName;
    $SignedProperties .= '</ds:X509IssuerName>';
    $SignedProperties .= '<ds:X509SerialNumber>';
    $SignedProperties .= $X509SerialNumber;
    $SignedProperties .= '</ds:X509SerialNumber>';
    $SignedProperties .= '</xades:IssuerSerial>';
    $SignedProperties .= '</xades:Cert>';
    $SignedProperties .= '</xades:SigningCertificate>';
    $SignedProperties .= '</xades:SignedSignatureProperties>';

    $SignedProperties .= '<xades:SignedDataObjectProperties>';
    $SignedProperties .= "<xades:DataObjectFormat ObjectReference='#Reference-ID-$Reference_ID_number'>";
    $SignedProperties .= '<xades:MimeType>';
    $SignedProperties .= 'text/xml';
    $SignedProperties .= '</xades:MimeType>';
    $SignedProperties .= '<xades:Encoding>';
    $SignedProperties .= 'UTF-8';
    $SignedProperties .= '</xades:Encoding>';
    $SignedProperties .= '</xades:DataObjectFormat>';
    $SignedProperties .= '</xades:SignedDataObjectProperties>';

    $SignedProperties .= '</xades:SignedProperties>';
    $SignedProperties = preg_replace('/(?<=\>)(\r?\n)/', '', $SignedProperties);
    $SignedProperties = preg_replace('/(?<=\>)(\s*)/', '', $SignedProperties);

    $dom = new DOMDocument();
    $dom->loadXML($SignedProperties);
    $canonicalSignedProperties = $dom->C14N(true, false);    
    $sha_SignedProperties = self::sha256_base64($canonicalSignedProperties);
    #endregion

    #region KEYINFO
    $modulusBase64 = base64_encode($aInfoPublicKey['rsa']['n']);
    $exponentBase64 = trim(base64_encode($aInfoPublicKey['rsa']['e'])); 
    $KeyInfo = '<ds:KeyInfo Id="Certificate-KeyInfo' . $Certificate_number . '">';
    $KeyInfo .= '<ds:X509Data>';
    $KeyInfo .= '<ds:X509Certificate>';
    $KeyInfo .= htmlspecialchars($certificateX509, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $KeyInfo .= '</ds:X509Certificate>';
    $KeyInfo .= '</ds:X509Data>';
    $KeyInfo .= '<ds:KeyValue>';
    $KeyInfo .= '<ds:RSAKeyValue>';
    $KeyInfo .= '<ds:Modulus>';
    $KeyInfo .= htmlspecialchars($modulusBase64, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $KeyInfo .= '</ds:Modulus>';
    $KeyInfo .= '<ds:Exponent>';
    $KeyInfo .= htmlspecialchars($exponentBase64, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $KeyInfo .= '</ds:Exponent>';
    $KeyInfo .= '</ds:RSAKeyValue>';
    $KeyInfo .= '</ds:KeyValue>';
    $KeyInfo .= '</ds:KeyInfo>';
    $KeyInfo = preg_replace('/(?<=\>)(\r?\n)/', '', $KeyInfo);
    $KeyInfo = preg_replace('/(?<=\>)(\s*)/', '', $KeyInfo);

    $dom = new DOMDocument();
    $dom->loadXML($KeyInfo);
    $canonicalKeyInfo = $dom->C14N(true, false);
    $sha_KeyInfo = self::sha256_base64($canonicalKeyInfo);
    /*$sha_KeyInfo = self::sha256_base64(
      str_replace('<ds:KeyInfo', '<ds:KeyInfo ' . $xmlns, $KeyInfo)
    );*/
    #endregion

    #region SIGNEDINFO
    $SignedInfo = '<ds:SignedInfo>';
    $SignedInfo .= '<ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"></ds:CanonicalizationMethod>';
    $SignedInfo .= '<ds:SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></ds:SignatureMethod>';
    $SignedInfo .= '<ds:Reference Id="ReferenceKeyInfo" URI="#Certificate-KeyInfo'. $Certificate_number. '">';
    $SignedInfo .= '<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmlenc#sha256"></ds:DigestMethod>';
    $SignedInfo .= '<ds:DigestValue>';
    $SignedInfo .= $sha_KeyInfo;
    $SignedInfo .= '</ds:DigestValue>';
    $SignedInfo .= '</ds:Reference>';
    $SignedInfo .= '<ds:Reference Type="http://uri.etsi.org/01903#SignedProperties" URI="#Signature' . $Signature_number. '-SignedProperties'. $SignedProperties_number .'">'; // Id="SignedPropertiesID'. $SignedPropertiesID_number. '"
    $SignedInfo .= '<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmlenc#sha256"></ds:DigestMethod>';
    $SignedInfo .= '<ds:DigestValue>';
    $SignedInfo .= $sha_SignedProperties;
    $SignedInfo .= '</ds:DigestValue>';
    $SignedInfo .= '</ds:Reference>';
    $SignedInfo .= '<ds:Reference Id="Reference-ID-'. $Reference_ID_number . '" URI="#comprobante">';
    $SignedInfo .= '<ds:Transforms>';
    $SignedInfo .= '<ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"></ds:Transform>';
    $SignedInfo .= '</ds:Transforms>';
    $SignedInfo .= '<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmlenc#sha256"></ds:DigestMethod>';
    $SignedInfo .= '<ds:DigestValue>';
    $SignedInfo .= $sha_Comprobante;
    $SignedInfo .= '</ds:DigestValue>';
    $SignedInfo .= '</ds:Reference>';
    $SignedInfo .= '</ds:SignedInfo>';
    $SignedInfo = preg_replace('/(?<=\>)(\r?\n)/', '', $SignedInfo);
    $SignedInfo = preg_replace('/(?<=\>)(\s*)/', '', $SignedInfo);
    
    $dom = new DOMDocument();
    $dom->loadXML($SignedInfo);
    $canonicalSignedInfo = $dom->C14N(true, false);
    //$sha_SignedInfo = SHA256::make($canonicalSignedInfo, true); //hash('sha256', $canonicalSignedInfo, true);
    $signature = '';
    openssl_sign($canonicalSignedInfo, $signature, $privateKey, OPENSSL_ALGO_SHA1);
    $signatureB64 = base64_encode($signature);

    /*$dom = new DOMDocument();
    $dom->loadXML($SignedInfo);
    $canonicalSignedInfo = $dom->C14N(true, false);
    $sha_SignedInfo = hash('sha256', $canonicalSignedInfo, true);
    //$sha_SignedInfo = str_replace('<ds:SignedInfo', "<ds:SignedInfo {$xmlns}", $SignedInfo);
    #endregion

    //$signature = '';
    $digest = openssl_digest($sha_SignedInfo, 'SHA1');

    // Firmar el hash utilizando la clave privada
    $signature = '';
    openssl_sign($digest, $signature, $privateKey, OPENSSL_ALGO_SHA1);
    //openssl_sign($sha_SignedInfo, $signature, $key, OPENSSL_ALGO_SHA1);
    $signatureB64 = base64_encode($signature);
    // $signatureB64 = chunk_split(base64_encode($signature), 76, PHP_EOL);*/

    $xades_bes = PHP_EOL . '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="Signature' . $Signature_number . '">';
    $xades_bes .= $SignedInfo;
    $xades_bes .= '<ds:SignatureValue Id="SignatureValue' . $SignatureValue_number . '">';
    $xades_bes .= $signatureB64;
    $xades_bes .= '</ds:SignatureValue>';
    $xades_bes .= $KeyInfo;
    $xades_bes .= '<ds:Object Id="Signature' . $Signature_number . '-Object' . $Object_number . '">';
    $xades_bes .= '<xades:QualifyingProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Target="#Signature' . $Signature_number . '">';
    $xades_bes .= $SignedProperties;
    $xades_bes .= '</xades:QualifyingProperties>';
    $xades_bes .= '</ds:Object>';
    $xades_bes .= '</ds:Signature>';

    $xades_bes = preg_replace('/(<[^<]+)$/', $xades_bes . '$1', $comprobante);

    /*$dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadXML($xades_bes);
    $dom->save('archivoFirmado.xml');*/

    return $xades_bes;
  }
  private static function sha256_base64($txt) {
    //$digest = hash('sha256', $txt, true);
    $digest = SHA256::make($txt, true);
    return base64_encode($digest);
  }

  private static function sha1_base64($txt) {
    //$sha1 = sha1($txt, true); // true outputs raw binary data
    // Encode the raw binary SHA-1 hash in Base64
    //return base64_encode($sha1);
    return base64_encode(pack('H*', sha1($txt)));
  }

  private static function obtenerAleatorio() {
    return rand(990, 999000);
  }

  /*function bigint2base64($bigint) {
    $hex = gmp_strval($bigint, 16);
    if (strlen($hex) % 2 != 0) {
        $hex = '0' . $hex;
    }
    $binary = hex2bin($hex);
    $base64 = base64_encode($binary);
    $formatted_base64 = chunk_split($base64, 76, "\n");
    return rtrim($formatted_base64, "\n");
  }

  function hexToBase64($str) {
    $hex = (strlen($str) % 2 != 0) ? "0{$str}" : $str;
    $binary = hex2bin($hex);
    $base64 = base64_encode($binary);
    return $base64;
  }*/


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

