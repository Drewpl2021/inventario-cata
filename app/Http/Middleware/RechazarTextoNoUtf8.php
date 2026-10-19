<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Texto que no es UTF-8 válido (por ejemplo, copiado de un sistema antiguo en
 * Latin-1) se rechaza como error de validación del campo, en vez de terminar
 * en un error 500 al guardarlo en la base o al codificarlo en JSON.
 */
class RechazarTextoNoUtf8
{
    public const MENSAJE = 'El texto contiene caracteres no válidos. Vuelva a escribirlo o péguelo desde otro programa.';

    public function handle(Request $request, Closure $next): Response
    {
        $errores = [];
        $this->revisar([...$request->query(), ...$request->post()], '', $errores);

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return $next($request);
    }

    /**
     * @param  array<array-key, mixed>  $datos
     * @param  array<string, string>  $errores
     */
    private function revisar(array $datos, string $prefijo, array &$errores): void
    {
        foreach ($datos as $clave => $valor) {
            $campo = $prefijo === '' ? (string) $clave : "{$prefijo}.{$clave}";

            if (! mb_check_encoding((string) $clave, 'UTF-8')) {
                $errores['_campos'] = self::MENSAJE;
            } elseif (is_array($valor)) {
                $this->revisar($valor, $campo, $errores);
            } elseif (is_string($valor) && ! mb_check_encoding($valor, 'UTF-8')) {
                $errores[$campo] = self::MENSAJE;
            }
        }
    }
}
