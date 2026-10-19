<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Bien;
use App\Models\Permiso;
use App\Models\Responsable;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RolesYPermisosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: RolSistema, 1: string, 2: int}>
     */
    public static function accesosPorRol(): array
    {
        return [
            'administrador entra a usuarios' => [RolSistema::Administrador, 'usuarios.index', 200],
            'administrador entra a roles' => [RolSistema::Administrador, 'roles.index', 200],
            'operador entra a bienes' => [RolSistema::Operador, 'bienes.index', 200],
            'operador puede registrar bienes' => [RolSistema::Operador, 'bienes.create', 200],
            'operador no entra a usuarios' => [RolSistema::Operador, 'usuarios.index', 403],
            'operador no entra a sedes' => [RolSistema::Operador, 'sedes.index', 403],
            'auditor ve bienes' => [RolSistema::Auditor, 'bienes.index', 200],
            'auditor ve auditoría' => [RolSistema::Auditor, 'auditoria.index', 200],
            'auditor no registra bienes' => [RolSistema::Auditor, 'bienes.create', 403],
            'auditor no registra movimientos' => [RolSistema::Auditor, 'movimientos.create', 403],
            'usuario ve sus bienes' => [RolSistema::Usuario, 'mis-bienes.index', 200],
            'usuario no ve el inventario completo' => [RolSistema::Usuario, 'bienes.index', 403],
            'usuario no entra a movimientos' => [RolSistema::Usuario, 'movimientos.index', 403],
        ];
    }

    #[DataProvider('accesosPorRol')]
    public function test_cada_rol_accede_solo_a_sus_modulos(RolSistema $rol, string $ruta, int $estado): void
    {
        $this->actingAs($this->usuarioCon($rol))->get(route($ruta))->assertStatus($estado);
    }

    public function test_un_invitado_es_enviado_al_login(): void
    {
        $this->get(route('bienes.index'))->assertRedirect(route('login'));
    }

    public function test_el_menu_lateral_solo_muestra_los_modulos_permitidos(): void
    {
        $this->actingAs($this->usuarioCon(RolSistema::Operador))
            ->get(route('bienes.index'))
            ->assertSee(route('movimientos.index'))
            ->assertDontSee(route('usuarios.index'))
            ->assertDontSee(route('roles.index'));
    }

    public function test_el_administrador_crea_un_rol_personalizado_que_concede_solo_sus_permisos(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $permisos = Permiso::whereIn('slug', ['dashboard.ver', 'bienes.ver', 'reportes.ver'])->pluck('id')->all();

        $this->actingAs($admin)->post(route('roles.store'), [
            'nombre' => 'Consulta de inventario',
            'descripcion' => 'Solo lectura de bienes',
            'permisos' => $permisos,
        ])->assertRedirect(route('roles.index'));

        $rol = Role::where('nombre', 'Consulta de inventario')->firstOrFail();
        $this->assertFalse($rol->es_sistema);
        $this->assertEqualsCanonicalizing(['dashboard.ver', 'bienes.ver', 'reportes.ver'], $rol->slugsDePermisos());

        $consultor = Usuario::factory()->create(['rol_id' => $rol->id]);
        $this->actingAs($consultor)->get(route('bienes.index'))->assertOk();
        $this->actingAs($consultor)->get(route('bienes.create'))->assertForbidden();
    }

    public function test_al_editar_un_rol_el_cambio_de_permisos_se_aplica_de_inmediato(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $auditor = $this->usuarioCon(RolSistema::Auditor);
        $this->actingAs($auditor)->get(route('bienes.index'))->assertOk();

        $rolAuditor = Role::delSistema(RolSistema::Auditor);
        $this->actingAs($admin)->put(route('roles.update', $rolAuditor), [
            'nombre' => 'Auditor',
            'permisos' => Permiso::where('slug', 'dashboard.ver')->pluck('id')->all(),
        ])->assertRedirect(route('roles.index'));

        $this->actingAs($auditor->fresh())->get(route('bienes.index'))->assertForbidden();
    }

    public function test_un_usuario_sin_permiso_de_roles_no_puede_modificarlos(): void
    {
        $operador = $this->usuarioCon(RolSistema::Operador);
        $rol = Role::delSistema(RolSistema::Operador);

        $this->actingAs($operador)->put(route('roles.update', $rol), [
            'nombre' => 'Operador',
            'permisos' => Permiso::pluck('id')->all(),
        ])->assertForbidden();

        $this->assertNotContains('usuarios.crear', $rol->fresh()->slugsDePermisos());
    }

    public function test_al_administrador_no_se_le_pueden_quitar_permisos(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $rolAdmin = Role::delSistema(RolSistema::Administrador);

        $this->actingAs($admin)->put(route('roles.update', $rolAdmin), [
            'nombre' => 'Administrador',
            'permisos' => [],
        ])->assertRedirect(route('roles.index'));

        $this->assertSame(Permiso::count(), $rolAdmin->permisos()->count());
    }

    public function test_los_roles_del_sistema_no_se_renombran_ni_se_eliminan(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $rol = Role::delSistema(RolSistema::Operador);

        $this->actingAs($admin)->put(route('roles.update', $rol), ['nombre' => 'Digitador'])
            ->assertSessionHasErrors('nombre');
        $this->actingAs($admin)->delete(route('roles.destroy', $rol));

        $this->assertDatabaseHas('roles', ['id' => $rol->id, 'nombre' => 'Operador']);
    }

    public function test_el_rol_administrador_no_se_puede_desactivar(): void
    {
        $admin = $this->usuarioCon(RolSistema::Administrador);
        $rolAdmin = Role::delSistema(RolSistema::Administrador);

        $this->actingAs($admin)->patch(route('roles.estado', $rolAdmin), ['estado' => 'inactivo'])
            ->assertSessionHas('error');

        $this->assertSame('activo', $rolAdmin->fresh()->estado);
    }

    public function test_el_usuario_solo_ve_el_detalle_de_los_bienes_a_su_cargo(): void
    {
        $usuario = $this->usuarioCon(RolSistema::Usuario);
        $responsable = Responsable::factory()->create(['usuario_id' => $usuario->id]);
        $propio = Bien::factory()->create(['responsable_id' => $responsable->id]);
        $ajeno = Bien::factory()->create();

        $this->actingAs($usuario)->get(route('bienes.show', $propio))->assertOk();
        $this->actingAs($usuario)->get(route('bienes.show', $ajeno))->assertForbidden();
        $this->actingAs($usuario)->get(route('mis-bienes.index'))
            ->assertSee($propio->codigo_barras)
            ->assertDontSee($ajeno->codigo_barras);
    }
}
