<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Area;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\InventarioDetalle;
use App\Models\Sede;
use App\Models\Usuario;
use App\Services\TomaDeInventario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TomaDeInventarioTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $operador;

    private Sede $sede;

    private Area $aula1;

    private Area $aula2;

    /** @var array<string, Bien> */
    private array $bienes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuarioCon(RolSistema::Administrador);
        $this->operador = $this->usuarioCon(RolSistema::Operador);

        $this->sede = Sede::factory()->create(['codigo' => 'S01']);
        $this->aula1 = Area::factory()->create(['sede_id' => $this->sede->id, 'nombre' => 'Aula 1']);
        $this->aula2 = Area::factory()->create(['sede_id' => $this->sede->id, 'nombre' => 'Aula 2']);
        $otraSede = Area::factory()->create(['nombre' => 'Biblioteca']);

        $this->bienes = [
            'en_aula1' => Bien::factory()->create(['area_id' => $this->aula1->id, 'sede_id' => $this->sede->id]),
            'en_aula2' => Bien::factory()->create(['area_id' => $this->aula2->id, 'sede_id' => $this->sede->id]),
            'otra_sede' => Bien::factory()->create(['area_id' => $otraSede->id, 'sede_id' => $otraSede->sede_id]),
            'de_baja' => Bien::factory()->dadoDeBaja()->create(['area_id' => $this->aula1->id, 'sede_id' => $this->sede->id]),
            'sin_escanear' => Bien::factory()->create(['area_id' => $this->aula1->id, 'sede_id' => $this->sede->id]),
        ];
    }

    private function crearCampana(?Area $area = null, ?Usuario $creador = null): Inventario
    {
        $this->actingAs($creador ?? $this->operador)->post(route('inventarios.store'), [
            'nombre' => 'Inventario de prueba',
            'sede_id' => $this->sede->id,
            'area_id' => $area?->id,
            'fecha_inicio' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        return Inventario::latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function escanear(Inventario $inventario, string $codigo, ?Area $area = null, ?Usuario $usuario = null): array
    {
        return $this->actingAs($usuario ?? $this->operador)
            ->postJson(route('inventarios.lecturas', $inventario), ['codigo' => $codigo, 'area_escaneo_id' => $area?->id])
            ->assertOk()
            ->json('lectura');
    }

    public function test_clasifica_las_cinco_situaciones_de_lectura(): void
    {
        $inventario = $this->crearCampana();

        $this->assertSame('encontrado', $this->escanear($inventario, $this->bienes['en_aula1']->codigo_barras, $this->aula1)['resultado']);

        $fuera = $this->escanear($inventario, $this->bienes['en_aula2']->codigo_barras, $this->aula1);
        $this->assertSame('fuera_de_ubicacion', $fuera['resultado']);
        $this->assertSame('ambar', $fuera['color']);
        $this->assertStringContainsString('Aula 2', $fuera['mensaje'], 'Debe indicar dónde debería estar.');

        $this->assertSame('fuera_de_ubicacion', $this->escanear($inventario, $this->bienes['otra_sede']->codigo_barras)['resultado']);
        $this->assertSame('dado_de_baja', $this->escanear($inventario, $this->bienes['de_baja']->codigo_barras)['resultado']);
        $this->assertSame('sobrante_no_registrado', $this->escanear($inventario, 'ABC-999')['resultado']);

        $repetida = $this->escanear($inventario, strtolower($this->bienes['en_aula1']->codigo_barras).' ');
        $this->assertSame('ya_escaneado', $repetida['resultado']);
        $this->assertSame('gris', $repetida['color']);

        $this->assertSame(5, $inventario->lecturas()->count());
    }

    public function test_sin_elegir_area_cualquier_bien_de_la_sede_esta_encontrado(): void
    {
        $inventario = $this->crearCampana();

        $this->assertSame('encontrado', $this->escanear($inventario, $this->bienes['en_aula2']->codigo_barras)['resultado']);
    }

    public function test_en_una_campana_por_area_los_bienes_de_otra_area_estan_fuera_de_ubicacion(): void
    {
        $inventario = $this->crearCampana($this->aula1);

        $this->assertSame('encontrado', $this->escanear($inventario, $this->bienes['en_aula1']->codigo_barras)['resultado']);
        $this->assertSame('fuera_de_ubicacion', $this->escanear($inventario, $this->bienes['en_aula2']->codigo_barras)['resultado']);
    }

    public function test_dos_usuarios_que_leen_el_mismo_codigo_no_duplican_la_lectura(): void
    {
        $inventario = $this->crearCampana(creador: $this->admin);
        $codigo = $this->bienes['en_aula1']->codigo_barras;

        $this->assertSame('encontrado', $this->escanear($inventario, $codigo, usuario: $this->admin)['resultado']);
        $segunda = $this->escanear($inventario, $codigo, usuario: $this->usuarioCon(RolSistema::Administrador));

        $this->assertSame('ya_escaneado', $segunda['resultado']);
        $this->assertSame(1, $inventario->detalles()->where('codigo_leido', $codigo)->count());
    }

    public function test_la_base_de_datos_resuelve_la_carrera_entre_dos_lecturas_simultaneas(): void
    {
        $inventario = $this->crearCampana();
        $codigo = $this->bienes['en_aula1']->codigo_barras;

        // Simula que otro usuario inserta la misma lectura justo después de la verificación previa.
        InventarioDetalle::creating(function (InventarioDetalle $detalle) use ($codigo) {
            if ($detalle->codigo_leido === $codigo && ! InventarioDetalle::where('codigo_leido', $codigo)->exists()) {
                InventarioDetalle::withoutEvents(fn () => InventarioDetalle::create([...$detalle->getAttributes(), 'uuid_cliente' => null, 'escaneado_por' => $this->admin->id]));
            }
        });

        $this->assertSame('ya_escaneado', $this->escanear($inventario, $codigo)['resultado']);
        $this->assertSame(1, $inventario->detalles()->count());

        $this->expectException(QueryException::class);
        InventarioDetalle::withoutEvents(fn () => InventarioDetalle::create([
            'inventario_id' => $inventario->id, 'codigo_leido' => $codigo, 'resultado' => 'encontrado', 'fecha_hora' => now(),
        ]));
    }

    public function test_la_sincronizacion_offline_es_idempotente_y_no_duplica(): void
    {
        $inventario = $this->crearCampana();
        $horaOffline = now()->subMinutes(30)->startOfSecond();

        // Otro usuario ya leyó este código en línea antes de que se sincronice la cola.
        $this->escanear($inventario, $this->bienes['en_aula2']->codigo_barras);

        $lote = [
            ['uuid' => (string) Str::uuid(), 'codigo' => $this->bienes['en_aula1']->codigo_barras, 'area_escaneo_id' => $this->aula1->id, 'estado_fisico' => 'regular', 'fecha_hora' => $horaOffline->toIso8601String()],
            ['uuid' => (string) Str::uuid(), 'codigo' => 'SIN-REGISTRO-1', 'fecha_hora' => $horaOffline->toIso8601String()],
            ['uuid' => (string) Str::uuid(), 'codigo' => $this->bienes['en_aula2']->codigo_barras, 'fecha_hora' => $horaOffline->toIso8601String()],
        ];

        $primera = $this->actingAs($this->operador)->postJson(route('inventarios.sincronizar', $inventario), ['lecturas' => $lote])->assertOk()->json('respuestas');
        $this->assertSame(['encontrado', 'sobrante_no_registrado', 'ya_escaneado'], array_column(array_column($primera, 'lectura'), 'resultado'));

        // Se reenvía el mismo lote (p. ej. se cortó la red antes de recibir la respuesta).
        $segunda = $this->actingAs($this->operador)->postJson(route('inventarios.sincronizar', $inventario), ['lecturas' => $lote])->assertOk()->json('respuestas');
        $this->assertSame(array_column(array_column($primera, 'lectura'), 'resultado'), array_column(array_column($segunda, 'lectura'), 'resultado'));

        $this->assertSame(3, $inventario->lecturas()->count());
        $offline = $inventario->lecturas()->where('codigo_leido', $this->bienes['en_aula1']->codigo_barras)->sole();
        $this->assertTrue($offline->registrado_offline);
        $this->assertSame('regular', $offline->estado_fisico);
        $this->assertEquals($horaOffline, $offline->fecha_hora);
    }

    public function test_al_cerrar_se_registran_como_faltantes_los_esperados_no_escaneados(): void
    {
        $inventario = $this->crearCampana();
        $this->escanear($inventario, $this->bienes['en_aula1']->codigo_barras, $this->aula1);
        $this->escanear($inventario, $this->bienes['en_aula2']->codigo_barras, $this->aula1); // fuera de ubicación, pero está
        $this->escanear($inventario, 'XX-1');

        $this->actingAs($this->operador)->patch(route('inventarios.cerrar', $inventario))
            ->assertRedirect(route('inventarios.show', $inventario));

        $inventario->refresh();
        $this->assertSame('cerrado', $inventario->estado);
        $this->assertSame(3, $inventario->total_esperados, 'Se esperan los 3 bienes activos de la sede (el de baja no cuenta).');

        $faltantes = $inventario->detalles()->where('resultado', 'faltante')->pluck('bien_id')->all();
        $this->assertSame([$this->bienes['sin_escanear']->id], $faltantes);

        $this->actingAs($this->operador)->postJson(route('inventarios.lecturas', $inventario), ['codigo' => 'XX-2'])->assertForbidden();

        $resumen = app(TomaDeInventario::class)->resumen($inventario);
        $this->assertSame(['esperados' => 3, 'escaneados' => 2, 'faltante' => 1], array_intersect_key($resumen, array_flip(['esperados', 'escaneados', 'faltante'])));
    }

    public function test_solo_quien_aprueba_puede_reabrir_y_se_descartan_los_faltantes(): void
    {
        $inventario = $this->crearCampana();
        $this->actingAs($this->operador)->patch(route('inventarios.cerrar', $inventario));

        $this->actingAs($this->operador)->patch(route('inventarios.reabrir', $inventario))->assertForbidden();
        $this->actingAs($this->admin)->patch(route('inventarios.reabrir', $inventario))->assertRedirect();

        $inventario->refresh();
        $this->assertTrue($inventario->estaAbierto());
        $this->assertSame(0, $inventario->detalles()->where('resultado', 'faltante')->count());
    }

    public function test_solo_puede_haber_una_campana_abierta_por_ambito(): void
    {
        $this->crearCampana();

        foreach ([null, $this->aula1] as $area) {
            $this->actingAs($this->operador)->post(route('inventarios.store'), [
                'nombre' => 'Duplicada', 'sede_id' => $this->sede->id, 'area_id' => $area?->id, 'fecha_inicio' => now()->toDateString(),
            ])->assertSessionHasErrors('sede_id');
        }

        $otraSede = Sede::factory()->create();
        $this->actingAs($this->operador)->post(route('inventarios.store'), [
            'nombre' => 'Otra sede', 'sede_id' => $otraSede->id, 'fecha_inicio' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Inventario::abiertos()->count());
    }

    public function test_la_base_de_datos_impide_dos_campanas_abiertas_iguales(): void
    {
        $inventario = $this->crearCampana($this->aula1);

        $this->expectException(QueryException::class);
        Inventario::withoutEvents(fn () => Inventario::create([
            ...$inventario->only(['nombre', 'sede_id', 'area_id', 'fecha_inicio', 'usuario_id']),
            'codigo' => 'INV-OTRO', 'estado' => 'abierto',
        ]));
    }

    public function test_deshacer_una_lectura_la_anula_queda_en_auditoria_y_permite_volver_a_leer(): void
    {
        $inventario = $this->crearCampana();
        $lectura = $this->escanear($inventario, $this->bienes['en_aula1']->codigo_barras);

        $this->actingAs($this->operador)->deleteJson(route('inventarios.lecturas.deshacer', [$inventario, $lectura['id']]))->assertOk();

        $detalle = InventarioDetalle::findOrFail($lectura['id']);
        $this->assertNotNull($detalle->anulado_at);
        $this->assertSame($this->operador->id, $detalle->anulado_por);
        $this->assertTrue(Auditoria::where('tabla', 'inventario_detalles')->where('registro_id', $detalle->id)->where('accion', 'actualizar')->exists());

        $this->assertSame('encontrado', $this->escanear($inventario, $this->bienes['en_aula1']->codigo_barras)['resultado']);
    }

    public function test_marca_el_estado_fisico_de_una_lectura(): void
    {
        $inventario = $this->crearCampana();
        $lectura = $this->escanear($inventario, $this->bienes['en_aula1']->codigo_barras);

        $this->actingAs($this->operador)
            ->patchJson(route('inventarios.lecturas.estado-fisico', [$inventario, $lectura['id']]), ['estado_fisico' => 'malo'])
            ->assertOk()
            ->assertJsonPath('lectura.estado_fisico', 'malo');
    }

    public function test_permisos_de_la_toma_de_inventario(): void
    {
        $inventario = $this->crearCampana();
        $auditor = $this->usuarioCon(RolSistema::Auditor);
        $otroOperador = $this->usuarioCon(RolSistema::Operador);

        $this->actingAs($auditor)->get(route('inventarios.show', $inventario))->assertOk();
        $this->actingAs($auditor)->get(route('inventarios.exportar', [$inventario, 'xlsx']))->assertOk();
        $this->actingAs($auditor)->get(route('inventarios.create'))->assertForbidden();
        $this->actingAs($auditor)->get(route('inventarios.escanear', $inventario))->assertForbidden();
        $this->actingAs($auditor)->postJson(route('inventarios.lecturas', $inventario), ['codigo' => 'X'])->assertForbidden();

        $this->actingAs($this->usuarioCon(RolSistema::Usuario))->get(route('inventarios.index'))->assertForbidden();

        // Un operador que no es responsable del conteo no escanea; el Administrador supervisa todas.
        $this->actingAs($otroOperador)->get(route('inventarios.escanear', $inventario))->assertForbidden();
        $this->actingAs($this->admin)->get(route('inventarios.escanear', $inventario))->assertOk();
        $this->actingAs($this->operador)->get(route('inventarios.escanear', $inventario))->assertOk();
    }

    public function test_desde_el_resultado_se_generan_solicitudes_y_se_registran_sobrantes(): void
    {
        $inventario = $this->crearCampana(creador: $this->admin);
        $this->escanear($inventario, $this->bienes['en_aula2']->codigo_barras, $this->aula1, $this->admin);
        $sobrante = $this->escanear($inventario, '740805999999', $this->aula1, $this->admin);
        $this->actingAs($this->admin)->patch(route('inventarios.cerrar', $inventario));

        $this->actingAs($this->admin)->get(route('inventarios.show', $inventario))
            ->assertOk()
            ->assertSee('Generar movimiento')
            ->assertSee('Iniciar baja')
            ->assertSee('Registrar como bien');

        // Aunque el Administrador puede aprobar, lo generado desde el inventario queda como solicitud.
        $this->actingAs($this->admin)->post(route('movimientos.store'), [
            'bien_id' => $this->bienes['en_aula2']->id, 'tipo' => 'reubicacion', 'inventario_id' => $inventario->id,
            'sede_destino_id' => $this->sede->id, 'area_destino_id' => $this->aula1->id,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('movimientos', ['bien_id' => $this->bienes['en_aula2']->id, 'estado' => 'solicitado', 'inventario_id' => $inventario->id]);
        $this->assertSame($this->aula2->id, $this->bienes['en_aula2']->fresh()->area_id);

        $this->actingAs($this->admin)->post(route('bajas.store'), [
            'bien_id' => $this->bienes['sin_escanear']->id, 'motivo' => 'perdida', 'fecha_baja' => now()->toDateString(),
            'descripcion' => 'No se encontró en la toma de inventario.', 'inventario_id' => $inventario->id,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bajas', ['bien_id' => $this->bienes['sin_escanear']->id, 'estado' => 'solicitada', 'inventario_id' => $inventario->id]);

        $this->actingAs($this->admin)->post(route('bienes.store'), [
            'nombre' => 'Silla encontrada', 'categoria_id' => Categoria::factory()->create()->id,
            'sede_id' => $this->sede->id, 'area_id' => $this->aula1->id, 'estado' => 'regular',
            'codigo_patrimonial' => '740805999999', 'inventario_detalle_id' => $sobrante['id'],
        ])->assertSessionHasNoErrors();

        $nuevo = Bien::where('codigo_patrimonial', '740805999999')->sole();
        $this->assertSame($nuevo->id, InventarioDetalle::findOrFail($sobrante['id'])->bien_id);
        $this->actingAs($this->admin)->get(route('inventarios.show', $inventario))->assertSee('Registrado como');
    }

    public function test_exporta_el_resultado_en_pdf_y_excel(): void
    {
        $inventario = $this->crearCampana();
        $this->escanear($inventario, $this->bienes['en_aula1']->codigo_barras);
        $this->actingAs($this->operador)->patch(route('inventarios.cerrar', $inventario));

        $this->actingAs($this->operador)->get(route('inventarios.exportar', [$inventario, 'pdf']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->operador)->get(route('inventarios.exportar', [$inventario, 'xlsx']))
            ->assertOk()->assertDownload("resultado-{$inventario->codigo}.xlsx");
    }

    public function test_el_catalogo_offline_solo_trae_los_bienes_del_ambito_con_campos_minimos(): void
    {
        $inventario = $this->crearCampana($this->aula1);
        $this->escanear($inventario, $this->bienes['en_aula1']->codigo_barras);

        $respuesta = $this->actingAs($this->operador)->getJson(route('inventarios.catalogo', $inventario))
            ->assertOk()
            ->assertJsonPath("bienes.{$this->bienes['de_baja']->codigo_barras}.e", 'dado_de_baja')
            ->assertJsonPath('leidos.0', $this->bienes['en_aula1']->codigo_barras)
            ->json();

        // Solo los bienes del área de la campaña (no los de otra área ni de otra sede).
        $this->assertEqualsCanonicalizing(
            [$this->bienes['en_aula1']->codigo_barras, $this->bienes['de_baja']->codigo_barras, $this->bienes['sin_escanear']->codigo_barras],
            array_keys($respuesta['bienes']),
        );
        // Solo id, nombre corto, área y estado: nada de responsables ni valores.
        foreach ($respuesta['bienes'] as $bien) {
            $this->assertSame(['i', 'n', 'a', 'e'], array_keys($bien));
        }
    }
}
