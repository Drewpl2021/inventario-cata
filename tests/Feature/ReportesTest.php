<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Jobs\GenerarReporteExcel;
use App\Models\Area;
use App\Models\Auditoria;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\InventarioDetalle;
use App\Models\Movimiento;
use App\Models\Responsable;
use App\Models\Sede;
use App\Models\Usuario;
use App\Notifications\ReporteListo;
use App\Reportes\CatalogoReportes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Sede $sedeA;

    private Sede $sedeB;

    private Responsable $responsable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuarioCon(RolSistema::Administrador);
        $this->sedeA = Sede::factory()->create(['nombre' => 'Sede Alfa', 'codigo' => 'S01']);
        $this->sedeB = Sede::factory()->create(['nombre' => 'Sede Beta', 'codigo' => 'S02']);
        $areaA = Area::factory()->create(['sede_id' => $this->sedeA->id]);
        $areaB = Area::factory()->create(['sede_id' => $this->sedeB->id]);
        $muebles = Categoria::factory()->create(['nombre' => 'Muebles']);
        $this->responsable = Responsable::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Quispe', 'dni' => '40000001', 'area_id' => $areaA->id, 'sede_id' => $this->sedeA->id]);

        Bien::factory()->create(['nombre' => 'Mesa A1', 'area_id' => $areaA->id, 'sede_id' => $this->sedeA->id, 'categoria_id' => $muebles->id, 'valor_adquisicion' => 100.50, 'fecha_adquisicion' => '2025-01-10', 'responsable_id' => $this->responsable->id]);
        Bien::factory()->create(['nombre' => 'Mesa A2', 'area_id' => $areaA->id, 'sede_id' => $this->sedeA->id, 'categoria_id' => $muebles->id, 'valor_adquisicion' => 200.25, 'fecha_adquisicion' => '2026-03-01', 'responsable_id' => $this->responsable->id]);
        Bien::factory()->create(['nombre' => 'Mesa B1', 'area_id' => $areaB->id, 'sede_id' => $this->sedeB->id, 'valor_adquisicion' => 50, 'fecha_adquisicion' => '2026-05-05']);
        Bien::factory()->dadoDeBaja()->create(['nombre' => 'Mesa rota', 'area_id' => $areaA->id, 'sede_id' => $this->sedeA->id, 'valor_adquisicion' => 999]);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function reportesConFiltros(): array
    {
        return [
            'inventario general' => ['inventario-general', ['categoria_id' => null, 'desde' => '2025-01-01', 'hasta' => '2026-12-31']],
            'por sede' => ['por-sede', ['estado' => 'bueno']],
            'por categoría' => ['por-categoria', []],
            'por responsable' => ['por-responsable', []],
            'por ubicación' => ['por-ubicacion', ['area_id' => null]],
            'dados de baja' => ['dados-de-baja', ['desde' => '2026-01-01']],
            'historial de movimientos' => ['historial-movimientos', ['desde' => '2026-01-01', 'hasta' => '2026-12-31', 'estado' => 'aprobado']],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    #[DataProvider('reportesConFiltros')]
    public function test_cada_reporte_muestra_su_vista_previa_y_exporta_pdf_y_excel(string $clave, array $filtros): void
    {
        $filtros = array_filter($filtros, fn ($v) => $v !== null);

        $this->actingAs($this->admin)->get(route('reportes.show', [$clave, ...$filtros]))
            ->assertOk()
            ->assertSee('Vista previa');

        $pdf = $this->actingAs($this->admin)->get(route('reportes.exportar', [$clave, 'pdf', ...$filtros]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);

        $this->actingAs($this->admin)->get(route('reportes.exportar', [$clave, 'xlsx', ...$filtros]))->assertOk()->assertDownload();
    }

    public function test_la_vista_previa_se_pagina_de_25_en_25_con_totales_de_todo_el_reporte(): void
    {
        Bien::factory()->count(27)->create(['area_id' => Area::factory()->create(['sede_id' => $this->sedeB->id])->id, 'sede_id' => $this->sedeB->id, 'valor_adquisicion' => 10]);
        $ultimo = Bien::where('estado', '!=', 'dado_de_baja')->orderByDesc('codigo_barras')->first();

        $this->actingAs($this->admin)->get(route('reportes.show', 'inventario-general'))
            ->assertOk()
            ->assertSee('Página 1 de 2')
            ->assertSee('S/ 620.75')
            ->assertDontSee($ultimo->codigo_barras);

        $this->actingAs($this->admin)->get(route('reportes.show', ['inventario-general', 'page' => 2]))
            ->assertSee('Página 2 de 2')
            ->assertSee($ultimo->codigo_barras);
    }

    public function test_el_inventario_general_filtra_y_suma_el_valor_de_adquisicion(): void
    {
        $this->actingAs($this->admin)->get(route('reportes.show', ['inventario-general', 'sede_id' => $this->sedeA->id]))
            ->assertSee('Mesa A1')->assertSee('Mesa A2')
            ->assertDontSee('Mesa B1')
            ->assertDontSee('Mesa rota')
            ->assertSee('S/ 300.75');

        $reporte = CatalogoReportes::buscar('inventario-general');
        $filas = $reporte->filas(['desde' => '2026-01-01']);
        $this->assertSame(['Mesa A2', 'Mesa B1'], $filas->pluck('nombre')->sort()->values()->all());
        $this->assertSame(250.25, $reporte->totales($filas)['valor']);
    }

    public function test_por_sede_calcula_subtotales_por_grupo(): void
    {
        $reporte = CatalogoReportes::buscar('por-sede');
        $totales = $reporte->totales($reporte->filas([]));

        $this->assertSame(3, $totales['cantidad']);
        $this->assertSame(['cantidad' => 2, 'valor' => 300.75], $totales['grupos']['Sede Alfa']);
        $this->assertSame(['cantidad' => 1, 'valor' => 50.0], $totales['grupos']['Sede Beta']);
    }

    public function test_dados_de_baja_y_movimientos_filtran_por_fechas(): void
    {
        $rota = Bien::where('nombre', 'Mesa rota')->sole();
        Baja::create(['bien_id' => $rota->id, 'motivo' => 'deterioro', 'estado' => 'aprobada', 'fecha_baja' => '2026-02-01', 'usuario_id' => $this->admin->id, 'valor_residual' => 10]);
        Movimiento::forceCreate(['bien_id' => $rota->id, 'sede_destino_id' => $this->sedeB->id, 'tipo' => 'traslado', 'estado' => 'aprobado', 'fecha_movimiento' => '2026-04-10 10:00:00', 'usuario_id' => $this->admin->id]);
        Movimiento::forceCreate(['bien_id' => $rota->id, 'sede_destino_id' => $this->sedeB->id, 'tipo' => 'traslado', 'estado' => 'aprobado', 'fecha_movimiento' => '2025-04-10 10:00:00', 'usuario_id' => $this->admin->id]);

        $bajas = CatalogoReportes::buscar('dados-de-baja');
        $this->assertCount(1, $bajas->filas(['desde' => '2026-01-01', 'hasta' => '2026-12-31']));
        $this->assertCount(0, $bajas->filas(['hasta' => '2025-12-31']));

        $movimientos = CatalogoReportes::buscar('historial-movimientos');
        $this->assertCount(1, $movimientos->filas(['desde' => '2026-01-01', 'hasta' => '2026-12-31']));
        $this->assertCount(2, $movimientos->filas([]));
    }

    public function test_la_toma_de_inventario_exige_la_campana_y_agrupa_por_resultado(): void
    {
        $inventario = Inventario::create(['codigo' => 'INV-2026-001', 'nombre' => 'Prueba', 'sede_id' => $this->sedeA->id, 'fecha_inicio' => now(), 'usuario_id' => $this->admin->id, 'estado' => 'cerrado', 'total_esperados' => 2]);
        $mesa = Bien::where('nombre', 'Mesa A1')->sole();
        InventarioDetalle::create(['inventario_id' => $inventario->id, 'bien_id' => $mesa->id, 'codigo_leido' => $mesa->codigo_barras, 'resultado' => 'faltante', 'fecha_hora' => now()]);

        $this->actingAs($this->admin)->get(route('reportes.show', 'toma-de-inventario'))->assertOk()->assertSee('Elija los filtros obligatorios');
        $this->actingAs($this->admin)->get(route('reportes.exportar', ['toma-de-inventario', 'pdf']))->assertSessionHasErrors('inventario_id');

        $reporte = CatalogoReportes::buscar('toma-de-inventario');
        $totales = $reporte->totales($reporte->filas(['inventario_id' => $inventario->id]));
        $this->assertSame(['cantidad' => 1, 'valor' => 100.5], $totales['grupos']['1. Faltante']);
    }

    public function test_el_excel_tiene_encabezados_en_negrita_autofiltro_y_formatos(): void
    {
        $archivo = $this->actingAs($this->admin)->get(route('reportes.exportar', ['inventario-general', 'xlsx']))->assertOk()->getFile()->getPathname();
        $hoja = IOFactory::load($archivo)->getActiveSheet();

        $this->assertSame('Inventario general', $hoja->getCell('A2')->getValue());
        $this->assertSame('Código', $hoja->getCell('A6')->getValue());
        $this->assertTrue($hoja->getStyle('A6')->getFont()->getBold());
        $this->assertSame('A6:J9', $hoja->getAutoFilter()->getRange());
        $this->assertSame('"S/" #,##0.00', $hoja->getStyle('J7')->getNumberFormat()->getFormatCode());
        $this->assertSame('dd/mm/yyyy', $hoja->getStyle('I7')->getNumberFormat()->getFormatCode());
        $this->assertSame('Total: 3 bienes', $hoja->getCell('A11')->getValue());
        $this->assertEqualsWithDelta(350.75, $hoja->getCell('J11')->getValue(), 0.001);
    }

    public function test_los_excel_grandes_van_a_la_cola_y_se_notifica_al_terminar(): void
    {
        config(['sibip.reportes.limite_excel_directo' => 2]);
        Storage::fake('local');
        Queue::fake();

        $this->actingAs($this->admin)->from(route('reportes.show', 'inventario-general'))
            ->get(route('reportes.exportar', ['inventario-general', 'xlsx']))
            ->assertRedirect(route('reportes.show', 'inventario-general'))
            ->assertSessionHas('success');

        $trabajo = null;
        Queue::assertPushed(GenerarReporteExcel::class, function (GenerarReporteExcel $job) use (&$trabajo) {
            $trabajo = $job;

            return $job->usuarioId === $this->admin->id && $job->clave === 'inventario-general';
        });

        app()->call([$trabajo, 'handle']);

        Storage::disk('local')->assertExists("reportes/{$trabajo->archivo}");
        $aviso = $this->admin->notifications()->where('type', ReporteListo::class)->sole();
        $this->actingAs($this->admin)->get($aviso->data['url'])->assertOk()->assertDownload();

        // Nadie más puede descargar ese archivo.
        $this->actingAs($this->usuarioCon(RolSistema::Auditor))->get($aviso->data['url'])->assertNotFound();
    }

    public function test_cada_exportacion_queda_en_la_auditoria(): void
    {
        $this->actingAs($this->admin)->get(route('reportes.exportar', ['por-categoria', 'pdf', 'sede_id' => $this->sedeA->id]));

        $registro = Auditoria::where('accion', 'exportar_reporte')->sole();
        $this->assertSame($this->admin->id, $registro->usuario_id);
        $this->assertEquals(['reporte' => 'por-categoria', 'formato' => 'pdf', 'filtros' => ['sede_id' => $this->sedeA->id], 'registros' => 2, 'en_cola' => false], $registro->datos_nuevos);
    }

    public function test_permisos_de_los_reportes(): void
    {
        $operador = $this->usuarioCon(RolSistema::Operador);
        $auditor = $this->usuarioCon(RolSistema::Auditor);
        $usuario = $this->usuarioCon(RolSistema::Usuario);

        $this->actingAs($operador)->get(route('reportes.show', 'inventario-general'))->assertOk();
        $this->actingAs($operador)->get(route('reportes.exportar', ['inventario-general', 'pdf']))->assertForbidden();

        $this->actingAs($auditor)->get(route('reportes.exportar', ['inventario-general', 'xlsx']))->assertOk();

        $this->actingAs($usuario)->get(route('reportes.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('reportes.exportar', ['por-responsable', 'pdf']))->assertForbidden();
    }

    public function test_el_usuario_descarga_el_acta_solo_de_sus_bienes(): void
    {
        $usuario = $this->usuarioCon(RolSistema::Usuario);
        $this->responsable->update(['usuario_id' => $usuario->id]);

        $this->actingAs($usuario)->get(route('mis-bienes.index'))->assertSee('Acta de asignación');
        $this->actingAs($usuario)->get(route('mis-bienes.acta', ['responsable_id' => 999]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $registro = Auditoria::where('accion', 'exportar_reporte')->sole();
        $this->assertEquals(['responsable_id' => $this->responsable->id], $registro->datos_nuevos['filtros'], 'Solo sus bienes, aunque pida otro responsable.');
        $this->assertSame(2, $registro->datos_nuevos['registros']);

        $this->actingAs($this->usuarioCon(RolSistema::Usuario))->get(route('mis-bienes.acta'))->assertNotFound();
    }
}
