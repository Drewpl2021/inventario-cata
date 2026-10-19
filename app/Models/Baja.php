<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Baja extends Model
{
    use Auditable;

    protected $table = 'bajas';

    public $timestamps = false;

    protected $fillable = [
        'bien_id',
        'inventario_id',
        'motivo',
        'estado',
        'descripcion',
        'fecha_baja',
        'documento_referencia',
        'fotografia',
        'valor_residual',
        'usuario_id',
        'aprobado_por',
        'fecha_resolucion',
        'observaciones',
        'motivo_rechazo',
    ];

    /** Estados que bloquean una nueva solicitud de baja para el mismo bien. */
    public const ESTADOS_ACTIVOS = ['solicitada', 'aprobada'];

    /** Foto de evidencia servida por ArchivoController (disco privado). */
    public function getUrlFotografiaAttribute(): ?string
    {
        return $this->fotografia ? route('archivos.bajas.foto', [$this, 'v' => substr(md5($this->fotografia), 0, 8)]) : null;
    }

    /** @return array{label: string, class: string, icon: string} */
    public function getEstadoBadgeAttribute(): array
    {
        return match ($this->estado) {
            'solicitada' => ['label' => 'Solicitud Pendiente', 'class' => 'bg-amber-100 text-amber-800 border-amber-200', 'icon' => 'fa-clock'],
            'aprobada' => ['label' => 'Baja Aprobada', 'class' => 'bg-rose-100 text-rose-800 border-rose-200', 'icon' => 'fa-ban'],
            'rechazada' => ['label' => 'Baja Rechazada', 'class' => 'bg-slate-100 text-slate-700 border-slate-200', 'icon' => 'fa-circle-xmark'],
        };
    }

    protected function casts(): array
    {
        return [
            'fecha_baja' => 'date',
            'valor_residual' => 'decimal:2',
            'fecha_resolucion' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Bien, $this> */
    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class, 'bien_id');
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

    public function estaSolicitada(): bool
    {
        return $this->estado === 'solicitada';
    }
}
