<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Area;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Movimiento;
use App\Models\Sede;
use App\Models\Usuario;
use App\Support\ConfiguracionEtiqueta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class CodigoDeBarrasYEtiquetasTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuarioCon(RolSistema::Administrador);
    }

    public function test_el_codigo_de_barras_se_genera_al_crear_el_bien_con_el_prefijo_de_su_sede(): void
    {
        $sede = Sede::factory()->create(['codigo' => 'S02']);
        $area = Area::factory()->create(['sede_id' => $sede->id]);

        $this->actingAs($this->admin)->post(route('bienes.store'), [
            'nombre' => 'Proyector Epson',
            'categoria_id' => Categoria::factory()->create()->id,
            'sede_id' => $sede->id,
            'area_id' => $area->id,
            'estado' => 'nuevo',
        ])->assertRedirect(route('bienes.show', Bien::firstOrFail()));

        $bien = Bien::firstOrFail();
        $this->assertSame(sprintf('CAT-S02-%06d', $bien->id), $bien->codigo_barras);
        $this->assertNull($bien->codigo_patrimonial, 'El código patrimonial es opcional y no se inventa.');

        $alta = Auditoria::where('tabla', 'bienes')->where('accion', 'crear')->where('registro_id', $bien->id)->sole();
        $this->assertSame($bien->codigo_barras, $alta->datos_nuevos['codigo_barras']);
        $this->assertSame($this->admin->id, $alta->usuario_id);
    }

    public function test_si_el_codigo_de_la_sede_no_es_corto_se_usa_s_mas_el_id(): void
    {
        $sede = Sede::factory()->create(['codigo' => 'SEDE- CENTRAL']);
        $bien = Bien::factory()->create(['area_id' => Area::factory()->create(['sede_id' => $sede->id])->id]);

        $this->assertSame(sprintf('CAT-S%02d-%06d', $sede->id, $bien->id), $bien->codigo_barras);
    }

    public function test_los_codigos_son_unicos(): void
    {
        $bienes = Bien::factory()->count(5)->create();

        $this->assertCount(5, $bienes->pluck('codigo_barras')->filter()->unique());
    }

    public function test_el_codigo_no_cambia_al_editar_el_bien(): void
    {
        $bien = Bien::factory()->create();
        $original = $bien->codigo_barras;

        $this->actingAs($this->admin)->put(route('bienes.update', $bien), [
            'codigo_barras' => 'CAT-HACK-999999',
            'nombre' => 'Nombre corregido',
            'categoria_id' => $bien->categoria_id,
            'sede_id' => $bien->sede_id,
            'area_id' => $bien->area_id,
            'estado' => 'bueno',
        ])->assertRedirect(route('bienes.index'));

        $bien->update(['nombre' => 'Otro nombre']);
        $bien->forceFill(['codigo_barras' => 'CAT-OTRO-000001'])->save();

        $this->assertSame($original, $bien->fresh()->codigo_barras);
    }

    public function test_el_codigo_no_cambia_al_trasladar_el_bien_a_otra_sede(): void
    {
        $bien = Bien::factory()->create();
        $original = $bien->codigo_barras;
        $destino = Area::factory()->create(['sede_id' => Sede::factory()->create(['codigo' => 'S09'])->id]);

        $this->actingAs($this->admin)->post(route('movimientos.store'), [
            'bien_id' => $bien->id,
            'tipo' => 'traslado',
            'sede_destino_id' => $destino->sede_id,
            'area_destino_id' => $destino->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('aprobado', Movimiento::firstOrFail()->estado);
        $this->assertSame($destino->sede_id, $bien->fresh()->sede_id);
        $this->assertSame($original, $bien->fresh()->codigo_barras);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: int, 2: int}>
     */
    public static function formatos(): array
    {
        return [
            '50x25 a 203 dpi' => [['preset' => '50x25', 'dpi' => 203], 400, 200],
            '40x30 a 300 dpi' => [['preset' => '40x30', 'dpi' => 300, 'ancho_modulo' => 3], 472, 354],
            '30x20 a 203 dpi' => [['preset' => '30x20', 'dpi' => 203], 240, 160],
            'personalizado 60x40 a 203 dpi' => [['preset' => 'personalizado', 'ancho_mm' => 60, 'alto_mm' => 40, 'dpi' => 203], 480, 320],
        ];
    }

    /**
     * @param  array<string, mixed>  $formato
     */
    #[DataProvider('formatos')]
    public function test_el_png_tiene_el_tamano_en_puntos_de_la_etiqueta_configurada(array $formato, int $ancho, int $alto): void
    {
        ConfiguracionEtiqueta::desdeArray($formato)->guardar();
        $bien = Bien::factory()->create();

        $respuesta = $this->actingAs($this->admin)->get(route('bienes.etiqueta', [$bien, 'png']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        [$anchoPng, $altoPng] = getimagesizefromstring($respuesta->getContent());
        $this->assertSame([$ancho, $alto], [$anchoPng, $altoPng]);
    }

    /**
     * @param  array<string, mixed>  $formato
     */
    #[DataProvider('formatos')]
    public function test_el_pdf_tiene_una_pagina_por_etiqueta_del_tamano_exacto(array $formato): void
    {
        $config = ConfiguracionEtiqueta::desdeArray($formato);
        $config->guardar();
        $bienes = Bien::factory()->count(3)->create();

        $pdf = $this->actingAs($this->admin)->post(route('etiquetas.generar'), [
            'formato' => 'pdf',
            'alcance' => 'seleccion',
            'bien_ids' => $bienes->pluck('id')->all(),
        ])->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();

        // Solo los objetos /Page (el nodo raíz /Pages también declara un MediaBox).
        preg_match_all('#/Type\s*/Page\s*/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]#', $pdf, $cajas, PREG_SET_ORDER);
        $this->assertCount(3, $cajas, 'Debe haber una página por etiqueta.');

        foreach ($cajas as $caja) {
            $this->assertEqualsWithDelta($config->anchoMm, ($caja[3] - $caja[1]) / 72 * 25.4, 0.1);
            $this->assertEqualsWithDelta($config->altoMm, ($caja[4] - $caja[2]) / 72 * 25.4, 0.1);
        }
    }

    public function test_el_lote_en_zip_trae_un_png_por_bien_y_marca_la_impresion(): void
    {
        $sede = Sede::factory()->create();
        $enSede = Bien::factory()->count(2)->create(['area_id' => Area::factory()->create(['sede_id' => $sede->id])->id]);
        $otro = Bien::factory()->create();

        $respuesta = $this->actingAs($this->admin)->post(route('etiquetas.generar'), [
            'formato' => 'zip',
            'alcance' => 'filtro',
            'sede_id' => $sede->id,
        ])->assertOk()->assertDownload();

        $zip = new ZipArchive;
        $zip->open($respuesta->getFile()->getPathname());
        $nombres = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->sort()->values()->all();
        $zip->close();

        $this->assertSame($enSede->map(fn ($b) => "{$b->codigo_barras}.png")->sort()->values()->all(), $nombres);
        $enSede->each(fn (Bien $b) => $this->assertNotNull($b->fresh()->etiqueta_impresa_at));
        $this->assertNull($otro->fresh()->etiqueta_impresa_at);
        $this->assertDatabaseHas('auditoria', ['accion' => 'imprimir_etiquetas', 'usuario_id' => $this->admin->id]);
    }

    public function test_se_pueden_filtrar_los_bienes_sin_etiqueta_impresa(): void
    {
        $impreso = Bien::factory()->create();
        $pendiente = Bien::factory()->create();
        $this->actingAs($this->admin)->get(route('bienes.etiqueta', [$impreso, 'html']))->assertOk();

        $this->actingAs($this->admin)->get(route('bienes.index', ['etiqueta' => 'sin']))
            ->assertSee($pendiente->codigo_barras)
            ->assertDontSee($impreso->codigo_barras);

        $this->actingAs($this->admin)->get(route('etiquetas.index', ['solo_sin_etiqueta' => 1]))
            ->assertSee($pendiente->codigo_barras)
            ->assertDontSee($impreso->codigo_barras);
    }

    public function test_la_impresion_directa_usa_el_tamano_configurado_en_page(): void
    {
        ConfiguracionEtiqueta::desdeArray(['preset' => '40x30'])->guardar();
        $bien = Bien::factory()->create();

        $this->actingAs($this->admin)->get(route('bienes.etiqueta', [$bien, 'html']))
            ->assertOk()
            ->assertSee('size: 40mm 30mm', false);
    }

    public function test_los_bienes_dados_de_baja_no_entran_en_los_lotes(): void
    {
        $activo = Bien::factory()->create();
        $dadoDeBaja = Bien::factory()->dadoDeBaja()->create();

        $this->actingAs($this->admin)->get(route('etiquetas.index'))
            ->assertSee($activo->codigo_barras)
            ->assertDontSee($dadoDeBaja->codigo_barras);
    }

    public function test_sin_permiso_de_etiquetas_no_se_puede_imprimir(): void
    {
        $bien = Bien::factory()->create();

        $this->actingAs($this->usuarioCon(RolSistema::Auditor))->get(route('bienes.etiqueta', [$bien, 'pdf']))->assertForbidden();
        $this->actingAs($this->usuarioCon(RolSistema::Usuario))->get(route('etiquetas.index'))->assertForbidden();
        $this->assertNull($bien->fresh()->etiqueta_impresa_at);
    }

    public function test_el_operador_imprime_etiquetas(): void
    {
        $bien = Bien::factory()->create();

        $this->actingAs($this->usuarioCon(RolSistema::Operador))->get(route('bienes.etiqueta', [$bien, 'pdf']))->assertOk();
    }

    public function test_guarda_la_configuracion_y_rechaza_una_etiqueta_donde_el_codigo_no_entra(): void
    {
        $valida = [
            'preset' => 'personalizado', 'ancho_mm' => 45, 'alto_mm' => 25, 'dpi' => 300,
            'ancho_modulo' => 2, 'alto_barras_mm' => 12, 'mostrar_nombre' => 1, 'mostrar_logo' => 0,
        ];

        $this->actingAs($this->admin)->put(route('configuracion.etiquetas.guardar'), $valida)
            ->assertRedirect(route('configuracion.etiquetas'));

        $config = ConfiguracionEtiqueta::actual();
        $this->assertSame([45.0, 25.0, 300, false], [$config->anchoMm, $config->altoMm, $config->dpi, $config->mostrarLogo]);

        // A 203 dpi, 20 mm son 160 puntos: no alcanzan para los 187 módulos del código con su zona de silencio.
        $this->actingAs($this->admin)->put(route('configuracion.etiquetas.guardar'), [...$valida, 'ancho_mm' => 20, 'dpi' => 203])
            ->assertSessionHasErrors('ancho_mm');
        $this->assertSame(45.0, ConfiguracionEtiqueta::actual()->anchoMm);
    }

    public function test_el_operador_no_puede_cambiar_la_configuracion(): void
    {
        $this->actingAs($this->usuarioCon(RolSistema::Operador))
            ->put(route('configuracion.etiquetas.guardar'), ['preset' => '30x20', 'dpi' => 203, 'ancho_modulo' => 1, 'alto_barras_mm' => 8])
            ->assertForbidden();
    }

    public function test_la_pagina_de_prueba_respeta_el_tamano_y_no_marca_bienes(): void
    {
        ConfiguracionEtiqueta::desdeArray(['preset' => '30x20', 'dpi' => 203])->guardar();
        $bien = Bien::factory()->create();

        $png = $this->actingAs($this->admin)->get(route('etiquetas.prueba', 'png'))->assertOk()->getContent();

        $this->assertSame([240, 160], array_slice(getimagesizefromstring($png), 0, 2));
        $this->assertNull($bien->fresh()->etiqueta_impresa_at);
    }

    public function test_un_nombre_muy_largo_no_impide_generar_la_etiqueta(): void
    {
        $bien = Bien::factory()->create(['nombre' => str_repeat('Computadora de escritorio con monitor ', 3)]);

        $this->actingAs($this->admin)->get(route('bienes.etiqueta', [$bien, 'png']))->assertOk();
    }

    public function test_el_detalle_del_bien_muestra_el_codigo_de_barras_en_svg(): void
    {
        $bien = Bien::factory()->create();

        $this->actingAs($this->admin)->get(route('bienes.show', $bien))
            ->assertOk()
            ->assertSee($bien->codigo_barras)
            ->assertSee(route('bienes.codigo-barras', $bien));

        $this->actingAs($this->admin)->get(route('bienes.codigo-barras', $bien))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }
}
