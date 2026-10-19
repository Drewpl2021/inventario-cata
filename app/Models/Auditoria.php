<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Auditoria extends Model
{
    protected $table = 'auditoria';

    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'accion',
        'tabla',
        'registro_id',
        'datos_anteriores',
        'datos_nuevos',
        'ip',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'datos_anteriores' => 'array',
            'datos_nuevos' => 'array',
            'fecha' => 'datetime',
        ];
    }

    /**
     * Guarda un registro de auditoría con el usuario autenticado y la IP de la petición.
     *
     * @param  array<string, mixed>|null  $anteriores
     * @param  array<string, mixed>|null  $nuevos
     */
    public static function registrar(string $accion, string $tabla, int|string|null $registroId, ?array $anteriores = null, ?array $nuevos = null): self
    {
        return static::create([
            'usuario_id' => Auth::id(),
            'accion' => $accion,
            'tabla' => $tabla,
            'registro_id' => $registroId,
            'datos_anteriores' => $anteriores,
            'datos_nuevos' => $nuevos,
            'ip' => request()->ip(),
            'fecha' => now(),
        ]);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id')->withTrashed();
    }
}
