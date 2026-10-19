<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mantenimiento extends Model
{
    use Auditable;

    protected $table = 'mantenimientos';

    protected $fillable = [
        'bien_id',
        'tipo',
        'descripcion',
        'proveedor',
        'fecha_inicio',
        'fecha_fin',
        'costo',
        'estado',
        'usuario_id',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'costo' => 'decimal:2',
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
}
