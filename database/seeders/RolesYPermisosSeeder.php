<?php

namespace Database\Seeders;

use App\Enums\RolSistema;
use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogo de módulos, permisos y los cuatro roles base con sus permisos.
 *
 * Es idempotente: se puede volver a ejecutar en producción para agregar módulos
 * o permisos nuevos (`php artisan db:seed --class=RolesYPermisosSeeder --force`).
 * Los permisos de los roles base solo se asignan cuando el rol se crea, para no
 * pisar los cambios hechos desde la pantalla "Roles y permisos"; el
 * Administrador siempre recibe todos.
 */
class RolesYPermisosSeeder extends Seeder
{
    /**
     * slug => [nombre, icono, ruta, grupo, acciones]
     *
     * @var array<string, array{0: string, 1: string, 2: string|null, 3: string, 4: list<string>}>
     */
    public const MODULOS = [
        'dashboard' => ['Inicio', 'fa-house', 'dashboard', 'principal', ['ver']],
        'bienes' => ['Bienes', 'fa-box-archive', 'bienes.index', 'principal', ['ver', 'crear', 'editar', 'eliminar', 'exportar', 'imprimir']],
        'mis_bienes' => ['Mis bienes', 'fa-box-open', 'mis-bienes.index', 'principal', ['ver']],
        'mis_solicitudes' => ['Mis solicitudes', 'fa-inbox', 'solicitudes.index', 'principal', ['ver', 'crear']],
        'aprobaciones' => ['Bandeja de aprobación', 'fa-inbox', 'aprobaciones.index', 'principal', ['ver']],
        'movimientos' => ['Movimientos', 'fa-right-left', 'movimientos.index', 'principal', ['ver', 'crear', 'aprobar', 'exportar']],
        'bajas' => ['Bajas', 'fa-ban', 'bajas.index', 'principal', ['ver', 'crear', 'aprobar', 'exportar']],
        'requerimientos' => ['Requerimientos', 'fa-clipboard-list', 'requerimientos.index', 'principal', ['ver', 'crear', 'eliminar', 'aprobar', 'exportar']],
        'mantenimientos' => ['Mantenimientos', 'fa-screwdriver-wrench', 'mantenimientos.index', 'principal', ['ver', 'crear', 'editar', 'exportar']],
        'documentos' => ['Documentos', 'fa-file-contract', 'documentos.index', 'principal', ['ver', 'crear', 'eliminar']],
        'toma_inventario' => ['Toma de inventario', 'fa-barcode', 'inventarios.index', 'principal', ['ver', 'crear', 'editar', 'aprobar', 'exportar']],
        'etiquetas' => ['Etiquetas', 'fa-tags', 'etiquetas.index', 'principal', ['ver', 'imprimir']],
        'reportes' => ['Reportes', 'fa-chart-pie', 'reportes.index', 'principal', ['ver', 'exportar']],
        'sedes' => ['Sedes', 'fa-building', 'sedes.index', 'configuracion', ['ver', 'crear', 'editar', 'eliminar']],
        'areas' => ['Áreas', 'fa-sitemap', 'areas.index', 'configuracion', ['ver', 'crear', 'editar', 'eliminar']],
        'categorias' => ['Categorías', 'fa-layer-group', 'categorias.index', 'configuracion', ['ver', 'crear', 'editar', 'eliminar']],
        'responsables' => ['Responsables', 'fa-id-card', 'responsables.index', 'configuracion', ['ver', 'crear', 'editar', 'eliminar']],
        'usuarios' => ['Usuarios', 'fa-users', 'usuarios.index', 'configuracion', ['ver', 'crear', 'editar', 'eliminar']],
        'roles' => ['Roles y permisos', 'fa-user-shield', 'roles.index', 'configuracion', ['ver', 'crear', 'editar', 'eliminar']],
        'configuracion' => ['Configuración', 'fa-gear', 'configuracion.index', 'configuracion', ['ver', 'editar']],
        'auditoria' => ['Auditoría', 'fa-clock-rotate-left', 'auditoria.index', 'configuracion', ['ver', 'exportar']],
    ];

    public function run(): void
    {
        $this->sembrarModulosYPermisos();

        foreach (RolSistema::cases() as $rolSistema) {
            $rol = Role::firstOrNew(['nombre' => $rolSistema->value]);
            $esNuevo = ! $rol->exists;

            $rol->fill([
                'descripcion' => $rolSistema->descripcion(),
                'es_sistema' => true,
                'estado' => $rol->estado ?? 'activo',
            ])->save();

            if ($esNuevo || $rolSistema === RolSistema::Administrador) {
                $rol->sincronizarPermisos($this->permisosDe($rolSistema));
            }
        }

        Cache::memo()->forget('menu:modulos');
    }

    private function sembrarModulosYPermisos(): void
    {
        $orden = 0;

        foreach (self::MODULOS as $slug => [$nombre, $icono, $ruta, $grupo, $acciones]) {
            $modulo = Modulo::updateOrCreate(
                ['slug' => $slug],
                ['nombre' => $nombre, 'icono' => $icono, 'ruta' => $ruta, 'grupo' => $grupo, 'orden' => $orden += 10],
            );

            foreach ($acciones as $accion) {
                Permiso::updateOrCreate(
                    ['slug' => "{$slug}.{$accion}"],
                    ['modulo_id' => $modulo->id, 'accion' => $accion],
                );
            }
        }
    }

    /**
     * @return list<int>
     */
    private function permisosDe(RolSistema $rol): array
    {
        $slugs = match ($rol) {
            RolSistema::Administrador => Permiso::pluck('slug')->all(),
            RolSistema::Operador => [
                'dashboard.ver',
                'bienes.ver', 'bienes.crear', 'bienes.editar', 'bienes.exportar', 'bienes.imprimir',
                'etiquetas.ver', 'etiquetas.imprimir',
                'toma_inventario.ver', 'toma_inventario.crear', 'toma_inventario.editar', 'toma_inventario.exportar',
                'movimientos.ver', 'movimientos.crear',
                'bajas.ver', 'bajas.crear',
                'requerimientos.ver', 'requerimientos.crear',
                'mantenimientos.ver', 'mantenimientos.crear',
                'reportes.ver',
            ],
            RolSistema::Auditor => Permiso::whereIn('accion', ['ver', 'exportar'])
                ->whereHas('modulo', fn ($q) => $q->whereNotIn('slug', ['usuarios', 'roles', 'configuracion', 'etiquetas', 'mis_bienes', 'mis_solicitudes', 'aprobaciones']))
                ->pluck('slug')
                ->all(),
            RolSistema::Usuario => ['dashboard.ver', 'mis_bienes.ver', 'mis_solicitudes.ver', 'mis_solicitudes.crear'],
        };

        return array_values(Permiso::whereIn('slug', $slugs)->get()->map(fn (Permiso $permiso) => $permiso->id)->all());
    }
}
