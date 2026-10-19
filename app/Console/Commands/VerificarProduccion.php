<?php

namespace App\Console\Commands;

use App\Jobs\LatidoDeCola;
use App\Models\Usuario;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Revisión del servidor antes (y después) de la puesta en marcha. Cada punto
 * sale en verde (bien) o rojo (corregir). Termina con error si hay algún rojo,
 * así también sirve en scripts.
 */
#[Signature('sibip:verificar-produccion')]
#[Description('Revisa la configuración de producción y muestra cada punto en verde o rojo')]
class VerificarProduccion extends Command
{
    /** Minutos sin latido a partir de los cuales se considera detenido. */
    private const MINUTOS_CRON = 3;

    private const MINUTOS_COLA = 12;

    private const HORAS_RESPALDO = 26;

    /** Contraseñas que vienen escritas en el código (seeders y factories). */
    private const CONTRASENAS_CONOCIDAS = ['Password123'];

    /** @var list<array{0: bool, 1: string, 2: string}> */
    private array $resultados = [];

    public function handle(): int
    {
        $this->line('<options=bold>Verificación de producción · SIBIP</>');

        $this->seccion('Configuración');
        $this->revisar(app()->isProduction(), 'APP_ENV=production', 'Valor actual: '.app()->environment());
        $this->revisar(config('app.debug') === false, 'APP_DEBUG=false', 'Con APP_DEBUG=true los errores muestran datos internos.');
        $this->revisar(filled(config('app.key')), 'APP_KEY definida', 'Ejecute una sola vez: php artisan key:generate');
        $this->revisar(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL con https://', 'Valor actual: '.config('app.url'));
        $this->revisar(config('session.secure') === true && config('session.encrypt') === true, 'Sesión cifrada y solo por HTTPS', 'SESSION_SECURE_COOKIE=true y SESSION_ENCRYPT=true');
        $this->revisar(app()->configurationIsCached(), 'Configuración en caché', 'Ejecute: php artisan config:cache');
        $this->revisar(blank(config('sibip.admin_inicial.password')), 'SIBIP_ADMIN_PASSWORD quitada del .env', 'Ya no se necesita después de crear el Administrador inicial.');

        $this->seccion('Base de datos y cuentas');
        $baseDisponible = $this->revisarBase();
        if ($baseDisponible) {
            $this->revisarCuentasDemo();
        }

        $this->seccion('Carpetas');
        foreach (['storage/app/private', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $carpeta) {
            $ruta = base_path($carpeta);
            $this->revisar(File::isDirectory($ruta) && File::isWritable($ruta), "{$carpeta} con permiso de escritura", 'Vea "Permisos" en DEPLOY.md.');
        }
        $this->revisar(! File::exists(public_path('uploads')), 'Sin archivos en public/uploads', 'Ejecute: php artisan sibip:migrar-archivos');
        $this->revisar(File::exists(public_path('build/manifest.json')), 'Estilos y scripts compilados', 'Ejecute: npm ci && npm run build');
        if (PHP_OS_FAMILY !== 'Windows') {
            $publico = File::exists(base_path('.env')) && (fileperms(base_path('.env')) & 0004) !== 0;
            $this->revisar(! $publico, '.env no legible por otros usuarios', 'Ejecute: chmod 640 .env');
        }

        $this->seccion('Correo');
        $mailer = (string) config('mail.default');
        $this->revisar(! in_array($mailer, ['log', 'array'], true), 'Envío de correo real', "MAIL_MAILER={$mailer}: los correos no salen del servidor.");
        $remitente = (string) config('mail.from.address');
        $this->revisar(filled($remitente) && ! Str::endsWith($remitente, ['example.com', '.local']), 'Remitente configurado', "MAIL_FROM_ADDRESS={$remitente}");

        $this->seccion('Cola y tareas programadas');
        $this->revisar(config('queue.default') !== 'sync', 'Cola en segundo plano', 'QUEUE_CONNECTION=sync hace esperar al usuario.');
        $this->revisarLatido('sibip:latido-cron', self::MINUTOS_CRON, 'El cron ejecuta schedule:run', 'Configure el cron de cada minuto (DEPLOY.md).');
        $this->revisarLatido(LatidoDeCola::CLAVE, self::MINUTOS_COLA, 'La cola procesa trabajos', 'Inicie el worker: sudo systemctl start sibip-cola (o el cron de la cola en cPanel).');
        if ($baseDisponible) {
            $atascados = DB::table('jobs')->where('created_at', '<', now()->subMinutes(self::MINUTOS_COLA)->timestamp)->count();
            $this->revisar($atascados === 0, 'Sin trabajos atascados', "{$atascados} trabajos esperan hace más de ".self::MINUTOS_COLA.' minutos.');
            $fallidos = DB::table('failed_jobs')->count();
            $this->revisar($fallidos === 0, 'Sin trabajos fallidos', "{$fallidos} fallidos. Véalos con: php artisan queue:failed");
        }
        $this->revisarUltimoRespaldo();

        return $this->resumen();
    }

    private function revisarBase(): bool
    {
        try {
            DB::select('select 1');
            $this->revisar(true, 'Conexión a la base de datos', '');
        } catch (Throwable $e) {
            $this->revisar(false, 'Conexión a la base de datos', Str::limit($e->getMessage(), 160));

            return false;
        }

        $this->revisar(DB::table('migrations')->exists(), 'Migraciones aplicadas', 'Ejecute: php artisan migrate --force');

        return true;
    }

    private function revisarCuentasDemo(): void
    {
        $correosDemo = array_keys(DemoSeeder::CREDENCIALES);
        $demo = Usuario::withTrashed()->whereIn('email', $correosDemo)->pluck('email');
        $this->revisar($demo->isEmpty(), 'Sin cuentas de demostración', 'Existen: '.$demo->implode(', ').'. Use una base sin DemoSeeder.');

        $conocidas = [...self::CONTRASENAS_CONOCIDAS, ...array_column(DemoSeeder::CREDENCIALES, 0)];
        $conContrasenaConocida = Usuario::where('estado', 'activo')->get(['email', 'password'])
            ->filter(fn (Usuario $u) => collect($conocidas)->contains(fn (string $clave) => Hash::check($clave, $u->password)))
            ->pluck('email');
        $this->revisar($conContrasenaConocida->isEmpty(), 'Ninguna cuenta usa contraseñas del código', 'Cambie la contraseña de: '.$conContrasenaConocida->implode(', '));

        $administradores = Usuario::where('estado', 'activo')->with('rol')->get()->filter(fn (Usuario $u) => $u->isAdmin())->count();
        $this->revisar($administradores >= 1, 'Hay un Administrador activo', 'Cree el Administrador inicial: php artisan db:seed --force');
    }

    private function revisarLatido(string $clave, int $minutos, string $titulo, string $ayuda): void
    {
        $marca = Cache::get($clave);
        $hace = is_numeric($marca) ? (int) floor((now()->getTimestamp() - (int) $marca) / 60) : null;

        $this->revisar($hace !== null && $hace <= $minutos, $titulo, $hace === null ? "Nunca se registró. {$ayuda}" : "Último registro hace {$hace} minutos. {$ayuda}");
    }

    private function revisarUltimoRespaldo(): void
    {
        $carpeta = (string) config('sibip.respaldos.ruta');
        $ultimo = File::isDirectory($carpeta)
            ? collect(File::files($carpeta))->filter(fn ($f) => preg_match(RespaldarSistema::PATRON_NOMBRE, $f->getFilename()) === 1)->max(fn ($f) => $f->getMTime())
            : null;
        $horas = $ultimo ? (int) floor((time() - (int) $ultimo) / 3600) : null;

        $this->revisar($horas !== null && $horas <= self::HORAS_RESPALDO, 'Respaldo de las últimas 24 horas', $horas === null ? 'No hay respaldos. Pruebe: php artisan sibip:respaldar' : "El último es de hace {$horas} horas.");
    }

    private function seccion(string $titulo): void
    {
        $this->newLine();
        $this->line("<fg=cyan;options=bold>{$titulo}</>");
    }

    private function revisar(bool $correcto, string $titulo, string $ayuda): void
    {
        $this->resultados[] = [$correcto, $titulo, $ayuda];

        $correcto
            ? $this->line("  <fg=green>✔</> {$titulo}")
            : $this->line("  <fg=red>✘ {$titulo}</>".($ayuda !== '' ? "\n      <fg=gray>{$ayuda}</>" : ''));
    }

    private function resumen(): int
    {
        $fallas = count(array_filter($this->resultados, fn (array $r) => ! $r[0]));
        $this->newLine();

        if ($fallas === 0) {
            $this->line('<fg=green;options=bold>Todo en orden ('.count($this->resultados).' puntos).</>');

            return self::SUCCESS;
        }

        $this->line("<fg=red;options=bold>{$fallas} de ".count($this->resultados).' puntos por corregir.</>');

        return self::FAILURE;
    }
}
