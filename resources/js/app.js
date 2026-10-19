import { catalogos, pendientes } from './almacen-offline';

/**
 * Comportamiento común de la interfaz SIBIP:
 * menú lateral en celular, toasts, diálogos de confirmación, buscador global,
 * notificaciones, gráficos y cierre de sesión seguro (lecturas offline).
 */
const token = document.querySelector('meta[name="csrf-token"]')?.content;

// ---------------------------------------------------------------------------
// Menú lateral (hamburguesa en celular y tablet) y submenús desplegables
// ---------------------------------------------------------------------------
function iniciarMenu() {
    const menu = document.getElementById('menu-lateral');
    const fondo = document.getElementById('menu-fondo');
    if (!menu) return;

    const alternar = (abrir) => {
        menu.classList.toggle('-translate-x-full', !abrir);
        fondo?.classList.toggle('hidden', !abrir);
        document.body.classList.toggle('overflow-hidden', abrir);
    };

    document.querySelectorAll('[data-abrir-menu]').forEach((b) => b.addEventListener('click', () => alternar(true)));
    document.querySelectorAll('[data-cerrar-menu]').forEach((b) => b.addEventListener('click', () => alternar(false)));
    fondo?.addEventListener('click', () => alternar(false));

    document.querySelectorAll('[data-submenu]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const lista = document.getElementById(boton.dataset.submenu);
            const abierto = lista.classList.toggle('hidden') === false;
            boton.setAttribute('aria-expanded', String(abierto));
            boton.querySelector('[data-flecha]')?.classList.toggle('rotate-180', abierto);
        });
    });
}

// ---------------------------------------------------------------------------
// Toasts de éxito y error
// ---------------------------------------------------------------------------
const ESTILO_TOAST = {
    exito: ['bg-emerald-600', 'fa-circle-check'],
    error: ['bg-rose-600', 'fa-triangle-exclamation'],
    aviso: ['bg-amber-500', 'fa-circle-exclamation'],
};

export function mostrarToast(tipo, mensaje) {
    let contenedor = document.getElementById('toasts');
    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'toasts';
        contenedor.className = 'fixed z-[60] top-4 right-4 left-4 sm:left-auto sm:w-96 flex flex-col gap-2 pointer-events-none';
        contenedor.setAttribute('aria-live', 'polite');
        document.body.append(contenedor);
    }

    const [fondo, icono] = ESTILO_TOAST[tipo] || ESTILO_TOAST.exito;
    const toast = document.createElement('div');
    toast.className = `pointer-events-auto flex items-start gap-3 p-4 rounded-xl text-white shadow-lg ${fondo} transition duration-300`;
    toast.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
    toast.innerHTML = `<i class="fa-solid ${icono} mt-0.5"></i><p class="flex-1 text-sm font-medium"></p><button type="button" class="opacity-80 hover:opacity-100" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>`;
    toast.querySelector('p').textContent = mensaje;

    const quitar = () => { toast.classList.add('opacity-0', 'translate-x-4'); setTimeout(() => toast.remove(), 300); };
    toast.querySelector('button').addEventListener('click', quitar);
    contenedor.append(toast);
    setTimeout(quitar, tipo === 'error' ? 8000 : 5000);
}

function mostrarToastsIniciales() {
    const datos = document.getElementById('toasts-iniciales');
    if (!datos) return;
    JSON.parse(datos.textContent || '[]').forEach(([tipo, mensaje]) => mostrarToast(tipo, mensaje));
}

// ---------------------------------------------------------------------------
// Diálogo de confirmación (reemplaza confirm/prompt del navegador)
// ---------------------------------------------------------------------------
export function confirmar({ titulo = '¿Confirmar acción?', mensaje = '', aceptar = 'Confirmar', peligro = false, pedirMotivo = false }) {
    return new Promise((resolver) => {
        const fondo = document.createElement('div');
        fondo.className = 'fixed inset-0 z-[70] bg-slate-900/50 flex items-end sm:items-center justify-center p-4';
        fondo.innerHTML = `
            <div role="dialog" aria-modal="true" class="bg-white w-full max-w-md rounded-2xl shadow-xl p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 shrink-0 rounded-full flex items-center justify-center ${peligro ? 'bg-rose-50 text-rose-600' : 'bg-marino-50 text-marino-700'}">
                        <i class="fa-solid ${peligro ? 'fa-triangle-exclamation' : 'fa-circle-question'}"></i>
                    </span>
                    <div><h2 class="text-base font-bold text-slate-800" data-titulo></h2><p class="text-sm text-slate-500 mt-1" data-mensaje></p></div>
                </div>
                ${pedirMotivo ? '<div><label class="etiqueta-campo" for="dialogo-motivo">Motivo</label><textarea id="dialogo-motivo" rows="3" class="campo" required></textarea><p class="text-xs text-rose-600 mt-1 hidden" data-error>Indique el motivo.</p></div>' : ''}
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <button type="button" class="btn-secundario" data-cancelar>Cancelar</button>
                    <button type="button" class="${peligro ? 'btn-peligro' : 'btn-primario'}" data-aceptar></button>
                </div>
            </div>`;
        fondo.querySelector('[data-titulo]').textContent = titulo;
        fondo.querySelector('[data-mensaje]').textContent = mensaje;
        fondo.querySelector('[data-aceptar]').textContent = aceptar;

        const cerrar = (valor) => { fondo.remove(); document.removeEventListener('keydown', teclas); resolver(valor); };
        const teclas = (e) => { if (e.key === 'Escape') cerrar(false); };
        document.addEventListener('keydown', teclas);
        fondo.addEventListener('click', (e) => { if (e.target === fondo) cerrar(false); });
        fondo.querySelector('[data-cancelar]').addEventListener('click', () => cerrar(false));
        fondo.querySelector('[data-aceptar]').addEventListener('click', () => {
            if (!pedirMotivo) return cerrar(true);
            const motivo = fondo.querySelector('#dialogo-motivo').value.trim();
            if (!motivo) return fondo.querySelector('[data-error]').classList.remove('hidden');
            cerrar(motivo);
        });

        document.body.append(fondo);
        (fondo.querySelector('#dialogo-motivo') || fondo.querySelector('[data-aceptar]')).focus();
    });
}

/**
 * Formularios con data-confirmar="mensaje" piden confirmación antes de enviarse.
 * Con data-pedir-motivo, el texto ingresado se copia al campo motivo_rechazo.
 */
function iniciarConfirmaciones() {
    document.addEventListener('submit', async (evento) => {
        const form = evento.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirmar || form.dataset.confirmado) return;
        evento.preventDefault();

        const respuesta = await confirmar({
            titulo: form.dataset.titulo || '¿Confirmar acción?',
            mensaje: form.dataset.confirmar,
            aceptar: form.dataset.aceptar || 'Confirmar',
            peligro: form.dataset.peligro !== undefined,
            pedirMotivo: form.dataset.pedirMotivo !== undefined,
        });
        if (!respuesta) return;

        if (typeof respuesta === 'string') {
            form.querySelector('[name="motivo_rechazo"]').value = respuesta;
        }
        form.dataset.confirmado = '1';
        form.requestSubmit(evento.submitter || undefined);
    }, true);
}

// ---------------------------------------------------------------------------
// Buscador global del encabezado
// ---------------------------------------------------------------------------
function iniciarBuscador() {
    const entrada = document.getElementById('buscador-global');
    const resultados = document.getElementById('buscador-resultados');
    if (!entrada || !resultados) return;

    let espera;
    let ultima = '';
    const ocultar = () => resultados.classList.add('hidden');

    entrada.addEventListener('input', () => {
        clearTimeout(espera);
        const texto = entrada.value.trim();
        if (texto.length < 2) return ocultar();

        espera = setTimeout(async () => {
            ultima = texto;
            const respuesta = await fetch(`${entrada.dataset.url}?q=${encodeURIComponent(texto)}`, { headers: { Accept: 'application/json' } });
            if (!respuesta.ok || texto !== ultima) return;
            const { resultados: lista } = await respuesta.json();

            resultados.innerHTML = '';
            if (!lista.length) {
                resultados.innerHTML = '<p class="px-4 py-6 text-sm text-center text-slate-400"><i class="fa-solid fa-magnifying-glass"></i> Sin resultados.</p>';
            }
            lista.forEach((bien) => {
                const enlace = document.createElement('a');
                enlace.href = bien.url;
                enlace.className = 'flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
                enlace.innerHTML = '<i class="fa-solid fa-box-archive text-slate-400"></i><div class="min-w-0"><p class="text-sm font-semibold text-slate-800 truncate"></p><p class="text-xs text-slate-500 truncate"></p></div>';
                enlace.querySelector('p').textContent = bien.nombre;
                enlace.querySelectorAll('p')[1].textContent = [bien.codigo, bien.patrimonial, bien.categoria].filter(Boolean).join(' · ');
                resultados.append(enlace);
            });
            resultados.classList.remove('hidden');
        }, 250);
    });

    entrada.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') ocultar();
        if (e.key === 'ArrowDown') resultados.querySelector('a')?.focus();
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('#buscador')) ocultar(); });
}

// ---------------------------------------------------------------------------
// Tablas: en pantallas pequeñas se muestran como tarjetas. A las celdas sin
// data-label se les asigna el título de su columna.
// ---------------------------------------------------------------------------
function iniciarTablasResponsivas() {
    document.querySelectorAll('main table:not([data-sin-tarjetas])').forEach((tabla) => {
        tabla.classList.add('tabla-tarjetas');
        const titulos = [...tabla.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        tabla.querySelectorAll('tbody tr').forEach((fila) => {
            [...fila.children].forEach((celda, i) => {
                if (!celda.hasAttribute('data-label') && !celda.hasAttribute('colspan')) {
                    celda.setAttribute('data-label', titulos[i] || '');
                }
            });
        });
    });
}

// ---------------------------------------------------------------------------
// Catálogos: un mismo formulario sirve para crear y editar.
// [data-catalogo] contiene [data-panel-formulario] (con <form data-url-crear>),
// botones [data-nuevo] y botones [data-editar='{"url": "...", "valores": {...}}'].
// ---------------------------------------------------------------------------
function iniciarCatalogos() {
    document.querySelectorAll('[data-catalogo]').forEach((catalogo) => {
        const panel = catalogo.querySelector('[data-panel-formulario]');
        const form = panel?.querySelector('form');
        if (!form) return;

        const metodo = form.querySelector('input[name="_method"]');
        const urlEdicion = form.querySelector('input[name="_url_edicion"]');
        const titulo = panel.querySelector('[data-titulo-formulario]');
        const botonGuardar = form.querySelector('[data-boton-guardar]');
        const campos = () => [...form.elements].filter((c) => c.name && !['_token', '_method', '_url_edicion'].includes(c.name) && c.type !== 'submit');

        const abrir = (editar) => {
            panel.classList.remove('hidden');
            if (editar) {
                form.action = editar.url;
                metodo.disabled = false;
                urlEdicion.value = editar.url;
                titulo.textContent = panel.dataset.tituloEditar;
                botonGuardar.textContent = 'Guardar cambios';
                campos().forEach((c) => {
                    if (!(c.name in editar.valores)) return;
                    const valor = editar.valores[c.name] ?? '';
                    if (c.type === 'checkbox') c.checked = Boolean(valor);
                    else c.value = valor;
                });
            } else {
                form.action = form.dataset.urlCrear;
                metodo.disabled = true;
                urlEdicion.value = '';
                titulo.textContent = panel.dataset.tituloCrear;
                botonGuardar.textContent = 'Guardar';
                campos().forEach((c) => {
                    if (c.type === 'checkbox') c.checked = false;
                    else if (c.tagName === 'SELECT') c.value = c.dataset.porDefecto ?? '';
                    else c.value = '';
                });
            }
            campos().forEach((c) => c.dispatchEvent(new Event('change')));
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            campos()[0]?.focus({ preventScroll: true });
        };

        catalogo.querySelectorAll('[data-nuevo]').forEach((b) => b.addEventListener('click', () => abrir(null)));
        catalogo.querySelectorAll('[data-editar]').forEach((b) => b.addEventListener('click', () => abrir(JSON.parse(b.dataset.editar))));
        panel.querySelectorAll('[data-cancelar-formulario]').forEach((b) => b.addEventListener('click', () => panel.classList.add('hidden')));

        // Tras un error de validación, el formulario vuelve abierto en el mismo modo.
        if (urlEdicion.value) {
            form.action = urlEdicion.value;
            metodo.disabled = false;
            titulo.textContent = panel.dataset.tituloEditar;
            botonGuardar.textContent = 'Guardar cambios';
        }
    });
}

// ---------------------------------------------------------------------------
// Pestañas: [data-pestanas] con botones [data-pestana="x"] y paneles [data-panel="x"]
// ---------------------------------------------------------------------------
function iniciarPestanas() {
    document.querySelectorAll('[data-pestanas]').forEach((grupo) => {
        const botones = grupo.querySelectorAll('[data-pestana]');
        const activar = (nombre) => {
            botones.forEach((b) => {
                const activo = b.dataset.pestana === nombre;
                b.setAttribute('aria-selected', String(activo));
                b.classList.toggle('border-marino-700', activo);
                b.classList.toggle('text-marino-800', activo);
                b.classList.toggle('border-transparent', !activo);
                b.classList.toggle('text-slate-500', !activo);
            });
            grupo.querySelectorAll('[data-panel]').forEach((p) => p.classList.toggle('hidden', p.dataset.panel !== nombre));
        };
        botones.forEach((b) => b.addEventListener('click', () => activar(b.dataset.pestana)));
        activar(grupo.dataset.activa || botones[0]?.dataset.pestana);
    });
}

// ---------------------------------------------------------------------------
// Selector de bien de los formularios de solicitud
// ---------------------------------------------------------------------------
function iniciarSelectoresDeBien() {
    document.querySelectorAll('[data-selector-bien]').forEach((selector) => {
        const valor = selector.querySelector('[data-valor]');
        const texto = selector.querySelector('[data-texto]');
        const resultados = selector.querySelector('[data-resultados]');
        const tarjeta = selector.querySelector('[data-tarjeta]');
        const buscador = selector.querySelector('[data-buscador]');
        let espera;

        const elegir = (bien) => {
            valor.value = bien?.id ?? '';
            tarjeta.classList.toggle('hidden', !bien);
            buscador.classList.toggle('hidden', Boolean(bien));
            resultados.classList.add('hidden');
            if (bien) {
                ['nombre', 'codigo', 'categoria', 'estado'].forEach((c) => { tarjeta.querySelector(`[data-campo="${c}"]`).textContent = bien[c] ?? ''; });
                const foto = tarjeta.querySelector('[data-foto]');
                foto.innerHTML = '';
                if (bien.foto) {
                    const img = document.createElement('img');
                    img.src = bien.foto;
                    img.alt = '';
                    img.className = 'w-full h-full object-cover';
                    foto.append(img);
                } else {
                    foto.innerHTML = '<i class="fa-solid fa-box-archive text-2xl text-slate-300"></i>';
                }
            } else {
                texto.value = '';
                texto.focus();
            }
            selector.dispatchEvent(new CustomEvent('bien-seleccionado', { bubbles: true, detail: bien }));
        };

        const buscar = async () => {
            const respuesta = await fetch(`${selector.dataset.url}&q=${encodeURIComponent(texto.value.trim())}`, { headers: { Accept: 'application/json' } });
            if (!respuesta.ok) return;
            const { resultados: lista } = await respuesta.json();
            resultados.innerHTML = '';
            if (!lista.length) {
                resultados.innerHTML = '<p class="px-4 py-4 text-sm text-slate-400 text-center">No hay bienes que coincidan.</p>';
            }
            lista.forEach((bien) => {
                const opcion = document.createElement('button');
                opcion.type = 'button';
                opcion.className = 'w-full text-left flex flex-col px-4 py-2.5 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
                opcion.innerHTML = '<span class="text-sm font-semibold text-slate-800"></span><span class="text-xs text-slate-500"></span>';
                opcion.children[0].textContent = bien.nombre;
                opcion.children[1].textContent = `${bien.codigo} · ${bien.ubicacion}`;
                opcion.addEventListener('click', () => elegir(bien));
                resultados.append(opcion);
            });
            resultados.classList.remove('hidden');
        };

        texto.addEventListener('input', () => { clearTimeout(espera); espera = setTimeout(buscar, 250); });
        texto.addEventListener('focus', buscar);
        selector.querySelector('[data-quitar]').addEventListener('click', () => elegir(null));
        document.addEventListener('click', (e) => { if (!buscador.contains(e.target)) resultados.classList.add('hidden'); });

        if (valor.value) buscador.classList.add('hidden');
    });
}

// ---------------------------------------------------------------------------
// Notificaciones (campana)
// ---------------------------------------------------------------------------
function iniciarNotificaciones() {
    const boton = document.getElementById('boton-notificaciones');
    const panel = document.getElementById('panel-notificaciones');
    if (!boton || !panel) return;

    boton.addEventListener('click', (e) => {
        e.stopPropagation();
        const abrir = panel.classList.toggle('hidden') === false;
        boton.setAttribute('aria-expanded', String(abrir));
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('#notificaciones')) panel.classList.add('hidden'); });

    const marcar = (url) => fetch(url, { method: 'PATCH', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } });

    panel.querySelectorAll('[data-notificacion]').forEach((enlace) => {
        enlace.addEventListener('click', async (e) => {
            e.preventDefault();
            await marcar(enlace.dataset.marcar).catch(() => {});
            window.location.href = enlace.href;
        });
    });

    panel.querySelector('[data-leer-todas]')?.addEventListener('click', async (e) => {
        await marcar(e.currentTarget.dataset.url).catch(() => {});
        panel.querySelectorAll('[data-no-leida]').forEach((n) => n.removeAttribute('data-no-leida'));
        panel.querySelectorAll('[data-punto]').forEach((p) => p.remove());
        document.getElementById('contador-notificaciones')?.remove();
    });
}

// ---------------------------------------------------------------------------
// Gráficos (Chart.js, cargado solo donde hace falta)
// ---------------------------------------------------------------------------
async function iniciarGraficos() {
    const lienzos = document.querySelectorAll('canvas[data-grafico-dona]');
    if (!lienzos.length) return;

    const { Chart, DoughnutController, ArcElement, Tooltip } = await import('chart.js');
    Chart.register(DoughnutController, ArcElement, Tooltip);

    lienzos.forEach((lienzo) => {
        const datos = JSON.parse(lienzo.dataset.graficoDona);
        new Chart(lienzo, {
            type: 'doughnut',
            data: {
                labels: datos.etiquetas,
                datasets: [{ data: datos.valores, backgroundColor: datos.colores, borderWidth: 3, borderColor: '#fff' }],
            },
            options: { cutout: '72%', plugins: { legend: { display: false } }, maintainAspectRatio: false },
        });
    });
}

// ---------------------------------------------------------------------------
// Pantallas de acceso: mostrar la contraseña y accesos demo (solo en local)
// ---------------------------------------------------------------------------
function iniciarBotonesDeImpresion() {
    document.querySelectorAll('[data-imprimir]').forEach((boton) => boton.addEventListener('click', () => window.print()));
}

function iniciarPantallasDeAcceso() {
    document.querySelectorAll('[data-ver-password]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const campo = document.getElementById(boton.dataset.verPassword);
            const visible = campo.type === 'password';
            campo.type = visible ? 'text' : 'password';
            boton.innerHTML = visible ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
            boton.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });

    document.querySelectorAll('[data-demo-correo]').forEach((boton) => boton.addEventListener('click', () => {
        document.getElementById('login_input').value = boton.dataset.demoCorreo;
        document.getElementById('password_input').value = boton.dataset.demoClave;
    }));
}

// ---------------------------------------------------------------------------
// Cierre por inactividad: el servidor cierra la sesión tras SESSION_LIFETIME
// minutos sin peticiones. Cinco minutos antes se avisa con opción de seguir.
// La última actividad se comparte entre pestañas con localStorage.
// ---------------------------------------------------------------------------
const CLAVE_ACTIVIDAD = 'sibip:ultima-actividad';
let ultimaActividadLocal = Date.now();

function marcarActividad() {
    ultimaActividadLocal = Date.now();
    try { localStorage.setItem(CLAVE_ACTIVIDAD, String(ultimaActividadLocal)); } catch { /* sin almacenamiento: solo esta pestaña */ }
}

function ultimaActividad() {
    let compartida = 0;
    try { compartida = Number(localStorage.getItem(CLAVE_ACTIVIDAD)) || 0; } catch { /* sin almacenamiento */ }
    return Math.max(ultimaActividadLocal, compartida);
}

function iniciarAvisoDeInactividad() {
    const minutos = Number(document.querySelector('meta[name="sesion-minutos"]')?.content);
    const urlRenovar = document.querySelector('meta[name="sesion-renovar"]')?.content;
    if (!minutos || !urlRenovar) return;

    const duracion = minutos * 60_000;
    const anticipacion = Math.min(5 * 60_000, duracion / 2);
    let aviso = null;

    marcarActividad();

    // Cada petición al servidor (fetch) también renueva la sesión.
    const fetchOriginal = window.fetch.bind(window);
    window.fetch = (...argumentos) => fetchOriginal(...argumentos).then((respuesta) => { marcarActividad(); return respuesta; });

    const cerrarAviso = () => { aviso?.remove(); aviso = null; };

    const mostrarAviso = () => {
        aviso = document.createElement('div');
        aviso.className = 'fixed inset-0 z-[80] bg-slate-900/50 flex items-end sm:items-center justify-center p-4';
        aviso.innerHTML = `
            <div role="alertdialog" aria-modal="true" aria-labelledby="aviso-sesion-titulo" class="bg-white w-full max-w-md rounded-2xl shadow-xl p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 shrink-0 rounded-full flex items-center justify-center bg-amber-50 text-amber-600"><i class="fa-solid fa-clock"></i></span>
                    <div>
                        <h2 id="aviso-sesion-titulo" class="text-base font-bold text-slate-800">Su sesión está por cerrarse</h2>
                        <p class="text-sm text-slate-500 mt-1">Por inactividad, la sesión se cerrará en <strong data-restante></strong>. Lo que no haya guardado se perderá.</p>
                    </div>
                </div>
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <button type="button" class="btn-secundario" data-salir>Cerrar sesión</button>
                    <button type="button" class="btn-primario" data-seguir>Seguir conectado</button>
                </div>
            </div>`;
        aviso.querySelector('[data-seguir]').addEventListener('click', async () => {
            const respuesta = await fetch(urlRenovar, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } }).catch(() => null);
            if (respuesta?.ok) { cerrarAviso(); return; }
            window.location.href = '/login?expirada=1';
        });
        aviso.querySelector('[data-salir]').addEventListener('click', () => {
            const form = document.querySelector('form[data-cerrar-sesion]');
            form ? form.requestSubmit() : (window.location.href = '/login');
        });
        document.body.append(aviso);
        aviso.querySelector('[data-seguir]').focus();
    };

    const revisar = async () => {
        const restante = ultimaActividad() + duracion - Date.now();

        if (restante <= 0) {
            clearInterval(temporizador);
            await catalogos.borrarTodos().catch(() => {});
            window.location.href = '/login?expirada=1';
            return;
        }
        if (restante > anticipacion) { cerrarAviso(); return; } // otra pestaña renovó la sesión
        if (!aviso) mostrarAviso();

        const segundos = Math.ceil(restante / 1000);
        aviso.querySelector('[data-restante]').textContent = segundos >= 60
            ? `${Math.floor(segundos / 60)} min ${String(segundos % 60).padStart(2, '0')} s`
            : `${segundos} segundos`;
    };

    const temporizador = setInterval(revisar, 1000);
}

// ---------------------------------------------------------------------------
// Cerrar sesión sin perder lecturas offline
// ---------------------------------------------------------------------------
function iniciarCierreDeSesion() {
    document.querySelectorAll('form[data-cerrar-sesion]').forEach((form) => {
        form.addEventListener('submit', async (evento) => {
            if (form.dataset.confirmado) return;
            evento.preventDefault();

            const cantidad = await pendientes.contarTodos().catch(() => 0);
            if (cantidad > 0) {
                const seguir = await confirmar({
                    titulo: 'Hay lecturas sin sincronizar',
                    mensaje: `Tiene ${cantidad} lecturas de toma de inventario guardadas sin conexión. Vuelva a la pantalla de escaneo con conexión para sincronizarlas. Si sale ahora, quedarán guardadas en este navegador hasta que vuelva a entrar.`,
                    aceptar: 'Salir de todos modos',
                    peligro: true,
                });
                if (!seguir) return;
            }

            await catalogos.borrarTodos().catch(() => {});
            form.dataset.confirmado = '1';
            form.submit();
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    iniciarMenu();
    mostrarToastsIniciales();
    iniciarConfirmaciones();
    iniciarBuscador();
    iniciarTablasResponsivas();
    iniciarCatalogos();
    iniciarPestanas();
    iniciarSelectoresDeBien();
    iniciarNotificaciones();
    iniciarCierreDeSesion();
    iniciarPantallasDeAcceso();
    iniciarBotonesDeImpresion();
    iniciarAvisoDeInactividad();
    iniciarGraficos();
});

window.SIBIP = { mostrarToast, confirmar };
