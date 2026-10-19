<?php

namespace App\Policies;

use App\Models\Requerimiento;
use App\Models\Usuario;

class RequerimientoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->can('requerimientos.ver');
    }

    public function view(Usuario $usuario, Requerimiento $requerimiento): bool
    {
        return $usuario->can('requerimientos.ver') || $requerimiento->usuario_id === $usuario->id;
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->can('requerimientos.crear') || $usuario->can('mis_solicitudes.crear');
    }

    public function cambiarEstado(Usuario $usuario, Requerimiento $requerimiento): bool
    {
        return $usuario->can('requerimientos.aprobar') && $requerimiento->estadosSiguientes() !== [];
    }

    /**
     * Con permiso de eliminar, o el propio solicitante mientras siga pendiente.
     */
    public function delete(Usuario $usuario, Requerimiento $requerimiento): bool
    {
        return $usuario->can('requerimientos.eliminar')
            || ($requerimiento->usuario_id === $usuario->id && $requerimiento->estado === 'pendiente');
    }
}
