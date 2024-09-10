<?php

namespace Pointerp\Modelos\Ventas;

class InfoTributaria {
    
    public $ambiente;
    public $tipoEmision;
    public $razonSocial;
    public $nombreComercial;
    public $ruc;
    public $claveAcceso;
    public $codDoc;
    public $estab;
    public $ptoEmi;
    public $secuencial;
    public $dirMatriz;
    public $contribuyenteRimpe;
    
    public function __construct($datos) {
        // Asignar los valores del arreglo a las propiedades
        foreach ($datos as $clave => $valor) {
            $this->{$clave} = $valor;
        }
    }
	
	public function generarXML($xml) {
		$infoTributariaXML = $xml->addChild('infoTributaria');
			
        $infoTributariaXML->addChild('ambiente', $ambiente);
		$infoTributariaXML->addChild('tipoEmision', $tipoEmision);
		$infoTributariaXML->addChild('razonSocial', $razonSocial);
		$infoTributariaXML->addChild('nombreComercial', $nombreComercial);
		$infoTributariaXML->addChild('ruc', $ruc);
		$infoTributariaXML->addChild('claveAcceso', $claveAcceso);
		$infoTributariaXML->addChild('codDoc', $codDoc);
		$infoTributariaXML->addChild('estab', $estab);
		$infoTributariaXML->addChild('ptoEmi', $ptoEmi);
		$infoTributariaXML->addChild('secuencial', $secuencial);
		$infoTributariaXML->addChild('dirMatriz', $dirMatriz);
		$infoTributariaXML->addChild('contribuyenteRimpe', $contribuyenteRimpe);
	}
}

class InfoFactura {
    // Propiedades de la clase
    public $fechaEmision;
    public $dirEstablecimiento;
    public $tipoIdentificacionComprador;
    public $razonSocialComprador;
    public $identificacionComprador;
    public $totalSinImpuestos;
    public $totalDescuento;
    public $totalConImpuestos = [];

    // Constructor para inicializar las propiedades
    public function __construct($datos) {
        // Asignar los valores del arreglo a las propiedades
        foreach ($datos as $clave => $valor) {
            if ($clave === 'totalConImpuestos') {
                // Si es un array anidado, crea un nuevo objeto InfoImpuesto y agrega a la propiedad totalConImpuestos
                $this->{$clave} = new InfoImpuesto($valor);
            } else {
                $this->{$clave} = $valor;
            }
        }
    }
	
	public function generarXML($xml) {
		$infoTributariaXML = $xml->addChild('infoTributaria');
			
        $infoTributariaXML->addChild('fechaEmision', $fechaEmision);
		$infoTributariaXML->addChild('dirEstablecimiento', $dirEstablecimiento);
		$infoTributariaXML->addChild('tipoIdentificacionComprador', $tipoIdentificacionComprador);
		$infoTributariaXML->addChild('razonSocialComprador', $razonSocialComprador);
		$infoTributariaXML->addChild('identificacionComprador', $identificacionComprador);
		$infoTributariaXML->addChild('totalSinImpuestos', $totalSinImpuestos);
		$infoTributariaXML->addChild('totalDescuento', $totalDescuento);
		
		$infoTributariaXML->addChild('totalConImpuestos', $totalConImpuestos);
	}
}

class InfoImpuesto {
    // Propiedades de la clase
    public $totalImpuesto;

    // Constructor para inicializar las propiedades
    public function __construct($datos) {
        $this->totalImpuesto = new TotalImpuesto($datos['totalImpuesto']);
    }
}

class TotalImpuesto {
    // Propiedades de la clase
    public $codigo;
    public $codigoPorcentaje;
    public $baseImponible;
    public $tarifa;
    public $valor;

    // Constructor para inicializar las propiedades
    public function __construct($datos) {
        foreach ($datos as $clave => $valor) {
            $this->{$clave} = $valor;
        }
    }
}

class DetalleFactura {
    // Propiedades de la clase
    public $detalles = [];

    // Constructor para inicializar las propiedades
    public function __construct($datos) {
        // Recorre cada elemento del arreglo $datos
        foreach ($datos as $detalle) {
            // Crea un nuevo objeto Detalle y agrega a la propiedad detalles
            $this->detalles[] = new Detalle($detalle);
        }
    }
}

class Detalle {
    // Propiedades de la clase
    public $codigoPrincipal;
    public $descripcion;
    public $cantidad;
    public $precioUnitario;
    public $descuento;
    public $precioTotalSinImpuesto;
    public $impuestos;

    // Constructor para inicializar las propiedades
    public function __construct($datos) {
        // Asigna los valores del arreglo a las propiedades
        foreach ($datos as $clave => $valor) {
            if ($clave === 'impuestos') {
                // Si es un array anidado, crea un nuevo objeto Impuesto y asigna a la propiedad impuestos
                $this->{$clave} = new Impuesto($valor['impuesto']);
            } else {
                $this->{$clave} = $valor;
            }
        }
    }
}

class Impuesto {
    // Propiedades de la clase
    public $codigo;
    public $codigoPorcentaje;
    public $tarifa;
    public $baseImponible;
    public $valor;

    // Constructor para inicializar las propiedades
    public function __construct($datos) {
        // Asigna los valores del arreglo a las propiedades
        foreach ($datos as $clave => $valor) {
            $this->{$clave} = $valor;
        }
    }
}

class Factura {
	private $xml;
	
	public function __construct() {
        // Crear un nuevo objeto SimpleXMLElement
        $this->xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><factura></factura>');

        // Agregar atributos
        $this->xml->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $this->xml->addAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $this->xml->addAttribute('version', '1.0.0');
        $this->xml->addAttribute('id', 'comprobante');
    }
	
    public function agregarInfoTributaria(InfoTributaria $infoTributaria) {
        $infoTributaria->generarXML($this->xml);
    }

    public function agregarInfoFactura(InfoFactura $infoFactura) {
        $infoFactura->generarXML($this->xml);
    }

	public function agregarInfoAdicional($infoAdicional) {
        $infoAdicionalXML = $this->xml->addChild('infoAdicional');

        foreach ($infoAdicional as $nombre => $valor) {
            $campoAdicionalXML = $infoAdicionalXML->addChild('campoAdicional', $valor);
            $campoAdicionalXML->addAttribute('nombre', $nombre);
        }
    }
}