<?php

namespace App\Policies;

use App\Models\Inventario;
use App\Models\Usuario;

/**
 * - Ver resultados: "toma_inventario.ver" (Auditor incluido).
 * - Escanear y cerrar: "toma_inventario.editar", siendo responsable del conteo
 *   (o con permiso de aprobar, que supervisa todas las campañas).
 * - Reabrir: "toma_inventario.aprobar".
 */
class InventarioPolicy
{
    public function view(Usuario $usuario, Inventario $inventario): bool
    {
        return $usuario->can('toma_inventario.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('toma_inventario.crear');
    }

    public function escanear(Usuario $usuario, Inventario $inventario): bool
    {
        return $inventario->estaAbierto()
            && $usuario->can('toma_inventario.editar')
            && ($usuario->can('toma_inventario.aprobar') || $inventario->tieneResponsable($usuario));
    }

    public function cerrar(Usuario $usuario, Inventario $inventario): bool
    {
        return $this->escanear($usuario, $inventario);
    }

    public function reabrir(Usuario $usuario, Inventario $inventario): bool
    {
        return ! $inventario->estaAbierto() && $usuario->can('toma_inventario.aprobar');
    }

    public function exportar(Usuario $usuario, Inventario $inventario): bool
    {
        return $usuario->can('toma_inventario.exportar');
    }
}
