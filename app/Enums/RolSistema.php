<?php

namespace App\Enums;

/**
 * Roles base del sistema. Sus nombres no se pueden cambiar ni eliminar.
 */
enum RolSistema: string
{
    case Administrador = 'Administrador';
    case Operador = 'Operador';
    case Auditor = 'Auditor';
    case Usuario = 'Usuario';

    public function descripcion(): string
    {
        return match ($this) {
            self::Administrador => 'Acceso total al sistema de inventario SIBIP',
            self::Operador => 'Registro de bienes, etiquetas y toma de inventario; registra solicitudes de movimientos, bajas y requerimientos',
            self::Auditor => 'Solo lectura y exportación; reportes y auditoría',
            self::Usuario => 'Responsable de los bienes de un área; consulta sus bienes y registra solicitudes',
        };
    }
}
