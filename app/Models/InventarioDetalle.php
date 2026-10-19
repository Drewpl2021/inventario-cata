<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lectura de un código durante una toma de inventario (o, al cerrar, un bien
 * esperado que no se escaneó: resultado 'faltante').
 */
class InventarioDetalle extends Model
{
    use Auditable;

    /**
     * Clasificaciones con su etiqueta y color de la pantalla de escaneo.
     * 'ya_escaneado' no se guarda: es la respuesta a una lectura repetida.
     */
    public const RESULTADOS = [
        'encontrado' => ['etiqueta' => 'Encontrado', 'color' => 'verde'],
        'fuera_de_ubicacion' => ['etiqueta' => 'Fuera de ubicación', 'color' => 'ambar'],
        'ya_escaneado' => ['etiqueta' => 'Ya escaneado', 'color' => 'gris'],
        'dado_de_baja' => ['etiqueta' => 'Figura dado de baja', 'color' => 'morado'],
        'sobrante_no_registrado' => ['etiqueta' => 'No registrado', 'color' => 'rojo'],
        'faltante' => ['etiqueta' => 'Faltante', 'color' => 'rojo'],
    ];

    public const ESTADOS_FISICOS = ['bueno' => 'Bueno', 'regular' => 'Regular', 'malo' => 'Malo'];

    protected $table = 'inventario_detalles';

    protected $fillable = [
        'inventario_id',
        'bien_id',
        'codigo_leido',
        'resultado',
        'estado_fisico',
        'area_escaneo_id',
        'escaneado_por',
        'fecha_hora',
        'uuid_cliente',
        'registrado_offline',
        'anulado_at',
        'anulado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'anulado_at' => 'datetime',
            'registrado_offline' => 'boolean',
        ];
    }

    /** @return BelongsTo<Inventario, $this> */
    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class, 'inventario_id');
    }

    /** @return BelongsTo<Bien, $this> */
    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class, 'bien_id')->withTrashed();
    }

    /** @return BelongsTo<Area, $this> */
    public function areaEscaneo(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_escaneo_id')->withTrashed();
    }

    /** @return BelongsTo<Usuario, $this> */
    public function escaneadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'escaneado_por')->withTrashed();
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }
}
