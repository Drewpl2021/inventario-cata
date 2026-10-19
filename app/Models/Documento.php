<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documento extends Model
{
    protected $table = 'documentos';

    public $timestamps = false;

    protected $fillable = [
        'bien_id',
        'tipo',
        'nombre',
        'ruta',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Archivo del disco privado (por ArchivoController) o, en registros antiguos, un enlace externo.
     */
    public function getUrlAttribute(): string
    {
        return preg_match('#^https?://#i', $this->ruta) === 1 ? $this->ruta : route('archivos.documentos', $this);
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
}
