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

include APP_PATH . '/library/XML.php';
use sasco\LibreDTE\XML;

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
    const XMLXSI = 'http://www.w3.org/2001/XMLSchema-instance';
    const SHA1 = 'http://www.w3.org/2000/09/xmldsig#sha1';
    const RSASHA1 = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
    const SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';
    const SHA384 = 'http://www.w3.org/2001/04/xmldsig-more#sha384';
    const SHA512 = 'http://www.w3.org/2001/04/xmlenc#sha512';
    const RIPEMD160 = 'http://www.w3.org/2001/04/xmlenc#ripemd160';
    const ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    const C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';

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
            if (class_exists('\sowerphp\core\Configure')) {
                $config = (array)\sowerphp\core\Configure::read('firma_electronica.default');
            } else {
                $config = [];
            }
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
        return 'CN=' . $this->data['issuer']['CN'] . ',' .
            'OU=' . $this->data['issuer']['OU'] . ',' .
            'O=' . $this->data['issuer']['O'] . ',' .
            'C=' . $this->data['issuer']['C'];
    }

    public function getSerialNumber() {
        return $this->data['serialNumber'];
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
    public function signXML($xml, $reference = '', $tag = null, $xmlns_xsi = false)
    {
        $Certificate_number = $this->codigoAleatorio();
        $Signature_number = $this->codigoAleatorio();
        $SignedProperties_number = $this->codigoAleatorio();
        //numeros fuera de los hash:
        $SignedInfo_number = $this->codigoAleatorio();
        $SignedPropertiesID_number = $this->codigoAleatorio();
        $Reference_ID_number = $this->codigoAleatorio();
        $SignatureValue_number = $this->codigoAleatorio();
        $Object_number = $this->codigoAleatorio();

        //$namespace = $xmlns_xsi;
        // normalizar 4to parámetro que puede ser boolean o array
        if (is_array($xmlns_xsi)) {
            $namespace = $xmlns_xsi;
            $xmlns_xsi = false;
        } else {
            $namespace = null;
        }
        // obtener objeto del XML que se va a firmar
        $strComp = str_replace('<?xml version="1.0" encoding="UTF-8"?>\n', '', $xml);
        $doc = new XML();
        $doc->loadXML($xml);
        if (!$doc->documentElement) {
            return $this->error('No se pudo obtener el documentElement desde el XML a firmar (posible XML mal formado)');
        }

        $docComp = new XML();
        $docComp->loadXML($strComp);
        // calcular DigestValue
        $item = $docComp->C14N();
        if ($tag) {
            $element = $docComp->documentElement->getElementsByTagName($tag)->item(0);
            if (!$element) {
                return $this->error('No fue posible obtener el nodo con el tag '.$tag);
            } else {
                $item = $element->C14N();
            }
        }
        $sha1_comprobante = base64_encode(sha1($item, true));
        $digCertificado = base64_encode(sha1($this->getCertificate(true), true));
        $digCertref = base64_encode(sha1($this->getCertificate(true), true));

        // Nodo del proveedor
        $proveedorTag = (new XML())->generate([
            'etsi:QualifyingProperties' => [
                '@attributes' => [
                    'Target' => '#Signature' . $Signature_number,
                ],
                'etsi:SignedProperties' => [
                    '@attributes' => [
                        'Id' => 'Signature' . $Signature_number . '-' . 'SignedProperties' . $SignedProperties_number,
                    ],
                    'etsi:SignedSignatureProperties' => [
                        'etsi:SigningTime' => (new \DateTime())->format('Y-m-tTH:i:s'),
                        'etsi:SigningCertificate' => [
                            'etsi:Cert' => [
                                'etsi:CertDigest' => [
                                    'ds:DigestMethod' => [
                                        '@attributes' => [
                                            'Algorithm' => self::SHA1,
                                        ],
                                    ],
                                    'ds:DigestValue' =>  $digCertificado,
                                ],
                                'etsi:IssuerSerial' => [
                                    'ds:X509IssuerName' => $this->getIssuer(),
                                    'ds:X509SerialNumber' => $this->getSerialNumber(),
                                ]
                            ],
                        ],
                    ],
                    'etsi:SignedDataObjectProperties' => [
                        'etsi:DataObjectFormat' => [
                            '@attributes' => [
                                'ObjectReference' => '#Reference-ID-' . $Reference_ID_number,
                            ],
                            'etsi:Description' => 'contenido comprobante',
                            'etsi:MimeType' => 'text/xml',
                        ],
                    ],
                ],
            ]
        ])->documentElement;

        $certRefTag = (new XML())->generate([
            'Reference' => [
                '@attributes' => [
                    'URI' => '#Certificate' . $Certificate_number,
                ],
                'DigestMethod' => [
                    '@attributes' => [
                        'Algorithm' => self::SHA1,
                    ],
                ],
                'DigestValue' => $digCertificado,
            ],
        ], $namespace)->documentElement;

        $compRefTag = (new XML())->generate([
            'ds:Reference' => [
                '@attributes' => [
                    'Id' => 'Reference-ID-' . $Reference_ID_number,
                    'URI' => $reference,
                ],
                'ds:Transforms' => [
                    'ds:Transform' => [
                        '@attributes' => [
                            'Algorithm' => 'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                        ],
                    ],
                ],
                'ds:DigestMethod' => [
                    '@attributes' => [
                        'ds:Algorithm' => 'http://www.w3.org/2000/09/xmldsig#sha1',
                    ],
                ],
                'ds:DigestValue' => $sha1_comprobante,
            ]
        ])->documentElement;

        $dig = $proveedorTag->getElementsByTagName('etsi:SignedProperties')->item(0);
        $digestProveedor = base64_encode(sha1($dig->nodeValue, true));

        $digProvTag = (new XML())->generate([
            'ds:Reference' => [
                '@attributes' => [
                    'Id' => 'SignedPropertiesID' . $SignedPropertiesID_number,
                    'Type' => 'http://uri.etsi.org/01903#SignedProperties',
                    'URI' => '#Signature' . $Signature_number . '-' . 'SignedProperties' . $SignedProperties_number,
                ],
                'ds:DigestMethod' => [
                    '@attributes' => [
                        'Algorithm' => self::SHA1,
                    ],
                ],
                'ds:DigestValue' => $digestProveedor,
            ],
        ])->documentElement;

        // crear nodo para la firma
        $SignatureTag = $doc->importNode((new XML())->generate([
            'Signature' => [
                '@attributes' => [
                    'xmlns:etsi' => self::XMLETSI,
                    'Id' => 'Signature' . $Signature_number,
                ],
                'SignedInfo' => [
                    '@attributes' => [
                        'xmlns:xsi' => self::XMLXSI,
                        'Id' => 'Signature-SignedInfo' . $SignedInfo_number,
                    ],
                    'CanonicalizationMethod' => [
                        '@attributes' => [
                            'Algorithm' => self::C14N,
                        ],
                    ],
                    'SignatureMethod' => [
                        '@attributes' => [
                            'Algorithm' => self::RSASHA1,
                        ],
                    ],
                ],
                'SignatureValue' => [
                    '@attributes' => [
                        'Id' => 'SignatureValue' . $SignatureValue_number,
                    ],
                ],
                'KeyInfo' => [
                    '@attributes' => [
                        'Id' => 'Certificate' . $Certificate_number,
                    ],
                    'X509Data' => [
                        'X509Certificate' => null,
                    ],
                    'KeyValue' => [
                        'RSAKeyValue' => [
                            'Modulus' => null,
                            'Exponent' => null,
                        ],
                    ],
                ],
            ],
        ], $namespace)->documentElement, true);
        
        $sinfo = $SignatureTag->getElementsByTagName('SignedInfo')->item(0);        

        $inscomp = $doc->importNode($compRefTag, true);
        $inscomp = $sinfo->appendChild($inscomp);

        $objectTag = $doc->createElement('ds:Object');
        $objectTag->setAttribute('Id', 'Signature' . $Signature_number . '-' . 'Object' . $Object_number);
        $objectTag = $SignatureTag->appendChild($objectTag);
        $insprv = $doc->importNode($proveedorTag, true);
        $insprv = $objectTag->appendChild($insprv);

        // reemplazar valores en la firma de        
        $SignatureTag->getElementsByTagName('Modulus')->item(0)->nodeValue = $this->getModulus();
        $SignatureTag->getElementsByTagName('Exponent')->item(0)->nodeValue = $this->getExponent();
        $SignatureTag->getElementsByTagName('X509Certificate')->item(0)->nodeValue = $this->getCertificate(true);

        // calcular SignatureValue
        /*$hsinfo = $doc->saveHTML($sinfo);
        $firmado = $this->sign($hsinfo);*/
        $hsinfo = $doc->saveHTML($sinfo);
        $firmado = $this->sign($hsinfo);
        if (!$firmado)
            return false;
        $firma = wordwrap($firmado, $this->config['wordwrap'], "\n", true);        
        $SignatureTag->getElementsByTagName('SignatureValue')->item(0)->nodeValue = $firma;
        
        $inscert = $doc->importNode($certRefTag, true);
        $inscert = $sinfo->appendChild($inscert);

        $insdigp = $doc->importNode($digProvTag, true);
        $insdigp = $sinfo->appendChild($insdigp);

        // agregar y entregar firma
        $doc->documentElement->appendChild($SignatureTag);
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
