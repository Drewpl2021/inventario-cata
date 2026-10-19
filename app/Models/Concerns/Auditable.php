<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;

/**
 * Registra en la tabla `auditoria` cada alta, modificación, eliminación y
 * restauración del modelo, con los datos anteriores y nuevos, el usuario y la IP.
 *
 * Los modelos pueden definir `protected array $noAuditables = [...]` para
 * excluir columnas adicionales (por ejemplo, marcas de último acceso), y un
 * método `completarAntesDeAuditarAlta()` para completar datos que dependen del
 * id recién creado (como el código de barras) antes de registrar el alta.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model) {
            if (method_exists($model, 'completarAntesDeAuditarAlta')) {
                $model->completarAntesDeAuditarAlta();
            }

            $model->registrarAuditoria('crear', null, $model->datosAuditables($model->getAttributes()));
        });

        static::updated(function (self $model) {
            $nuevos = $model->datosAuditables($model->getChanges());

            if ($nuevos === []) {
                return;
            }

            $anteriores = array_intersect_key($model->getPrevious(), $nuevos);
            $model->registrarAuditoria('actualizar', $anteriores, $nuevos);
        });

        static::deleted(function (self $model) {
            $accion = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting() ? 'desactivar' : 'eliminar';
            $model->registrarAuditoria($accion, $model->datosAuditables($model->getAttributes()), null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (self $model) {
                $model->registrarAuditoria('restaurar', null, $model->datosAuditables($model->getAttributes()));
            });
        }
    }

    /**
     * @param  array<string, mixed>|null  $anteriores
     * @param  array<string, mixed>|null  $nuevos
     */
    protected function registrarAuditoria(string $accion, ?array $anteriores, ?array $nuevos): void
    {
        Auditoria::registrar($accion, $this->getTable(), $this->getKey(), $anteriores, $nuevos);
    }

    /**
     * Quita contraseñas, tokens y marcas de tiempo que no aportan al historial.
     *
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    protected function datosAuditables(array $atributos): array
    {
        $excluidos = array_merge(
            ['password', 'remember_token', 'created_at', 'updated_at', 'deleted_at'],
            $this->getHidden(),
            property_exists($this, 'noAuditables') ? $this->noAuditables : [],
        );

        return array_diff_key($atributos, array_flip($excluidos));
    }
}
