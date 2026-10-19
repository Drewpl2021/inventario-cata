<?php

namespace App\Policies;

use App\Models\Baja;
use App\Models\Usuario;

/**
 * Quien gestiona bajas ve todas; el solicitante, solo las suyas.
 */
class BajaPolicy
{
    public function view(Usuario $usuario, Baja $baja): bool
    {
        return $usuario->can('bajas.ver')
            || $usuario->can('bajas.aprobar')
            || $baja->usuario_id === $usuario->id;
    }
}
