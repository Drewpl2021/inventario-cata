<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    /** Acciones posibles, en el orden en que se muestran en la matriz de permisos. */
    public const ACCIONES = [
        'ver' => 'Ver',
        'crear' => 'Crear',
        'editar' => 'Editar',
        'eliminar' => 'Eliminar',
        'aprobar' => 'Aprobar',
        'exportar' => 'Exportar',
        'imprimir' => 'Imprimir',
    ];

    protected $table = 'permisos';

    protected $fillable = [
        'modulo_id',
        'accion',
        'slug',
    ];

    /** @return BelongsTo<Modulo, $this> */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'rol_permiso', 'permiso_id', 'rol_id');
    }
}
