<?php

namespace Pointerp\Rutas;

class NominaRutas extends \Phalcon\Mvc\Router\Group
{
  public function initialize()
  {
    $controlador = 'nomina';
    $this->setPaths(['namespace' => 'Pointerp\Controladores',]);
    $this->setPrefix('/api/v4/nomina');

    #region Tablas y Registros
    $this->addGet('/tablas/{tabla}/registros/sub/{sub}/emp/{emp}', [
      'controller' => $controlador,
      'action'     => 'registrosPorTabla',
    ]);
    $this->addGet('/tablas/{tabla}/sub/{sub}/emp/{emp}/registros', [
      'controller' => $controlador,
      'action'     => 'registrosPorTabla',
    ]);
    $this->addGet('/tablas/{tabla}/registro/indice/{indice}/sub/{sub}/emp/{emp}', [
      'controller' => $controlador,
      'action'     => 'registroPorTablaIndice',
    ]);
    $this->addGet('/tablas/registros/{id}', [
      'controller' => $controlador,
      'action'     => 'registroPorId',
    ]);
    #endregion

    #region Cargos
    $this->addGet('/cargos/sub/{sub}/emp/{emp}/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'cargosPorEstado',
    ]);
    $this->addGet('/cargos/{id}', [
      'controller' => $controlador,
      'action'     => 'cargoPorId',
    ]);
    $this->addPatch('/cargos/{id}/estado/{estado}/modificar', [
      'controller' => $controlador,
      'action'     => 'cargoModificarEstado',
    ]);
    $this->addPost('/cargos/guardar', [
      'controller' => $controlador,
      'action'     => 'cargoGuardar',
    ]);
    #endregion

    #region Rubros
    $this->addGet('/rubros/sub/{sub}/emp/{emp}/estado/{estado}', [
      'controller' => $controlador,
      'action'     => 'rubrosPorEstado',
    ]);
    $this->addPatch('/rubros/{id}/estado/{estado}/modificar', [
      'controller' => $controlador,
      'action'     => 'rubroModificarEstado',
    ]);
    $this->addPost('/rubros/guardar', [
      'controller' => $controlador,
      'action'     => 'rubroGuardar',
    ]);
    #endregion

    #region Empleados
    $this->addGet('/empleados/sub/{sub}/emp/{emp}/tipo/{tipo}/estado/{estado}/atributo/{atrib}/filtro/{filtro}/buscar', [
      'controller' => $controlador,
      'action'     => 'empleadosBuscar',
    ]);
    $this->addGet('/empleados/buscar/{filtro}', [
      'controller' => $controlador,
      'action'     => 'empleadosBuscarMin',
    ]);
    $this->addGet('/empleados/{id}', [
      'controller' => $controlador,
      'action'     => 'empleadoPorId',
    ]);
    $this->addGet('/empleados/{id}/sub/{sub}/emp/{emp}/ced/{cedula}/nom/{nombres}/registrado', [
      'controller' => $controlador,
      'action'     => 'empleadoRegistrado',
    ]);
    $this->addGet('/empleados/sub/{sub}/emp/{emp}/ced/{cedula}', [
      'controller' => $controlador,
      'action'     => 'empleadoPorCedula',
    ]);
    $this->addPatch('/empleados/{id}/estado/{est}/modificar', [
      'controller' => $controlador,
      'action'     => 'empleadoModificarEstado',
    ]);
    $this->addPost('/empleados/guardar', [
      'controller' => $controlador,
      'action'     => 'empleadoGuardar',
    ]);
    #endregion
    
    #region Movimientos
    $this->addGet('/movimientos/{id}', [
      'controller' => $controlador,
      'action'     => 'movimientoPorId',
    ]);
    $this->addGet('/movimientos/sub/{sub}/emp/{emp}/clase/{clase}/estado/{estado}/desde/{desde}/hasta/{hasta}/tipo/{tipo}/filtro/{filtro}/buscar', [
      'controller' => $controlador,
      'action'     => 'movimientosBuscar',
    ]);
    $this->addPatch('/movimientos/{id}/estado/{estado}/modificar', [
      'controller' => $controlador,
      'action'     => 'movimientoModificarEstado',
    ]);
    $this->addPost('/movimiento/guardar', [
      'controller' => $controlador,
      'action'     => 'movimientoGuardar',
    ]);
    #endregion

    #region Roles
    $this->addGet('/roles/sub/{sub}/emp/{emp}/clase/{clase}/estado/{estado}/desde/{desde}/hasta/{hasta}/tipo/{tipo}/filtro/{filtro}/buscar', [
      'controller' => $controlador,
      'action'     => 'rolesBuscar',
    ]);
    $this->addGet('/roles/sub/{sub}/emp/{emp}/mes/{mes}/anio/{anio}/rubros', [
      'controller' => $controlador,
      'action'     => 'rubrosPeriodo',
    ]);
    $this->addPatch('/roles/{id}/estado/{estado}/modificar', [
      'controller' => $controlador,
      'action'     => 'rolesModificarEstado',
    ]);
    $this->addPatch('/roles/{id}/liquidar', [
      'controller' => $controlador,
      'action'     => 'rolesLiquidar',
    ]);
    $this->addPost('/roles/guardar', [
      'controller' => $controlador,
      'action'     => 'rolesGuardar',
    ]);
    #endregion
  }
}