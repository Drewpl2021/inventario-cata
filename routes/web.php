<?php

use App\Http\Controllers\AprobacionController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BajaController;
use App\Http\Controllers\BienController;
use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MantenimientoController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\RecuperarPasswordController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RequerimientoController;
use App\Http\Controllers\ResponsableController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::get('/', [HomeController::class, 'index'])->name('home');

// Auth Routes (el registro público se eliminó: solo el Administrador crea cuentas)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Recuperación de contraseña por correo (solo sin sesión)
Route::middleware('guest')->group(function () {
    Route::get('/recuperar-password', [RecuperarPasswordController::class, 'create'])->name('password.request');
    Route::post('/recuperar-password', [RecuperarPasswordController::class, 'store'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/restablecer-password/{token}', [RecuperarPasswordController::class, 'edit'])->name('password.reset');
    Route::post('/restablecer-password', [RecuperarPasswordController::class, 'update'])->name('password.update')->middleware('throttle:5,1');
});

// Protected Routes (Require Login). Cada ruta exige su permiso "modulo.accion".
Route::middleware('auth')->group(function () {
    Route::get('/cambiar-password', [PasswordController::class, 'edit'])->name('password.cambiar');
    Route::put('/cambiar-password', [PasswordController::class, 'update'])->name('password.cambiar.update');
    Route::post('/sesion/renovar', [AuthController::class, 'renovarSesion'])->name('sesion.renovar');

    // Comunes: buscador global, selector de bienes y notificaciones
    Route::get('/buscar', [BusquedaController::class, 'global'])->name('buscar')->middleware('permiso:bienes.ver,mis_bienes.ver');
    Route::get('/buscar/bienes-solicitables', [BusquedaController::class, 'bienesSolicitables'])->name('buscar.bienes-solicitables')
        ->middleware('permiso:bajas.crear,movimientos.crear,mis_solicitudes.crear');
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::patch('/notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leer-todas');
    Route::patch('/notificaciones/{id}/leer', [NotificacionController::class, 'leer'])->name('notificaciones.leer');

    // Archivos subidos (disco privado): cada método revisa la Policy del registro
    Route::prefix('archivos')->name('archivos.')->group(function () {
        Route::get('bienes/{bien}/foto', [ArchivoController::class, 'fotoBien'])->name('bienes.foto')->middleware('permiso:bienes.ver,mis_bienes.ver');
        Route::get('bienes/{bien}/comprobante', [ArchivoController::class, 'comprobanteBien'])->name('bienes.comprobante')->middleware('permiso:bienes.ver,mis_bienes.ver');
        Route::get('documentos/{documento}', [ArchivoController::class, 'documento'])->name('documentos')->middleware('permiso:documentos.ver,bienes.ver,mis_bienes.ver');
        Route::get('bajas/{baja}/foto', [ArchivoController::class, 'fotoBaja'])->name('bajas.foto')->middleware('permiso:bajas.ver,bajas.aprobar,bajas.crear,mis_solicitudes.crear');
        Route::get('requerimientos/{requerimiento}/adjunto', [ArchivoController::class, 'adjuntoRequerimiento'])->name('requerimientos.adjunto')->middleware('permiso:requerimientos.ver,requerimientos.crear,mis_solicitudes.crear');
    });

    // Solicitudes del responsable y bandeja de aprobación
    Route::get('/mis-solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index')->middleware('permiso:mis_solicitudes.ver');
    Route::get('/aprobaciones', [AprobacionController::class, 'index'])->name('aprobaciones.index')->middleware('permiso:aprobaciones.ver');

    // Reportes: vista previa con "ver"; PDF y Excel con "exportar"
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index')->middleware('permiso:reportes.ver');
    Route::get('/reportes/descargas/{archivo}', [ReporteController::class, 'descargar'])->name('reportes.descargar')->middleware('permiso:reportes.exportar');
    Route::get('/reportes/{clave}', [ReporteController::class, 'show'])->name('reportes.show')->middleware('permiso:reportes.ver');
    Route::get('/reportes/{clave}/exportar/{formato}', [ReporteController::class, 'exportar'])->name('reportes.exportar')->middleware('permiso:reportes.exportar');
    Route::get('/mis-bienes/acta', [ReporteController::class, 'actaPropia'])->name('mis-bienes.acta')->middleware('permiso:mis_bienes.ver');

    // Módulo 2: Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('permiso:dashboard.ver');

    // Módulo 3: Bienes (el detalle lo autoriza BienPolicy: todos o solo los propios)
    Route::get('/mis-bienes', [BienController::class, 'misBienes'])->name('mis-bienes.index')->middleware('permiso:mis_bienes.ver');
    Route::get('bienes', [BienController::class, 'index'])->name('bienes.index')->middleware('permiso:bienes.ver');
    Route::get('bienes/create', [BienController::class, 'create'])->name('bienes.create')->middleware('permiso:bienes.crear');
    Route::post('bienes', [BienController::class, 'store'])->name('bienes.store')->middleware('permiso:bienes.crear');
    Route::get('bienes/{biene}', [BienController::class, 'show'])->name('bienes.show')->middleware('permiso:bienes.ver,mis_bienes.ver');
    Route::get('bienes/{biene}/edit', [BienController::class, 'edit'])->name('bienes.edit')->middleware('permiso:bienes.editar');
    Route::match(['put', 'patch'], 'bienes/{biene}', [BienController::class, 'update'])->name('bienes.update')->middleware('permiso:bienes.editar');
    Route::delete('bienes/{biene}', [BienController::class, 'destroy'])->name('bienes.destroy')->middleware('permiso:bienes.eliminar');
    Route::get('bienes/{biene}/codigo-barras.svg', [EtiquetaController::class, 'codigoBarrasSvg'])->name('bienes.codigo-barras')->middleware('permiso:bienes.ver,mis_bienes.ver');

    // Etiquetas con código de barras (individual, por lote y página de prueba)
    Route::get('bienes/{biene}/etiqueta/{formato}', [EtiquetaController::class, 'bien'])->name('bienes.etiqueta')->middleware('permiso:etiquetas.imprimir');
    Route::get('etiquetas', [EtiquetaController::class, 'index'])->name('etiquetas.index')->middleware('permiso:etiquetas.ver');
    Route::post('etiquetas/generar', [EtiquetaController::class, 'generar'])->name('etiquetas.generar')->middleware('permiso:etiquetas.imprimir');
    Route::get('etiquetas/prueba/{formato}', [EtiquetaController::class, 'prueba'])->name('etiquetas.prueba')->middleware('permiso:etiquetas.imprimir,configuracion.ver');

    // Toma de inventario por escaneo (las Policies controlan responsables y estado de la campaña)
    Route::middleware('permiso:toma_inventario.ver')->group(function () {
        Route::get('inventarios', [InventarioController::class, 'index'])->name('inventarios.index');
        Route::get('inventarios/create', [InventarioController::class, 'create'])->name('inventarios.create')->middleware('permiso:toma_inventario.crear');
        Route::post('inventarios', [InventarioController::class, 'store'])->name('inventarios.store')->middleware('permiso:toma_inventario.crear');
        Route::get('inventarios/{inventario}', [InventarioController::class, 'show'])->name('inventarios.show');
        Route::get('inventarios/{inventario}/estado', [InventarioController::class, 'estado'])->name('inventarios.estado');
        Route::get('inventarios/{inventario}/escanear', [InventarioController::class, 'escanear'])->name('inventarios.escanear')->middleware('permiso:toma_inventario.editar');
        Route::get('inventarios/{inventario}/catalogo', [InventarioController::class, 'catalogo'])->name('inventarios.catalogo')->middleware('permiso:toma_inventario.editar');
        Route::post('inventarios/{inventario}/lecturas', [InventarioController::class, 'lectura'])->name('inventarios.lecturas')->middleware('permiso:toma_inventario.editar');
        Route::post('inventarios/{inventario}/sincronizar', [InventarioController::class, 'sincronizar'])->name('inventarios.sincronizar')->middleware('permiso:toma_inventario.editar');
        Route::delete('inventarios/{inventario}/lecturas/{detalle}', [InventarioController::class, 'deshacer'])->name('inventarios.lecturas.deshacer')->scopeBindings()->middleware('permiso:toma_inventario.editar');
        Route::patch('inventarios/{inventario}/lecturas/{detalle}/estado-fisico', [InventarioController::class, 'estadoFisico'])->name('inventarios.lecturas.estado-fisico')->scopeBindings()->middleware('permiso:toma_inventario.editar');
        Route::patch('inventarios/{inventario}/cerrar', [InventarioController::class, 'cerrar'])->name('inventarios.cerrar')->middleware('permiso:toma_inventario.editar');
        Route::patch('inventarios/{inventario}/reabrir', [InventarioController::class, 'reabrir'])->name('inventarios.reabrir')->middleware('permiso:toma_inventario.aprobar');
        Route::get('inventarios/{inventario}/exportar/{formato}', [InventarioController::class, 'exportar'])->name('inventarios.exportar')->middleware('permiso:toma_inventario.exportar');
    });

    // Configuración del sistema
    Route::get('configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index')->middleware('permiso:configuracion.ver');
    Route::get('configuracion/etiquetas', [ConfiguracionController::class, 'etiquetas'])->name('configuracion.etiquetas')->middleware('permiso:configuracion.ver');
    Route::get('configuracion/etiquetas/vista-previa', [ConfiguracionController::class, 'vistaPrevia'])->name('configuracion.etiquetas.vista-previa')->middleware('permiso:configuracion.ver');
    Route::put('configuracion/etiquetas', [ConfiguracionController::class, 'guardarEtiquetas'])->name('configuracion.etiquetas.guardar')->middleware('permiso:configuracion.editar');

    // Módulo 4: Requerimientos
    Route::get('requerimientos', [RequerimientoController::class, 'index'])->name('requerimientos.index')->middleware('permiso:requerimientos.ver');
    Route::get('requerimientos/create', [RequerimientoController::class, 'create'])->name('requerimientos.create')->middleware('permiso:requerimientos.crear,mis_solicitudes.crear');
    Route::post('requerimientos', [RequerimientoController::class, 'store'])->name('requerimientos.store')->middleware('permiso:requerimientos.crear,mis_solicitudes.crear');
    Route::get('requerimientos/{requerimiento}', [RequerimientoController::class, 'show'])->name('requerimientos.show')->middleware('permiso:requerimientos.ver,requerimientos.crear,mis_solicitudes.crear');
    Route::delete('requerimientos/{requerimiento}', [RequerimientoController::class, 'destroy'])->name('requerimientos.destroy')->middleware('permiso:requerimientos.eliminar,requerimientos.crear,mis_solicitudes.crear');
    Route::patch('requerimientos/{requerimiento}/estado', [RequerimientoController::class, 'updateEstado'])->name('requerimientos.estado')->middleware('permiso:requerimientos.aprobar');

    // Módulo 5: Movimientos y Traslados (solicitud → aprobación)
    Route::get('movimientos', [MovimientoController::class, 'index'])->name('movimientos.index')->middleware('permiso:movimientos.ver');
    Route::get('movimientos/create', [MovimientoController::class, 'create'])->name('movimientos.create')->middleware('permiso:movimientos.crear,mis_solicitudes.crear');
    Route::post('movimientos', [MovimientoController::class, 'store'])->name('movimientos.store')->middleware('permiso:movimientos.crear,mis_solicitudes.crear');
    Route::middleware('permiso:movimientos.aprobar')->group(function () {
        Route::patch('movimientos/{movimiento}/aprobar', [MovimientoController::class, 'aprobar'])->name('movimientos.aprobar');
        Route::patch('movimientos/{movimiento}/rechazar', [MovimientoController::class, 'rechazar'])->name('movimientos.rechazar');
        Route::patch('movimientos/{movimiento}/ejecutar', [MovimientoController::class, 'ejecutar'])->name('movimientos.ejecutar');
    });

    // Módulo 6: Mantenimientos (Solicitudes / Gestión con permiso de edición)
    Route::get('mantenimientos', [MantenimientoController::class, 'index'])->name('mantenimientos.index')->middleware('permiso:mantenimientos.ver');
    Route::get('mantenimientos/create', [MantenimientoController::class, 'create'])->name('mantenimientos.create')->middleware('permiso:mantenimientos.crear');
    Route::post('mantenimientos', [MantenimientoController::class, 'store'])->name('mantenimientos.store')->middleware('permiso:mantenimientos.crear');
    Route::match(['put', 'patch'], 'mantenimientos/{mantenimiento}', [MantenimientoController::class, 'update'])->name('mantenimientos.update')->middleware('permiso:mantenimientos.editar');

    // Módulo 7: Bajas (solicitud → aprobación)
    Route::get('bajas', [BajaController::class, 'index'])->name('bajas.index')->middleware('permiso:bajas.ver');
    Route::get('bajas/create', [BajaController::class, 'create'])->name('bajas.create')->middleware('permiso:bajas.crear,mis_solicitudes.crear');
    Route::post('bajas', [BajaController::class, 'store'])->name('bajas.store')->middleware('permiso:bajas.crear,mis_solicitudes.crear');
    Route::patch('bajas/{baja}/aprobar', [BajaController::class, 'aprobar'])->name('bajas.aprobar')->middleware('permiso:bajas.aprobar');
    Route::patch('bajas/{baja}/rechazar', [BajaController::class, 'rechazar'])->name('bajas.rechazar')->middleware('permiso:bajas.aprobar');

    // Módulo 8: Archivo Documentario
    Route::get('documentos', [DocumentoController::class, 'index'])->name('documentos.index')->middleware('permiso:documentos.ver');
    Route::post('documentos', [DocumentoController::class, 'store'])->name('documentos.store')->middleware('permiso:documentos.crear');
    Route::delete('documentos/{documento}', [DocumentoController::class, 'destroy'])->name('documentos.destroy')->middleware('permiso:documentos.eliminar');

    // Módulo 9: Organización y Catálogos
    foreach ([
        'sedes' => SedeController::class,
        'areas' => AreaController::class,
        'categorias' => CategoriaController::class,
        'responsables' => ResponsableController::class,
    ] as $modulo => $controlador) {
        Route::get($modulo, [$controlador, 'index'])->name("{$modulo}.index")->middleware("permiso:{$modulo}.ver");
        Route::post($modulo, [$controlador, 'store'])->name("{$modulo}.store")->middleware("permiso:{$modulo}.crear");
        Route::resource($modulo, $controlador)->only(['update'])->middleware("permiso:{$modulo}.editar");
        Route::resource($modulo, $controlador)->only(['destroy'])->middleware("permiso:{$modulo}.eliminar");
    }

    // Seguridad: usuarios, roles y auditoría
    Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index')->middleware('permiso:usuarios.ver');
    Route::get('usuarios/create', [UsuarioController::class, 'create'])->name('usuarios.create')->middleware('permiso:usuarios.crear');
    Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store')->middleware('permiso:usuarios.crear');
    Route::middleware('permiso:usuarios.editar')->group(function () {
        Route::get('usuarios/{usuario}/edit', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::match(['put', 'patch'], 'usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->name('usuarios.estado');
        Route::patch('usuarios/{usuario}/restablecer-password', [UsuarioController::class, 'restablecerPassword'])->name('usuarios.password');
    });
    Route::delete('usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy')->middleware('permiso:usuarios.eliminar');

    Route::get('roles', [RolController::class, 'index'])->name('roles.index')->middleware('permiso:roles.ver');
    Route::get('roles/create', [RolController::class, 'create'])->name('roles.create')->middleware('permiso:roles.crear');
    Route::post('roles', [RolController::class, 'store'])->name('roles.store')->middleware('permiso:roles.crear');
    Route::middleware('permiso:roles.editar')->group(function () {
        Route::get('roles/{rol}/edit', [RolController::class, 'edit'])->name('roles.edit');
        Route::match(['put', 'patch'], 'roles/{rol}', [RolController::class, 'update'])->name('roles.update');
        Route::patch('roles/{rol}/estado', [RolController::class, 'cambiarEstado'])->name('roles.estado');
    });
    Route::delete('roles/{rol}', [RolController::class, 'destroy'])->name('roles.destroy')->middleware('permiso:roles.eliminar');

    Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index')->middleware('permiso:auditoria.ver');
});
