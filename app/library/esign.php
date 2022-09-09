<?php

/**
 * LibreDTE
 * Copyright (C) SASCO SpA (https://sasco.cl)
 *
 * Este programa es software libre: usted puede redistribuirlo y/o
 * modificarlo bajo los términos de la Licencia Pública General Affero de GNU
 * publicada por la Fundación para el Software Libre, ya sea la versión
 * 3 de la Licencia, o (a su elección) cualquier versión posterior de la
 * misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General Affero de GNU para
 * obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General Affero de GNU
 * junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

namespace sasco\LibreDTE;

/**
 * Clase para trabajar con firma electrónica, permite firmar y verificar firmas.
 * Provee los métodos: sign(), verify(), signXML() y verifyXML()
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
 * @version 2021-08-16
 */
class FirmaElectronica
{
    const XMLDSIGNS = 'http://www.w3.org/2000/09/xmldsig#';
    const XMLETSI = 'http://uri.etsi.org/01903/v1.3.2#';
    const SHA1 = 'http://www.w3.org/2000/09/xmldsig#sha1';
    const RSASHA1 = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
    const SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';
    const SHA384 = 'http://www.w3.org/2001/04/xmldsig-more#sha384';
    const SHA512 = 'http://www.w3.org/2001/04/xmlenc#sha512';
    const RIPEMD160 = 'http://www.w3.org/2001/04/xmlenc#ripemd160';
    const ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    const C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    const C14N_COMMENTS = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315#WithComments';
    const EXC_C14N = 'http://www.w3.org/2001/10/xml-exc-c14n#';
    const EXC_C14N_COMMENTS = 'http://www.w3.org/2001/10/xml-exc-c14n#WithComments';

    private $config; ///< Configuración de la firma electrónica
    private $certs; ///< Certificados digitales de la firma
    private $data; ///< Datos del certificado digial

    /**
     * Constructor para la clase: crea configuración y carga certificado digital
     *
     * Si se desea pasar una configuración específica para la firma electrónica
     * se debe hacer a través de un arreglo con los índices file y pass, donde
     * file es la ruta hacia el archivo .p12 que contiene tanto la clave privada
     * como la pública y pass es la contraseña para abrir dicho archivo.
     * Ejemplo:
     *
     * \code{.php}
     *   $firma_config = ['file'=>'/ruta/al/certificado.p12', 'pass'=>'contraseña'];
     *   $firma = new \sasco\LibreDTE\FirmaElectronica($firma_config);
     * \endcode
     *
     * También se permite que en vez de pasar la ruta al certificado p12 se pase
     * el contenido del certificado, esto servirá por ejemplo si los datos del
     * archivo están almacenados en una base de datos. Ejemplo:
     *
     * \code{.php}
     *   $firma_config = ['data'=>file_get_contents('/ruta/al/certificado.p12'), 'pass'=>'contraseña'];
     *   $firma = new \sasco\LibreDTE\FirmaElectronica($firma_config);
     * \endcode
     *
     * @param config Configuración para la clase, si no se especifica se tratará de determinar
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2019-12-04
     */
    public function __construct(array $config = [])
    {
        // crear configuración
        if (!$config) {
            /*if (class_exists('\sowerphp\core\Configure')) {
                $config = (array)\sowerphp\core\Configure::read('firma_electronica.default');
            } else {
                $config = [];
            }*/
            return $this->error('La configuracion es invalida');
        }
        $this->config = array_merge([
            'file' => null,
            'pass' => null,
            'wordwrap' => 64,
        ], $config);
        // leer datos de la firma electrónica desde configuración con índices: cert y pkey
        if (!empty($this->config['cert']) and !empty($this->config['pkey'])) {
            $this->certs = [
                'cert' => $this->config['cert'],
                'pkey' => $this->config['pkey'],
            ];
            unset($this->config['cert'], $this->config['pkey']);
        }
        // se pasó el archivo de la firma o bien los datos de la firma
        else {
            // cargar firma electrónica desde el contenido del archivo .p12 si no
            // se pasaron como datos del arreglo de configuración
            if (empty($this->config['data']) and $this->config['file']) {
                if (is_readable($this->config['file'])) {
                    $this->config['data'] = file_get_contents($this->config['file']);
                } else {
                    return $this->error('Archivo de la firma electrónica '.basename($this->config['file']).' no puede ser leído');
                }
            }
            // leer datos de la firma desde el archivo que se ha pasado
            if (!empty($this->config['data'])) {
                if (openssl_pkcs12_read($this->config['data'], $this->certs, $this->config['pass'])===false) {
                    return $this->error('No fue posible leer los datos de la firma electrónica (verificar la contraseña)');
                }
                unset($this->config['data']);
            }
        }
        $this->data = openssl_x509_parse($this->certs['cert']);
    }

    /**
     * Método para generar un error usando una excepción de SowerPHP o terminar
     * el script si no se está usando el framework
     * @param msg Mensaje del error
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-08-04
     */
    private function error($msg)
    {
        if (class_exists('\sasco\LibreDTE\Estado') and class_exists('\sasco\LibreDTE\Log')) {
            $msg = \sasco\LibreDTE\Estado::get(\sasco\LibreDTE\Estado::FIRMA_ERROR, $msg);
            \sasco\LibreDTE\Log::write(\sasco\LibreDTE\Estado::FIRMA_ERROR, $msg);
            return false;
        } else {
            throw new \Exception($msg);
        }
    }

    /**
     * Método para generar un codigo aleatorioa partir de la current timestamp
     * @author Martin Barberan Guillen, jmbarberan (jmbarberan[at]gmail.com)
     * @version 2022-09-08
     */
    private function codigoAleatorio() {
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

    /**
     * Método que agrega el inicio y fin de un certificado (clave pública)
     * @param cert Certificado que se desea normalizar
     * @return Certificado con el inicio y fin correspondiente
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-08-20
     */
    private function normalizeCert($cert)
    {
        if (strpos($cert, '-----BEGIN CERTIFICATE-----')===false) {
            $body = trim($cert);
            $cert = '-----BEGIN CERTIFICATE-----'."\n";
            $cert .= wordwrap($body, $this->config['wordwrap'], "\n", true)."\n";
            $cert .= '-----END CERTIFICATE-----'."\n";
        }
        return $cert;
    }

    /**
     * Método que entrega el RUN/RUT asociado al certificado
     * @return RUN/RUT asociado al certificado en formato: 11222333-4
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2020-12-31
     */
    public function getID()
    {
        // RUN/RUT se encuentra en la extensión del certificado, esto de acuerdo
        // a Ley 19.799 sobre documentos electrónicos y firma electrónica
        $x509 = new \phpseclib\File\X509();
        $cert = $x509->loadX509($this->certs['cert']);
        if (isset($cert['tbsCertificate']['extensions'])) {
            foreach ($cert['tbsCertificate']['extensions'] as $e) {
                if ($e['extnId']=='id-ce-subjectAltName') {
                    return strtoupper(ltrim($e['extnValue'][0]['otherName']['value']['ia5String'], '0'));
                }
            }
        }
        // se obtiene desde serialNumber (esto es sólo para que funcione la firma para tests)
        if (isset($this->data['subject']['serialNumber'])) {
            return strtoupper(ltrim($this->data['subject']['serialNumber'], '0'));
        }
        // no se encontró el RUN
        return $this->error('No fue posible obtener el ID de la firma');
    }

    /**
     * Método que entrega el CN del subject
     * @return CN del subject
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2016-02-12
     */
    public function getName()
    {
        if (isset($this->data['subject']['CN']))
            return $this->data['subject']['CN'];
        return $this->error('No fue posible obtener el Name (subject.CN) de la firma');
    }

    /**
     * Método que entrega el emailAddress del subject
     * @return emailAddress del subject
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2016-02-12
     */
    public function getEmail()
    {
        if (isset($this->data['subject']['emailAddress'])) {
            return $this->data['subject']['emailAddress'];
        }
        return $this->error('No fue posible obtener el Email (subject.emailAddress) de la firma');
    }

    /**
     * Método que entrega desde cuando es válida la firma
     * @return validFrom_time_t
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-22
     */
    public function getFrom()
    {
        return date('Y-m-d H:i:s', $this->data['validFrom_time_t']);
    }

    /**
     * Método que entrega hasta cuando es válida la firma
     * @return validTo_time_t
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-22
     */
    public function getTo()
    {
        return date('Y-m-d H:i:s', $this->data['validTo_time_t']);
    }

    /**
     * Método que entrega los días totales que la firma es válida
     * @return int Días totales en que la firma es válida
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2019-02-12
     */
    public function getTotalDays()
    {
        $start = new \DateTime($this->getFrom());
        $end = new \DateTime($this->getTo());
        $diff = $start->diff($end);
        return $diff->format('%a');
    }

    /**
     * Método que entrega los días que faltan para que la firma expire
     * @return int Días que faltan para que la firma expire
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2019-02-12
     */
    public function getExpirationDays($desde = null)
    {
        if (!$desde) {
            $desde = date('Y-m-d H:i:s');
        }
        $start = new \DateTime($desde);
        $end = new \DateTime($this->getTo());
        $diff = $start->diff($end);
        return $diff->format('%a');
    }

    /**
     * Método que indica si la firma está vigente o vencida
     * @return bool =true si la firma está vigente, =false si está vencida
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2021-08-16
     */
    public function isActive($when = null)
    {
        if (!$when) {
            $when = date('Y-m-d').' 00:00:00';
        }
        return $this->getTo() > $when;
    }

    /**
     * Método que entrega el nombre del emisor de la firma
     * @return CN del issuer
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-22
     */
    public function getIssuer()
    {
        return $this->data['issuer']['CN'];
        // serialNumber
    }

    public function getSerialNumber() {
        $ret = $this->data['serialNumber'];
        //return $this->data['subject']['serialNumber']
    }

    /**
     * Método que entrega los datos del certificado
     * @return Arreglo con todo los datos del certificado
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-11
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Método que obtiene el módulo de la clave privada
     * @return Módulo en base64
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2014-12-07
     */
    public function getModulus()
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->certs['pkey']));
        return wordwrap(base64_encode($details['rsa']['n']), $this->config['wordwrap'], "\n", true);
    }

    /**
     * Método que obtiene el exponente público de la clave privada
     * @return Exponente público en base64
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2014-12-06
     */
    public function getExponent()
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->certs['pkey']));
        return wordwrap(base64_encode($details['rsa']['e']), $this->config['wordwrap'], "\n", true);
    }

    /**
     * Método que entrega el certificado de la firma
     * @return Contenido del certificado, clave pública del certificado digital, en base64
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-08-24
     */
    public function getCertificate($clean = false)
    {
        if ($clean) {
            return trim(str_replace(
                ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'],
                '',
                $this->certs['cert']
            ));
        } else {
            return $this->certs['cert'];
        }
    }

    /**
     * Método que entrega la clave privada de la firma
     * @return Contenido de la clave privada del certificado digital en base64
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-08-24
     */
    public function getPrivateKey($clean = false)
    {
        if ($clean) {
            return trim(str_replace(
                ['-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----'],
                '',
                $this->certs['pkey']
            ));
        } else {
            return $this->certs['pkey'];
        }
    }

    /**
     * Método para realizar la firma de datos
     * @param data Datos que se desean firmar
     * @param signature_alg Algoritmo que se utilizará para firmar (por defect SHA1)
     * @return Firma digital de los datos en base64 o =false si no se pudo firmar
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2014-12-08
     */
    public function sign($data, $signature_alg = OPENSSL_ALGO_SHA1)
    {
        $signature = null;
        if (openssl_sign($data, $signature, $this->certs['pkey'], $signature_alg)==false) {
            return $this->error('No fue posible firmar los datos');
        }
        return base64_encode($signature);
    }

    /**
     * Método que verifica la firma digital de datos
     * @param data Datos que se desean verificar
     * @param signature Firma digital de los datos en base64
     * @param pub_key Certificado digital, clave pública, de la firma
     * @param signature_alg Algoritmo que se usó para firmar (por defect SHA1)
     * @return =true si la firma está ok, =false si está mal o no se pudo determinar
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2014-12-08
     */
    public function verify($data, $signature, $pub_key = null, $signature_alg = OPENSSL_ALGO_SHA1)
    {
        if ($pub_key === null)
            $pub_key = $this->certs['cert'];
        $pub_key = $this->normalizeCert($pub_key);
        return openssl_verify($data, base64_decode($signature), $pub_key, $signature_alg) == 1 ? true : false;
    }

    /**
     * Método que firma un XML utilizando RSA y SHA1
     *
     * Referencia: http://www.di-mgt.com.au/xmldsig2.html
     *
     * @param xml Datos XML que se desean firmar
     * @param reference Referencia a la que hace la firma
     * @return XML firmado o =false si no se pudo fimar
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-10-22
     */
    public function signXML($xml, $reference = '', $tag = "etsi:SignedProperties", $xmlns_xsi = false)
    {
        // normalizar 4to parámetro que puede ser boolean o array
        if (is_array($xmlns_xsi)) {
            $namespace = $xmlns_xsi;
            $xmlns_xsi = false;
        } else {
            $namespace = null;
        }

        $sha1_comprobante = base64_encode(str_replace('<?xml version="1.0" encoding="UTF-8"?>\n', '', $xml));
        $Certificate_number = $this->codigoAleatorio();
        $Signature_number = $this->codigoAleatorio();
        $SignedProperties_number = $this->codigoAleatorio();
        //numeros fuera de los hash:
        $SignedInfo_number = $this->codigoAleatorio();
        $SignedPropertiesID_number = $this->codigoAleatorio();
        $Reference_ID_number = $this->codigoAleatorio();
        $SignatureValue_number = $this->codigoAleatorio();
        $Object_number = $this->codigoAleatorio();
        
        $signature = '123';
        $digCertificado = base64_encode(hash('sha1', strval($this->getCertificate()), true));

        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = false;
        $doc->loadXML($xml);

        $fac = $doc->getElementsByTagName('factura')->item(0);
        $sign = $doc->createElement('ds:Signature');
        $sign = $fac->appendChild($sign);
        $sign->setAttribute("xmlns:ds", self::XMLDSIGNS);
        $sign->setAttribute("xmlns:etsi", self::XMLETSI);
        $sign->setAttribute("Id", "Signature" . $Signature_number); // SIGNATURE + ID        

          $signedInfo = $doc->createElement('ds:SignedInfo');
          $signedInfo = $sign->appendChild($signedInfo);
          
          $signedValue = $doc->createElement('ds:SignatureValue', $signature);
          $signedValue = $sign->appendChild($signedValue);
          $signedValue->setAttribute("Id", "SignatureValue" . $SignatureValue_number);

          $keyInfo = $doc->createElement('ds:KeyInfo');
          $keyInfo = $sign->appendChild($keyInfo);
          $keyInfo->setAttribute("Id", "Certificate" . $Certificate_number);

            $kiX509Data = $doc->createElement('ds:X509Data');
            $kiX509Data = $keyInfo->appendChild($kiX509Data);

              $kiX509Certificate = $doc->createElement('ds:X509Certificate', $this->getCertificate(true));
              $kiX509Certificate = $kiX509Data->appendChild($kiX509Certificate);

            $kiKeyValue = $doc->createElement('ds:KeyValue');
            $kiKeyValue = $keyInfo->appendChild($kiKeyValue);
            
              $kvRSAKeyValue = $doc->createElement('ds:RSAKeyValue');
              $kvRSAKeyValue = $kiKeyValue->appendChild($kvRSAKeyValue);

                $modulus = $doc->createElement('ds:Modulus', $this->getModulus());
                $modulus = $kvRSAKeyValue->appendChild($modulus);

                $exponent = $doc->createElement('ds:Exponent', $this->getExponent());
                $exponent = $kvRSAKeyValue->appendChild($exponent);
          
          $object = $doc->createElement('ds:Object');
          $object = $sign->appendChild($object);
          $object->setAttribute("Id", "Signature" . $Signature_number . "-" . "Object" . $Object_number);
          
            $eQualiProps = $doc->createElement('etsi:QualifyingProperties');
            $eQualiProps = $object->appendChild($eQualiProps);
            $eQualiProps->setAttribute("Target", "#Signature" . $Signature_number);

              $refSignedProps = $doc->createElement('etsi:SignedProperties');
              $refSignedProps = $eQualiProps->appendChild($refSignedProps);
              $refSignedProps->setAttribute("Id", "Signature" . $Signature_number . "-" . "SignedProperties" . $SignedProperties_number);

                $refSignedSignProps = $doc->createElement('etsi:SignedSignatureProperties');
                $refSignedSignProps = $refSignedProps->appendChild($refSignedSignProps);

                  $SigningTime = $doc->createElement('etsi:SigningTime', (new \DateTime())->format('Y-m-t H:i:s'));
                  $SigningTime = $refSignedSignProps->appendChild($SigningTime);

                  $signingCert = $doc->createElement('etsi:SigningCertificate');
                  $signingCert = $refSignedSignProps->appendChild($signingCert);

                    $cert = $doc->createElement('etsi:Cert');
                    $cert = $signingCert->appendChild($cert);

                      $certDigest = $doc->createElement('etsi:CertDigest');
                      $certDigest = $cert->appendChild($certDigest);

                        $certDigMethod = $doc->createElement('etsi:DigestMethod');
                        $certDigMethod = $certDigest->appendChild($certDigMethod);
                        $certDigMethod->setAttribute("Algorithm", self::SHA1);

                        $certDigValue = $doc->createElement('etsi:DigestValue', $digCertificado);
                        $certDigValue = $certDigest->appendChild($certDigValue);

                      $issuerSerial = $doc->createElement('etsi:IssuerSerial');
                      $issuerSerial = $cert->appendChild($issuerSerial);

                        $x509IssuerName = $doc->createElement('ds:X509IssuerName', $this->getIssuer());
                        $x509IssuerName = $issuerSerial->appendChild($x509IssuerName);

                        $x509SerialNumber = $doc->createElement('ds:X509SerialNumber', $this->getSerialNumber());
                        $x509SerialNumber = $issuerSerial->appendChild($x509SerialNumber);

                $refSignedDataProps = $doc->createElement('etsi:SignedDataObjectProperties');
                $refSignedDataProps = $refSignedProps->appendChild($refSignedDataProps);

                  $dataFormat = $doc->createElement('etsi:DataObjectFormat');
                  $dataFormat = $refSignedDataProps->appendChild($dataFormat);
                  $dataFormat->setAttribute("ObjectReference", "#Reference-ID-" . $Reference_ID_number);

                    $dfDescription = $doc->createElement('etsi:Description', "contenido componente");
                    $dfDescription = $dataFormat->appendChild($dfDescription);

                    $dfMimeType = $doc->createElement('etsi:MimeType', "text/xml");
                    $dfMimeType = $dataFormat->appendChild($dfMimeType);     

          // Obtener el digest de xx
          $dig = $doc->getElementsByTagName('etsi:SignedProperties')->item(0)->nodeValue;
          $digest = base64_encode(hash('sha1', $dig, true));

          // INFO DE FIRMA
          $signedInfo->setAttribute("Id", "Signature-SignedInfo" . $SignedInfo_number);

            $canonicalizationMethod = $doc->createElement('ds:CanonicalizationMethod');
            $canonicalizationMethod = $signedInfo->appendChild($canonicalizationMethod);
            $canonicalizationMethod->setAttribute("Algorithm", self::C14N);

            $signatureMethod = $doc->createElement('ds:SignatureMethod');
            $signatureMethod = $signedInfo->appendChild($signatureMethod);
            $signatureMethod->setAttribute("Algorithm", self::RSASHA1);
        
            $reference1 = $doc->createElement('ds:Reference');
            $reference1 = $signedInfo->appendChild($reference1);
            $reference1->setAttribute("Id", "SignedPropertiesID" . $SignedPropertiesID_number);
            $reference1->setAttribute("Type", "http://uri.etsi.org/01903#SignedProperties");
            $reference1->setAttribute("URI", "#Signature". $Signature_number . "-" . "SignedProperties" . $SignedProperties_number);

              $refDigestMethod = $doc->createElement('ds:DigestMethod');
              $refDigestMethod = $reference1->appendChild($refDigestMethod);
              $refDigestMethod->setAttribute("Algorithm", self::SHA1);
              
              $ref1DigestValue = $doc->createElement('ds:DigestValue', $digest);
              $ref1DigestValue = $reference1->appendChild($ref1DigestValue);

            $reference2 = $doc->createElement('ds:Reference');
            $reference2 = $signedInfo->appendChild($reference2);
            $reference2->setAttribute("URI", "#Certificate" . $Certificate_number);

              $ref2DigestMethod = $doc->createElement('ds:DigestMethod');
              $ref2DigestMethod = $reference2->appendChild($ref2DigestMethod);
              $ref2DigestMethod->setAttribute("Algorithm", self::SHA1);
                
              $digest2 = base64_encode(hash('sha1', $this->getCertificate(), true));

              $ref2DigestValue = $doc->createElement('ds:DigestValue', $digest2);
              $ref2DigestValue = $reference2->appendChild($ref2DigestValue);

            $reference3 = $doc->createElement('ds:Reference');
            $reference3 = $signedInfo->appendChild($reference3);
            $reference3->setAttribute("Id", "Reference-ID-" . $Reference_ID_number);
            $reference3->setAttribute("URI", "#comprobante");

              $ref3Transforms = $doc->createElement('ds:Transforms');
              $ref3Transforms = $reference3->appendChild($ref3Transforms);

                $ref3Transform = $doc->createElement('ds:Transform');
                $ref3Transform = $ref3Transforms->appendChild($ref3Transform);
                $ref3Transform->setAttribute("Algorithm", self::ENVELOPED);

              $ref3DigestMethod = $doc->createElement('ds:DigestMethod');
              $ref3DigestMethod = $reference3->appendChild($ref3DigestMethod);
              $ref3DigestMethod->setAttribute("Algorithm", self::SHA1);
                
              $digest3 = base64_encode(hash('sha1', $sha1_comprobante, true));

              $ref3DigestValue = $doc->createElement('ds:DigestValue', $digest3);
              $ref3DigestValue = $reference3->appendChild($ref3DigestValue);

          $signedInfoTag = $doc->getElementsByTagName('ds:SignedInfo')->item(0)->nodeValue;
          $firmado = $this->sign($signedInfoTag);
          if (!$firmado)
            return false;
          $signature = wordwrap($firmado, $this->config['wordwrap'], "\n", true);
          $doc->getElementsByTagName('ds:SignatureValue')->item(0)->nodeValue = $signature;
          
        // agregar y entregar firma
        //$doc->documentElement->appendChild($Signature);
        return $doc->saveXML();
    }

    /**
     * Método que verifica la validez de la firma de un XML utilizando RSA y SHA1
     * @param xml_data Archivo XML que se desea validar
     * @return =true si la firma del documento XML es válida o =false si no lo es
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-02
     */
    public function verifyXML($xml_data, $tag = null)
    {
        $doc = new XML();
        $doc->loadXML($xml_data);
        // preparar datos que se verificarán
        $SignaturesElements = $doc->documentElement->getElementsByTagName('Signature');
        $Signature = $doc->documentElement->removeChild($SignaturesElements->item($SignaturesElements->length-1));
        $SignedInfo = $Signature->getElementsByTagName('SignedInfo')->item(0);
        $SignedInfo->setAttribute('xmlns', $Signature->getAttribute('xmlns'));
        $signed_info = $doc->saveHTML($SignedInfo);
        $signature = $Signature->getElementsByTagName('SignatureValue')->item(0)->nodeValue;
        $pub_key = $Signature->getElementsByTagName('X509Certificate')->item(0)->nodeValue;
        // verificar firma
        if (!$this->verify($signed_info, $signature, $pub_key))
            return false;
        // verificar digest
        $digest_original = $Signature->getElementsByTagName('DigestValue')->item(0)->nodeValue;
        if ($tag) {
            $digest_calculado = base64_encode(sha1($doc->documentElement->getElementsByTagName($tag)->item(0)->C14N(), true));
        } else {
            $digest_calculado = base64_encode(sha1($doc->C14N(), true));
        }
        return $digest_original == $digest_calculado;
    }

    /**
     * Método que obtiene la clave asociada al módulo y exponente entregados
     * @param modulus Módulo de la clave
     * @param exponent Exponente de la clave
     * @return Entrega la clave asociada al módulo y exponente
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-19
     */
    public static function getFromModulusExponent($modulus, $exponent)
    {
        $rsa = new \phpseclib\Crypt\RSA();
        $modulus = new \phpseclib\Math\BigInteger(base64_decode($modulus), 256);
        $exponent = new \phpseclib\Math\BigInteger(base64_decode($exponent), 256);
        $rsa->loadKey(['n' => $modulus, 'e' => $exponent]);
        $rsa->setPublicKey();
        return $rsa->getPublicKey();
    }

}