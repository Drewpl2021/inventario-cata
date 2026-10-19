<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Configuración del sistema guardada como clave/valor (JSON).
 */
class Configuracion extends Model
{
    use Auditable;

    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor'];

    protected function casts(): array
    {
        return ['valor' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn (Configuracion $configuracion) => Cache::forget("configuracion:{$configuracion->clave}"));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function obtener(string $clave): ?array
    {
        return Cache::rememberForever("configuracion:{$clave}", fn () => static::where('clave', $clave)->first()?->valor);
    }

    /**
     * @param  array<string, mixed>  $valor
     */
    public static function guardar(string $clave, array $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }
}
