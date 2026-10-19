<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Area;
use App\Models\Bien;
use App\Models\Movimiento;
use App\Models\Permiso;
use App\Models\Responsable;
use App\Models\Role;
use App\Models\Sede;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuarioCon(RolSistema::Administrador);
    }

    /**
     * @return array<string, array{0: RolSistema}>
     */
    public static function rolesSinResponsable(): array
    {
        return [
            'administrador' => [RolSistema::Administrador],
            'operador' => [RolSistema::Operador],
            'auditor' => [RolSistema::Auditor],
        ];
    }

    #[DataProvider('rolesSinResponsable')]
    public function test_crea_una_cuenta_con_contrasena_temporal_que_se_muestra_una_vez(RolSistema $rol): void
    {
        $respuesta = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'nombre' => 'Lucía',
            'apellido' => 'Mamani',
            'email' => 'Lucia.Mamani@Colegio.test',
            'rol_id' => Role::delSistema($rol)->id,
            'estado' => 'activo',
        ]);

        $respuesta->assertRedirect(route('usuarios.index'))->assertSessionHas('password_temporal');

        $usuario = Usuario::where('email', 'lucia.mamani@colegio.test')->firstOrFail();
        $this->assertTrue($usuario->debe_cambiar_password);
        $this->assertTrue($usuario->rol->es($rol));
        $this->assertTrue(Hash::check(session('password_temporal.password'), $usuario->password));
        $this->assertNull($usuario->responsable);
        $this->assertDatabaseHas('auditoria', ['tabla' => 'usuarios', 'registro_id' => $usuario->id, 'accion' => 'crear', 'usuario_id' => $this->admin->id]);
    }

    public function test_una_cuenta_de_rol_usuario_crea_y_vincula_su_responsable(): void
    {
        $area = Area::factory()->create();

        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'nombre' => 'Rosa',
            'apellido' => 'Condori',
            'email' => 'rosa@colegio.test',
            'rol_id' => Role::delSistema(RolSistema::Usuario)->id,
            'sede_id' => $area->sede_id,
            'area_id' => $area->id,
            'estado' => 'activo',
        ])->assertRedirect(route('usuarios.index'));

        $usuario = Usuario::where('email', 'rosa@colegio.test')->firstOrFail();
        $this->assertDatabaseHas('responsables', [
            'usuario_id' => $usuario->id,
            'nombres' => 'Rosa',
            'apellidos' => 'Condori',
            'sede_id' => $area->sede_id,
            'area_id' => $area->id,
        ]);
    }

    public function test_una_cuenta_de_rol_usuario_puede_vincularse_a_un_responsable_existente(): void
    {
        $responsable = Responsable::factory()->create();

        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'nombre' => 'Pedro',
            'apellido' => 'Ticona',
            'email' => 'pedro@colegio.test',
            'rol_id' => Role::delSistema(RolSistema::Usuario)->id,
            'sede_id' => $responsable->sede_id,
            'area_id' => $responsable->area_id,
            'estado' => 'activo',
            'responsable_existente_id' => $responsable->id,
        ]);

        $usuario = Usuario::where('email', 'pedro@colegio.test')->firstOrFail();
        $this->assertSame($usuario->id, $responsable->fresh()->usuario_id);
        $this->assertSame(1, Responsable::count());
    }

    public function test_el_rol_usuario_exige_sede_y_un_area_de_esa_sede(): void
    {
        $datos = [
            'nombre' => 'Ana',
            'apellido' => 'Flores',
            'email' => 'ana@colegio.test',
            'rol_id' => Role::delSistema(RolSistema::Usuario)->id,
            'estado' => 'activo',
        ];

        $this->actingAs($this->admin)->post(route('usuarios.store'), $datos)
            ->assertSessionHasErrors(['sede_id', 'area_id']);

        $areaDeOtraSede = Area::factory()->create();
        $this->actingAs($this->admin)->post(route('usuarios.store'), [...$datos, 'sede_id' => Sede::factory()->create()->id, 'area_id' => $areaDeOtraSede->id])
            ->assertSessionHasErrors(['area_id' => 'El área seleccionada no pertenece a la sede indicada.']);

        $this->assertDatabaseMissing('usuarios', ['email' => 'ana@colegio.test']);
    }

    public function test_no_se_puede_desactivar_ni_quitar_el_rol_a_si_mismo(): void
    {
        $otroAdmin = $this->usuarioCon(RolSistema::Administrador);

        $this->actingAs($this->admin)->patch(route('usuarios.estado', $this->admin), ['estado' => 'inactivo'])
            ->assertSessionHasErrors('estado');

        $this->actingAs($this->admin)->put(route('usuarios.update', $this->admin), [
            'nombre' => $this->admin->nombre,
            'apellido' => $this->admin->apellido,
            'email' => $this->admin->email,
            'rol_id' => Role::delSistema(RolSistema::Operador)->id,
            'estado' => 'activo',
        ])->assertSessionHasErrors('rol_id');

        $this->assertTrue($this->admin->fresh()->isAdmin());
        $this->assertTrue($this->admin->fresh()->estaActivo());
        $this->assertTrue($otroAdmin->fresh()->estaActivo());
    }

    public function test_no_se_puede_desactivar_ni_degradar_al_ultimo_administrador_activo(): void
    {
        // Un segundo administrador inactivo no cuenta como respaldo.
        $adminInactivo = $this->usuarioCon(RolSistema::Administrador, ['estado' => 'inactivo']);
        $operadorConPermisos = $this->usuarioCon(RolSistema::Operador);
        Role::delSistema(RolSistema::Operador)->sincronizarPermisos(
            Permiso::whereIn('slug', ['usuarios.ver', 'usuarios.editar'])->pluck('id')->all()
        );

        $this->actingAs($operadorConPermisos)->patch(route('usuarios.estado', $this->admin), ['estado' => 'inactivo'])
            ->assertSessionHasErrors(['rol_id' => 'Debe quedar al menos un Administrador activo en el sistema.']);

        $this->actingAs($operadorConPermisos)->put(route('usuarios.update', $this->admin), [
            'nombre' => $this->admin->nombre,
            'apellido' => $this->admin->apellido,
            'email' => $this->admin->email,
            'rol_id' => Role::delSistema(RolSistema::Auditor)->id,
            'estado' => 'activo',
        ])->assertSessionHasErrors('rol_id');

        $this->assertTrue($this->admin->fresh()->isAdmin());
        $this->assertTrue($this->admin->fresh()->estaActivo());
        $this->assertFalse($adminInactivo->fresh()->estaActivo());
    }

    public function test_con_otro_administrador_activo_si_se_puede_desactivar_a_uno(): void
    {
        $otroAdmin = $this->usuarioCon(RolSistema::Administrador);

        $this->actingAs($this->admin)->patch(route('usuarios.estado', $otroAdmin), ['estado' => 'inactivo'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($otroAdmin->fresh()->estaActivo());
    }

    public function test_restablecer_la_contrasena_genera_una_temporal_y_obliga_a_cambiarla(): void
    {
        $operador = $this->usuarioCon(RolSistema::Operador);

        $this->actingAs($this->admin)->patch(route('usuarios.password', $operador))
            ->assertSessionHas('password_temporal');

        $operador->refresh();
        $this->assertTrue($operador->debe_cambiar_password);
        $this->assertTrue(Hash::check(session('password_temporal.password'), $operador->password));
    }

    public function test_un_usuario_con_historial_no_se_elimina_solo_se_desactiva(): void
    {
        $operador = $this->usuarioCon(RolSistema::Operador);
        Movimiento::forceCreate([
            'bien_id' => Bien::factory()->create()->id,
            'sede_destino_id' => Sede::factory()->create()->id,
            'tipo' => 'traslado',
            'estado' => 'solicitado',
            'fecha_movimiento' => now(),
            'usuario_id' => $operador->id,
        ]);

        $this->actingAs($this->admin)->delete(route('usuarios.destroy', $operador))
            ->assertSessionHasErrors('usuario');

        $this->assertNotSoftDeleted($operador);
    }

    public function test_un_usuario_sin_historial_se_elimina_con_soft_delete(): void
    {
        $operador = $this->usuarioCon(RolSistema::Operador);

        $this->actingAs($this->admin)->delete(route('usuarios.destroy', $operador))
            ->assertRedirect(route('usuarios.index'));

        $this->assertSoftDeleted($operador);
    }
}
