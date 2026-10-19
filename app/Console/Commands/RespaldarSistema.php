<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Services\AlmacenArchivos;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use RuntimeException;
use ZipArchive;

/**
 * Respaldo diario (programado en routes/console.php):
 * - sibip-base-AAAAMMDD-HHMMSS.sql.gz: mysqldump comprimido de la base.
 * - sibip-archivos-AAAAMMDD-HHMMSS.zip: storage/app/private (archivos subidos).
 * Se borran los respaldos con más de `sibip.respaldos.dias` días.
 * Cómo copiarlos fuera del servidor y restaurarlos: DEPLOY.md, sección Respaldos.
 */
#[Signature('sibip:respaldar {--solo-base : No respalda los archivos subidos}')]
#[Description('Respalda la base de datos y los archivos subidos, y borra los respaldos antiguos')]
class RespaldarSistema extends Command
{
    public const PATRON_NOMBRE = '/^sibip-(base|archivos)-(\d{8}-\d{6})\.(sql\.gz|zip)$/';

    public function handle(): int
    {
        $carpeta = (string) config('sibip.respaldos.ruta');
        File::ensureDirectoryExists($carpeta, 0750);
        $marca = now()->format('Ymd-His');

        try {
            $base = $this->respaldarBase("{$carpeta}/sibip-base-{$marca}.sql.gz");
            $this->info('Base de datos: '.basename($base).' ('.Number::fileSize((int) filesize($base)).')');

            if (! $this->option('solo-base')) {
                $archivos = $this->respaldarArchivos("{$carpeta}/sibip-archivos-{$marca}.zip");
                $this->info('Archivos subidos: '.basename($archivos).' ('.Number::fileSize((int) filesize($archivos)).')');
            }
        } catch (RuntimeException $e) {
            $this->error("El respaldo falló: {$e->getMessage()}");

            return self::FAILURE;
        }

        $borrados = $this->borrarAntiguos($carpeta);
        $this->line("Respaldos antiguos borrados: {$borrados}. Carpeta: {$carpeta}");
        Auditoria::registrar('respaldo', 'sistema', null, null, ['marca' => $marca, 'antiguos_borrados' => $borrados]);

        return self::SUCCESS;
    }

    private function respaldarBase(string $destino): string
    {
        $conexion = config('database.connections.'.config('database.default'));
        if (! is_array($conexion) || ! in_array($conexion['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('El respaldo de la base solo está disponible para MySQL o MariaDB.');
        }

        $temporal = "{$destino}.tmp";
        $resultado = Process::timeout(1800)
            ->env(['MYSQL_PWD' => (string) ($conexion['password'] ?? '')]) // la contraseña no aparece en la lista de procesos
            ->run([
                (string) config('sibip.respaldos.mysqldump'),
                '--host='.$conexion['host'],
                '--port='.$conexion['port'],
                '--user='.$conexion['username'],
                '--single-transaction',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                '--result-file='.$temporal,
                (string) $conexion['database'],
            ]);

        if ($resultado->failed() || ! is_file($temporal)) {
            File::delete($temporal);
            throw new RuntimeException('mysqldump terminó con error: '.trim($resultado->errorOutput() ?: 'no generó el archivo').'.');
        }

        $this->comprimir($temporal, $destino);
        File::delete($temporal);

        return $destino;
    }

    private function comprimir(string $origen, string $destino): void
    {
        $entrada = fopen($origen, 'rb');
        $salida = gzopen($destino, 'wb6');
        if ($entrada === false || $salida === false) {
            throw new RuntimeException('No se pudo comprimir el respaldo de la base.');
        }

        while (! feof($entrada)) {
            gzwrite($salida, (string) fread($entrada, 1024 * 1024));
        }

        fclose($entrada);
        gzclose($salida);
    }

    private function respaldarArchivos(string $destino): string
    {
        $origen = Storage::disk(AlmacenArchivos::DISCO)->path(''); // storage/app/private
        $zip = new ZipArchive;
        if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear {$destino}.");
        }

        // Carpeta vacía igual queda registrada, para que la restauración sea uniforme.
        $zip->addEmptyDir('private');
        if (File::isDirectory($origen)) {
            foreach (File::allFiles($origen) as $archivo) {
                $zip->addFile($archivo->getPathname(), 'private/'.str_replace('\\', '/', $archivo->getRelativePathname()));
            }
        }

        if (! $zip->close()) {
            throw new RuntimeException("No se pudo cerrar {$destino}.");
        }

        return $destino;
    }

    private function borrarAntiguos(string $carpeta): int
    {
        $limite = now()->subDays((int) config('sibip.respaldos.dias'));
        $borrados = 0;

        foreach (File::files($carpeta) as $archivo) {
            if (preg_match(self::PATRON_NOMBRE, $archivo->getFilename(), $partes) !== 1) {
                continue; // nunca se toca algo que no sea un respaldo de SIBIP
            }
            if (Carbon::createFromFormat('Ymd-His', $partes[2])?->lt($limite)) {
                File::delete($archivo->getPathname());
                $borrados++;
            }
        }

        return $borrados;
    }
}
