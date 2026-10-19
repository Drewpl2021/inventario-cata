<?php

namespace App\Reportes;

use App\Models\Bien;
use Illuminate\Database\Eloquent\Model;

/**
 * Bienes a cargo de cada responsable. El PDF es el "Acta de asignación de
 * bienes", con una página por responsable y espacio para las firmas.
 */
class PorResponsable extends ReporteDeBienes
{
    public function clave(): string
    {
        return 'por-responsable';
    }

    public function titulo(): string
    {
        return 'Bienes por responsable';
    }

    public function descripcion(): string
    {
        return 'Bienes asignados a cada persona, con acta de asignación para firmar.';
    }

    public function icono(): string
    {
        return 'fa-user';
    }

    public function filtros(): array
    {
        return ['responsable_id', 'sede_id', 'area_id', 'categoria_id', 'estado'];
    }

    public function tituloGrupo(): ?string
    {
        return 'Responsable';
    }

    public function vistaPdf(): string
    {
        return 'reportes.pdf.acta';
    }

    /**
     * El acta necesita los datos del responsable (DNI, cargo, área), no solo su nombre.
     */
    protected function fila(Model $registro): array
    {
        return [...parent::fila($registro), '_responsable_id' => $registro->getAttribute('responsable_id')];
    }

    protected function grupo(Model $registro): ?string
    {
        /** @var Bien $registro */
        return $registro->responsable->nombre_completo ?? 'Sin responsable asignado';
    }
}
