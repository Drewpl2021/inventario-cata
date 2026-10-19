<?php

namespace App\Reportes;

use App\Models\Bien;
use Illuminate\Database\Eloquent\Model;

class PorSede extends ReporteDeBienes
{
    public function clave(): string
    {
        return 'por-sede';
    }

    public function titulo(): string
    {
        return 'Inventario por sede';
    }

    public function descripcion(): string
    {
        return 'Bienes agrupados por sede institucional, con subtotales.';
    }

    public function icono(): string
    {
        return 'fa-chart-column';
    }

    public function tituloGrupo(): ?string
    {
        return 'Sede';
    }

    protected function grupo(Model $registro): ?string
    {
        /** @var Bien $registro */
        return $registro->sede->nombre ?? 'Sin sede';
    }
}
