<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimiento extends Model
{
    use Auditable;

    public const TIPOS = [
        'traslado' => 'Traslado',
        'asignacion' => 'Asignación',
        'devolucion' => 'Devolución',
        'reubicacion' => 'Reubicación',
    ];

    protected $table = 'movimientos';

    public $timestamps = false;

    protected $fillable = [
        'bien_id',
        'inventario_id',
        'sede_origen_id',
        'area_origen_id',
        'sede_destino_id',
        'area_destino_id',
        'responsable_anterior_id',
        'responsable_nuevo_id',
        'tipo',
        'estado',
        'motivo',
        'fecha_movimiento',
        'usuario_id',
        'aprobado_por',
        'fecha_resolucion',
        'motivo_rechazo',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_movimiento' => 'datetime',
            'fecha_resolucion' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return array{label: string, class: string, icon: string}
     */
    public function getEstadoBadgeAttribute(): array
    {
        return match ($this->estado) {
            'solicitado' => ['label' => 'Solicitado', 'class' => 'bg-amber-50 text-amber-700 border-amber-200', 'icon' => 'fa-clock'],
            'aprobado' => ['label' => 'Aprobado', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'fa-circle-check'],
            'ejecutado' => ['label' => 'Ejecutado', 'class' => 'bg-blue-50 text-blue-700 border-blue-200', 'icon' => 'fa-truck-ramp-box'],
            'rechazado' => ['label' => 'Rechazado', 'class' => 'bg-rose-50 text-rose-700 border-rose-200', 'icon' => 'fa-circle-xmark'],
        };
    }

    public function estaSolicitado(): bool
    {
        return $this->estado === 'solicitado';
    }

    /** @return BelongsTo<Bien, $this> */
    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class, 'bien_id');
    }

    /** @return BelongsTo<Sede, $this> */
    public function sedeOrigen(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_origen_id');
    }

    /** @return BelongsTo<Area, $this> */
    public function areaOrigen(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_origen_id');
    }

    /** @return BelongsTo<Sede, $this> */
    public function sedeDestino(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_destino_id');
    }

    /** @return BelongsTo<Area, $this> */
    public function areaDestino(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_destino_id');
    }

    /** @return BelongsTo<Responsable, $this> */
    public function responsableAnterior(): BelongsTo
    {
        return $this->belongsTo(Responsable::class, 'responsable_anterior_id');
    }

    /** @return BelongsTo<Responsable, $this> */
    public function responsableNuevo(): BelongsTo
    {
        return $this->belongsTo(Responsable::class, 'responsable_nuevo_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id')->withTrashed();
    }

    /** @return BelongsTo<Usuario, $this> */
    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobado_por')->withTrashed();
    }
}
