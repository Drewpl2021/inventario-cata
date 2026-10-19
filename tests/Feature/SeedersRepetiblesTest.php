<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\Modulo;
use App\Models\Movimiento;
use App\Models\Permiso;
use App\Models\Requerimiento;
use App\Models\Responsable;
use App\Models\Role;
use App\Models\Sede;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El contenedor de Docker ejecuta los seeders en cada arranque: repetirlos no
 * debe duplicar registros ni deshacer lo que el usuario cambió.
 */
class SeedersRepetiblesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, int> */
    private function conteos(): array
    {
        return collect([Role::class, Modulo::class, Permiso::class, Sede::class, Area::class, Categoria::class, Responsable::class,
            Bien::class, Usuario::class, Movimiento::class, Baja::class, Requerimiento::class])
            ->mapWithKeys(fn (string $modelo) => [class_basename($modelo) => in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($modelo), true)
                ? $modelo::withTrashed()->count()
                : $modelo::count()])
            ->all();
    }

    public function test_ejecutar_los_seeders_dos_veces_no_duplica_ni_pisa_cambios(): void
    {
        $this->seed(DatabaseSeeder::class);
        $antes = $this->conteos();

        // Cambios hechos por las personas entre un arranque y otro.
        $admin = Usuario::where('email', 'admin@sibip.local')->firstOrFail();
        $admin->update(['password' => 'MiClaveNueva2026', 'debe_cambiar_password' => false]);
        $bien = Bien::orderBy('id')->firstOrFail();
        $bien->update(['nombre' => 'Laptop renombrada']);
        $bienBorrado = Bien::orderByDesc('id')->firstOrFail();
        $bienBorrado->delete();
        Sede::where('codigo', 'S03')->update(['nombre' => 'Sede renombrada']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($antes, $this->conteos());
        $this->assertTrue(Hash::check('MiClaveNueva2026', $admin->fresh()->password));
        $this->assertFalse($admin->fresh()->debe_cambiar_password);
        $this->assertSame('Laptop renombrada', $bien->fresh()->nombre);
        $this->assertSoftDeleted($bienBorrado);
        $this->assertSame('Sede renombrada', Sede::where('codigo', 'S03')->value('nombre'));
        $this->assertSame($bien->codigo_barras, $bien->fresh()->codigo_barras);
    }
}
