<?php

namespace App\Models;

use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'categorias';

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
    ];

    /** @return HasMany<Bien, $this> */
    public function bienes(): HasMany
    {
        return $this->hasMany(Bien::class, 'categoria_id');
    }
}
