<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Requerimiento extends Model
{
    use Auditable;

    /**
     * Transiciones de estado permitidas: estado actual => estados siguientes.
     * 'rechazado' y 'atendido' son estados finales.
     */
    public const TRANSICIONES = [
        'pendiente' => ['en_cotizacion', 'aprobado', 'rechazado'],
        'en_cotizacion' => ['aprobado', 'rechazado'],
        'aprobado' => ['atendido'],
        'rechazado' => [],
        'atendido' => [],
    ];

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'en_cotizacion' => 'En cotización',
        'aprobado' => 'Aprobado',
        'rechazado' => 'Rechazado',
        'atendido' => 'Atendido / Entregado',
    ];

    protected $table = 'requerimientos';

    protected $fillable = [
        'codigo',
        'usuario_id',
        'aprobado_por',
        'tipo_requerimiento',
        'descripcion',
        'justificacion',
        'observaciones',
        'cantidad',
        'costo_estimado',
        'sede_id',
        'area_id',
        'prioridad',
        'estado',
        'archivo_adjunto',
        'archivo_tipo',
        'archivo_nombre_original',
        'motivo_rechazo',
        'fecha_resolucion',
        'fecha_solicitud',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'datetime',
            'fecha_resolucion' => 'datetime',
            'costo_estimado' => 'decimal:2',
            'cantidad' => 'integer',
        ];
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

    /** @return list<string> */
    public function estadosSiguientes(): array
    {
        return self::TRANSICIONES[$this->estado];
    }

    public function puedePasarA(string $estado): bool
    {
        return in_array($estado, $this->estadosSiguientes(), true);
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

    /** Adjunto servido por ArchivoController (disco privado). */
    public function getUrlAdjuntoAttribute(): ?string
    {
        return $this->archivo_adjunto ? route('archivos.requerimientos.adjunto', $this) : null;
    }

    /** @return array{label: string, class: string, icon: string} */
    public function getEstadoBadgeAttribute(): array
    {
        return match ($this->estado) {
            'pendiente' => ['label' => 'Pendiente', 'class' => 'bg-amber-50 text-amber-700 border-amber-200', 'icon' => 'fa-clock'],
            'en_cotizacion' => ['label' => 'En Cotización', 'class' => 'bg-blue-50 text-blue-700 border-blue-200', 'icon' => 'fa-file-invoice-dollar'],
            'aprobado' => ['label' => 'Aprobado', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'fa-circle-check'],
            'rechazado' => ['label' => 'Rechazado', 'class' => 'bg-rose-50 text-rose-700 border-rose-200', 'icon' => 'fa-circle-xmark'],
            'atendido' => ['label' => 'Atendido / Entregado', 'class' => 'bg-purple-50 text-purple-700 border-purple-200', 'icon' => 'fa-box-check'],
        };
    }

    /** @return array{label: string, class: string} */
    public function getPrioridadBadgeAttribute(): array
    {
        return match ($this->prioridad) {
            'baja' => ['label' => 'Baja', 'class' => 'bg-slate-100 text-slate-600'],
            'media' => ['label' => 'Media', 'class' => 'bg-sky-100 text-sky-700'],
            'alta' => ['label' => 'Alta', 'class' => 'bg-amber-100 text-amber-800'],
            'urgente' => ['label' => 'Urgente', 'class' => 'bg-rose-100 text-rose-800 font-bold'],
        };
    }
}
