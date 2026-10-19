<?php

namespace App\Models;

use Database\Factories\SedeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sede extends Model
{
    /** @use HasFactory<SedeFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'sedes';

    protected $fillable = [
        'nombre',
        'codigo',
        'direccion',
        'telefono',
        'estado',
    ];

    /**
     * Prefijo de la sede en el código de barras (ej. "S01"). Se usa el código de
     * la sede si es corto y alfanumérico; si no, "S" + id con dos dígitos.
     */
    public function prefijoCodigoBarras(): string
    {
        $codigo = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $this->codigo));

        return preg_match('/^[A-Z0-9]{1,4}$/', $codigo)
            ? $codigo
            : 'S'.str_pad((string) $this->id, 2, '0', STR_PAD_LEFT);
    }

    /** @return HasMany<Area, $this> */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class, 'sede_id');
    }

    /** @return HasMany<Bien, $this> */
    public function bienes(): HasMany
    {
        return $this->hasMany(Bien::class, 'sede_id');
    }

    /** @return HasMany<Responsable, $this> */
    public function responsables(): HasMany
    {
        return $this->hasMany(Responsable::class, 'sede_id');
    }
}
