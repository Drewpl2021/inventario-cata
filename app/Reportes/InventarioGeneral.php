<?php

namespace App\Reportes;

class InventarioGeneral extends ReporteDeBienes
{
    public function clave(): string
    {
        return 'inventario-general';
    }

    public function titulo(): string
    {
        return 'Inventario general';
    }

    public function descripcion(): string
    {
        return 'Lista completa de todos los bienes.';
    }

    public function icono(): string
    {
        return 'fa-clipboard-list';
    }
}
