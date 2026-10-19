<?php

namespace App\Models;

use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'areas';

    protected $fillable = [
        'sede_id',
        'nombre',
        'codigo',
        'tipo',
        'ubicacion',
        'estado',
    ];

    /** @return BelongsTo<Sede, $this> */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /** @return HasMany<Responsable, $this> */
    public function responsables(): HasMany
    {
        return $this->hasMany(Responsable::class, 'area_id');
    }

    /** @return HasMany<Bien, $this> */
    public function bienes(): HasMany
    {
        return $this->hasMany(Bien::class, 'area_id');
    }
}
