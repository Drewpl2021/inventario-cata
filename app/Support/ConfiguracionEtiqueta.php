<?php

namespace App\Support;

use App\Models\Configuracion;

/**
 * Formato de la etiqueta térmica: tamaño, resolución de la impresora y
 * contenido. Se guarda en `configuraciones` con la clave "etiquetas".
 */
final class ConfiguracionEtiqueta
{
    public const CLAVE = 'etiquetas';

    /** Presets de tamaño en milímetros (ancho × alto). */
    public const PRESETS = [
        '50x25' => [50, 25],
        '40x30' => [40, 30],
        '30x20' => [30, 20],
    ];

    public const DPI_PERMITIDOS = [203, 300];

    public function __construct(
        public readonly string $preset,
        public readonly float $anchoMm,
        public readonly float $altoMm,
        public readonly int $dpi,
        public readonly int $anchoModulo,
        public readonly float $altoBarrasMm,
        public readonly bool $mostrarNombre,
        public readonly bool $mostrarLogo,
    ) {}

    public static function predeterminada(): self
    {
        return self::desdeArray([]);
    }

    public static function actual(): self
    {
        return self::desdeArray(Configuracion::obtener(self::CLAVE) ?? []);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArray(array $datos): self
    {
        $preset = (string) ($datos['preset'] ?? '50x25');
        [$ancho, $alto] = self::PRESETS[$preset] ?? [$datos['ancho_mm'] ?? 50, $datos['alto_mm'] ?? 25];

        return new self(
            preset: array_key_exists($preset, self::PRESETS) ? $preset : 'personalizado',
            anchoMm: (float) $ancho,
            altoMm: (float) $alto,
            dpi: (int) ($datos['dpi'] ?? 203),
            anchoModulo: (int) ($datos['ancho_modulo'] ?? 2),
            altoBarrasMm: (float) ($datos['alto_barras_mm'] ?? 10),
            mostrarNombre: (bool) ($datos['mostrar_nombre'] ?? true),
            mostrarLogo: (bool) ($datos['mostrar_logo'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset,
            'ancho_mm' => $this->anchoMm,
            'alto_mm' => $this->altoMm,
            'dpi' => $this->dpi,
            'ancho_modulo' => $this->anchoModulo,
            'alto_barras_mm' => $this->altoBarrasMm,
            'mostrar_nombre' => $this->mostrarNombre,
            'mostrar_logo' => $this->mostrarLogo,
        ];
    }

    public function guardar(): void
    {
        Configuracion::guardar(self::CLAVE, $this->toArray());
    }

    /** Ancho de la etiqueta en puntos de la impresora. */
    public function anchoPx(): int
    {
        return $this->mmAPx($this->anchoMm);
    }

    /** Alto de la etiqueta en puntos de la impresora. */
    public function altoPx(): int
    {
        return $this->mmAPx($this->altoMm);
    }

    public function mmAPx(float $milimetros): int
    {
        return (int) round($milimetros / 25.4 * $this->dpi);
    }

    public function pxAMm(int $puntos): float
    {
        return $puntos / $this->dpi * 25.4;
    }

    public function descripcion(): string
    {
        return sprintf('%s × %s mm a %d dpi', $this->formatearMm($this->anchoMm), $this->formatearMm($this->altoMm), $this->dpi);
    }

    private function formatearMm(float $milimetros): string
    {
        return rtrim(rtrim(number_format($milimetros, 1, '.', ''), '0'), '.');
    }
}
