<?php

namespace App\Providers;

use App\Models\Usuario;
use App\Services\GeneradorEtiquetas;
use App\Support\MenuLateral;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El generador usa siempre el formato de etiqueta guardado en Configuración.
        $this->app->bind(GeneradorEtiquetas::class, fn () => GeneradorEtiquetas::conConfiguracionActual());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        // Política de contraseñas: mínimo 8 caracteres, con letras y números.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Fuera de producción, cargar una relación sin "with()" es un error: así se detectan las consultas N+1.
        Model::preventLazyLoading(! $this->app->isProduction());

        // /up (monitoreo): responde 500 si la base de datos no está disponible. No muestra datos.
        Event::listen(DiagnosingHealth::class, fn () => DB::select('select 1'));

        $this->registrarPermisosDinamicos();
        $this->compartirDatosDelLayout();
    }

    /**
     * Toda habilidad con forma "modulo.accion" (ej. "bienes.crear") se resuelve
     * contra la tabla de permisos del rol del usuario. Las demás habilidades
     * (view, update, ...) se delegan a las Policies.
     */
    private function registrarPermisosDinamicos(): void
    {
        Gate::before(function (Usuario $usuario, string $habilidad) {
            if (! str_contains($habilidad, '.')) {
                return null;
            }

            return $usuario->tienePermiso($habilidad);
        });
    }

    /**
     * Menú lateral, notificaciones de la campana y mensajes flash del layout.
     */
    private function compartirDatosDelLayout(): void
    {
        View::composer('layouts.app', function ($view) {
            /** @var Usuario|null $usuario */
            $usuario = Auth::user();

            $view->with([
                'menuLateral' => MenuLateral::para($usuario),
                'notificaciones' => $usuario?->notifications()->latest()->limit(8)->get() ?? collect(),
                'notificacionesSinLeer' => $usuario?->unreadNotifications()->count() ?? 0,
                'toasts' => collect([
                    ['exito', session('success')],
                    ['error', session('error')],
                    ['aviso', session('warning')],
                ])->filter(fn (array $toast) => filled($toast[1]))->values(),
            ]);
        });
    }
}
