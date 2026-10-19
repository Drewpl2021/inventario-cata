<?php

namespace App\Support;

use App\Models\Baja;
use App\Models\Modulo;
use App\Models\Movimiento;
use App\Models\Requerimiento;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LogicException;

/**
 * Arma el menú lateral a partir de la tabla `modulos` y los permisos del usuario.
 *
 * - Grupo "principal": enlaces directos.
 * - Grupo "configuracion": se agrupan en el desplegable "Configuración".
 * - "Mis bienes" y "Mis solicitudes" solo aparecen si la cuenta tiene un
 *   responsable vinculado; "Mis solicitudes" es el desplegable "Solicitudes".
 * - La bandeja de aprobación muestra cuántas solicitudes esperan al usuario.
 */
final class MenuLateral
{
    /** Nombre del módulo dentro del desplegable, cuando difiere del nombre del módulo. */
    private const NOMBRES_EN_SUBMENU = ['configuracion' => 'Formato de etiquetas'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function para(?Usuario $usuario): array
    {
        if (! $usuario) {
            return [];
        }

        $tieneResponsable = $usuario->responsable()->exists();
        $modulos = self::modulos()->filter(fn (Modulo $m) => $m->ruta
            && Route::has($m->ruta)
            && $usuario->can("{$m->slug}.ver")
            && (! in_array($m->slug, ['mis_bienes', 'mis_solicitudes'], true) || $tieneResponsable));

        $menu = [];
        $configuracion = [];

        foreach ($modulos as $modulo) {
            if ($modulo->grupo === 'configuracion') {
                $configuracion[] = self::enlace($modulo, self::NOMBRES_EN_SUBMENU[$modulo->slug] ?? $modulo->nombre);

                continue;
            }

            if ($modulo->slug === 'mis_solicitudes') {
                $menu[] = self::submenuSolicitudes($usuario, $modulo);

                continue;
            }

            $enlace = self::enlace($modulo, $modulo->nombre);
            if ($modulo->slug === 'aprobaciones') {
                $enlace['contador'] = self::pendientesDeAprobar($usuario);
            }
            $menu[] = $enlace;
        }

        if ($configuracion !== []) {
            $menu[] = [
                'tipo' => 'submenu',
                'id' => 'submenu-configuracion',
                'nombre' => 'Configuración',
                'icono' => 'fa-gear',
                'hijos' => $configuracion,
                'activo' => collect($configuracion)->contains('activo', true),
            ];
        }

        return $menu;
    }

    /**
     * Solicitudes que el usuario puede aprobar y siguen pendientes.
     */
    public static function pendientesDeAprobar(Usuario $usuario): int
    {
        return ($usuario->can('bajas.aprobar') ? Baja::where('estado', 'solicitada')->count() : 0)
            + ($usuario->can('movimientos.aprobar') ? Movimiento::where('estado', 'solicitado')->count() : 0)
            + ($usuario->can('requerimientos.aprobar') ? Requerimiento::whereIn('estado', ['pendiente', 'en_cotizacion'])->count() : 0);
    }

    /**
     * @return Collection<int, Modulo>
     */
    private static function modulos(): Collection
    {
        // Se guardan arrays simples: la caché no deserializa modelos Eloquent.
        return Modulo::hydrate(Cache::memo()->rememberForever(
            'menu:modulos',
            fn () => Modulo::activos()->orderBy('orden')->get()->map->getAttributes()->all(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function enlace(Modulo $modulo, string $nombre): array
    {
        $ruta = self::rutaDe($modulo);
        $prefijo = Str::beforeLast($ruta, '.');

        return [
            'tipo' => 'enlace',
            'nombre' => $nombre,
            'icono' => $modulo->icono,
            'url' => route($ruta),
            'activo' => request()->routeIs($ruta, "{$prefijo}.*"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function submenuSolicitudes(Usuario $usuario, Modulo $modulo): array
    {
        $hijos = [];

        if ($usuario->can('mis_solicitudes.crear')) {
            $hijos[] = ['nombre' => 'Dar de baja', 'icono' => 'fa-ban', 'url' => route('bajas.create'), 'activo' => request()->routeIs('bajas.create')];
            $hijos[] = ['nombre' => 'Movimiento', 'icono' => 'fa-right-left', 'url' => route('movimientos.create'), 'activo' => request()->routeIs('movimientos.create')];
            $hijos[] = ['nombre' => 'Requerimiento', 'icono' => 'fa-clipboard-list', 'url' => route('requerimientos.create'), 'activo' => request()->routeIs('requerimientos.create')];
        }
        $hijos[] = ['nombre' => 'Mis solicitudes', 'icono' => 'fa-inbox', 'url' => route(self::rutaDe($modulo)), 'activo' => request()->routeIs('solicitudes.*')];

        return [
            'tipo' => 'submenu',
            'id' => 'submenu-solicitudes',
            'nombre' => 'Solicitudes',
            'icono' => 'fa-paper-plane',
            'hijos' => $hijos,
            'activo' => collect($hijos)->contains('activo', true),
        ];
    }

    /**
     * Solo llegan al menú los módulos con ruta (ver el filtro en para()).
     */
    private static function rutaDe(Modulo $modulo): string
    {
        return $modulo->ruta ?? throw new LogicException("El módulo {$modulo->slug} no tiene ruta en el menú.");
    }
}
