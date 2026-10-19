<?php

namespace Database\Seeders;

use App\Enums\RolSistema;
use App\Models\Area;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Movimiento;
use App\Models\Requerimiento;
use App\Models\Responsable;
use App\Models\Role;
use App\Models\Sede;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Datos ficticios pero realistas para pruebas: 3 sedes con sus áreas,
 * categorías, responsables, ~50 bienes y una cuenta por cada rol.
 *
 * Toda área de un bien o responsable pertenece a su misma sede.
 */
class DemoSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string}> correo => [contraseña, rol] */
    public const CREDENCIALES = [
        'admin@sibip.local' => ['Admin2026!', 'Administrador'],
        'operador@sibip.local' => ['Operador2026!', 'Operador'],
        'auditor@sibip.local' => ['Auditor2026!', 'Auditor'],
        'usuario@sibip.local' => ['Usuario2026!', 'Usuario'],
    ];

    private const SEDES = [
        'S01' => ['Sede 01 - Administración', 'Jr. Túpac Amaru 245, Juliaca', '(051) 321456', [
            'DIR' => ['Dirección General', 'Oficina', 'Pabellón A, 2.° piso'],
            'SEC' => ['Secretaría Académica', 'Oficina', 'Pabellón A, 1.er piso'],
            'CON' => ['Contabilidad y Tesorería', 'Oficina', 'Pabellón A, oficina 203'],
            'SUM' => ['Sala de Reuniones', 'Sala', 'Pabellón A, 2.° piso'],
            'ALM' => ['Almacén Central', 'Almacén', 'Patio posterior'],
        ]],
        'S02' => ['Sede 02 - Primaria', 'Av. Circunvalación Norte 1180, Juliaca', '(051) 327890', [
            'A01' => ['Aula 1.° Primaria', 'Aula', 'Pabellón B, aula 01'],
            'A02' => ['Aula 2.° Primaria', 'Aula', 'Pabellón B, aula 02'],
            'A03' => ['Aula 3.° Primaria', 'Aula', 'Pabellón B, aula 03'],
            'DOC' => ['Sala de Docentes', 'Sala', 'Pabellón B, 1.er piso'],
            'BIB' => ['Biblioteca', 'Biblioteca', 'Pabellón C'],
            'CPU' => ['Sala de Cómputo Primaria', 'Laboratorio', 'Pabellón C, 2.° piso'],
        ]],
        'S03' => ['Sede 03 - Secundaria', 'Jr. Jerusalén 560, Juliaca', '(051) 329012', [
            'A11' => ['Aula 1.° Secundaria', 'Aula', 'Pabellón D, aula 11'],
            'A12' => ['Aula 2.° Secundaria', 'Aula', 'Pabellón D, aula 12'],
            'LAB' => ['Laboratorio de Ciencias', 'Laboratorio', 'Pabellón E'],
            'CPU' => ['Sala de Cómputo Secundaria', 'Laboratorio', 'Pabellón E, 2.° piso'],
            'AUD' => ['Auditorio', 'Auditorio', 'Pabellón F'],
            'IAL' => ['Taller de Industrias Alimentarias', 'Taller', 'Pabellón E, aula 02'],
        ]],
    ];

    private const CATEGORIAS = [
        'Equipos informáticos' => 'Computadoras, laptops, servidores, impresoras y periféricos',
        'Mobiliario' => 'Escritorios, sillas, estantes, mesas y muebles de oficina',
        'Equipos electrónicos' => 'Televisores, sistemas de sonido, UPS y electrodomésticos',
        'Equipos audiovisuales' => 'Proyectores, cámaras, micrófonos y pantallas',
        'Equipos de laboratorio' => 'Instrumentos de medición y equipos especializados',
        'Herramientas' => 'Herramientas manuales, eléctricas y de mantenimiento',
        'Material deportivo' => 'Implementos de educación física y deporte',
        'Otros' => 'Bienes patrimoniales diversos',
    ];

    /** sede => área => [nombres, apellidos, dni, cargo] */
    private const RESPONSABLES = [
        ['S01', 'DIR', 'Rubén Elías', 'Mamani Quispe', '40125678', 'Director General'],
        ['S01', 'SEC', 'María Elena', 'López Condori', '42567812', 'Secretaria Académica'],
        ['S01', 'CON', 'Josué David', 'Apaza Huanca', '43678921', 'Contador'],
        ['S01', 'ALM', 'Samuel', 'Ccama Flores', '44789032', 'Encargado de Almacén'],
        ['S02', 'DOC', 'Raquel Noemí', 'Choque Ticona', '45890143', 'Coordinadora de Primaria'],
        ['S02', 'BIB', 'Débora', 'Quispe Mamani', '46901254', 'Bibliotecaria'],
        ['S02', 'CPU', 'Caleb', 'Huanca Pari', '47012365', 'Docente de Computación'],
        ['S03', 'LAB', 'Ester Abigail', 'Pari Coaquira', '48123476', 'Docente de Ciencias'],
        ['S03', 'CPU', 'Daniel', 'Coaquira Limachi', '49234587', 'Soporte Técnico'],
        ['S03', 'IAL', 'Karla Damaris', 'Quispe Ordóñez', '75543719', 'Docente de Industrias Alimentarias'],
        ['S03', 'AUD', 'Isaac', 'Limachi Calla', '41345698', 'Coordinador de Secundaria'],
    ];

    /** [nombre, categoría, marca, modelo, valor, área(s) sugeridas] */
    private const BIENES = [
        ['Laptop', 'Equipos informáticos', 'Lenovo', 'ThinkPad E14', 3200, ['S01.DIR', 'S01.SEC', 'S01.CON', 'S02.DOC', 'S03.CPU']],
        ['Computadora de escritorio', 'Equipos informáticos', 'HP', 'ProDesk 400 G9', 2800, ['S02.CPU', 'S02.CPU', 'S02.CPU', 'S03.CPU', 'S03.CPU', 'S03.CPU', 'S01.SEC']],
        ['Impresora multifuncional', 'Equipos informáticos', 'Epson', 'EcoTank L6270', 1150, ['S01.SEC', 'S01.CON', 'S02.DOC']],
        ['Escritorio de oficina', 'Mobiliario', 'Muebles Juliaca', 'EO-120', 450, ['S01.DIR', 'S01.SEC', 'S01.CON', 'S02.DOC']],
        ['Silla ergonómica', 'Mobiliario', 'Ofisillas', 'Ergo Plus', 380, ['S01.DIR', 'S01.SEC', 'S01.CON', 'S01.SUM', 'S01.SUM']],
        ['Mesa unipersonal', 'Mobiliario', 'Muebles Juliaca', 'MU-60', 160, ['S02.A01', 'S02.A02', 'S02.A03', 'S03.A11', 'S03.A12']],
        ['Estante metálico', 'Mobiliario', 'Metalúrgica Sur', 'EM-5', 290, ['S01.ALM', 'S02.BIB', 'S02.BIB', 'S03.LAB']],
        ['Pizarra acrílica', 'Mobiliario', 'Artesco', 'PA-240', 320, ['S02.A01', 'S02.A02', 'S03.A11']],
        ['Proyector multimedia', 'Equipos audiovisuales', 'Epson', 'PowerLite X49', 2100, ['S01.SUM', 'S02.DOC', 'S03.AUD', 'S03.A12']],
        ['Televisor LED 55"', 'Equipos electrónicos', 'Samsung', 'UN55CU7000', 2300, ['S02.A03', 'S03.A11']],
        ['Equipo de sonido', 'Equipos electrónicos', 'Yamaha', 'Stagepas 400BT', 3500, ['S03.AUD']],
        ['UPS', 'Equipos electrónicos', 'APC', 'Back-UPS 1500', 890, ['S03.CPU', 'S01.CON']],
        ['Microscopio binocular', 'Equipos de laboratorio', 'Olympus', 'CX23', 4200, ['S03.LAB', 'S03.LAB']],
        ['Balanza analítica', 'Equipos de laboratorio', 'Ohaus', 'Pioneer PX224', 3900, ['S03.LAB']],
        ['Horno industrial', 'Equipos electrónicos', 'Oster', 'TSSTTVXLDG', 950, ['S03.IAL']],
        ['Licuadora industrial', 'Equipos electrónicos', 'Oster', 'BLSTPB', 520, ['S03.IAL']],
        ['Taladro percutor', 'Herramientas', 'Bosch', 'GSB 13 RE', 410, ['S01.ALM']],
        ['Juego de pelotas de vóley', 'Material deportivo', 'Mikasa', 'V200W', 600, ['S01.ALM']],
        ['Cámara fotográfica', 'Equipos audiovisuales', 'Canon', 'EOS Rebel T7', 2600, ['S01.SEC']],
        ['Archivador de 4 gavetas', 'Mobiliario', 'Metalúrgica Sur', 'AR-4', 540, ['S01.SEC', 'S01.CON']],
    ];

    public function run(): void
    {
        Model::withoutEvents(function () {
            $sedes = $this->sembrarSedesYAreas();
            $categorias = $this->sembrarCategorias();
            $responsables = $this->sembrarResponsables($sedes);
            $this->sembrarBienes($sedes, $categorias, $responsables);
            $usuarios = $this->sembrarUsuarios($sedes, $responsables);
            $this->sembrarSolicitudes($usuarios);
        });

        $this->imprimirCredenciales();
    }

    /**
     * @return array<string, Sede>
     */
    private function sembrarSedesYAreas(): array
    {
        $sedes = [];

        foreach (self::SEDES as $codigo => [$nombre, $direccion, $telefono, $areas]) {
            $sede = Sede::withTrashed()->firstOrCreate(['codigo' => $codigo], [
                'nombre' => $nombre,
                'direccion' => $direccion,
                'telefono' => $telefono,
                'estado' => 'activo',
            ]);

            foreach ($areas as $codigoArea => [$nombreArea, $tipo, $ubicacion]) {
                Area::withTrashed()->firstOrCreate(['sede_id' => $sede->id, 'codigo' => $codigoArea], [
                    'nombre' => $nombreArea,
                    'tipo' => $tipo,
                    'ubicacion' => $ubicacion,
                    'estado' => 'activo',
                ]);
            }

            $sedes[$codigo] = $sede->load('areas');
        }

        return $sedes;
    }

    /**
     * @return array<string, Categoria>
     */
    private function sembrarCategorias(): array
    {
        $categorias = [];

        foreach (self::CATEGORIAS as $nombre => $descripcion) {
            $categorias[$nombre] = Categoria::withTrashed()->firstOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion, 'estado' => 'activo']);
        }

        return $categorias;
    }

    /**
     * @param  array<string, Sede>  $sedes
     * @return array<string, Responsable> indexado por "SEDE.AREA"
     */
    private function sembrarResponsables(array $sedes): array
    {
        $responsables = [];

        foreach (self::RESPONSABLES as [$codigoSede, $codigoArea, $nombres, $apellidos, $dni, $cargo]) {
            $area = $this->area($sedes, "{$codigoSede}.{$codigoArea}");

            $responsables["{$codigoSede}.{$codigoArea}"] = Responsable::withTrashed()->firstOrCreate(['dni' => $dni], [
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'cargo' => $cargo,
                'sede_id' => $area->sede_id,
                'area_id' => $area->id,
                'email' => Str::lower(Str::ascii(explode(' ', $nombres)[0].'.'.explode(' ', $apellidos)[0])).'@sibip.local',
                'estado' => 'activo',
            ]);
        }

        return $responsables;
    }

    /**
     * @param  array<string, Sede>  $sedes
     * @param  array<string, Categoria>  $categorias
     * @param  array<string, Responsable>  $responsables
     */
    private function sembrarBienes(array $sedes, array $categorias, array $responsables): void
    {
        mt_srand(2026);
        $estados = ['bueno', 'bueno', 'bueno', 'bueno', 'nuevo', 'nuevo', 'regular', 'regular', 'mantenimiento', 'malo'];
        $numero = 0;

        foreach (self::BIENES as [$nombre, $categoria, $marca, $modelo, $valor, $ubicaciones]) {
            foreach ($ubicaciones as $ubicacion) {
                $numero++;
                $area = $this->area($sedes, $ubicacion);
                $codigoSede = explode('.', $ubicacion)[0];

                // Responsable del área o, si no hay, el primero de la misma sede.
                $responsable = $responsables[$ubicacion]
                    ?? collect($responsables)->first(fn (Responsable $r, string $clave) => str_starts_with($clave, "{$codigoSede}."));

                $esDonacion = mt_rand(1, 10) <= 2;

                $bien = Bien::withTrashed()->firstOrCreate(['codigo_interno' => str_pad((string) $numero, 5, '0', STR_PAD_LEFT)], [
                    // Los bienes donados aún no tienen código patrimonial oficial.
                    'codigo_patrimonial' => $esDonacion ? null : '740805'.str_pad((string) $numero, 6, '0', STR_PAD_LEFT),
                    'nombre' => $nombre,
                    'descripcion' => "{$nombre} {$marca} {$modelo}",
                    'marca' => $marca,
                    'modelo' => $modelo,
                    'numero_serie' => strtoupper(substr(md5("{$marca}{$numero}"), 0, 10)),
                    'categoria_id' => $categorias[$categoria]->id,
                    'sede_id' => $area->sede_id,
                    'area_id' => $area->id,
                    'responsable_id' => $responsable?->id,
                    'fecha_adquisicion' => now()->subDays(mt_rand(60, 1800))->toDateString(),
                    'valor_adquisicion' => $valor,
                    'estado' => $estados[mt_rand(0, count($estados) - 1)],
                    'tipo_ingreso' => $esDonacion ? 'donacion' : 'con_comprobante',
                    'numero_comprobante' => $esDonacion ? 'ACTA-DON-'.str_pad((string) $numero, 3, '0', STR_PAD_LEFT) : 'F001-'.str_pad((string) (1000 + $numero), 6, '0', STR_PAD_LEFT),
                ]);

                // El seeder corre sin eventos de modelo: el código se asigna explícitamente.
                $bien->asignarCodigoBarras();
            }
        }
    }

    /**
     * @param  array<string, Sede>  $sedes
     * @param  array<string, Responsable>  $responsables
     * @return array<string, Usuario> indexado por nombre de rol
     */
    private function sembrarUsuarios(array $sedes, array $responsables): array
    {
        $datos = [
            'admin@sibip.local' => ['Administrador', 'General', null],
            'operador@sibip.local' => ['Operador', 'Patrimonial', null],
            'auditor@sibip.local' => ['Auditor', 'Interno', null],
            // La cuenta de rol Usuario queda vinculada a la responsable de Secretaría.
            'usuario@sibip.local' => ['María', 'López', 'S01.SEC'],
        ];

        $usuarios = [];

        foreach ($datos as $email => [$nombre, $apellido, $ubicacion]) {
            [$password, $nombreRol] = self::CREDENCIALES[$email];
            $area = $ubicacion ? $this->area($sedes, $ubicacion) : null;

            $usuario = Usuario::withTrashed()->firstOrCreate(['email' => $email], [
                'nombre' => $nombre,
                'apellido' => $apellido,
                'password' => $password,
                'rol_id' => Role::delSistema(RolSistema::from($nombreRol))->id,
                'sede_id' => $area?->sede_id,
                'area_id' => $area?->id,
                'estado' => 'activo',
                'debe_cambiar_password' => true,
            ]);

            if ($ubicacion) {
                $responsables[$ubicacion]->update(['usuario_id' => $usuario->id]);
            }

            $usuarios[$nombreRol] = $usuario;
        }

        return $usuarios;
    }

    /**
     * Algunas solicitudes pendientes para probar las bandejas de aprobación.
     *
     * @param  array<string, Usuario>  $usuarios
     */
    private function sembrarSolicitudes(array $usuarios): void
    {
        $usuario = $usuarios['Usuario'];
        $responsable = Responsable::where('usuario_id', $usuario->id)->firstOrFail();
        $misBienes = Bien::where('responsable_id', $responsable->id)->orderBy('id')->take(2)->get();
        [$bienAMover, $bienADarDeBaja] = $misBienes->count() === 2 ? $misBienes->all() : [null, null];

        if ($bienAMover && Movimiento::count() === 0) {
            $destino = Area::whereHas('sede', fn ($q) => $q->where('codigo', 'S01'))->where('codigo', 'SUM')->firstOrFail();

            Movimiento::create([
                'bien_id' => $bienAMover->id,
                'sede_origen_id' => $bienAMover->sede_id,
                'area_origen_id' => $bienAMover->area_id,
                'sede_destino_id' => $destino->sede_id,
                'area_destino_id' => $destino->id,
                'responsable_anterior_id' => $responsable->id,
                'responsable_nuevo_id' => $responsable->id,
                'tipo' => 'reubicacion',
                'estado' => 'solicitado',
                'motivo' => 'Se necesita en la sala de reuniones para las sesiones del consejo.',
                'fecha_movimiento' => now()->subDay(),
                'usuario_id' => $usuario->id,
            ]);
        }

        if ($bienADarDeBaja && Baja::count() === 0) {
            Baja::create([
                'bien_id' => $bienADarDeBaja->id,
                'motivo' => 'deterioro',
                'estado' => 'solicitada',
                'descripcion' => 'Presenta desgaste por uso continuo y falla al encender.',
                'fecha_baja' => now()->toDateString(),
                'valor_residual' => 0,
                'usuario_id' => $usuario->id,
            ]);
        }

        if (Requerimiento::count() === 0) {
            Requerimiento::create([
                'codigo' => 'REQ-'.now()->year.'-0001',
                'usuario_id' => $usuario->id,
                'tipo_requerimiento' => 'Equipos de cómputo',
                'descripcion' => 'Una impresora láser para la emisión de constancias.',
                'justificacion' => 'La impresora actual no abastece la demanda de inicio de año.',
                'cantidad' => 1,
                'costo_estimado' => 1200,
                'sede_id' => $usuario->sede_id,
                'area_id' => $usuario->area_id,
                'prioridad' => 'alta',
                'estado' => 'pendiente',
                'fecha_solicitud' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, Sede>  $sedes
     */
    private function area(array $sedes, string $ubicacion): Area
    {
        [$codigoSede, $codigoArea] = explode('.', $ubicacion);

        return $sedes[$codigoSede]->areas->firstWhere('codigo', $codigoArea)
            ?? throw new RuntimeException("No existe el área {$ubicacion} en los datos de demostración.");
    }

    private function imprimirCredenciales(): void
    {
        $this->command->newLine();
        $this->command->info('Credenciales de prueba (el sistema pide cambiar la contraseña en el primer ingreso):');
        $this->command->table(
            ['Rol', 'Correo', 'Contraseña'],
            collect(self::CREDENCIALES)->map(fn (array $datos, string $correo) => [$datos[1], $correo, $datos[0]])->values()->all(),
        );
    }
}
