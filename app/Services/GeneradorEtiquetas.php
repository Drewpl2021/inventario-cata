<?php

namespace App\Services;

use App\Support\ConfiguracionEtiqueta;
use Barryvdh\DomPDF\Facade\Pdf;
use GdImage;
use Picqer\Barcode\Barcode;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Picqer\Barcode\Types\TypeCode128;
use RuntimeException;
use ZipArchive;

/**
 * Dibuja las etiquetas térmicas con GD, en blanco y negro puro y a la resolución
 * real de la impresora (203 o 300 dpi), para que las barras queden con un ancho
 * entero de puntos. La misma imagen se usa en los tres formatos de salida:
 * impresión desde el navegador, PDF del tamaño exacto y PNG/ZIP para las apps
 * de las impresoras Bluetooth.
 */
class GeneradorEtiquetas
{
    /** Zona de silencio mínima de Code 128 a cada lado, en módulos. */
    public const ZONA_SILENCIO_MODULOS = 10;

    /** Código de ejemplo de longitud típica, para la página de prueba y para validar la configuración. */
    public const CODIGO_DE_EJEMPLO = 'CAT-S01-000123';

    private const LOGO = 'images/logo_cata_emblema.png';

    public function __construct(private readonly ConfiguracionEtiqueta $config) {}

    public static function conConfiguracionActual(): self
    {
        return new self(ConfiguracionEtiqueta::actual());
    }

    public function configuracion(): ConfiguracionEtiqueta
    {
        return $this->config;
    }

    /**
     * Etiqueta de un bien en PNG (binario), con la resolución de la impresora en sus metadatos.
     */
    public function png(string $codigo, ?string $nombre = null): string
    {
        return $this->aPng($this->dibujar($codigo, $nombre));
    }

    /**
     * Página de calibración: bordes, marcas cada 5 mm, medidas y un código de ejemplo.
     */
    public function pngDePrueba(): string
    {
        $imagen = $this->dibujar(self::CODIGO_DE_EJEMPLO, null, conGuias: true);

        return $this->aPng($imagen);
    }

    /**
     * Ancho de módulo que realmente se usará (puede ser menor al configurado si
     * el código no entra en la etiqueta).
     */
    public function anchoModuloEfectivo(string $codigo = self::CODIGO_DE_EJEMPLO): int
    {
        $modulos = $this->codigoDeBarras($codigo)->getWidth() + 2 * self::ZONA_SILENCIO_MODULOS;
        $maximoQueEntra = intdiv($this->config->anchoPx() - 2 * $this->margenPx(), $modulos);

        return min($this->config->anchoModulo, $maximoQueEntra);
    }

    /** Indica si el código entra en la etiqueta con al menos un punto por módulo. */
    public function cabe(string $codigo = self::CODIGO_DE_EJEMPLO): bool
    {
        return $this->anchoModuloEfectivo($codigo) >= 1;
    }

    /**
     * PDF con una etiqueta por página, del tamaño exacto configurado.
     *
     * @param  list<string>  $pngs
     */
    public function pdf(array $pngs): string
    {
        $puntos = fn (float $mm) => $mm / 25.4 * 72;

        return Pdf::loadView('etiquetas.pdf', [
            'imagenes' => array_map(fn (string $png) => 'data:image/png;base64,'.base64_encode($png), $pngs),
            'config' => $this->config,
        ])
            ->setPaper([0, 0, $puntos($this->config->anchoMm), $puntos($this->config->altoMm)])
            ->output();
    }

    /**
     * ZIP con un PNG por etiqueta. Devuelve la ruta de un archivo temporal.
     *
     * @param  array<string, string>  $pngsPorNombre  nombre de archivo => PNG
     */
    public function zip(array $pngsPorNombre): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'etiquetas_');
        $zip = new ZipArchive;

        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP de etiquetas.');
        }

        foreach ($pngsPorNombre as $nombre => $png) {
            $zip->addFromString($nombre, $png);
        }

        $zip->close();

        return $ruta;
    }

    /**
     * Código de barras en SVG (para mostrar en pantalla, no para imprimir).
     */
    public function svg(string $codigo, int $altura = 60): string
    {
        return (new BarcodeGeneratorSVG)->getBarcode($codigo, BarcodeGeneratorSVG::TYPE_CODE_128, 2, $altura);
    }

    private function dibujar(string $codigo, ?string $nombre, bool $conGuias = false): GdImage
    {
        $ancho = $this->config->anchoPx();
        $alto = $this->config->altoPx();
        $margen = $this->margenPx();

        $imagen = imagecreatetruecolor(max(1, $ancho), max(1, $alto));
        imageresolution($imagen, $this->config->dpi, $this->config->dpi);
        $blanco = $this->color($imagen, 255, 255, 255);
        $negro = $this->color($imagen, 0, 0, 0);
        imagefilledrectangle($imagen, 0, 0, $ancho - 1, $alto - 1, $blanco);

        $modulo = $this->anchoModuloEfectivo($codigo);
        if ($modulo < 1) {
            throw new RuntimeException("El código {$codigo} no entra en una etiqueta de {$this->config->descripcion()}.");
        }

        // 1) Encabezado: logo y nombre del bien (cortado con "…" si no entra).
        $arriba = $margen;
        $altoEncabezado = 0;
        $mostrarNombre = $this->config->mostrarNombre && filled($nombre);
        if ($conGuias || $mostrarNombre || $this->config->mostrarLogo) {
            $altoEncabezado = min($this->config->mmAPx(3.4), max($this->config->mmAPx(2.6), (int) round($alto * 0.2)));
            $texto = $conGuias ? $this->leyendaDePrueba($modulo) : ($mostrarNombre ? $nombre : null);
            $this->dibujarEncabezado($imagen, $negro, $texto, $arriba, $altoEncabezado, $ancho, $margen, $conGuias || $this->config->mostrarLogo, reducirParaQueEntre: $conGuias);
            $arriba += $altoEncabezado + $this->config->mmAPx(0.6);
        }

        // 2) Texto legible: siempre el código completo, reduciendo la letra hasta que entre.
        $tamanoMaximo = min((int) round($alto * 0.13), (int) round($this->config->mmAPx(3.2) * 0.75));
        $tamanoCodigo = $this->ajustarTamanoFuente($codigo, $this->fuente(true), max(6, $tamanoMaximo), $ancho - 2 * $margen);
        $altoTextoCodigo = $this->altoTexto($codigo, $this->fuente(true), $tamanoCodigo);
        $baseTextoCodigo = $alto - $margen;

        // 3) Barras entre el encabezado y el texto legible.
        $abajoBarras = $baseTextoCodigo - $altoTextoCodigo - $this->config->mmAPx(0.6);
        $altoBarras = min($this->config->mmAPx($this->config->altoBarrasMm), $abajoBarras - $arriba);
        $arribaBarras = $arriba + intdiv(($abajoBarras - $arriba) - $altoBarras, 2);
        $this->dibujarBarras($imagen, $negro, $this->codigoDeBarras($codigo), $modulo, $arribaBarras, $altoBarras);

        $this->escribirCentrado($imagen, $negro, $codigo, $this->fuente(true), $tamanoCodigo, $baseTextoCodigo);

        if ($conGuias) {
            $this->dibujarGuias($imagen, $negro, $modulo);
        }

        return $imagen;
    }

    private function dibujarBarras(GdImage $imagen, int $negro, Barcode $barcode, int $modulo, int $arriba, int $alto): void
    {
        $x = intdiv(imagesx($imagen) - $barcode->getWidth() * $modulo, 2);

        foreach ($barcode->getBars() as $barra) {
            $anchoBarra = $barra->getWidth() * $modulo;
            if ($barra->isBar()) {
                imagefilledrectangle($imagen, $x, $arriba, $x + $anchoBarra - 1, $arriba + $alto - 1, $negro);
            }
            $x += $anchoBarra;
        }
    }

    private function dibujarEncabezado(GdImage $imagen, int $negro, ?string $texto, int $arriba, int $alto, int $ancho, int $margen, bool $conLogo, bool $reducirParaQueEntre = false): void
    {
        $x = $margen;
        $disponible = $ancho - 2 * $margen;

        if ($conLogo && ($logo = $this->logoEnSilueta($alto))) {
            imagecopy($imagen, $logo, $x, $arriba, 0, 0, imagesx($logo), imagesy($logo));
            $separacion = $this->config->mmAPx(1);
            $x += imagesx($logo) + $separacion;
            $disponible -= imagesx($logo) + $separacion;
        }

        if ($texto === null || $disponible <= 0) {
            return;
        }

        $fuente = $this->fuente(true);
        $tamano = max(6, (int) round($alto * 0.55));
        if ($reducirParaQueEntre) {
            $tamano = $this->ajustarTamanoFuente($texto, $fuente, $tamano, $disponible);
        }
        $texto = $this->recortarConPuntosSuspensivos($texto, $fuente, $tamano, $disponible);
        $base = $arriba + (int) round(($alto + $this->altoTexto('ÁNgy', $fuente, $tamano)) / 2) - (int) round($tamano * 0.18);

        // Color negativo: dibuja sin suavizado (las impresoras térmicas imprimen en 1 bit).
        imagettftext($imagen, $tamano, 0, $x, $base, -$negro, $fuente, $texto);
    }

    private function dibujarGuias(GdImage $imagen, int $negro, int $modulo): void
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        imagerectangle($imagen, 0, 0, $ancho - 1, $alto - 1, $negro);

        // Marcas cada 5 mm en los cuatro bordes; más largas cada 10 mm.
        for ($mm = 5; $mm < max($this->config->anchoMm, $this->config->altoMm); $mm += 5) {
            $largo = $this->config->mmAPx($mm % 10 === 0 ? 1.6 : 0.8);
            $p = $this->config->mmAPx($mm);
            if ($mm < $this->config->anchoMm) {
                imageline($imagen, $p, 0, $p, $largo, $negro);
                imageline($imagen, $p, $alto - 1, $p, $alto - 1 - $largo, $negro);
            }
            if ($mm < $this->config->altoMm) {
                imageline($imagen, 0, $p, $largo, $p, $negro);
                imageline($imagen, $ancho - 1, $p, $ancho - 1 - $largo, $p, $negro);
            }
        }
    }

    /** Ej.: "50×25mm 203dpi mód.2=0.25mm". */
    private function leyendaDePrueba(int $modulo): string
    {
        $mm = fn (float $valor) => rtrim(rtrim(number_format($valor, 1, '.', ''), '0'), '.');

        return sprintf('%s×%smm %ddpi mód.%d=%.2fmm', $mm($this->config->anchoMm), $mm($this->config->altoMm), $this->config->dpi, $modulo, $this->config->pxAMm($modulo));
    }

    /**
     * Logo convertido a silueta negra a partir de su canal alfa, escalado a la altura indicada.
     */
    private function logoEnSilueta(int $alto): ?GdImage
    {
        $ruta = public_path(self::LOGO);
        if (! is_file($ruta) || ! ($original = @imagecreatefrompng($ruta))) {
            return null;
        }

        $ancho = max(1, (int) round(imagesx($original) * $alto / imagesy($original)));
        $escalado = imagecreatetruecolor($ancho, max(1, $alto));
        imagealphablending($escalado, false);
        imagesavealpha($escalado, true);
        imagefilledrectangle($escalado, 0, 0, $ancho, $alto, $this->color($escalado, 0, 0, 0, 127));
        imagecopyresampled($escalado, $original, 0, 0, 0, 0, $ancho, $alto, imagesx($original), imagesy($original));

        $silueta = imagecreatetruecolor($ancho, max(1, $alto));
        $blanco = $this->color($silueta, 255, 255, 255);
        $negro = $this->color($silueta, 0, 0, 0);
        imagefilledrectangle($silueta, 0, 0, $ancho, $alto, $blanco);

        for ($x = 0; $x < $ancho; $x++) {
            for ($y = 0; $y < $alto; $y++) {
                $alfa = (imagecolorat($escalado, $x, $y) >> 24) & 0x7F;
                if ($alfa < 64) {
                    imagesetpixel($silueta, $x, $y, $negro);
                }
            }
        }

        return $silueta;
    }

    private function escribirCentrado(GdImage $imagen, int $negro, string $texto, string $fuente, int $tamano, int $base): void
    {
        $caja = $this->cajaDeTexto($texto, $fuente, $tamano);
        $x = intdiv(imagesx($imagen) - ($caja[2] - $caja[0]), 2) - $caja[0];

        imagettftext($imagen, $tamano, 0, $x, $base - $caja[1], -$negro, $fuente, $texto);
    }

    private function recortarConPuntosSuspensivos(string $texto, string $fuente, int $tamano, int $anchoMaximo): string
    {
        if ($this->anchoTexto($texto, $fuente, $tamano) <= $anchoMaximo) {
            return $texto;
        }

        $caracteres = mb_str_split($texto);
        $bajo = 0;
        $alto = count($caracteres);

        // Búsqueda binaria del prefijo más largo que entra junto con "…".
        while ($bajo < $alto) {
            $medio = intdiv($bajo + $alto + 1, 2);
            $candidato = rtrim(implode('', array_slice($caracteres, 0, $medio))).'…';
            if ($this->anchoTexto($candidato, $fuente, $tamano) <= $anchoMaximo) {
                $bajo = $medio;
            } else {
                $alto = $medio - 1;
            }
        }

        return $bajo === 0 ? '…' : rtrim(implode('', array_slice($caracteres, 0, $bajo))).'…';
    }

    private function ajustarTamanoFuente(string $texto, string $fuente, int $tamano, int $anchoMaximo): int
    {
        while ($tamano > 4 && $this->anchoTexto($texto, $fuente, $tamano) > $anchoMaximo) {
            $tamano--;
        }

        return $tamano;
    }

    private function anchoTexto(string $texto, string $fuente, int $tamano): int
    {
        $caja = $this->cajaDeTexto($texto, $fuente, $tamano);

        return $caja[2] - $caja[0];
    }

    private function altoTexto(string $texto, string $fuente, int $tamano): int
    {
        $caja = $this->cajaDeTexto($texto, $fuente, $tamano);

        return $caja[1] - $caja[7];
    }

    /**
     * Caja que ocupa el texto (ver imagettfbbox).
     *
     * @return array<int, int>
     */
    private function cajaDeTexto(string $texto, string $fuente, int $tamano): array
    {
        $caja = imagettfbbox($tamano, 0, $fuente, $texto);
        if ($caja === false) {
            throw new RuntimeException("No se pudo medir el texto con la fuente {$fuente}.");
        }

        return $caja;
    }

    /**
     * @param  int<0, 255>  $rojo
     * @param  int<0, 255>  $verde
     * @param  int<0, 255>  $azul
     * @param  int<0, 127>  $alfa  0 = opaco, 127 = transparente
     */
    private function color(GdImage $imagen, int $rojo, int $verde, int $azul, int $alfa = 0): int
    {
        $color = imagecolorallocatealpha($imagen, $rojo, $verde, $azul, $alfa);
        if ($color === false) {
            throw new RuntimeException('No se pudo reservar un color para la etiqueta.');
        }

        return $color;
    }

    private function codigoDeBarras(string $codigo): Barcode
    {
        return (new TypeCode128)->getBarcode($codigo);
    }

    private function margenPx(): int
    {
        return max(2, $this->config->mmAPx(1));
    }

    private function fuente(bool $negrita): string
    {
        return base_path('vendor/dompdf/dompdf/lib/fonts/'.($negrita ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf'));
    }

    private function aPng(GdImage $imagen): string
    {
        ob_start();
        imagepng($imagen, null, 9);

        return (string) ob_get_clean();
    }
}
