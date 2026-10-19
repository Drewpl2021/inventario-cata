import { catalogos, pendientes } from './almacen-offline';

/**
 * Pantalla de escaneo de la toma de inventario.
 *
 * - Lectoras USB/Bluetooth tipo teclado (envían Enter) o cámara (Code 128).
 * - Cada lectura se envía al servidor, que la clasifica. Sin conexión, se
 *   clasifica en el navegador con el catálogo de la sede/área de la campaña y
 *   queda en IndexedDB hasta sincronizarse (el uuid evita duplicados).
 */
const config = JSON.parse(document.getElementById('config-escaneo')?.textContent || 'null');

if (config) {
    iniciar(config);
}

function iniciar(CONFIG) {
    const ESTILOS = {
        verde: { fondo: 'bg-emerald-600', punto: 'bg-emerald-500' },
        ambar: { fondo: 'bg-amber-500', punto: 'bg-amber-400' },
        gris: { fondo: 'bg-slate-500', punto: 'bg-slate-400' },
        morado: { fondo: 'bg-purple-700', punto: 'bg-purple-600' },
        rojo: { fondo: 'bg-rose-600', punto: 'bg-rose-600' },
        azul: { fondo: 'bg-sky-600', punto: 'bg-sky-500' },
    };

    const $ = (id) => document.getElementById(id);
    const entrada = $('codigo');
    const clave = (nombre) => `sibip:inv${CONFIG.inventarioId}:${nombre}`;
    const leer = (nombre, defecto) => { try { return localStorage.getItem(clave(nombre)) ?? defecto; } catch { return defecto; } };
    const guardar = (nombre, valor) => { try { localStorage.setItem(clave(nombre), valor); } catch { /* sin almacenamiento */ } };
    const dialogo = (opciones) => window.SIBIP?.confirmar(opciones) ?? Promise.resolve(window.confirm(opciones.mensaje));

    let estadoPorDefecto = leer('estado', '');
    let ultimaLectura = null;
    let modoCamara = false;
    let catalogo = null;
    const leidos = new Set();
    let ultimas = [];

    // -------------------------------------------------------------------
    // Utilidades
    // -------------------------------------------------------------------
    function uuid() {
        if (window.crypto?.randomUUID) return crypto.randomUUID();
        // crypto.randomUUID no existe fuera de HTTPS; getRandomValues sí.
        const b = crypto.getRandomValues(new Uint8Array(16));
        b[6] = (b[6] & 0x0f) | 0x40;
        b[8] = (b[8] & 0x3f) | 0x80;
        const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
        return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
    }

    const normalizar = (codigo) => codigo.replace(/[\u0000-\u001F\u007F]/g, '').trim().toUpperCase();
    const areaActual = () => CONFIG.areaFija ?? (parseInt($('area-escaneo')?.value, 10) || null);

    async function pedir(url, opciones = {}, espera = 6000) {
        const control = new AbortController();
        const temporizador = setTimeout(() => control.abort(), espera);
        try {
            return await fetch(url, {
                ...opciones,
                signal: control.signal,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CONFIG.csrf, ...(opciones.headers || {}) },
            });
        } finally {
            clearTimeout(temporizador);
        }
    }

    // -------------------------------------------------------------------
    // Sonidos (Web Audio, sin archivos): uno distinto por resultado
    // -------------------------------------------------------------------
    let audio = null;
    function tono(frecuencias, duracion = 0.12, tipo = 'sine') {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            let t = audio.currentTime;
            frecuencias.forEach((f) => {
                const osc = audio.createOscillator();
                const vol = audio.createGain();
                osc.type = tipo;
                osc.frequency.value = f;
                vol.gain.setValueAtTime(0.25, t);
                vol.gain.exponentialRampToValueAtTime(0.001, t + duracion);
                osc.connect(vol).connect(audio.destination);
                osc.start(t);
                osc.stop(t + duracion);
                t += duracion + 0.04;
            });
        } catch { /* sin audio */ }
    }
    const SONIDOS = {
        encontrado: () => tono([1046], 0.12),
        fuera_de_ubicacion: () => tono([784, 523], 0.14, 'triangle'),
        ya_escaneado: () => tono([330], 0.08),
        dado_de_baja: () => tono([622, 622, 622], 0.09, 'square'),
        sobrante_no_registrado: () => tono([165], 0.45, 'sawtooth'),
        pendiente: () => tono([880, 1175], 0.08),
        error: () => tono([200, 150], 0.2, 'sawtooth'),
    };

    // -------------------------------------------------------------------
    // Presentación
    // -------------------------------------------------------------------
    function mostrarResumen(resumen) {
        if (!resumen) return;
        $('escaneados').textContent = resumen.escaneados;
        $('esperados').textContent = resumen.esperados;
        $('porcentaje').textContent = `${resumen.porcentaje} %`;
        $('barra-avance').style.width = `${Math.min(100, resumen.porcentaje)}%`;
        document.querySelectorAll('[data-total]').forEach((el) => { el.textContent = resumen[el.dataset.total] ?? 0; });
    }

    function mostrarResultado(lectura, nota = '') {
        const tarjeta = $('tarjeta-resultado');
        tarjeta.className = `rounded-2xl p-5 text-white shadow-lg transition-colors ${ESTILOS[lectura.color]?.fondo || 'bg-slate-600'}`;
        $('resultado-titulo').textContent = lectura.etiqueta;
        $('resultado-codigo').textContent = lectura.codigo;
        $('resultado-bien').textContent = lectura.bien ? lectura.bien.nombre : '';
        $('resultado-mensaje').textContent = [lectura.mensaje, nota].filter(Boolean).join(' · ');
        // Las acciones solo aplican a lecturas ya guardadas en el servidor.
        $('resultado-acciones').classList.toggle('hidden', !lectura.id || lectura.resultado === 'ya_escaneado');
        ultimaLectura = lectura;
    }

    async function pintarLista() {
        const enCola = (await pendientes.deInventario(CONFIG.inventarioId)).map((p) => ({ ...p.vista, pendiente: true }));
        const filas = [...enCola.reverse(), ...ultimas].slice(0, 20);
        const lista = $('lista-lecturas');
        lista.innerHTML = '';
        if (!filas.length) {
            lista.innerHTML = '<li class="px-4 py-8 text-center text-slate-400"><i class="fa-solid fa-barcode text-2xl block mb-1"></i>Aún no hay lecturas.</li>';
            return;
        }
        filas.forEach((l) => {
            const li = document.createElement('li');
            li.className = 'px-4 py-2.5 flex items-center gap-3';
            const punto = document.createElement('span');
            punto.className = `w-3 h-3 rounded-full shrink-0 ${ESTILOS[l.pendiente ? 'azul' : l.color]?.punto || 'bg-slate-300'}`;
            const texto = document.createElement('div');
            texto.className = 'flex-1 min-w-0';
            texto.innerHTML = '<p class="font-mono font-bold text-slate-800 truncate"></p><p class="text-xs text-slate-500 truncate"></p>';
            texto.children[0].textContent = l.codigo;
            texto.children[1].textContent = [l.etiqueta, l.bien?.nombre, l.estado_fisico ? `estado ${l.estado_fisico}` : '', l.pendiente ? 'pendiente de sincronizar' : l.por, l.hora].filter(Boolean).join(' · ');
            li.append(punto, texto);
            if (l.id && !l.pendiente) {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'btn-icono text-rose-600';
                boton.innerHTML = '<i class="fa-solid fa-rotate-left"></i>';
                boton.title = 'Deshacer esta lectura';
                boton.setAttribute('aria-label', `Deshacer la lectura de ${l.codigo}`);
                boton.addEventListener('click', () => deshacer(l));
                li.append(boton);
            }
            lista.append(li);
        });
    }

    async function mostrarPendientes() {
        const n = (await pendientes.deInventario(CONFIG.inventarioId)).length;
        $('numero-pendientes').textContent = n;
        $('indicador-pendientes').classList.toggle('hidden', n === 0);
        return n;
    }

    function mostrarConexion() {
        const indicador = $('indicador-conexion');
        indicador.className = navigator.onLine ? 'badge-verde' : 'badge-rojo';
        indicador.innerHTML = navigator.onLine ? '<i class="fa-solid fa-wifi"></i> En línea' : '<i class="fa-solid fa-plane"></i> Sin conexión';
    }

    // -------------------------------------------------------------------
    // Lecturas
    // -------------------------------------------------------------------
    let ultimoCodigo = '';
    let ultimoMomento = 0;

    async function procesar(codigoCrudo) {
        const codigo = normalizar(codigoCrudo);
        if (!codigo) return;

        // Algunas lectoras envían el código dos veces seguidas.
        const ahora = Date.now();
        if (codigo === ultimoCodigo && ahora - ultimoMomento < 1500) return;
        ultimoCodigo = codigo;
        ultimoMomento = ahora;

        const lectura = {
            uuid: uuid(),
            inventario_id: CONFIG.inventarioId,
            codigo,
            area_escaneo_id: areaActual(),
            estado_fisico: estadoPorDefecto || null,
            fecha_hora: new Date().toISOString(),
        };

        if (navigator.onLine) {
            try {
                const respuesta = await pedir(CONFIG.urls.lectura, { method: 'POST', body: JSON.stringify(lectura) });
                if (respuesta.ok) {
                    const datos = await respuesta.json();
                    aplicarLecturaDelServidor(datos.lectura);
                    mostrarResumen(datos.resumen);
                    SONIDOS[datos.lectura.resultado]?.();
                    mostrarResultado(datos.lectura);
                    pintarLista();
                    sincronizar();
                    return;
                }
                if (respuesta.status === 422 || respuesta.status === 403) {
                    const datos = await respuesta.json().catch(() => ({}));
                    SONIDOS.error();
                    mostrarResultado({ codigo, etiqueta: 'No se registró', color: 'rojo', mensaje: datos.message || 'La lectura fue rechazada.' });
                    return;
                }
                if (respuesta.status === 419 || respuesta.status === 401) {
                    window.location.reload();
                    return;
                }
            } catch { /* sin red o tiempo agotado: se guarda offline */ }
        }

        await guardarSinConexion(lectura);
    }

    function aplicarLecturaDelServidor(lectura) {
        if (lectura.resultado !== 'ya_escaneado') leidos.add(lectura.codigo);
        ultimas = [lectura, ...ultimas.filter((l) => l.id !== lectura.id)].slice(0, 20);
    }

    async function guardarSinConexion(lectura) {
        if (leidos.has(lectura.codigo)) {
            SONIDOS.ya_escaneado();
            mostrarResultado({ codigo: lectura.codigo, etiqueta: 'Ya escaneado', color: 'gris', mensaje: 'Ya se leyó en esta campaña.' });
            return;
        }

        const vista = clasificarLocal(lectura);
        leidos.add(lectura.codigo);
        await pendientes.agregar({ ...lectura, vista });
        (SONIDOS[vista.resultado] || SONIDOS.pendiente)();
        mostrarResultado(vista, 'Guardado sin conexión: se sincronizará solo.');
        mostrarPendientes();
        pintarLista();
    }

    /**
     * El catálogo solo tiene los bienes de la sede/área de la campaña. Un código
     * con formato SIBIP que no está en él puede ser de otra sede: se verifica al sincronizar.
     */
    function clasificarLocal(lectura) {
        const base = { codigo: lectura.codigo, uuid: lectura.uuid, hora: new Date().toLocaleTimeString('es-PE'), estado_fisico: lectura.estado_fisico };
        const pendiente = { ...base, resultado: 'pendiente', etiqueta: 'Por verificar', color: 'azul', mensaje: 'Se clasificará al recuperar la conexión.' };
        if (!catalogo) return pendiente;

        const bien = catalogo.bienes[lectura.codigo];
        if (!bien) {
            return /^CAT-[A-Z0-9]+-\d+$/.test(lectura.codigo)
                ? { ...pendiente, mensaje: 'No es de esta sede o área: se verificará al sincronizar.' }
                : { ...base, resultado: 'sobrante_no_registrado', etiqueta: 'No registrado', color: 'rojo', mensaje: 'Este código no corresponde a ningún bien registrado.' };
        }

        const lugar = catalogo.areas[bien.a] || 'sin área';
        const datosBien = { nombre: bien.n, ubicacion: lugar };
        if (bien.e === 'dado_de_baja') {
            return { ...base, bien: datosBien, resultado: 'dado_de_baja', etiqueta: 'Figura dado de baja', color: 'morado', mensaje: 'El bien figura dado de baja, pero está físicamente aquí.' };
        }
        const area = lectura.area_escaneo_id;
        return area === null || bien.a === area
            ? { ...base, bien: datosBien, resultado: 'encontrado', etiqueta: 'Encontrado', color: 'verde', mensaje: lugar }
            : { ...base, bien: datosBien, resultado: 'fuera_de_ubicacion', etiqueta: 'Fuera de ubicación', color: 'ambar', mensaje: `Debería estar en: ${lugar}` };
    }

    let sincronizando = false;
    async function sincronizar() {
        if (sincronizando || !navigator.onLine) return;
        const enCola = await pendientes.deInventario(CONFIG.inventarioId);
        if (!enCola.length) return;

        sincronizando = true;
        try {
            for (let i = 0; i < enCola.length; i += 100) {
                const lote = enCola.slice(i, i + 100).map(({ vista, inventario_id, ...lectura }) => lectura);
                const respuesta = await pedir(CONFIG.urls.sincronizar, { method: 'POST', body: JSON.stringify({ lecturas: lote }) }, 20000);
                if (!respuesta.ok) break;
                const datos = await respuesta.json();
                for (const r of datos.respuestas) {
                    await pendientes.quitar(r.uuid);
                    if (r.ok) {
                        aplicarLecturaDelServidor(r.lectura);
                    } else {
                        // P. ej., la campaña se cerró mientras se estaba sin conexión.
                        SONIDOS.error();
                        mostrarResultado({ codigo: '', etiqueta: 'Lectura no sincronizada', color: 'rojo', mensaje: r.error });
                    }
                }
                mostrarResumen(datos.resumen);
            }
        } catch { /* se reintenta más tarde */ } finally {
            sincronizando = false;
            mostrarPendientes();
            pintarLista();
        }
    }

    async function actualizarEstado() {
        if (!navigator.onLine) return;
        try {
            const respuesta = await pedir(CONFIG.urls.estado, { method: 'GET' });
            if (!respuesta.ok) return;
            const datos = await respuesta.json();
            mostrarResumen(datos.resumen);
            ultimas = datos.ultimas;
            ultimas.forEach((l) => leidos.add(l.codigo));
            pintarLista();
            if (!datos.abierto) {
                await catalogos.borrar(CONFIG.inventarioId);
                mostrarResultado({ codigo: '', etiqueta: 'Campaña cerrada', color: 'gris', mensaje: 'Ya no se admiten lecturas.' });
                entrada.disabled = true;
            }
        } catch { /* sin red */ }
    }

    async function cargarCatalogo() {
        try {
            const respuesta = await pedir(CONFIG.urls.catalogo, { method: 'GET' }, 30000);
            if (!respuesta.ok) throw new Error();
            const datos = await respuesta.json();
            catalogo = { bienes: datos.bienes, areas: datos.areas };
            datos.leidos.forEach((c) => leidos.add(c));
            await catalogos.guardar(CONFIG.inventarioId, { bienes: datos.bienes, areas: datos.areas, leidos: datos.leidos });
        } catch {
            const copia = await catalogos.obtener(CONFIG.inventarioId).catch(() => null);
            if (copia) {
                catalogo = { bienes: copia.bienes, areas: copia.areas };
                (copia.leidos || []).forEach((c) => leidos.add(c));
            }
        }
    }

    async function deshacer(lectura) {
        if (!lectura?.id || !navigator.onLine) {
            mostrarResultado({ codigo: lectura?.codigo || '', etiqueta: 'Sin conexión', color: 'gris', mensaje: 'Para deshacer una lectura se necesita conexión.' });
            return;
        }
        const seguir = await dialogo({ titulo: 'Deshacer lectura', mensaje: `¿Deshacer la lectura de ${lectura.codigo}? Quedará registrada en la auditoría.`, aceptar: 'Deshacer', peligro: true });
        if (seguir) {
            const respuesta = await pedir(`${CONFIG.urls.lecturas}/${lectura.id}`, { method: 'DELETE' });
            if (respuesta.ok) {
                const datos = await respuesta.json();
                leidos.delete(lectura.codigo);
                ultimas = ultimas.filter((l) => l.id !== lectura.id);
                mostrarResumen(datos.resumen);
                $('tarjeta-resultado').classList.add('hidden');
                pintarLista();
            }
        }
        entrada.focus();
    }

    async function marcarEstado(estado) {
        if (!ultimaLectura?.id) return;
        try {
            const respuesta = await pedir(`${CONFIG.urls.lecturas}/${ultimaLectura.id}/estado-fisico`, { method: 'PATCH', body: JSON.stringify({ estado_fisico: estado }) });
            if (respuesta.ok) {
                const datos = await respuesta.json();
                aplicarLecturaDelServidor(datos.lectura);
                $('resultado-mensaje').textContent = `Estado físico: ${estado}`;
                pintarLista();
            }
        } catch {
            $('resultado-mensaje').textContent = 'Sin conexión: no se pudo guardar el estado físico.';
        }
        entrada.focus();
    }

    // -------------------------------------------------------------------
    // Cámara (html5-qrcode, solo Code 128). Requiere HTTPS o localhost.
    // -------------------------------------------------------------------
    let lectorCamara = null;

    async function activarCamara() {
        $('panel-camara').classList.remove('hidden');
        if (!window.isSecureContext) {
            $('aviso-https').classList.remove('hidden');
            return;
        }
        try {
            $('estado-camara').textContent = 'Iniciando cámara…';
            const { Html5Qrcode, Html5QrcodeSupportedFormats } = await import('html5-qrcode');
            lectorCamara = new Html5Qrcode('lector-camara', { formatsToSupport: [Html5QrcodeSupportedFormats.CODE_128], verbose: false });
            await lectorCamara.start(
                { facingMode: 'environment' },
                { fps: 12, qrbox: { width: 300, height: 110 }, aspectRatio: 1.6 },
                (texto) => procesar(texto),
            );
            $('estado-camara').textContent = 'Apunte la cámara a la etiqueta (barras en horizontal).';
        } catch (error) {
            $('estado-camara').textContent = `No se pudo usar la cámara: ${error.message || error}`;
        }
    }

    async function desactivarCamara() {
        $('panel-camara').classList.add('hidden');
        if (lectorCamara) {
            try { await lectorCamara.stop(); } catch { /* ya detenida */ }
            lectorCamara = null;
        }
    }

    function cambiarModo(camara) {
        modoCamara = camara;
        $('modo-camara').className = camara ? 'btn-primario flex-1' : 'btn-secundario flex-1';
        $('modo-lectora').className = camara ? 'btn-secundario flex-1' : 'btn-primario flex-1';
        if (camara) {
            activarCamara();
        } else {
            desactivarCamara();
            entrada.focus();
        }
    }

    // -------------------------------------------------------------------
    // Eventos
    // -------------------------------------------------------------------
    $('form-lectura').addEventListener('submit', (evento) => {
        evento.preventDefault();
        const valor = entrada.value;
        entrada.value = '';
        procesar(valor);
    });

    // Foco permanente: si se pierde, vuelve solo, salvo que se use otro control o la cámara.
    entrada.addEventListener('blur', () => setTimeout(() => {
        const activo = document.activeElement;
        const usandoOtroControl = activo && activo !== document.body && activo !== entrada && activo.matches('select, textarea, input, [role="dialog"] *');
        if (!modoCamara && !usandoOtroControl && !entrada.disabled) entrada.focus({ preventScroll: true });
    }, 200));
    document.addEventListener('click', (evento) => {
        if (!modoCamara && !evento.target.closest('select, input, textarea, a, [role="dialog"]')) {
            setTimeout(() => entrada.focus({ preventScroll: true }), 50);
        }
    });

    $('modo-lectora').addEventListener('click', () => cambiarModo(false));
    $('modo-camara').addEventListener('click', () => cambiarModo(true));
    $('boton-deshacer').addEventListener('click', () => deshacer(ultimaLectura));
    document.querySelectorAll('[data-estado-lectura]').forEach((b) => b.addEventListener('click', () => marcarEstado(b.dataset.estadoLectura)));

    const botonesEstado = document.querySelectorAll('#estado-por-defecto [data-estado]');
    const pintarEstadoPorDefecto = () => botonesEstado.forEach((b) => {
        const activo = b.dataset.estado === estadoPorDefecto;
        b.className = `min-h-11 px-2 rounded-xl text-xs font-bold border ${activo ? 'bg-marino-800 text-white border-marino-800' : 'bg-white text-slate-600 border-slate-200'}`;
        b.setAttribute('aria-pressed', String(activo));
    });
    botonesEstado.forEach((b) => b.addEventListener('click', () => { estadoPorDefecto = b.dataset.estado; guardar('estado', estadoPorDefecto); pintarEstadoPorDefecto(); }));
    pintarEstadoPorDefecto();

    const selectorArea = $('area-escaneo');
    if (selectorArea) {
        selectorArea.value = leer('area', '');
        selectorArea.addEventListener('change', () => { guardar('area', selectorArea.value); entrada.focus(); });
    }

    const ocultarTeclado = $('ocultar-teclado');
    ocultarTeclado.checked = leer('ocultar-teclado', '0') === '1';
    const aplicarTeclado = () => { entrada.inputMode = ocultarTeclado.checked ? 'none' : 'text'; guardar('ocultar-teclado', ocultarTeclado.checked ? '1' : '0'); };
    ocultarTeclado.addEventListener('change', aplicarTeclado);
    aplicarTeclado();

    const formCerrar = $('form-cerrar');
    formCerrar?.addEventListener('submit', async (evento) => {
        if (formCerrar.dataset.confirmado) return;
        evento.preventDefault();

        const n = await mostrarPendientes();
        if (n > 0) {
            await dialogo({ titulo: 'Lecturas sin sincronizar', mensaje: `Hay ${n} lecturas sin sincronizar. Espere a recuperar la conexión antes de cerrar la campaña.`, aceptar: 'Entendido' });
            return;
        }
        const seguir = await dialogo({ titulo: 'Cerrar campaña', mensaje: 'Los bienes esperados que no se escanearon quedarán como FALTANTES.', aceptar: 'Cerrar campaña', peligro: true });
        if (!seguir) return;

        await catalogos.borrar(CONFIG.inventarioId).catch(() => {});
        formCerrar.dataset.confirmado = '1';
        formCerrar.submit();
    });

    window.addEventListener('online', () => { mostrarConexion(); sincronizar(); actualizarEstado(); });
    window.addEventListener('offline', mostrarConexion);
    setInterval(sincronizar, 15000);
    setInterval(actualizarEstado, 10000);

    mostrarConexion();
    cargarCatalogo().then(actualizarEstado).then(sincronizar);
    mostrarPendientes();
    entrada.focus();
}
