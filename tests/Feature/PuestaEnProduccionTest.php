<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Jobs\LatidoDeCola;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PuestaEnProduccionTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = storage_path('framework/testing/respaldos-'.uniqid());
        config(['sibip.respaldos.ruta' => $this->carpeta, 'sibip.respaldos.dias' => 14]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        parent::tearDown();
    }

    /** mysqldump simulado: escribe el archivo que pide --result-file. */
    private function simularMysqldump(int $codigoSalida = 0): void
    {
        Process::fake(function (PendingProcess $proceso) use ($codigoSalida) {
            $comando = (array) $proceso->command;
            $resultado = collect($comando)->first(fn ($a) => str_starts_with((string) $a, '--result-file='));
            if ($codigoSalida === 0 && $resultado) {
                file_put_contents(substr($resultado, strlen('--result-file=')), "CREATE TABLE `bienes` (id int);\n");
            }

            return Process::result(errorOutput: $codigoSalida ? 'Access denied' : '', exitCode: $codigoSalida);
        });
    }

    public function test_el_respaldo_comprime_la_base_y_los_archivos_y_borra_los_de_mas_de_14_dias(): void
    {
        $this->simularMysqldump();
        Storage::fake('local');
        File::ensureDirectoryExists($this->carpeta);
        $antiguo = $this->carpeta.'/sibip-base-'.now()->subDays(15)->format('Ymd-His').'.sql.gz';
        $reciente = $this->carpeta.'/sibip-base-'.now()->subDays(13)->format('Ymd-His').'.sql.gz';
        $ajeno = $this->carpeta.'/notas-del-administrador.txt';
        File::put($antiguo, 'x');
        File::put($reciente, 'x');
        File::put($ajeno, 'x');
        Storage::disk('local')->put('archivos/bienes/fotos/prueba.webp', 'imagen');

        $this->artisan('sibip:respaldar')->assertSuccessful();

        Process::assertRan(fn (PendingProcess $p) => in_array('--single-transaction', (array) $p->command, true)
            && ($p->environment['MYSQL_PWD'] ?? null) !== null
            && ! collect((array) $p->command)->contains(fn ($a) => str_starts_with((string) $a, '--password')));

        $base = File::glob($this->carpeta.'/sibip-base-'.now()->format('Ymd').'-*.sql.gz');
        $this->assertCount(1, $base);
        $this->assertStringContainsString('CREATE TABLE', (string) gzdecode((string) file_get_contents($base[0])));

        $zip = File::glob($this->carpeta.'/sibip-archivos-*.zip');
        $this->assertCount(1, $zip);
        $archivo = new \ZipArchive;
        $archivo->open($zip[0]);
        $this->assertNotFalse($archivo->locateName('private/archivos/bienes/fotos/prueba.webp'));
        $archivo->close();

        $this->assertFileDoesNotExist($antiguo);
        $this->assertFileExists($reciente);
        $this->assertFileExists($ajeno, 'Nunca se borra algo que no sea un respaldo de SIBIP.');
        $this->assertDatabaseHas('auditoria', ['accion' => 'respaldo']);
    }

    public function test_si_mysqldump_falla_el_respaldo_termina_con_error_y_no_deja_archivos(): void
    {
        $this->simularMysqldump(codigoSalida: 2);

        $this->artisan('sibip:respaldar')
            ->expectsOutputToContain('El respaldo falló: mysqldump terminó con error: Access denied.')
            ->assertFailed();

        $this->assertSame([], File::glob($this->carpeta.'/*'));
    }

    public function test_el_respaldo_esta_programado_a_diario(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('sibip:respaldar')
            ->expectsOutputToContain('sibip:latido-cron')
            ->assertSuccessful();
    }

    public function test_la_verificacion_marca_en_rojo_lo_que_no_es_de_produccion(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://sibip.test', 'mail.default' => 'log']);
        Usuario::factory()->rol(RolSistema::Administrador)->create(['email' => 'admin@sibip.local']);

        $this->artisan('sibip:verificar-produccion')
            ->expectsOutputToContain('✘ APP_ENV=production')
            ->expectsOutputToContain('✘ APP_DEBUG=false')
            ->expectsOutputToContain('✘ APP_URL con https://')
            ->expectsOutputToContain('✘ Sin cuentas de demostración')
            ->expectsOutputToContain('✘ Ninguna cuenta usa contraseñas del código')
            ->expectsOutputToContain('✘ Envío de correo real')
            ->expectsOutputToContain('✘ El cron ejecuta schedule:run')
            ->assertFailed();
    }

    public function test_la_verificacion_marca_en_verde_lo_que_esta_bien(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'app.debug' => false,
            'app.url' => 'https://inventario.cata.edu.pe',
            'session.secure' => true,
            'session.encrypt' => true,
            'mail.default' => 'smtp',
            'mail.from.address' => 'inventario@cata.edu.pe',
            'sibip.admin_inicial.password' => null,
        ]);
        Usuario::factory()->rol(RolSistema::Administrador)->create(['email' => 'director@cata.edu.pe', 'password' => 'UnaClaveReal2026']);
        Cache::forever('sibip:latido-cron', now()->timestamp);
        Cache::forever(LatidoDeCola::CLAVE, now()->subMinutes(4)->timestamp);
        File::ensureDirectoryExists($this->carpeta);
        File::put($this->carpeta.'/sibip-base-'.now()->format('Ymd-His').'.sql.gz', 'x');

        $this->artisan('sibip:verificar-produccion')
            ->expectsOutputToContain('✔ APP_ENV=production')
            ->expectsOutputToContain('✔ APP_DEBUG=false')
            ->expectsOutputToContain('✔ APP_URL con https://')
            ->expectsOutputToContain('✔ Sin cuentas de demostración')
            ->expectsOutputToContain('✔ Ninguna cuenta usa contraseñas del código')
            ->expectsOutputToContain('✔ Envío de correo real')
            ->expectsOutputToContain('✔ El cron ejecuta schedule:run')
            ->expectsOutputToContain('✔ La cola procesa trabajos')
            ->expectsOutputToContain('✔ Respaldo de las últimas 24 horas');
    }

    public function test_el_latido_de_la_cola_registra_la_hora(): void
    {
        (new LatidoDeCola)->handle();

        $this->assertEqualsWithDelta(now()->timestamp, Cache::get(LatidoDeCola::CLAVE), 2);
    }

    public function test_el_chequeo_de_salud_responde_sin_exponer_datos(): void
    {
        $respuesta = $this->get('/up')->assertOk();

        $respuesta->assertDontSee(config('database.connections.mysql.database'))
            ->assertDontSee('Laravel v')
            ->assertDontSee('PHP')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
