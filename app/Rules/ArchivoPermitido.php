<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Valida un archivo subido por su tipo REAL (contenido), no por la extensión
 * ni por lo que declara el navegador: imágenes JPG/PNG/WEBP hasta 5 MB y
 * documentos PDF hasta 10 MB.
 *
 * Uso: 'fotografia' => ['nullable', ArchivoPermitido::imagen()]
 */
class ArchivoPermitido implements ValidationRule
{
    /** @var array<string, string> */
    public const IMAGENES = ['image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/webp' => 'WEBP'];

    public const MAXIMO_IMAGEN_KB = 5 * 1024;

    public const MAXIMO_PDF_KB = 10 * 1024;

    /** Resolución máxima que se acepta antes de redimensionar (evita imágenes "bomba"). */
    public const MEGAPIXELES_MAXIMOS = 50;

    public function __construct(private readonly bool $aceptaImagenes, private readonly bool $aceptaPdf) {}

    public static function imagen(): self
    {
        return new self(aceptaImagenes: true, aceptaPdf: false);
    }

    public static function pdf(): self
    {
        return new self(aceptaImagenes: false, aceptaPdf: true);
    }

    public static function imagenOPdf(): self
    {
        return new self(aceptaImagenes: true, aceptaPdf: true);
    }

    /**
     * Tipo MIME según el contenido del archivo.
     */
    public static function mimeReal(UploadedFile $archivo): ?string
    {
        $ruta = $archivo->getRealPath();

        return $ruta ? ((new \finfo(FILEINFO_MIME_TYPE))->file($ruta) ?: null) : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('El archivo no se pudo subir. Verifique que no supere el tamaño permitido e inténtelo de nuevo.');

            return;
        }

        $mime = self::mimeReal($value);

        if ($this->aceptaImagenes && isset(self::IMAGENES[$mime])) {
            $this->validarImagen($value, $fail);

            return;
        }

        if ($this->aceptaPdf && $mime === 'application/pdf') {
            if ($value->getSize() > self::MAXIMO_PDF_KB * 1024) {
                $fail('El PDF no debe superar 10 MB.');
            }

            return;
        }

        $fail(match (true) {
            $this->aceptaImagenes && $this->aceptaPdf => 'Solo se aceptan documentos PDF o imágenes JPG, PNG o WEBP.',
            $this->aceptaPdf => 'Solo se aceptan documentos PDF.',
            default => 'Solo se aceptan imágenes JPG, PNG o WEBP.',
        });
    }

    private function validarImagen(UploadedFile $imagen, Closure $fail): void
    {
        if ($imagen->getSize() > self::MAXIMO_IMAGEN_KB * 1024) {
            $fail('La imagen no debe superar 5 MB.');

            return;
        }

        $dimensiones = @getimagesize((string) $imagen->getRealPath());

        if ($dimensiones === false) {
            $fail('La imagen está dañada o no se puede leer.');

            return;
        }

        if (self::MEGAPIXELES_MAXIMOS * 1_000_000 < $dimensiones[0] * $dimensiones[1]) {
            $fail('La imagen tiene demasiada resolución: el máximo es '.self::MEGAPIXELES_MAXIMOS.' megapíxeles.');
        }
    }
}
