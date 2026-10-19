<?php

namespace App\Services;

use App\Rules\ArchivoPermitido;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Archivos subidos (fotos, comprobantes, adjuntos, documentos) en el disco
 * privado storage/app/private. Nunca quedan en public/: se sirven por
 * ArchivoController después de revisar la Policy.
 *
 * Las imágenes se reducen a 1600 px de lado mayor y se guardan comprimidas
 * en WEBP; los PDF se guardan tal cual. Todos con nombre aleatorio.
 */
class AlmacenArchivos
{
    public const DISCO = 'local';

    /** Carpeta raíz dentro del disco privado: solo se sirve lo que esté aquí. */
    public const RAIZ = 'archivos';

    public const CARPETA_FOTOS_BIENES = self::RAIZ.'/bienes/fotos';

    public const CARPETA_COMPROBANTES = self::RAIZ.'/bienes/comprobantes';

    public const CARPETA_DOCUMENTOS = self::RAIZ.'/documentos';

    public const CARPETA_BAJAS = self::RAIZ.'/bajas';

    public const CARPETA_REQUERIMIENTOS = self::RAIZ.'/requerimientos';

    public const LADO_MAXIMO = 1600;

    public const CALIDAD = 80;

    public function disco(): Filesystem
    {
        return Storage::disk(self::DISCO);
    }

    /**
     * Guarda el archivo del campo (si se subió) y devuelve su ruta en el disco privado.
     * El campo ya debe estar validado con ArchivoPermitido.
     */
    public function guardarSubido(Request $request, string $campo, string $carpeta): ?string
    {
        $archivo = $request->file($campo);

        if (! $archivo instanceof UploadedFile) {
            return null;
        }

        return ArchivoPermitido::mimeReal($archivo) === 'application/pdf'
            ? $this->guardarPdf($archivo, $carpeta)
            : $this->guardarImagen($archivo, $carpeta, $campo);
    }

    public function guardarPdf(UploadedFile $archivo, string $carpeta): string
    {
        $nombre = Str::random(40).'.pdf';
        $this->disco()->putFileAs($carpeta, $archivo, $nombre);

        return "{$carpeta}/{$nombre}";
    }

    public function guardarImagen(UploadedFile $archivo, string $carpeta, string $campo = 'archivo'): string
    {
        // Una foto de celular de 48 MP ocupa ~200 MB descomprimida.
        ini_set('memory_limit', '512M');

        $contenido = (string) file_get_contents((string) $archivo->getRealPath());
        $imagen = @imagecreatefromstring($contenido);

        if (! $imagen instanceof GdImage) {
            throw ValidationException::withMessages([$campo => 'La imagen está dañada o no se puede leer.']);
        }

        $imagen = $this->reducir($this->enderezar($imagen, $archivo), self::LADO_MAXIMO);

        ob_start();
        $formatoWebp = function_exists('imagewebp');
        $formatoWebp ? imagewebp($imagen, null, self::CALIDAD) : imagejpeg($imagen, null, self::CALIDAD);
        $bytes = (string) ob_get_clean();

        $ruta = $carpeta.'/'.Str::random(40).($formatoWebp ? '.webp' : '.jpg');
        $this->disco()->put($ruta, $bytes);

        return $ruta;
    }

    public function eliminar(?string $ruta): void
    {
        if ($this->esRutaPrivada($ruta)) {
            $this->disco()->delete($ruta);
        }
    }

    /**
     * Respuesta para mostrar el archivo en el navegador. 404 si no existe o si
     * la ruta no es de un archivo subido (no se sirve nada fuera de "archivos/").
     */
    public function respuesta(?string $ruta, string $nombre): StreamedResponse
    {
        abort_unless($this->esRutaPrivada($ruta) && $this->disco()->exists($ruta), 404);

        $extension = pathinfo($ruta, PATHINFO_EXTENSION);
        $nombre = Str::slug(pathinfo($nombre, PATHINFO_FILENAME)) ?: 'archivo';

        return $this->disco()->response($ruta, "{$nombre}.{$extension}", [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @phpstan-assert-if-true non-empty-string $ruta
     */
    public function esRutaPrivada(?string $ruta): bool
    {
        return filled($ruta)
            && str_starts_with($ruta, self::RAIZ.'/')
            && ! str_contains($ruta, '..')
            && ! str_contains($ruta, '\\');
    }

    /**
     * Las fotos de celular traen la orientación en EXIF; se aplica para que no queden giradas.
     */
    private function enderezar(GdImage $imagen, UploadedFile $archivo): GdImage
    {
        if (! function_exists('exif_read_data') || ArchivoPermitido::mimeReal($archivo) !== 'image/jpeg') {
            return $imagen;
        }

        $exif = @exif_read_data((string) $archivo->getRealPath());
        $orientacion = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angulo = match ($orientacion) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angulo === 0 ? $imagen : (imagerotate($imagen, $angulo, 0) ?: $imagen);
    }

    private function reducir(GdImage $imagen, int $ladoMaximo): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        if (max($ancho, $alto) > $ladoMaximo) {
            $escala = $ladoMaximo / max($ancho, $alto);
            $imagen = imagescale($imagen, max(1, (int) round($ancho * $escala)), max(1, (int) round($alto * $escala)), IMG_BICUBIC) ?: $imagen;
        }

        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);

        return $imagen;
    }
}
