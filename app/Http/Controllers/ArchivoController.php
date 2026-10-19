<?php

namespace App\Http\Controllers;

use App\Models\Baja;
use App\Models\Bien;
use App\Models\Documento;
use App\Models\Requerimiento;
use App\Services\AlmacenArchivos;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve los archivos del disco privado solo a quien la Policy autoriza:
 * el Usuario ve únicamente los archivos de sus bienes y de sus solicitudes.
 */
class ArchivoController extends Controller
{
    public function __construct(private readonly AlmacenArchivos $archivos) {}

    public function fotoBien(Bien $bien): StreamedResponse
    {
        $this->authorize('view', $bien);

        return $this->archivos->respuesta($bien->fotografia, "foto-{$bien->codigo_barras}");
    }

    public function comprobanteBien(Bien $bien): StreamedResponse
    {
        $this->authorize('view', $bien);

        return $this->archivos->respuesta($bien->comprobante_archivo, "comprobante-{$bien->codigo_barras}");
    }

    public function documento(Documento $documento): StreamedResponse
    {
        $this->authorize('view', $documento);

        return $this->archivos->respuesta($documento->ruta, $documento->nombre);
    }

    public function fotoBaja(Baja $baja): StreamedResponse
    {
        $this->authorize('view', $baja);

        return $this->archivos->respuesta($baja->fotografia, "baja-{$baja->id}");
    }

    public function adjuntoRequerimiento(Requerimiento $requerimiento): StreamedResponse
    {
        $this->authorize('view', $requerimiento);

        return $this->archivos->respuesta($requerimiento->archivo_adjunto, $requerimiento->archivo_nombre_original ?? $requerimiento->codigo);
    }
}
