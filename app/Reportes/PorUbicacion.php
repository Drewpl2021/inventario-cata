<?php

namespace App\Reportes;

use App\Models\Bien;
use Illuminate\Database\Eloquent\Model;

class PorUbicacion extends ReporteDeBienes
{
    public function clave(): string
    {
        return 'por-ubicacion';
    }

    public function titulo(): string
    {
        return 'Inventario por ubicación';
    }

    public function descripcion(): string
    {
        return 'Ubicación física de los bienes: sede y área.';
    }

    public function icono(): string
    {
        return 'fa-location-dot';
    }

    public function tituloGrupo(): ?string
    {
        return 'Ubicación';
    }

    protected function grupo(Model $registro): ?string
    {
        /** @var Bien $registro */
        return trim(($registro->sede->nombre ?? 'Sin sede').' / '.($registro->area->nombre ?? 'Sin área'));
    }
}
