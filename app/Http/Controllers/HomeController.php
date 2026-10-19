<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * La raíz del sitio no muestra datos a visitantes: envía al login o, si hay
 * sesión, al panel. La vista pública `home` queda sin uso.
 */
class HomeController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($request->user() ? 'dashboard' : 'login');
    }
}
