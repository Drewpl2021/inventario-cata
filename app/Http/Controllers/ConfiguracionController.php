<?php

namespace App\Http\Controllers;

use App\Services\GeneradorEtiquetas;
use App\Support\ConfiguracionEtiqueta;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Configuración del sistema. Por ahora, el formato de las etiquetas térmicas;
 * en la Fase 4 se agregan sedes, áreas, categorías, responsables y usuarios.
 */
class ConfiguracionController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('configuracion.etiquetas');
    }

    public function etiquetas(): View
    {
        $config = ConfiguracionEtiqueta::actual();

        return view('configuracion.etiquetas', [
            'config' => $config,
            'generador' => new GeneradorEtiquetas($config),
        ]);
    }

    public function guardarEtiquetas(Request $request): RedirectResponse
    {
        $config = $this->configuracionValidada($request);
        $config->guardar();

        return redirect()->route('configuracion.etiquetas')->with('success', "Formato de etiqueta guardado: {$config->descripcion()}.");
    }

    /**
     * Vista previa en PNG con valores aún no guardados (la usa el formulario).
     */
    public function vistaPrevia(Request $request): Response
    {
        try {
            $config = $this->configuracionValidada($request);
        } catch (ValidationException) {
            $config = ConfiguracionEtiqueta::actual();
        }

        $png = (new GeneradorEtiquetas($config))->png(GeneradorEtiquetas::CODIGO_DE_EJEMPLO, 'Computadora de escritorio HP ProDesk');

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-store']);
    }

    private function configuracionValidada(Request $request): ConfiguracionEtiqueta
    {
        $datos = $request->validate([
            'preset' => ['required', Rule::in([...array_keys(ConfiguracionEtiqueta::PRESETS), 'personalizado'])],
            'ancho_mm' => ['required_if:preset,personalizado', 'nullable', 'numeric', 'between:20,110'],
            'alto_mm' => ['required_if:preset,personalizado', 'nullable', 'numeric', 'between:10,100'],
            'dpi' => ['required', Rule::in(ConfiguracionEtiqueta::DPI_PERMITIDOS)],
            'ancho_modulo' => ['required', 'integer', 'between:1,6'],
            'alto_barras_mm' => ['required', 'numeric', 'between:3,60'],
            'mostrar_nombre' => ['nullable', 'boolean'],
            'mostrar_logo' => ['nullable', 'boolean'],
        ], [
            'ancho_mm.required_if' => 'Indique el ancho de la etiqueta personalizada.',
            'alto_mm.required_if' => 'Indique el alto de la etiqueta personalizada.',
        ]);

        $config = ConfiguracionEtiqueta::desdeArray([
            ...$datos,
            'mostrar_nombre' => $request->boolean('mostrar_nombre'),
            'mostrar_logo' => $request->boolean('mostrar_logo'),
        ]);

        if (! (new GeneradorEtiquetas($config))->cabe()) {
            throw ValidationException::withMessages([
                'ancho_mm' => 'Un código como '.GeneradorEtiquetas::CODIGO_DE_EJEMPLO." no entra en {$config->descripcion()}. Use una etiqueta más ancha.",
            ]);
        }

        if ($config->altoBarrasMm >= $config->altoMm) {
            throw ValidationException::withMessages(['alto_barras_mm' => 'La altura de las barras debe ser menor que el alto de la etiqueta.']);
        }

        return $config;
    }
}
