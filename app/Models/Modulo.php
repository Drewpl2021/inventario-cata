<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modulo extends Model
{
    protected $table = 'modulos';

    protected $fillable = [
        'nombre',
        'slug',
        'icono',
        'ruta',
        'grupo',
        'orden',
        'estado',
    ];

    /** @return HasMany<Permiso, $this> */
    public function permisos(): HasMany
    {
        return $this->hasMany(Permiso::class, 'modulo_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', 'activo');
    }
}
