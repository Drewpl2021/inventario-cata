<?php

namespace App\Policies;

use App\Models\Usuario;

/**
 * Reglas de gestión de cuentas. Las validaciones de negocio (último
 * Administrador, vinculación con responsable) viven en GestionUsuarios.
 */
class UsuarioPolicy
{
    public function viewAny(Usuario $actor): bool
    {
        return $actor->can('usuarios.ver');
    }

    public function create(Usuario $actor): bool
    {
        return $actor->can('usuarios.crear');
    }

    public function update(Usuario $actor, Usuario $usuario): bool
    {
        return $actor->can('usuarios.editar');
    }

    public function delete(Usuario $actor, Usuario $usuario): bool
    {
        return $actor->can('usuarios.eliminar') && $actor->id !== $usuario->id;
    }
}
