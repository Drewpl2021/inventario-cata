<?php

namespace App\Services;

use App\Enums\RolSistema;
use App\Models\Responsable;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Alta, edición, activación y restablecimiento de cuentas, con las reglas que
 * protegen al sistema: nadie se desactiva ni se quita el rol a sí mismo, siempre
 * queda al menos un Administrador activo y las cuentas de tipo "Usuario" quedan
 * vinculadas a su registro de responsable.
 */
class GestionUsuarios
{
    /**
     * @param  array<string, mixed>  $datos
     * @return array{0: Usuario, 1: string} La cuenta y su contraseña temporal.
     */
    public function crear(array $datos): array
    {
        return DB::transaction(function () use ($datos) {
            $passwordTemporal = $this->generarPasswordTemporal();
            $rol = Role::findOrFail((int) $datos['rol_id']);

            $usuario = Usuario::create([
                ...$this->datosDeCuenta($datos, $rol),
                'password' => $passwordTemporal,
                'debe_cambiar_password' => true,
            ]);

            if ($rol->requiereResponsable()) {
                $this->vincularResponsable($usuario, $datos['responsable_existente_id'] ?? null, $this->datosDelResponsable($datos));
            }

            return [$usuario, $passwordTemporal];
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Usuario $usuario, array $datos, Usuario $actor): Usuario
    {
        return DB::transaction(function () use ($usuario, $datos, $actor) {
            $usuario = Usuario::lockForUpdate()->findOrFail($usuario->id);
            $rol = Role::findOrFail((int) $datos['rol_id']);
            $estado = $datos['estado'] ?? $usuario->estado;

            if ($actor->id === $usuario->id) {
                if ((int) $rol->id !== (int) $usuario->rol_id) {
                    throw ValidationException::withMessages(['rol_id' => 'No puede cambiar su propio rol.']);
                }
                if ($estado !== 'activo') {
                    throw ValidationException::withMessages(['estado' => 'No puede desactivar su propia cuenta.']);
                }
            }

            $dejaDeSerAdministradorActivo = $usuario->isAdmin() && $usuario->estaActivo()
                && (! $rol->esAdministrador() || $estado !== 'activo');

            if ($dejaDeSerAdministradorActivo) {
                $this->exigirOtroAdministradorActivo($usuario);
            }

            $usuario->update($this->datosDeCuenta($datos, $rol));

            if ($rol->requiereResponsable()) {
                $this->vincularResponsable($usuario, $datos['responsable_existente_id'] ?? null, $this->datosDelResponsable($datos));
            }

            if (! $usuario->estaActivo()) {
                $this->cerrarSesiones($usuario);
            }

            return $usuario;
        });
    }

    public function cambiarEstado(Usuario $usuario, string $estado, Usuario $actor): Usuario
    {
        return DB::transaction(function () use ($usuario, $estado, $actor) {
            $usuario = Usuario::lockForUpdate()->findOrFail($usuario->id);

            if ($estado !== 'activo') {
                if ($actor->id === $usuario->id) {
                    throw ValidationException::withMessages(['estado' => 'No puede desactivar su propia cuenta.']);
                }
                if ($usuario->isAdmin() && $usuario->estaActivo()) {
                    $this->exigirOtroAdministradorActivo($usuario);
                }
            }

            $usuario->update(['estado' => $estado]);

            if ($estado !== 'activo') {
                $this->cerrarSesiones($usuario);
            }

            return $usuario;
        });
    }

    /**
     * Genera una nueva contraseña temporal, obliga a cambiarla y cierra las sesiones abiertas.
     */
    public function restablecerPassword(Usuario $usuario, Usuario $actor): string
    {
        if ($actor->id === $usuario->id) {
            throw ValidationException::withMessages(['password' => 'Para cambiar su propia contraseña use la opción "Cambiar contraseña".']);
        }

        $passwordTemporal = $this->generarPasswordTemporal();

        $usuario->update([
            'password' => $passwordTemporal,
            'debe_cambiar_password' => true,
        ]);

        $this->cerrarSesiones($usuario);

        return $passwordTemporal;
    }

    /**
     * Solo se eliminan (soft delete) cuentas sin historial; las demás se desactivan.
     */
    public function eliminar(Usuario $usuario, Usuario $actor): void
    {
        DB::transaction(function () use ($usuario, $actor) {
            $usuario = Usuario::lockForUpdate()->findOrFail($usuario->id);

            if ($actor->id === $usuario->id) {
                throw ValidationException::withMessages(['usuario' => 'No puede eliminar su propia cuenta.']);
            }
            if ($usuario->tieneHistorial()) {
                throw ValidationException::withMessages(['usuario' => 'La cuenta tiene operaciones registradas y no se puede eliminar. Desactívela en su lugar.']);
            }
            if ($usuario->isAdmin() && $usuario->estaActivo()) {
                $this->exigirOtroAdministradorActivo($usuario);
            }

            $usuario->responsable()->update(['usuario_id' => null]);
            $usuario->update(['estado' => 'inactivo']);
            $this->cerrarSesiones($usuario);
            $usuario->delete();
        });
    }

    /**
     * Vincula la cuenta con un responsable existente (sin cuenta) o, si no se
     * indica ninguno, con el que ya tenía o con uno nuevo creado con sus datos.
     * La sede y el área del responsable se alinean con las de la cuenta.
     */
    /**
     * @param  array<string, string>  $datosPersonales  DNI, cargo y teléfono informados en el formulario.
     */
    private function vincularResponsable(Usuario $usuario, int|string|null $responsableExistenteId, array $datosPersonales = []): Responsable
    {
        $ubicacion = ['sede_id' => $usuario->sede_id, 'area_id' => $usuario->area_id, ...$datosPersonales];
        $vinculado = $usuario->responsable()->lockForUpdate()->first();

        if ($responsableExistenteId && (int) $responsableExistenteId !== $vinculado?->id) {
            $responsable = Responsable::lockForUpdate()->findOrFail($responsableExistenteId);

            if ($responsable->usuario_id && $responsable->usuario_id !== $usuario->id) {
                throw ValidationException::withMessages(['responsable_existente_id' => 'Ese responsable ya está vinculado a otra cuenta.']);
            }

            $vinculado?->update(['usuario_id' => null]);
            $responsable->update(['usuario_id' => $usuario->id, ...$ubicacion]);

            return $responsable;
        }

        if ($vinculado) {
            $vinculado->update($ubicacion);

            return $vinculado;
        }

        return Responsable::create([
            'usuario_id' => $usuario->id,
            'nombres' => $usuario->nombre,
            'apellidos' => $usuario->apellido,
            'email' => $usuario->email,
            'estado' => 'activo',
            ...$ubicacion,
        ]);
    }

    private function exigirOtroAdministradorActivo(Usuario $usuario): void
    {
        $otrosAdministradores = Usuario::query()
            ->whereKeyNot($usuario->id)
            ->where('estado', 'activo')
            ->whereHas('rol', fn ($q) => $q->where('nombre', RolSistema::Administrador->value)->where('estado', 'activo'))
            ->lockForUpdate()
            ->count();

        if ($otrosAdministradores === 0) {
            throw ValidationException::withMessages(['rol_id' => 'Debe quedar al menos un Administrador activo en el sistema.']);
        }
    }

    /**
     * Solo los datos personales que vinieron con valor (no borra los existentes).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, string>
     */
    private function datosDelResponsable(array $datos): array
    {
        return array_filter(
            array_intersect_key($datos, array_flip(['dni', 'cargo', 'telefono'])),
            fn ($valor) => filled($valor),
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function datosDeCuenta(array $datos, Role $rol): array
    {
        return [
            'nombre' => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'email' => mb_strtolower($datos['email']),
            'rol_id' => $rol->id,
            'sede_id' => $datos['sede_id'] ?? null,
            'area_id' => $datos['area_id'] ?? null,
            'estado' => $datos['estado'] ?? 'activo',
        ];
    }

    /**
     * Elimina las sesiones guardadas e invalida el "recordarme". Además, el
     * middleware VerificarCuentaActiva expulsa a las cuentas inactivas.
     */
    private function cerrarSesiones(Usuario $usuario): void
    {
        $usuario->cerrarSesiones();
    }

    private function generarPasswordTemporal(): string
    {
        return Str::password(12, symbols: false);
    }
}
