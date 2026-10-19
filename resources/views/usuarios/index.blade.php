@extends('layouts.app')

@section('title', 'Usuarios')
@section('page_header', 'Gestión de Usuarios del Sistema')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Usuarios</h1>
            <p class="text-xs text-slate-500 mt-0.5">Cuentas de acceso, roles y vinculación con responsables de bienes.</p>
        </div>
        <div class="flex items-center gap-2">
            @can('roles.ver')
                <a href="{{ route('roles.index') }}" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-lg text-sm font-semibold transition flex items-center gap-2">
                    <i class="fa-solid fa-user-shield"></i> Roles y permisos
                </a>
            @endcan
            @can('usuarios.crear')
                <a href="{{ route('usuarios.create') }}" class="px-4 py-2 bg-[#0b3b70] hover:bg-[#072a50] text-white rounded-lg text-sm font-semibold transition shadow-md shadow-blue-900/20 flex items-center gap-2">
                    <i class="fa-solid fa-user-plus"></i> Nuevo usuario
                </a>
            @endcan
        </div>
    </div>

    @if($ficha = session('password_temporal'))
        {{-- Contraseña temporal: se muestra una sola vez, con opción de copiarla o imprimir la ficha de acceso. --}}
        <div id="modal-password" class="fixed inset-0 z-[70] bg-slate-900/50 flex items-end sm:items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-password">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-xl p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 shrink-0 rounded-full bg-marino-50 text-marino-700 flex items-center justify-center"><i class="fa-solid fa-key"></i></span>
                    <div>
                        <h2 id="titulo-modal-password" class="text-base font-bold text-slate-800">Contraseña temporal generada</h2>
                        <p class="text-sm text-slate-500 mt-1">Entréguela por un medio seguro. <strong>Se muestra una sola vez</strong>: el sistema pedirá cambiarla en el primer ingreso.</p>
                    </div>
                </div>
                <dl class="rounded-xl bg-slate-50 border border-slate-200 p-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                    <dt class="text-slate-500">Usuario</dt><dd class="font-semibold text-slate-800">{{ $ficha['nombre'] ?? '' }}</dd>
                    <dt class="text-slate-500">Correo</dt><dd class="text-slate-800 break-all">{{ $ficha['email'] }}</dd>
                    <dt class="text-slate-500">Contraseña</dt><dd><code id="password-temporal" class="font-mono text-lg font-bold tracking-wider text-marino-900">{{ $ficha['password'] }}</code></dd>
                </dl>
                <div class="flex flex-col sm:flex-row gap-2">
                    <button type="button" id="copiar-password" class="btn-secundario flex-1"><i class="fa-regular fa-copy"></i> Copiar</button>
                    <button type="button" id="imprimir-ficha" class="btn-secundario flex-1"><i class="fa-solid fa-print"></i> Imprimir ficha de acceso</button>
                </div>
                <button type="button" id="cerrar-modal-password" class="btn-primario w-full">Listo</button>
            </div>
        </div>

        @php($datosFicha = ['nombre' => $ficha['nombre'] ?? '', 'email' => $ficha['email'], 'password' => $ficha['password'], 'rol' => $ficha['rol'] ?? '', 'url' => route('login'), 'fecha' => now()->translatedFormat('j \d\e F \d\e Y')])
        @push('scripts')
        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                const ficha = @json($datosFicha);
                const modal = document.getElementById('modal-password');
                const escapar = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

                document.getElementById('copiar-password').addEventListener('click', async (e) => {
                    try {
                        await navigator.clipboard.writeText(ficha.password);
                        e.currentTarget.innerHTML = '<i class="fa-solid fa-check"></i> Copiada';
                    } catch {
                        window.getSelection().selectAllChildren(document.getElementById('password-temporal'));
                    }
                });

                document.getElementById('imprimir-ficha').addEventListener('click', () => {
                    const ventana = window.open('', '_blank', 'width=720,height=640');
                    if (!ventana) return;
                    ventana.document.write(`<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Ficha de acceso · SIBIP</title>
                        <style>body{font-family:system-ui,sans-serif;color:#1e293b;margin:40px}h1{color:#0b3b70;font-size:20px;margin:0}
                        .caja{border:2px solid #0b3b70;border-radius:12px;padding:24px;max-width:520px}table{width:100%;margin:16px 0;border-collapse:collapse}
                        td{padding:6px 0;font-size:14px}td:first-child{color:#64748b;width:140px}code{font-size:20px;font-weight:700;letter-spacing:2px}
                        p{font-size:13px;color:#475569}</style></head><body><div class="caja">
                        <h1>SIBIP · Ficha de acceso</h1><p>Colegio Adventista Túpac Amaru · ${escapar(ficha.fecha)}</p>
                        <table><tr><td>Usuario</td><td><strong>${escapar(ficha.nombre)}</strong></td></tr>
                        <tr><td>Rol</td><td>${escapar(ficha.rol)}</td></tr>
                        <tr><td>Correo (usuario)</td><td>${escapar(ficha.email)}</td></tr>
                        <tr><td>Contraseña temporal</td><td><code>${escapar(ficha.password)}</code></td></tr>
                        <tr><td>Dirección</td><td>${escapar(ficha.url)}</td></tr></table>
                        <p>Al ingresar por primera vez, el sistema le pedirá elegir una contraseña propia (mínimo 8 caracteres, con letras y números). No comparta esta ficha y destrúyala después de usarla.</p>
                        </div></body></html>`);
                    ventana.document.close();
                    ventana.focus();
                    ventana.print();
                });

                const cerrar = () => modal.remove();
                document.getElementById('cerrar-modal-password').addEventListener('click', cerrar);
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrar(); });
                document.getElementById('copiar-password').focus();
            })();
        </script>
        @endpush
    @endif

    <form method="GET" class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm grid grid-cols-1 sm:grid-cols-4 gap-3">
        <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre o correo..." class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm sm:col-span-2">
        <select name="rol_id" class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm">
            <option value="">Todos los roles</option>
            @foreach($roles as $rol)
                <option value="{{ $rol->id }}" @selected(request('rol_id') == $rol->id)>{{ $rol->nombre }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <select name="estado" class="flex-1 px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm">
                <option value="">Todos los estados</option>
                <option value="activo" @selected(request('estado') === 'activo')>Activos</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
            </select>
            <button type="submit" class="px-3.5 py-2 bg-slate-800 text-white rounded-lg text-sm"><i class="fa-solid fa-filter"></i></button>
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-400 uppercase text-xs font-semibold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-4">Usuario</th>
                        <th class="py-3 px-4">Rol</th>
                        <th class="py-3 px-4">Sede / Área</th>
                        <th class="py-3 px-4">Estado</th>
                        <th class="py-3 px-4">Último acceso</th>
                        <th class="py-3 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($usuarios as $usr)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3.5 px-4">
                                <p class="font-semibold text-slate-800">{{ $usr->nombre_completo }}</p>
                                <p class="text-xs text-slate-500">{{ $usr->email }}</p>
                                @if($usr->responsable)
                                    <p class="text-[11px] text-emerald-700 mt-0.5"><i class="fa-solid fa-link"></i> Responsable: {{ $usr->responsable->nombre_completo }}</p>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs font-bold text-indigo-700">{{ $usr->rol->nombre ?? 'N/A' }}</td>
                            <td class="py-3.5 px-4 text-xs text-slate-600">
                                {{ $usr->sede->nombre ?? '—' }}
                                <span class="block text-[11px] text-slate-400">{{ $usr->area->nombre ?? '' }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase border {{ $usr->estaActivo() ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' }}">
                                    {{ $usr->estado }}
                                </span>
                                @if($usr->debe_cambiar_password)
                                    <span class="block text-[10px] text-amber-600 mt-1">Contraseña temporal</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-500 font-mono">{{ $usr->ultimo_acceso_at?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    @can('usuarios.editar')
                                        <a href="{{ route('usuarios.edit', $usr) }}" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold">Editar</a>

                                        @if($usr->id !== Auth::id())
                                            <form method="POST" action="{{ route('usuarios.estado', $usr) }}" data-confirmar="¿Confirma el cambio de estado de esta cuenta?">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="estado" value="{{ $usr->estaActivo() ? 'inactivo' : 'activo' }}">
                                                <button type="submit" class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $usr->estaActivo() ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                    {{ $usr->estaActivo() ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('usuarios.password', $usr) }}" data-confirmar="Se generará una contraseña temporal y se cerrarán sus sesiones. ¿Continuar?">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="px-2.5 py-1 bg-sky-50 text-sky-700 hover:bg-sky-100 rounded-lg text-xs font-semibold" title="Restablecer contraseña">
                                                    <i class="fa-solid fa-key"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endcan

                                    @can('usuarios.eliminar')
                                        @if($usr->id !== Auth::id())
                                            <form method="POST" action="{{ route('usuarios.destroy', $usr) }}" data-confirmar="¿Eliminar esta cuenta? Solo es posible si no registró operaciones." data-peligro data-aceptar="Eliminar">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="px-2.5 py-1 text-rose-600 hover:bg-rose-50 rounded-lg text-xs font-semibold" title="Eliminar">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-400 italic">No hay usuarios que coincidan con la búsqueda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($usuarios->hasPages())
            <div class="p-4 border-t border-slate-100">{{ $usuarios->links() }}</div>
        @endif
    </div>
</div>
@endsection
