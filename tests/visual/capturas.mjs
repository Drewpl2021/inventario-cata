/**
 * Capturas de las pantallas principales (Administrador y Usuario) a 375, 768 y 1366 px,
 * para compararlas con los mockups de public/images.
 *
 * Uso (con la base recién sembrada y el servidor en marcha):
 *   php artisan migrate:fresh --seed
 *   php artisan serve --port=8000
 *   node tests/visual/capturas.mjs [http://127.0.0.1:8000]
 *
 * Las imágenes quedan en storage/app/capturas/ (fuera de git).
 */
import { mkdirSync } from 'node:fs';
import { chromium } from 'playwright';

const BASE = process.argv[2] || 'http://127.0.0.1:8000';
const SALIDA = 'storage/app/capturas';
const ANCHOS = [375, 768, 1366];
const CLAVE_NUEVA = 'Capturas2026x';

const CUENTAS = {
    admin: { correo: 'admin@sibip.local', clave: 'Admin2026!' },
    usuario: { correo: 'usuario@sibip.local', clave: 'Usuario2026!' },
};

mkdirSync(SALIDA, { recursive: true });

async function iniciarSesion(pagina, { correo, clave }) {
    for (const intento of [clave, CLAVE_NUEVA]) {
        await pagina.goto(`${BASE}/login`);
        await pagina.fill('#login_input', correo);
        await pagina.fill('#password_input', intento);
        await Promise.all([pagina.waitForNavigation(), pagina.click('form button[type="submit"]')]);

        if (pagina.url().includes('/cambiar-password')) {
            await pagina.fill('#password_actual', intento);
            await pagina.fill('#password', CLAVE_NUEVA);
            await pagina.fill('#password_confirmation', CLAVE_NUEVA);
            await Promise.all([pagina.waitForURL((url) => !url.pathname.includes('cambiar-password')), pagina.click('main form button[type="submit"]')]);
        }
        await pagina.waitForLoadState('networkidle');
        if (!pagina.url().includes('/login')) return;
    }
    throw new Error(`No se pudo iniciar sesión como ${correo}`);
}

async function capturar(pagina, nombre, ancho) {
    await pagina.waitForLoadState('networkidle');
    await pagina.waitForTimeout(400); // animaciones del gráfico y fuentes
    await pagina.screenshot({ path: `${SALIDA}/${nombre}-${ancho}.png`, fullPage: true });
    console.log(`  ${nombre}-${ancho}.png`);
}

// Usa el Chrome instalado; si no hay, el Chromium de Playwright (npx playwright install chromium).
const navegador = await chromium.launch({ channel: 'chrome' }).catch(() => chromium.launch());
const errores = [];

// Modo "catalogos": solo las páginas de catálogos a 375 y 1366 px.
if (process.argv.includes('--catalogos')) {
    const contexto = await navegador.newContext({ viewport: { width: 1366, height: 900 }, locale: 'es-PE' });
    const pagina = await contexto.newPage();
    pagina.on('pageerror', (e) => errores.push(`admin: ${e.message}`));
    await iniciarSesion(pagina, CUENTAS.admin);

    const rutas = process.argv.includes('--reportes')
        ? ['reportes', 'reportes/por-sede', 'reportes/por-responsable', 'reportes/historial-movimientos']
        : ['sedes', 'areas', 'categorias', 'responsables', 'mantenimientos', 'mantenimientos/create', 'documentos', 'auditoria'];
    for (const ancho of [375, 1366]) {
        await pagina.setViewportSize({ width: ancho, height: 900 });
        for (const ruta of rutas) {
            await pagina.goto(`${BASE}/${ruta}`);
            await capturar(pagina, `catalogo-${ruta.replace('/', '-')}`, ancho);
        }
    }

    // Formulario de edición abierto
    await pagina.goto(`${BASE}/responsables`);
    await pagina.locator('[data-editar]').first().click();
    await capturar(pagina, 'catalogo-responsables-editar', 1366);

    await navegador.close();
    if (errores.length) {
        errores.forEach((e) => console.log(`  - ${e}`));
        process.exitCode = 1;
    }
    process.exit();
}

try {
    // Pantalla de login (sin sesión)
    for (const ancho of ANCHOS) {
        const contexto = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-PE' });
        const pagina = await contexto.newPage();
        await pagina.goto(`${BASE}/login`);
        await capturar(pagina, 'login', ancho);
        await contexto.close();
    }

    // Administrador
    const admin = await navegador.newContext({ viewport: { width: 1366, height: 900 }, locale: 'es-PE' });
    const paginaAdmin = await admin.newPage();
    paginaAdmin.on('pageerror', (e) => errores.push(`admin: ${e.message}`));
    await iniciarSesion(paginaAdmin, CUENTAS.admin);

    // Una campaña abierta para capturar la pantalla de escaneo
    await paginaAdmin.goto(`${BASE}/inventarios`);
    let urlEscaneo = await paginaAdmin.locator('a[href$="/escanear"]').first().getAttribute('href').catch(() => null);
    if (!urlEscaneo) {
        await paginaAdmin.goto(`${BASE}/inventarios/create`);
        await paginaAdmin.selectOption('#sede_id', { index: 1 });
        await Promise.all([paginaAdmin.waitForURL(/escanear/), paginaAdmin.click('main form button[type="submit"]')]);
        urlEscaneo = paginaAdmin.url();
    }

    const pantallasAdmin = {
        inicio: '/dashboard',
        bienes: '/bienes',
        'detalle-bien': '/bienes/2',
        reportes: '/reportes',
        aprobaciones: '/aprobaciones',
        movimientos: '/movimientos',
        etiquetas: '/etiquetas',
        'toma-inventario': '/inventarios',
        escaneo: new URL(urlEscaneo, BASE).pathname,
        usuarios: '/usuarios',
        roles: '/roles',
        'config-etiquetas': '/configuracion/etiquetas',
    };

    for (const ancho of ANCHOS) {
        await paginaAdmin.setViewportSize({ width: ancho, height: 900 });
        for (const [nombre, ruta] of Object.entries(pantallasAdmin)) {
            await paginaAdmin.goto(`${BASE}${ruta}`);
            await capturar(paginaAdmin, `admin-${nombre}`, ancho);
        }
    }

    // Menú lateral abierto en celular
    await paginaAdmin.setViewportSize({ width: 375, height: 900 });
    await paginaAdmin.goto(`${BASE}/dashboard`);
    await paginaAdmin.click('[data-abrir-menu]');
    await capturar(paginaAdmin, 'admin-menu-abierto', 375);
    await admin.close();

    // Usuario (responsable)
    const usuario = await navegador.newContext({ viewport: { width: 1366, height: 900 }, locale: 'es-PE' });
    const paginaUsuario = await usuario.newPage();
    paginaUsuario.on('pageerror', (e) => errores.push(`usuario: ${e.message}`));
    await iniciarSesion(paginaUsuario, CUENTAS.usuario);

    await paginaUsuario.goto(`${BASE}/mis-bienes`);
    const detalle = await paginaUsuario.locator('a[href*="/bienes/"]').first().getAttribute('href');
    const idBien = detalle.split('/').pop();

    const pantallasUsuario = {
        inicio: '/dashboard',
        'mis-bienes': '/mis-bienes',
        'detalle-bien': `/bienes/${idBien}`,
        'solicitar-baja': `/bajas/create?bien_id=${idBien}`,
        'solicitar-movimiento': `/movimientos/create?bien_id=${idBien}`,
        'solicitar-requerimiento': '/requerimientos/create',
        'mis-solicitudes': '/mis-solicitudes',
    };

    for (const ancho of ANCHOS) {
        await paginaUsuario.setViewportSize({ width: ancho, height: 900 });
        for (const [nombre, ruta] of Object.entries(pantallasUsuario)) {
            await paginaUsuario.goto(`${BASE}${ruta}`);
            await capturar(paginaUsuario, `usuario-${nombre}`, ancho);
        }
    }

    // Menú "Solicitudes" desplegado
    await paginaUsuario.setViewportSize({ width: 1366, height: 900 });
    await paginaUsuario.goto(`${BASE}/dashboard`);
    await paginaUsuario.click('[data-submenu="submenu-solicitudes"]');
    await capturar(paginaUsuario, 'usuario-menu-solicitudes', 1366);
    await usuario.close();
} finally {
    await navegador.close();
}

if (errores.length) {
    console.log('\nErrores de JavaScript en las páginas:');
    errores.forEach((e) => console.log(`  - ${e}`));
    process.exitCode = 1;
}
