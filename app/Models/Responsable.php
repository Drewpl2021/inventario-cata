<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ResponsableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Responsable extends Model
{
    /** @use HasFactory<ResponsableFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'responsables';

    protected $fillable = [
        'usuario_id',
        'nombres',
        'apellidos',
        'dni',
        'cargo',
        'sede_id',
        'area_id',
        'telefono',
        'email',
        'estado',
    ];

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    /** @return BelongsTo<Sede, $this> */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    /** @return HasMany<Bien, $this> */
    public function bienes(): HasMany
    {
        return $this->hasMany(Bien::class, 'responsable_id');
    }
}
