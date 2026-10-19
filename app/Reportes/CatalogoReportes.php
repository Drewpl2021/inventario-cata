<?php

namespace App\Reportes;

/**
 * Reportes disponibles, en el orden en que se muestran.
 */
final class CatalogoReportes
{
    /** @var list<class-string<Reporte>> */
    private const REPORTES = [
        InventarioGeneral::class,
        PorSede::class,
        PorCategoria::class,
        PorResponsable::class,
        PorUbicacion::class,
        DadosDeBaja::class,
        TomaDeInventario::class,
        HistorialMovimientos::class,
    ];

    /**
     * @return array<string, Reporte>
     */
    public static function todos(): array
    {
        $reportes = [];
        foreach (self::REPORTES as $clase) {
            $reporte = new $clase;
            $reportes[$reporte->clave()] = $reporte;
        }

        return $reportes;
    }

    public static function buscar(string $clave): ?Reporte
    {
        return self::todos()[$clave] ?? null;
    }
}
