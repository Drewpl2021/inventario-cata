<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Documento;
use App\Models\Requerimiento;
use App\Models\Responsable;
use App\Models\Usuario;
use App\Services\AlmacenArchivos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivosPrivadosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $usuarioA;

    private Usuario $usuarioB;

    private Bien $bienDeA;

    private Bien $bienDeB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = $this->usuarioCon(RolSistema::Administrador);
        $this->usuarioA = $this->usuarioCon(RolSistema::Usuario);
        $this->usuarioB = $this->usuarioCon(RolSistema::Usuario);
        $this->bienDeA = Bien::factory()->create(['responsable_id' => Responsable::factory()->create(['usuario_id' => $this->usuarioA->id])->id]);
        $this->bienDeB = Bien::factory()->create(['responsable_id' => Responsable::factory()->create(['usuario_id' => $this->usuarioB->id])->id]);
    }

    private function pdf(string $nombre = 'documento.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function datosBien(array $cambios = []): array
    {
        return [
            'nombre' => 'Proyector Epson',
            'categoria_id' => $this->bienDeA->categoria_id,
            'sede_id' => $this->bienDeA->sede_id,
            'estado' => 'bueno',
            ...$cambios,
        ];
    }

    public function test_la_foto_del_bien_se_guarda_en_el_disco_privado_reducida_a_1600_px_en_webp(): void
    {
        $this->actingAs($this->admin)->post(route('bienes.store'), $this->datosBien([
            'fotografia' => UploadedFile::fake()->image('foto.png', 3200, 2000),
        ]))->assertSessionHasNoErrors();

        $bien = Bien::latest('id')->firstOrFail();
        $this->assertStringStartsWith(AlmacenArchivos::CARPETA_FOTOS_BIENES.'/', $bien->fotografia);
        $this->assertStringEndsWith('.webp', $bien->fotografia);
        $this->assertMatchesRegularExpression('#/[A-Za-z0-9]{40}\.webp$#', $bien->fotografia);
        Storage::disk('local')->assertExists($bien->fotografia);

        [$ancho, $alto] = getimagesizefromstring(Storage::disk('local')->get($bien->fotografia));
        $this->assertSame([1600, 1000], [$ancho, $alto]);
        $this->assertDirectoryDoesNotExist(public_path('uploads'));
    }

    public function test_se_valida_el_tipo_real_del_archivo_y_no_la_extension(): void
    {
        $falsaImagen = UploadedFile::fake()->createWithContent('foto.jpg', '<?php echo "hola"; ?>');
        $falsoPdf = UploadedFile::fake()->createWithContent('factura.pdf', 'MZ ejecutable');

        $this->actingAs($this->admin)->post(route('bienes.store'), $this->datosBien(['fotografia' => $falsaImagen, 'comprobante_archivo' => $falsoPdf]))
            ->assertSessionHasErrors([
                'fotografia' => 'Solo se aceptan imágenes JPG, PNG o WEBP.',
                'comprobante_archivo' => 'Solo se aceptan documentos PDF o imágenes JPG, PNG o WEBP.',
            ]);
    }

    public function test_limites_de_tamano_imagen_5_mb_y_pdf_10_mb(): void
    {
        $this->actingAs($this->admin)->post(route('bienes.store'), $this->datosBien([
            'fotografia' => UploadedFile::fake()->image('foto.jpg', 100, 100)->size(5 * 1024 + 1),
        ]))->assertSessionHasErrors(['fotografia' => 'La imagen no debe superar 5 MB.']);

        $this->actingAs($this->admin)->post(route('documentos.store'), [
            'bien_id' => $this->bienDeA->id, 'tipo' => 'factura', 'nombre' => 'Factura',
            'archivo' => $this->pdf()->size(10 * 1024 + 1),
        ])->assertSessionHasErrors(['archivo' => 'El PDF no debe superar 10 MB.']);
    }

    public function test_el_archivo_documentario_solo_acepta_pdf_y_lo_guarda_con_nombre_aleatorio(): void
    {
        $this->actingAs($this->admin)->post(route('documentos.store'), [
            'bien_id' => $this->bienDeA->id, 'tipo' => 'acta', 'nombre' => 'Acta de entrega',
            'archivo' => UploadedFile::fake()->image('acta.png'),
        ])->assertSessionHasErrors(['archivo' => 'Solo se aceptan documentos PDF.']);

        $this->actingAs($this->admin)->post(route('documentos.store'), [
            'bien_id' => $this->bienDeA->id, 'tipo' => 'acta', 'nombre' => 'Acta de entrega',
            'archivo' => $this->pdf('acta de entrega.pdf'),
        ])->assertSessionHasNoErrors();

        $documento = Documento::sole();
        $this->assertMatchesRegularExpression('#^archivos/documentos/[A-Za-z0-9]{40}\.pdf$#', $documento->ruta);
        Storage::disk('local')->assertExists($documento->ruta);
    }

    public function test_el_usuario_ve_los_archivos_de_sus_bienes_pero_no_los_de_otro(): void
    {
        $this->bienDeA->update(['fotografia' => Storage::disk('local')->putFile(AlmacenArchivos::CARPETA_FOTOS_BIENES, UploadedFile::fake()->image('a.png'))]);
        $this->bienDeB->update([
            'fotografia' => Storage::disk('local')->putFile(AlmacenArchivos::CARPETA_FOTOS_BIENES, UploadedFile::fake()->image('b.png')),
            'comprobante_archivo' => Storage::disk('local')->putFile(AlmacenArchivos::CARPETA_COMPROBANTES, $this->pdf()),
        ]);
        $documentoDeB = Documento::create(['bien_id' => $this->bienDeB->id, 'tipo' => 'factura', 'nombre' => 'Factura', 'ruta' => $this->bienDeB->comprobante_archivo, 'usuario_id' => $this->admin->id]);

        $this->actingAs($this->usuarioA)->get($this->bienDeA->url_fotografia)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($this->usuarioA)->get(route('archivos.bienes.foto', $this->bienDeB))->assertForbidden();
        $this->actingAs($this->usuarioA)->get(route('archivos.bienes.comprobante', $this->bienDeB))->assertForbidden();
        $this->actingAs($this->usuarioA)->get(route('archivos.documentos', $documentoDeB))->assertForbidden();

        $this->actingAs($this->usuarioB)->get(route('archivos.documentos', $documentoDeB))->assertOk();
        $this->actingAs($this->admin)->get(route('archivos.bienes.comprobante', $this->bienDeB))->assertOk();
    }

    public function test_el_usuario_no_ve_la_foto_de_baja_ni_el_adjunto_de_solicitudes_ajenas(): void
    {
        $this->actingAs($this->usuarioB)->post(route('bajas.store'), [
            'bien_id' => $this->bienDeB->id, 'motivo' => 'deterioro', 'fecha_baja' => now()->toDateString(),
            'descripcion' => 'Pantalla rota.', 'fotografia' => UploadedFile::fake()->image('evidencia.jpg', 800, 600),
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->usuarioB)->post(route('requerimientos.store'), [
            'tipo_requerimiento' => 'Mobiliario', 'descripcion' => 'Mesa', 'justificacion' => 'Aula',
            'archivo_adjunto' => $this->pdf('cotización.pdf'),
        ])->assertSessionHasNoErrors();

        $baja = Baja::sole();
        $requerimiento = Requerimiento::sole();
        Storage::disk('local')->assertExists([$baja->fotografia, $requerimiento->archivo_adjunto]);

        $this->actingAs($this->usuarioA)->get($baja->url_fotografia)->assertForbidden();
        $this->actingAs($this->usuarioA)->get($requerimiento->url_adjunto)->assertForbidden();

        $this->actingAs($this->usuarioB)->get($baja->url_fotografia)->assertOk();
        $this->actingAs($this->usuarioB)->get($requerimiento->url_adjunto)
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename=cotizacion.pdf');
        $this->actingAs($this->admin)->get($baja->url_fotografia)->assertOk();
    }

    public function test_sin_sesion_no_se_sirve_ningun_archivo(): void
    {
        $this->bienDeA->update(['fotografia' => Storage::disk('local')->putFile(AlmacenArchivos::CARPETA_FOTOS_BIENES, UploadedFile::fake()->image('a.png'))]);

        $this->get(route('archivos.bienes.foto', $this->bienDeA))->assertRedirect(route('login'));
    }

    public function test_no_se_sirven_rutas_fuera_de_la_carpeta_de_archivos(): void
    {
        Storage::disk('local')->put('reportes/ajeno.xlsx', 'secreto');
        $documento = Documento::create(['bien_id' => $this->bienDeA->id, 'tipo' => 'otro', 'nombre' => 'Raro', 'ruta' => 'reportes/ajeno.xlsx', 'usuario_id' => $this->admin->id]);
        $traversal = Documento::create(['bien_id' => $this->bienDeA->id, 'tipo' => 'otro', 'nombre' => 'Raro', 'ruta' => 'archivos/../reportes/ajeno.xlsx', 'usuario_id' => $this->admin->id]);

        $this->actingAs($this->admin)->get(route('archivos.documentos', $documento))->assertNotFound();
        $this->actingAs($this->admin)->get(route('archivos.documentos', $traversal))->assertNotFound();
    }

    public function test_el_comando_migra_public_uploads_al_disco_privado_y_se_puede_repetir(): void
    {
        $publica = storage_path('framework/testing/public-'.uniqid());
        $this->app->usePublicPath($publica);
        File::ensureDirectoryExists("{$publica}/uploads/comprobantes");
        File::ensureDirectoryExists("{$publica}/uploads/bajas");
        File::put("{$publica}/uploads/comprobantes/comp_1.pdf", '%PDF-1.4 comprobante');
        File::put("{$publica}/uploads/bajas/huerfana.jpg", 'sin registro');

        // El comprobante del bien y su documento comparten la misma ruta.
        $this->bienDeA->update(['comprobante_archivo' => 'uploads/comprobantes/comp_1.pdf', 'fotografia' => 'uploads/bienes/no-existe.jpg']);
        $documento = Documento::create(['bien_id' => $this->bienDeA->id, 'tipo' => 'factura', 'nombre' => 'Factura', 'ruta' => 'uploads/comprobantes/comp_1.pdf', 'usuario_id' => $this->admin->id]);

        $this->artisan('sibip:migrar-archivos')->assertSuccessful();

        $bien = $this->bienDeA->fresh();
        $this->assertStringStartsWith(AlmacenArchivos::CARPETA_COMPROBANTES.'/', $bien->comprobante_archivo);
        $this->assertSame($bien->comprobante_archivo, $documento->fresh()->ruta);
        $this->assertSame('%PDF-1.4 comprobante', Storage::disk('local')->get($bien->comprobante_archivo));
        Storage::disk('local')->assertExists('archivos/sin-referencia/bajas/huerfana.jpg');
        $this->assertSame('uploads/bienes/no-existe.jpg', $bien->fotografia, 'Un archivo que no existe se informa y no se toca.');
        $this->assertDirectoryDoesNotExist("{$publica}/uploads");

        // Segunda ejecución: no cambia nada.
        $this->artisan('sibip:migrar-archivos')->assertSuccessful();
        $this->assertSame($bien->comprobante_archivo, $this->bienDeA->fresh()->comprobante_archivo);
        $this->assertCount(1, Storage::disk('local')->allFiles(AlmacenArchivos::CARPETA_COMPROBANTES));

        File::deleteDirectory($publica);
    }
}
