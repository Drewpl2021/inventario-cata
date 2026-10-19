<?php

namespace App\Policies;

use App\Models\Documento;
use App\Models\Usuario;

/**
 * Quien tiene el archivo documentario ve todos los documentos; los demás,
 * solo los de bienes que pueden ver (el responsable, los de sus bienes).
 */
class DocumentoPolicy
{
    public function view(Usuario $usuario, Documento $documento): bool
    {
        return $usuario->can('documentos.ver')
            || ($documento->bien !== null && $usuario->can('view', $documento->bien));
    }
}
