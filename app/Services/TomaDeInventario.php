<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Bien;
use App\Models\Inventario;
use App\Models\InventarioDetalle;
use App\Models\Sede;
use App\Models\Usuario;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de la toma de inventario por escaneo.
 *
 * Clasificación de cada lectura:
 * - sobrante_no_registrado: el código no corresponde a ningún bien.
 * - dado_de_baja: el bien figura de baja pero sigue físicamente.
 * - encontrado: el bien está en la sede de la campaña y en el área donde se cuenta.
 * - fuera_de_ubicacion: el bien está registrado en otra sede u otra área.
 * - ya_escaneado: el código ya se leyó en esta campaña (no se guarda de nuevo).
 *
 * La base de datos garantiza una sola lectura vigente por código y campaña, y
 * `uuid_cliente` hace que reenviar una lectura offline no la duplique.
 */
class TomaDeInventario
{
    /**
     * @param  array<string, mixed>  $datos
     * @param  list<int>  $responsables
     */
    public function crear(Usuario $creador, array $datos, array $responsables): Inventario
    {
        return DB::transaction(function () use ($creador, $datos, $responsables) {
            // Serializa la creación de campañas de la misma sede.
            Sede::whereKey($datos['sede_id'])->lockForUpdate()->firstOrFail();
            $this->exigirSinCampanaSuperpuesta($datos['sede_id'], $datos['area_id'] ?? null);

            $inventario = Inventario::create([
                ...$datos,
                'codigo' => $this->siguienteCodigo(),
                'estado' => 'abierto',
                'usuario_id' => $creador->id,
            ]);

            $inventario->responsables()->sync(array_unique([...$responsables, $creador->id]));

            return $inventario;
        });
    }

    /**
     * Registra una lectura y devuelve su clasificación.
     *
     * @return array{resultado: string, detalle: InventarioDetalle, repetida: bool}
     */
    public function registrarLectura(
        Inventario $inventario,
        Usuario $usuario,
        string $codigo,
        ?int $areaEscaneoId = null,
        ?string $estadoFisico = null,
        ?string $uuidCliente = null,
        ?CarbonInterface $fechaHora = null,
        bool $offline = false,
    ): array {
        if ($uuidCliente && ($previa = InventarioDetalle::where('uuid_cliente', $uuidCliente)->first())) {
            return $this->respuestaIdempotente($previa);
        }

        $inventario->refresh();
        if (! $inventario->estaAbierto()) {
            throw ValidationException::withMessages(['codigo' => 'La campaña está cerrada: ya no admite lecturas.']);
        }

        $codigo = $this->normalizar($codigo);
        if ($codigo === '') {
            throw ValidationException::withMessages(['codigo' => 'El código leído está vacío.']);
        }

        $areaEscaneoId = $this->areaDeConteo($inventario, $areaEscaneoId);

        if ($existente = $this->lecturaVigente($inventario, $codigo)) {
            return ['resultado' => 'ya_escaneado', 'detalle' => $existente, 'repetida' => false];
        }

        $bien = Bien::with(['sede', 'area'])->where('codigo_barras', $codigo)->first();

        try {
            $detalle = InventarioDetalle::create([
                'inventario_id' => $inventario->id,
                'bien_id' => $bien?->id,
                'codigo_leido' => $codigo,
                'resultado' => $this->clasificar($inventario, $bien, $areaEscaneoId),
                'estado_fisico' => $estadoFisico,
                'area_escaneo_id' => $areaEscaneoId,
                'escaneado_por' => $usuario->id,
                'fecha_hora' => $fechaHora ?? now(),
                'uuid_cliente' => $uuidCliente,
                'registrado_offline' => $offline,
            ]);
        } catch (QueryException $e) {
            // Otro usuario registró el mismo código (o se reenvió el mismo uuid) en el mismo instante.
            if (! $this->esViolacionDeUnicidad($e)) {
                throw $e;
            }
            if ($uuidCliente && ($previa = InventarioDetalle::where('uuid_cliente', $uuidCliente)->first())) {
                return $this->respuestaIdempotente($previa);
            }

            return ['resultado' => 'ya_escaneado', 'detalle' => $this->lecturaVigente($inventario, $codigo) ?? throw $e, 'repetida' => false];
        }

        return ['resultado' => $detalle->resultado, 'detalle' => $detalle, 'repetida' => false];
    }

    public function clasificar(Inventario $inventario, ?Bien $bien, ?int $areaEscaneoId): string
    {
        if (! $bien) {
            return 'sobrante_no_registrado';
        }
        if ($bien->estaDadoDeBaja()) {
            return 'dado_de_baja';
        }

        $areaEsperada = $areaEscaneoId ?? $inventario->area_id;
        $enSuLugar = $bien->sede_id === $inventario->sede_id
            && ($areaEsperada === null || $bien->area_id === $areaEsperada);

        return $enSuLugar ? 'encontrado' : 'fuera_de_ubicacion';
    }

    /**
     * Deshace una lectura equivocada. Queda registrada como anulada (y en la auditoría).
     */
    public function deshacer(InventarioDetalle $detalle, Usuario $usuario): void
    {
        DB::transaction(function () use ($detalle, $usuario) {
            $detalle = InventarioDetalle::lockForUpdate()->findOrFail($detalle->id);
            $this->exigirCampanaAbierta($detalle->inventario()->firstOrFail());

            if ($detalle->estaAnulado() || $detalle->resultado === 'faltante') {
                throw ValidationException::withMessages(['detalle' => 'Esta lectura ya no se puede deshacer.']);
            }

            $detalle->update(['anulado_at' => now(), 'anulado_por' => $usuario->id]);
        });
    }

    public function marcarEstadoFisico(InventarioDetalle $detalle, ?string $estado): void
    {
        $this->exigirCampanaAbierta($detalle->inventario()->firstOrFail());
        $detalle->update(['estado_fisico' => $estado]);
    }

    /**
     * Cierra la campaña y registra como 'faltante' cada bien esperado que no se escaneó.
     */
    public function cerrar(Inventario $inventario, Usuario $usuario): Inventario
    {
        return DB::transaction(function () use ($inventario, $usuario) {
            $inventario = Inventario::lockForUpdate()->findOrFail($inventario->id);
            $this->exigirCampanaAbierta($inventario);

            $escaneados = $inventario->lecturas()->whereNotNull('bien_id')->pluck('bien_id');
            $esperados = $inventario->bienesEsperados();
            $faltantes = (clone $esperados)->whereNotIn('id', $escaneados)->get(['id', 'codigo_barras']);
            $ahora = now();

            foreach ($faltantes->chunk(500) as $lote) {
                InventarioDetalle::insert($lote->map(fn (Bien $bien) => [
                    'inventario_id' => $inventario->id,
                    'bien_id' => $bien->id,
                    'codigo_leido' => $bien->codigo_barras,
                    'resultado' => 'faltante',
                    'fecha_hora' => $ahora,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])->all());
            }

            $inventario->update([
                'estado' => 'cerrado',
                'cerrado_at' => $ahora,
                'cerrado_por' => $usuario->id,
                'total_esperados' => $esperados->count(),
            ]);

            return $inventario;
        });
    }

    /**
     * Reabre una campaña cerrada: descarta los faltantes calculados y vuelve a admitir lecturas.
     */
    public function reabrir(Inventario $inventario): Inventario
    {
        return DB::transaction(function () use ($inventario) {
            $inventario = Inventario::lockForUpdate()->findOrFail($inventario->id);

            if ($inventario->estaAbierto()) {
                throw ValidationException::withMessages(['inventario' => 'La campaña ya está abierta.']);
            }

            Sede::whereKey($inventario->sede_id)->lockForUpdate()->first();
            $this->exigirSinCampanaSuperpuesta($inventario->sede_id, $inventario->area_id, $inventario->id);

            $inventario->detalles()->where('resultado', 'faltante')->delete();
            $inventario->update(['estado' => 'abierto', 'cerrado_at' => null, 'cerrado_por' => null, 'total_esperados' => null]);

            return $inventario;
        });
    }

    /**
     * Avance y totales por clasificación.
     *
     * @return array<string, int|float>
     */
    public function resumen(Inventario $inventario): array
    {
        $porResultado = $inventario->lecturas()
            ->selectRaw('resultado, COUNT(*) as total')
            ->groupBy('resultado')
            ->pluck('total', 'resultado');

        $esperados = $inventario->estaAbierto() ? $inventario->bienesEsperados()->count() : (int) $inventario->total_esperados;

        $esperadosEscaneados = $inventario->lecturas()
            ->where('resultado', '!=', 'faltante')
            ->whereIn('bien_id', $inventario->bienesEsperados()->select('id'))
            ->count();

        // Al cerrar, los faltantes ya están guardados; mientras está abierta se calculan.
        $faltantes = $inventario->estaAbierto() ? max(0, $esperados - $esperadosEscaneados) : (int) ($porResultado['faltante'] ?? 0);

        return [
            'esperados' => $esperados,
            'escaneados' => $esperadosEscaneados,
            'porcentaje' => $esperados > 0 ? round($esperadosEscaneados / $esperados * 100, 1) : 0.0,
            'lecturas' => (int) $porResultado->except('faltante')->sum(),
            'encontrado' => (int) ($porResultado['encontrado'] ?? 0),
            'fuera_de_ubicacion' => (int) ($porResultado['fuera_de_ubicacion'] ?? 0),
            'sobrante_no_registrado' => (int) ($porResultado['sobrante_no_registrado'] ?? 0),
            'dado_de_baja' => (int) ($porResultado['dado_de_baja'] ?? 0),
            'faltante' => $faltantes,
        ];
    }

    public function normalizar(string $codigo): string
    {
        // Las lectoras tipo teclado pueden añadir espacios o caracteres de control.
        return Str::upper(trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $codigo)));
    }

    private function lecturaVigente(Inventario $inventario, string $codigo): ?InventarioDetalle
    {
        return $inventario->lecturas()->where('codigo_leido', $codigo)->first();
    }

    /**
     * Área física donde se cuenta: en una campaña por área es siempre esa área;
     * en una campaña por sede es opcional y debe pertenecer a la sede.
     */
    private function areaDeConteo(Inventario $inventario, ?int $areaEscaneoId): ?int
    {
        if ($inventario->area_id) {
            return $inventario->area_id;
        }

        if ($areaEscaneoId && ! Area::whereKey($areaEscaneoId)->where('sede_id', $inventario->sede_id)->exists()) {
            throw ValidationException::withMessages(['area_escaneo_id' => 'El área elegida no pertenece a la sede de la campaña.']);
        }

        return $areaEscaneoId;
    }

    /**
     * @return array{resultado: string, detalle: InventarioDetalle, repetida: bool}
     */
    private function respuestaIdempotente(InventarioDetalle $previa): array
    {
        return ['resultado' => $previa->resultado, 'detalle' => $previa, 'repetida' => true];
    }

    /**
     * Una campaña por sede completa se superpone con cualquier campaña de un área
     * de esa sede, y viceversa.
     */
    private function exigirSinCampanaSuperpuesta(int $sedeId, ?int $areaId, ?int $excepto = null): void
    {
        $superpuesta = Inventario::abiertos()
            ->where('sede_id', $sedeId)
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto))
            ->when($areaId, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('area_id')->orWhere('area_id', $areaId)))
            ->first();

        if ($superpuesta) {
            throw ValidationException::withMessages([
                'sede_id' => "Ya hay una campaña abierta para ese ámbito: {$superpuesta->codigo} ({$superpuesta->nombre}). Ciérrela antes de abrir otra.",
            ]);
        }
    }

    private function exigirCampanaAbierta(Inventario $inventario): void
    {
        if (! $inventario->estaAbierto()) {
            throw ValidationException::withMessages(['inventario' => 'La campaña está cerrada.']);
        }
    }

    private function siguienteCodigo(): string
    {
        $anio = now()->year;
        $numero = Inventario::where('codigo', 'like', "INV-{$anio}-%")->count() + 1;

        do {
            $codigo = sprintf('INV-%d-%03d', $anio, $numero++);
        } while (Inventario::where('codigo', $codigo)->exists());

        return $codigo;
    }

    private function esViolacionDeUnicidad(QueryException $e): bool
    {
        return in_array($e->errorInfo[1] ?? null, [1062, 19], true) || $e->getCode() === '23000';
    }
}
