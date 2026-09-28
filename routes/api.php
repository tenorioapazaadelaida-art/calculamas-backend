<?php

use App\Http\Controllers\Api\AutenticacionController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\CompraController;
use App\Http\Controllers\Api\GastoController;
use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\ImpuestoController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\PermisoController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\TipoUtilidadController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\VentaController;
use App\Http\Controllers\Api\NotificacionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AutenticacionController::class, 'register'])
        ->middleware('throttle:5,1');
    Route::post('login', [AutenticacionController::class, 'login'])
        ->middleware('throttle:10,1');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auditorias', [AuditoriaController::class, 'index'])
        ->middleware('permission:configuracion.ver');
    Route::get('auth/me', [AutenticacionController::class, 'me']);
    Route::post('auth/logout', [AutenticacionController::class, 'logout']);
    Route::get('notificaciones', [NotificacionController::class, 'index']);
    Route::patch('notificaciones/leer-todas', [NotificacionController::class, 'leerTodas']);
    Route::patch('notificaciones/{id}/leer', [NotificacionController::class, 'leer']);

    Route::get('permisos', [PermisoController::class, 'index'])
        ->middleware('permission:roles.ver');
    Route::get('roles', [RolController::class, 'index'])
        ->middleware('permission:roles.ver');
    Route::post('roles', [RolController::class, 'store'])
        ->middleware('permission:roles.crear');
    Route::put('roles/{id}', [RolController::class, 'update'])
        ->middleware('permission:roles.editar');
    Route::put(
        'roles/{id}/permisos',
        [RolController::class, 'asignarPermisos']
    )->middleware('permission:roles.asignar_permisos');

    Route::get('usuarios', [UsuarioController::class, 'index'])
        ->middleware('permission:usuarios.ver');
    Route::post('usuarios', [UsuarioController::class, 'store'])
        ->middleware('permission:usuarios.crear');
    Route::put('usuarios/{id}', [UsuarioController::class, 'update'])
        ->middleware('permission:usuarios.editar');
    Route::delete('usuarios/{id}', [UsuarioController::class, 'destroy'])
        ->middleware('permission:usuarios.desactivar');
    Route::get('usuarios/{id}/permisos', [UsuarioController::class, 'permisos'])
        ->middleware('permission:roles.ver');
    Route::put(
        'usuarios/{id}/permisos',
        [UsuarioController::class, 'actualizarPermisos']
    )->middleware('permission:roles.asignar_permisos');

    Route::get('categorias', [CategoriaController::class, 'index'])
        ->middleware('permission:categorias.ver');
    Route::post('categorias', [CategoriaController::class, 'store'])
        ->middleware('permission:categorias.crear');
    Route::put('categorias/{id}', [CategoriaController::class, 'update'])
        ->middleware('permission:categorias.editar');
    Route::patch(
        'categorias/{id}/estado',
        [CategoriaController::class, 'cambiarEstado']
    )->middleware('permission:categorias.desactivar');
    Route::get('productos', [ProductoController::class, 'index'])
        ->middleware('permission:productos.ver');
    Route::post('productos', [ProductoController::class, 'store'])
        ->middleware('permission:productos.crear');
    Route::put('productos/{id}', [ProductoController::class, 'update'])
        ->middleware('permission:productos.editar');
    Route::delete('productos/{id}', [ProductoController::class, 'destroy'])
        ->middleware('permission:productos.desactivar');
    Route::get('compras', [CompraController::class, 'index'])
        ->middleware('permission:compras.ver');
    Route::get('compras/{id}', [CompraController::class, 'show'])
        ->middleware('permission:compras.ver');
    Route::get('compras/{id}/comprobante-pdf', [CompraController::class, 'comprobantePdf'])
        ->middleware('permission:compras.ver');
    Route::post('compras', [CompraController::class, 'store'])
        ->middleware('permission:compras.crear');
    Route::put('compras/{id}', [CompraController::class, 'update'])
        ->middleware('permission:compras.editar');
    Route::delete('compras/{id}', [CompraController::class, 'destroy'])
        ->middleware('permission:compras.anular');
    Route::get('compras/{id}/devoluciones', [CompraController::class, 'devoluciones'])
        ->middleware('permission:compras.ver');
    Route::post('compras/{id}/devoluciones', [CompraController::class, 'devolver'])
        ->middleware('permission:compras.devolver');
    Route::get('compras/{id}/devoluciones/{devolucion}/comprobante-pdf', [CompraController::class, 'comprobanteDevolucionPdf'])
        ->middleware('permission:compras.ver');
    Route::get('ventas', [VentaController::class, 'index'])
        ->middleware('permission:ventas.ver');
    Route::get(
        'tipos-utilidad',
        [TipoUtilidadController::class, 'index']
    )->middleware('permission:utilidades.ver');
    Route::get(
        'tipos-utilidad-opciones',
        [TipoUtilidadController::class, 'opciones']
    )->middleware('permission:ventas.crear');
    Route::get(
        'tipos-utilidad-compras',
        [TipoUtilidadController::class, 'opcionesCompra']
    )->middleware('permission:utilidades.ver_en_compras');
    Route::post(
        'tipos-utilidad',
        [TipoUtilidadController::class, 'store']
    )->middleware('permission:utilidades.crear');
    Route::put(
        'tipos-utilidad/{id}',
        [TipoUtilidadController::class, 'update']
    )->middleware('permission:utilidades.editar');
    Route::patch(
        'tipos-utilidad/{id}/estado',
        [TipoUtilidadController::class, 'estado']
    )->middleware('permission:utilidades.desactivar');
    Route::get('ventas/{id}', [VentaController::class, 'show'])
        ->middleware('permission:ventas.ver');
    Route::get('ventas/{id}/comprobante-pdf', [VentaController::class, 'comprobantePdf'])
        ->middleware('permission:ventas.ver');
    Route::post('ventas', [VentaController::class, 'store'])
        ->middleware('permission:ventas.crear');
    Route::put('ventas/{id}', [VentaController::class, 'update'])
        ->middleware('permission:ventas.editar');
    Route::delete('ventas/{id}', [VentaController::class, 'destroy'])
        ->middleware('permission:ventas.anular');
    Route::get('ventas/{id}/devoluciones', [VentaController::class, 'devoluciones'])
        ->middleware('permission:ventas.ver');
    Route::post('ventas/{id}/devoluciones', [VentaController::class, 'devolver'])
        ->middleware('permission:ventas.devolver');
    Route::get('ventas/{id}/devoluciones/{devolucion}/comprobante-pdf', [VentaController::class, 'comprobanteDevolucionPdf'])
        ->middleware('permission:ventas.ver');
    Route::get('inventario', [InventarioController::class, 'index'])
        ->middleware('permission:inventario.ver');
    Route::get('kardex', [InventarioController::class, 'kardexGeneral'])
        ->middleware('permission:kardex.ver');
    Route::get('kardex/pdf', [InventarioController::class, 'kardexPdf'])
        ->middleware('permission:kardex.ver');
    Route::get('kardex/{producto}', [InventarioController::class, 'kardex'])
        ->middleware('permission:kardex.ver');
    Route::get(
        'categorias-gastos',
        [GastoController::class, 'categorias']
    )->middleware('permission:categorias_gastos.ver');
    Route::post(
        'categorias-gastos',
        [GastoController::class, 'guardarCategoria']
    )->middleware('permission:categorias_gastos.crear');
    Route::put(
        'categorias-gastos/{id}',
        [GastoController::class, 'actualizarCategoria']
    )->middleware('permission:categorias_gastos.editar');
    Route::delete(
        'categorias-gastos/{id}',
        [GastoController::class, 'cambiarEstadoCategoria']
    )->middleware('permission:categorias_gastos.desactivar');
    Route::get('gastos', [GastoController::class, 'index'])
        ->middleware('permission:gastos.ver');
    Route::post('gastos', [GastoController::class, 'store'])
        ->middleware('permission:gastos.crear');
    Route::get('gastos/{id}', [GastoController::class, 'show'])
        ->middleware('permission:gastos.ver');
    Route::put('gastos/{id}', [GastoController::class, 'update'])
        ->middleware('permission:gastos.editar');
    Route::delete('gastos/{id}', [GastoController::class, 'destroy'])
        ->middleware('permission:gastos.anular');
    Route::get(
        'reportes/utilidad',
        [ReporteController::class, 'utilidad']
    )->middleware('permission:reportes.ver');
    Route::get(
        'dashboard',
        [ReporteController::class, 'dashboard']
    )->middleware('permission:dashboard.ver');
    Route::get(
        'impuestos/resumen',
        [ImpuestoController::class, 'resumen']
    )->middleware('permission:impuestos.ver');
    Route::get(
        'impuestos/productos',
        [ImpuestoController::class, 'productos']
    )->middleware('permission:impuestos.ver');
    Route::get(
        'impuestos/configuracion',
        [ImpuestoController::class, 'configuracion']
    )->middleware('permission:impuestos.ver');
    Route::put(
        'impuestos/configuracion',
        [ImpuestoController::class, 'guardarConfiguracion']
    )->middleware('permission:impuestos.configurar');
    Route::post(
        'impuestos/cerrar',
        [ImpuestoController::class, 'cerrar']
    )->middleware('permission:impuestos.configurar');
    Route::get('impuestos/iue', [ImpuestoController::class, 'iue'])
        ->middleware('permission:impuestos.ver');
});
