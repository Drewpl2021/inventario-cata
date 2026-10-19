<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Services\AlmacenArchivos;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Mueve los archivos subidos antes de la Fase 6 (public/uploads, accesibles por
 * cualquiera con el enlace) al disco privado y actualiza las rutas en la base.
 *
 * Se puede ejecutar varias veces: el nombre nuevo se deriva de la ruta antigua,
 * así que una ruta compartida (comprobante del bien y su documento) o una
 * ejecución interrumpida terminan siempre en el mismo archivo.
 */
#[Signature('sibip:migrar-archivos {--simular : Muestra lo que haría sin mover nada}')]
#[Description('Mueve los archivos de public/uploads al disco privado y actualiza las rutas en la base')]
class MigrarArchivosPrivados extends Command
{
    /** @var list<array{0: string, 1: string}> tabla y columna con rutas de archivos */
    private const COLUMNAS = [
        ['bienes', 'fotografia'],
        ['bienes', 'comprobante_archivo'],
        ['documentos', 'ruta'],
        ['bajas', 'fotografia'],
        ['requerimientos', 'archivo_adjunto'],
    ];

    /** @var array<string, string> carpeta antigua => carpeta nueva */
    private const CARPETAS = [
        'uploads/bienes/' => AlmacenArchivos::CARPETA_FOTOS_BIENES,
        'uploads/comprobantes/' => AlmacenArchivos::CARPETA_COMPROBANTES,
        'uploads/bajas/' => AlmacenArchivos::CARPETA_BAJAS,
        'uploads/requerimientos/' => AlmacenArchivos::CARPETA_REQUERIMIENTOS,
        'uploads/documentos/' => AlmacenArchivos::CARPETA_DOCUMENTOS,
    ];

    private const CARPETA_SIN_REFERENCIA = AlmacenArchivos::RAIZ.'/sin-referencia';

    public function handle(AlmacenArchivos $archivos): int
    {
        $simular = (bool) $this->option('simular');
        $disco = $archivos->disco();
        $actualizados = 0;
        /** @var list<string> $faltantes */
        $faltantes = [];
        /** @var array<string, true> $origenesMovidos */
        $origenesMovidos = [];

        foreach (self::COLUMNAS as [$tabla, $columna]) {
            $filas = DB::table($tabla)->select('id', $columna)
                ->where(fn ($q) => $q->where($columna, 'like', 'uploads/%')->orWhere($columna, 'like', '/uploads/%'))
                ->orderBy('id')
                ->get();

            foreach ($filas as $fila) {
                $anterior = ltrim((string) $fila->{$columna}, '/');
                $origen = public_path($anterior);
                $nueva = $this->rutaNueva($anterior);

                if (! $disco->exists($nueva)) {
                    if (! is_file($origen)) {
                        $faltantes[] = "{$tabla} #{$fila->id} ({$columna}): {$anterior}";

                        continue;
                    }
                    if (! $simular) {
                        $flujo = fopen($origen, 'rb');
                        if ($flujo === false) {
                            $faltantes[] = "{$tabla} #{$fila->id} ({$columna}): no se pudo leer {$anterior}";

                            continue;
                        }
                        $disco->put($nueva, $flujo);
                        fclose($flujo);
                    }
                }

                if (! $simular) {
                    DB::table($tabla)->where('id', $fila->id)->update([$columna => $nueva]);
                }
                $origenesMovidos[$origen] = true;
                $actualizados++;
                $this->line("  {$tabla} #{$fila->id}: {$anterior} → {$nueva}");
            }
        }

        // Los originales se borran al final: una misma ruta puede estar en varias tablas.
        if (! $simular) {
            foreach (array_keys($origenesMovidos) as $origen) {
                File::delete($origen);
            }
        }

        $sinReferencia = $this->moverSinReferencia($archivos, $simular);
        $carpetaEliminada = $this->eliminarCarpetaPublica($simular);

        $this->newLine();
        $this->info(($simular ? '[Simulación] ' : '')."Rutas actualizadas: {$actualizados}. Archivos sin referencia guardados en storage/app/private/".self::CARPETA_SIN_REFERENCIA.": {$sinReferencia}.");

        if ($faltantes !== []) {
            $this->warn('Registros cuyo archivo no existe en public/uploads (revíselos a mano):');
            foreach ($faltantes as $faltante) {
                $this->line("  - {$faltante}");
            }
        }

        $this->line($carpetaEliminada ? 'La carpeta public/uploads se eliminó.' : 'public/uploads no existe o ya está vacía.');

        if (! $simular && ($actualizados > 0 || $sinReferencia > 0)) {
            Auditoria::registrar('migrar_archivos', 'archivos', null, null, [
                'rutas_actualizadas' => $actualizados,
                'sin_referencia' => $sinReferencia,
                'faltantes' => count($faltantes),
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * "uploads/bienes/foto.jpg" → "archivos/bienes/fotos/<hash>.jpg". Siempre el mismo
     * resultado para la misma ruta, por eso el comando se puede repetir sin duplicar.
     */
    private function rutaNueva(string $anterior): string
    {
        $carpeta = collect(self::CARPETAS)->first(fn ($nueva, $vieja) => str_starts_with($anterior, $vieja), AlmacenArchivos::CARPETA_DOCUMENTOS);
        $extension = Str::lower(pathinfo($anterior, PATHINFO_EXTENSION)) ?: 'bin';

        return "{$carpeta}/".sha1($anterior).".{$extension}";
    }

    /**
     * Archivos que quedaron en public/uploads sin que ningún registro los use:
     * no se borran, se guardan en el disco privado por si alguien los necesita.
     */
    private function moverSinReferencia(AlmacenArchivos $archivos, bool $simular): int
    {
        $carpeta = public_path('uploads');
        if (! File::isDirectory($carpeta)) {
            return 0;
        }

        $movidos = 0;
        foreach (File::allFiles($carpeta) as $archivo) {
            $relativa = str_replace('\\', '/', $archivo->getRelativePathname());
            if (! $simular) {
                $archivos->disco()->put(self::CARPETA_SIN_REFERENCIA.'/'.$relativa, (string) file_get_contents($archivo->getPathname()));
                File::delete($archivo->getPathname());
            }
            $this->line("  sin referencia: uploads/{$relativa}");
            $movidos++;
        }

        return $movidos;
    }

    private function eliminarCarpetaPublica(bool $simular): bool
    {
        $carpeta = public_path('uploads');
        if ($simular || ! File::isDirectory($carpeta) || File::allFiles($carpeta) !== []) {
            return false;
        }

        return File::deleteDirectory($carpeta);
    }
}
