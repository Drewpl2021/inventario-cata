/**
 * Almacenamiento local de la toma de inventario (IndexedDB):
 * - "pendientes": lecturas hechas sin conexión, a la espera de sincronizarse.
 * - "catalogos": bienes de la sede/área de cada campaña, para clasificar sin conexión.
 *
 * Los catálogos se borran al cerrar la campaña y al cerrar sesión. Los pendientes
 * nunca se borran sin haberse sincronizado.
 */
const NOMBRE_BD = 'sibip-escaneo';
const VERSION_BD = 2;

let conexion = null;

function abrir() {
    if (!window.indexedDB) return Promise.resolve(null);
    if (conexion) return conexion;

    conexion = new Promise((resolver, rechazar) => {
        const pedido = indexedDB.open(NOMBRE_BD, VERSION_BD);
        pedido.onupgradeneeded = () => {
            const bd = pedido.result;
            if (!bd.objectStoreNames.contains('pendientes')) {
                bd.createObjectStore('pendientes', { keyPath: 'uuid' }).createIndex('inventario', 'inventario_id');
            }
            if (!bd.objectStoreNames.contains('catalogos')) {
                bd.createObjectStore('catalogos', { keyPath: 'inventario_id' });
            }
        };
        pedido.onsuccess = () => resolver(pedido.result);
        pedido.onerror = () => rechazar(pedido.error);
    }).catch(() => null);

    return conexion;
}

async function operar(almacen, modo, accion) {
    const bd = await abrir();
    if (!bd) return undefined;

    return new Promise((resolver, rechazar) => {
        const tx = bd.transaction(almacen, modo);
        const pedido = accion(tx.objectStore(almacen));
        tx.oncomplete = () => resolver(pedido?.result);
        tx.onerror = () => rechazar(tx.error);
    });
}

export const pendientes = {
    agregar: (lectura) => operar('pendientes', 'readwrite', (s) => s.put(lectura)),
    quitar: (uuid) => operar('pendientes', 'readwrite', (s) => s.delete(uuid)),
    async deInventario(inventarioId) {
        const todas = (await operar('pendientes', 'readonly', (s) => s.index('inventario').getAll(inventarioId))) || [];
        return todas.sort((a, b) => a.fecha_hora.localeCompare(b.fecha_hora));
    },
    async contarTodos() {
        return (await operar('pendientes', 'readonly', (s) => s.count())) || 0;
    },
};

export const catalogos = {
    guardar: (inventarioId, datos) => operar('catalogos', 'readwrite', (s) => s.put({ inventario_id: inventarioId, ...datos })),
    obtener: (inventarioId) => operar('catalogos', 'readonly', (s) => s.get(inventarioId)),
    borrar: (inventarioId) => operar('catalogos', 'readwrite', (s) => s.delete(inventarioId)),
    borrarTodos: () => operar('catalogos', 'readwrite', (s) => s.clear()),
};
