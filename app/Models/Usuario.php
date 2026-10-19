<?php

namespace App\Models;

use App\Enums\RolSistema;
use App\Models\Concerns\Auditable;
use App\Notifications\RestablecerPassword;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use Auditable, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'password',
        'rol_id',
        'sede_id',
        'area_id',
        'estado',
        'debe_cambiar_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @var list<string> */
    protected array $noAuditables = ['ultimo_acceso_at'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'debe_cambiar_password' => 'boolean',
            'ultimo_acceso_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Role, $this> */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
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

    /**
     * Registro de responsable de bienes vinculado a esta cuenta.
     *
     * @return HasOne<Responsable, $this>
     */
    public function responsable(): HasOne
    {
        return $this->hasOne(Responsable::class, 'usuario_id');
    }

    /** @return HasMany<Movimiento, $this> */
    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class, 'usuario_id');
    }

    /** @return HasMany<Mantenimiento, $this> */
    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class, 'usuario_id');
    }

    /** @return HasMany<Baja, $this> */
    public function bajas(): HasMany
    {
        return $this->hasMany(Baja::class, 'usuario_id');
    }

    /** @return HasMany<Documento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'usuario_id');
    }

    /** @return HasMany<Auditoria, $this> */
    public function auditorias(): HasMany
    {
        return $this->hasMany(Auditoria::class, 'usuario_id');
    }

    /** @return HasMany<Requerimiento, $this> */
    public function requerimientos(): HasMany
    {
        return $this->hasMany(Requerimiento::class, 'usuario_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido}");
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'activo';
    }

    /**
     * Cierra las sesiones abiertas de la cuenta (salvo, si se indica, la actual)
     * e invalida el recordatorio de sesión.
     */
    public function cerrarSesiones(?string $exceptoSesionId = null): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $this->id)
                ->when($exceptoSesionId, fn ($q) => $q->where('id', '!=', $exceptoSesionId))
                ->delete();
        }

        $this->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    /**
     * Correo de recuperación de contraseña en español con el diseño del sistema.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new RestablecerPassword($token));
    }

    /**
     * Permiso dinámico (ej. "bienes.crear"). El Administrador los tiene todos;
     * una cuenta o un rol inactivo no tiene ninguno.
     */
    public function tienePermiso(string $slug): bool
    {
        $rol = $this->rol;

        if (! $this->estaActivo() || ! $rol || ! $rol->estaActivo()) {
            return false;
        }

        if ($rol->esAdministrador()) {
            return true;
        }

        return in_array($slug, $rol->slugsDePermisos(), true);
    }

    /** Indica si el bien está a cargo del responsable vinculado a esta cuenta. */
    public function esResponsableDe(Bien $bien): bool
    {
        return $bien->responsable_id !== null
            && $this->responsable !== null
            && $bien->responsable_id === $this->responsable->id;
    }

    /** Indica si la cuenta registró movimientos, bajas, mantenimientos, documentos o requerimientos. */
    public function tieneHistorial(): bool
    {
        return $this->movimientos()->exists()
            || $this->bajas()->exists()
            || $this->mantenimientos()->exists()
            || $this->documentos()->exists()
            || $this->requerimientos()->exists();
    }

    public function isAdmin(): bool
    {
        return (bool) $this->rol?->es(RolSistema::Administrador);
    }

    public function isOperador(): bool
    {
        return (bool) $this->rol?->es(RolSistema::Operador);
    }

    public function isAuditor(): bool
    {
        return (bool) $this->rol?->es(RolSistema::Auditor);
    }

    public function isUsuario(): bool
    {
        return (bool) $this->rol?->es(RolSistema::Usuario);
    }
}
