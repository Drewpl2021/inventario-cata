<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\BienFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class Bien extends Model
{
    /** @use HasFactory<BienFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /** Estados físicos/administrativos posibles de un bien. */
    public const ESTADOS = [
        'nuevo' => 'Nuevo',
        'bueno' => 'Bueno',
        'regular' => 'Regular',
        'malo' => 'Malo',
        'mantenimiento' => 'En mantenimiento',
        'inoperativo' => 'Inoperativo',
        'dado_de_baja' => 'Dado de baja',
    ];

    protected $table = 'bienes';

    protected $fillable = [
        'codigo_patrimonial',
        'codigo_interno',
        'nombre',
        'descripcion',
        'marca',
        'modelo',
        'numero_serie',
        'categoria_id',
        'sede_id',
        'area_id',
        'responsable_id',
        'fecha_adquisicion',
        'valor_adquisicion',
        'estado',
        'tipo_ingreso',
        'numero_comprobante',
        'comprobante_archivo',
        'fotografia',
        'observaciones',
    ];

    /** @var list<string> La impresión de etiquetas se audita en un solo registro por lote. */
    protected array $noAuditables = ['etiqueta_impresa_at'];

    /**
     * El código de barras nunca cambia: ni al editar el bien ni al trasladarlo
     * a otra sede (la etiqueta ya está pegada).
     */
    protected static function booted(): void
    {
        static::updating(function (Bien $bien) {
            if ($bien->isDirty('codigo_barras') && $bien->getOriginal('codigo_barras') !== null) {
                $bien->codigo_barras = $bien->getOriginal('codigo_barras');
            }
        });
    }

    /**
     * Formato CAT-{prefijo de sede}-{correlativo de 6 dígitos}. El correlativo es
     * el id del bien: global, único y nunca reutilizado (los bienes no se borran físicamente).
     */
    public static function formatearCodigoBarras(Sede $sede, int $correlativo): string
    {
        return sprintf('CAT-%s-%06d', $sede->prefijoCodigoBarras(), $correlativo);
    }

    /**
     * El código de barras se asigna al crear el bien (necesita su id), en el
     * mismo evento y antes de registrar el alta, para que la auditoría lo incluya.
     */
    public function completarAntesDeAuditarAlta(): void
    {
        $this->asignarCodigoBarras();
    }

    /** Asigna el código si aún no tiene uno. Sin efecto en bienes que ya lo tienen. */
    public function asignarCodigoBarras(): void
    {
        if ($this->codigo_barras !== null) {
            return;
        }

        $this->forceFill(['codigo_barras' => static::formatearCodigoBarras($this->sede()->withTrashed()->firstOrFail(), $this->id)])
            ->saveQuietly();
    }

    /**
     * Código de barras del bien. Se asigna al crearlo (asignarCodigoBarras),
     * así que solo falta mientras se está guardando el alta.
     */
    public function codigoDeBarras(): string
    {
        return $this->codigo_barras ?? throw new LogicException("El bien {$this->id} todavía no tiene código de barras.");
    }

    public function getTipoIngresoTextoAttribute(): string
    {
        return match ($this->tipo_ingreso) {
            'con_comprobante' => 'Con Comprobante / Factura',
            'sin_comprobante' => 'Sin Comprobante',
            'donacion' => 'Donación',
            'transferencia' => 'Transferencia',
            'otro' => 'Otro',
            default => $this->tipo_ingreso ?? 'No especificado'
        };
    }

    /** Fotografía servida por ArchivoController (disco privado). */
    public function getUrlFotografiaAttribute(): ?string
    {
        return $this->fotografia ? self::urlArchivo($this->fotografia, 'archivos.bienes.foto', $this) : null;
    }

    public function getUrlComprobanteAttribute(): ?string
    {
        return $this->comprobante_archivo ? self::urlArchivo($this->comprobante_archivo, 'archivos.bienes.comprobante', $this) : null;
    }

    /**
     * Enlace al archivo privado; "v" cambia cuando se reemplaza el archivo (evita la caché del navegador).
     */
    private static function urlArchivo(string $ruta, string $nombreRuta, mixed $parametro): string
    {
        return route($nombreRuta, [$parametro, 'v' => substr(md5($ruta), 0, 8)]);
    }

    protected function casts(): array
    {
        return [
            'fecha_adquisicion' => 'date',
            'etiqueta_impresa_at' => 'datetime',
            'valor_adquisicion' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
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

    /** @return BelongsTo<Responsable, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Responsable::class, 'responsable_id');
    }

    /** @return HasMany<Movimiento, $this> */
    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class, 'bien_id');
    }

    /** @return HasMany<Mantenimiento, $this> */
    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class, 'bien_id');
    }

    /** @return HasMany<Baja, $this> */
    public function bajas(): HasMany
    {
        return $this->hasMany(Baja::class, 'bien_id');
    }

    /**
     * Última baja registrada del bien (solicitada, aprobada o rechazada).
     *
     * @return HasOne<Baja, $this>
     */
    public function baja(): HasOne
    {
        return $this->hasOne(Baja::class, 'bien_id')->latestOfMany();
    }

    /** Siguiente código interno libre (5 dígitos), incluidos los bienes eliminados. */
    public static function siguienteCodigoInterno(): string
    {
        return self::siguienteCodigoLibre('codigo_interno', '', 5);
    }

    /** Siguiente código patrimonial libre con el prefijo institucional. */
    public static function siguienteCodigoPatrimonial(): string
    {
        return self::siguienteCodigoLibre('codigo_patrimonial', '740805', 6);
    }

    private static function siguienteCodigoLibre(string $columna, string $prefijo, int $digitos): string
    {
        $numero = (static::withTrashed()->max('id') ?? 0) + 1;

        do {
            $codigo = $prefijo.str_pad((string) $numero, $digitos, '0', STR_PAD_LEFT);
            $numero++;
        } while (static::withTrashed()->where($columna, $codigo)->exists());

        return $codigo;
    }

    public function estaDadoDeBaja(): bool
    {
        return $this->estado === 'dado_de_baja';
    }

    /** Indica si el bien tiene movimientos, bajas o mantenimientos registrados. */
    public function tieneHistorial(): bool
    {
        return $this->movimientos()->exists()
            || $this->bajas()->exists()
            || $this->mantenimientos()->exists();
    }

    /** @return HasMany<Documento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'bien_id');
    }
}
