<?php

namespace App\Reportes;

use App\Models\Bien;
use Illuminate\Database\Eloquent\Model;

class PorCategoria extends ReporteDeBienes
{
    public function clave(): string
    {
        return 'por-categoria';
    }

    public function titulo(): string
    {
        return 'Inventario por categoría';
    }

    public function descripcion(): string
    {
        return 'Clasificación por tipo de bien, con subtotales.';
    }

    public function icono(): string
    {
        return 'fa-table-cells-large';
    }

    public function tituloGrupo(): ?string
    {
        return 'Categoría';
    }

    protected function grupo(Model $registro): ?string
    {
        /** @var Bien $registro */
        return $registro->categoria->nombre ?? 'Sin categoría';
    }
}
