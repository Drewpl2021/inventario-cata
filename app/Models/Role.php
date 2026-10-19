<?php

namespace App\Models;

use App\Enums\RolSistema;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Role extends Model
{
    use Auditable;

    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'descripcion',
        'es_sistema',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'es_sistema' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (Role $rol) => $rol->limpiarCachePermisos());
        static::deleted(fn (Role $rol) => $rol->limpiarCachePermisos());
    }

    /** @return HasMany<Usuario, $this> */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rol_id');
    }

    /** @return BelongsToMany<Permiso, $this> */
    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'rol_id', 'permiso_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Rol base del sistema. Siempre existe: lo crea RolesYPermisosSeeder.
     */
    public static function delSistema(RolSistema $rol): self
    {
        return static::where('nombre', $rol->value)->firstOrFail();
    }

    public function es(RolSistema $rol): bool
    {
        return $this->nombre === $rol->value;
    }

    public function esAdministrador(): bool
    {
        return $this->es(RolSistema::Administrador);
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'activo';
    }

    /**
     * Indica si las cuentas con este rol deben estar vinculadas a un responsable
     * de bienes (y por tanto requieren sede y área).
     */
    public function requiereResponsable(): bool
    {
        if ($this->es(RolSistema::Usuario)) {
            return true;
        }

        return ! $this->es_sistema && in_array('mis_bienes.ver', $this->slugsDePermisos(), true);
    }

    /**
     * Slugs de los permisos del rol, guardados en caché hasta que el rol cambie.
     *
     * @return list<string>
     */
    public function slugsDePermisos(): array
    {
        // memo(): dentro de una misma petición la caché se lee una sola vez (cada can() la consulta).
        return Cache::memo()->rememberForever($this->claveCachePermisos(), function () {
            return array_values($this->permisos()->get()->map(fn (Permiso $permiso) => $permiso->slug)->all());
        });
    }

    /**
     * Reemplaza los permisos del rol. Al Administrador siempre se le asignan todos.
     *
     * @param  list<int>  $permisoIds
     */
    public function sincronizarPermisos(array $permisoIds): void
    {
        if ($this->esAdministrador()) {
            $permisoIds = Permiso::pluck('id')->all();
        }

        $anteriores = $this->permisos()->pluck('slug')->sort()->values()->all();
        $this->permisos()->sync($permisoIds);
        $this->limpiarCachePermisos();
        $nuevos = $this->permisos()->pluck('slug')->sort()->values()->all();

        if ($anteriores !== $nuevos) {
            Auditoria::registrar('permisos', $this->getTable(), $this->id, ['permisos' => $anteriores], ['permisos' => $nuevos]);
        }
    }

    public function limpiarCachePermisos(): void
    {
        Cache::memo()->forget($this->claveCachePermisos());
    }

    private function claveCachePermisos(): string
    {
        return "rol:{$this->id}:permisos";
    }
}
