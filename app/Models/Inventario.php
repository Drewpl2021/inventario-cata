<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campaña de toma de inventario de una sede (y opcionalmente un área).
 */
class Inventario extends Model
{
    use Auditable;

    protected $table = 'inventarios';

    protected $fillable = [
        'codigo',
        'nombre',
        'sede_id',
        'area_id',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'observaciones',
        'usuario_id',
        'cerrado_at',
        'cerrado_por',
        'total_esperados',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'cerrado_at' => 'datetime',
            'total_esperados' => 'integer',
        ];
    }

    /** @return BelongsTo<Sede, $this> */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id')->withTrashed();
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id')->withTrashed();
    }

    /** @return BelongsTo<Usuario, $this> */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id')->withTrashed();
    }

    /** @return BelongsTo<Usuario, $this> */
    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cerrado_por')->withTrashed();
    }

    /** @return BelongsToMany<Usuario, $this> */
    public function responsables(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'inventario_responsables', 'inventario_id', 'usuario_id');
    }

    /** @return HasMany<InventarioDetalle, $this> */
    public function detalles(): HasMany
    {
        return $this->hasMany(InventarioDetalle::class, 'inventario_id');
    }

    /**
     * Lecturas vigentes (no deshechas).
     *
     * @return HasMany<InventarioDetalle, $this>
     */
    public function lecturas(): HasMany
    {
        return $this->detalles()->whereNull('anulado_at');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === 'abierto';
    }

    public function tieneResponsable(Usuario $usuario): bool
    {
        return $this->responsables()->whereKey($usuario->id)->exists();
    }

    /**
     * Bienes que deberían encontrarse: activos (no dados de baja ni retirados)
     * de la sede y, si la campaña es por área, de esa área.
     *
     * @return Builder<Bien>
     */
    public function bienesEsperados(): Builder
    {
        return Bien::query()
            ->where('sede_id', $this->sede_id)
            ->when($this->area_id, fn (Builder $q) => $q->where('area_id', $this->area_id))
            ->where('estado', '!=', 'dado_de_baja');
    }

    public function ambito(): string
    {
        return trim(($this->sede->nombre ?? '').($this->area ? ' / '.$this->area->nombre : ' (toda la sede)'));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->where('estado', 'abierto');
    }
}
