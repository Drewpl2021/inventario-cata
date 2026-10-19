<?php

namespace App\Policies;

use App\Models\Bien;
use App\Models\Usuario;

/**
 * Propiedad de los bienes: quien tiene "bienes.ver" ve todos; quien solo tiene
 * "mis_bienes.ver" ve únicamente los bienes de los que es responsable.
 */
class BienPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('bienes.ver');
    }

    public function view(Usuario $usuario, Bien $bien): bool
    {
        return $usuario->can('bienes.ver')
            || ($usuario->can('mis_bienes.ver') && $usuario->esResponsableDe($bien));
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('bienes.crear');
    }

    public function update(Usuario $usuario, Bien $bien): bool
    {
        return $usuario->can('bienes.editar') && ! $bien->estaDadoDeBaja();
    }

    public function delete(Usuario $usuario, Bien $bien): bool
    {
        return $usuario->can('bienes.eliminar');
    }

    /**
     * Puede registrar solicitudes (baja, movimiento) sobre el bien: con el permiso
     * del módulo, o como responsable del bien desde "Mis solicitudes".
     */
    public function solicitar(Usuario $usuario, Bien $bien, string $modulo): bool
    {
        if ($bien->estaDadoDeBaja()) {
            return false;
        }

        return $usuario->can("{$modulo}.crear")
            || ($usuario->can('mis_solicitudes.crear') && $usuario->esResponsableDe($bien));
    }
}
