# Entrega del proyecto SIBIP

**Sistema Integrado de Bienes Patrimoniales** · Colegio Adventista Túpac Amaru (Juliaca)
Fecha de cierre: 17 de octubre de 2026 · Base: commit `b2df5f1` ("Entregable proyecto") · Rama: `main` (solo commits locales, sin push)

Estado final verificado el día de la entrega:

| Verificación | Resultado |
|---|---|
| `php artisan test` | **185 / 185** pruebas correctas (MySQL `sibip_test`) |
| Larastan (PHPStan) | **Nivel 8 de 10, 0 errores**, sin baseline |
| `npm run build` | Correcto |
| `php artisan migrate:fresh --seed` | Correcto (roles, permisos y datos de demostración) |
| `composer audit` / `npm audit` | Sin vulnerabilidades conocidas |
| Pint | Todo el proyecto formateado |

Documentos del proyecto: [README.md](README.md) (instalación local) · [DOCKER.md](DOCKER.md) (levantarlo con Docker) · [DEPLOY.md](DEPLOY.md) (servidor, actualización y respaldos) · [SEGURIDAD.md](SEGURIDAD.md) · [ESCANEO.md](ESCANEO.md) · [CHECKLIST_PUESTA_EN_MARCHA.md](CHECKLIST_PUESTA_EN_MARCHA.md).

---

## 1. Lo que se hizo, por fase

**Fase 0 · Diagnóstico.** Revisión del sistema original (Laravel 13 + MySQL) y plan aprobado por fases. Se instaló Laravel Boost y se actualizaron dos dependencias con avisos de seguridad.

**Fase 1 · Base de datos, roles y permisos.**
- Autenticación unificada en la tabla `usuarios`: se eliminó la tabla `users`, que estaba vacía.
- Contraseña temporal con cambio obligatorio en el primer ingreso. Ya no hay registro público.
- Permisos por módulo y acción, editables por rol (Administrador, Operador, Auditor, Usuario), con Policies que limitan a cada responsable a sus propios bienes.
- Flujos de aprobación para bajas, movimientos y requerimientos.
- Auditoría automática de cada cambio.
- Desactivación o eliminación suave (soft delete) en lugar de borrado físico.
- Español y zona horaria America/Lima.

**Fase 2 · Código de barras y etiquetas.**
- Código Code 128 `CAT-S01-000123`: único, inmutable, asignado al crear el bien.
- Etiquetas térmicas dibujadas a la resolución real de la impresora (203/300 dpi), en tres formatos: impresión directa, PDF y PNG/ZIP para impresoras Bluetooth.
- Impresión por lote, configuración del formato de etiqueta y página de prueba para calibrar la impresora.

**Fase 3 · Toma de inventario por escaneo.**
- Campañas por sede o área.
- Escaneo con lectora USB/Bluetooth o con la cámara, con color y sonido según el resultado; varias personas pueden escanear a la vez.
- Modo sin conexión (IndexedDB) con sincronización idempotente.
- Al cerrar la campaña se registran los faltantes. Desde el resultado se generan solicitudes y se exporta a PDF/Excel.

**Fase 4 · Interfaz según los mockups.**
- Diseño institucional responsive (celular, tablet y escritorio).
- Paneles de Administrador y de Usuario, buscador global y notificaciones.
- Todo se sirve con Vite, sin CDN.
- Catálogos con alta y edición en la misma pantalla.
- Capturas automáticas con Playwright.

**Fase 5 · Reportes.**
- Ocho reportes con filtros y vista previa paginada de 25 en 25.
- PDF con encabezado institucional y totales, y Excel con formato. El Excel se genera en la cola cuando pasa de 1000 filas.
- Acta de asignación con firmas.

**Fase 6 · Seguridad y calidad.**
- Archivos en almacenamiento privado, servidos con control de permisos y validados por su tipo real.
- Login con límite de intentos y auditoría de accesos; recuperación de contraseña por correo; cierre de sesión por inactividad.
- Encabezados de seguridad y CSP; páginas de error en español.
- Pruebas de autorización (todas las rutas, IDOR, separación de funciones).
- Larastan nivel 8, corrección de consultas N+1 e índices nuevos.
- SEGURIDAD.md y README.

**Fase 7 · Despliegue.**
- DEPLOY.md (VPS Ubuntu, cPanel y red local) y `deploy.sh` para actualizar.
- Respaldo diario (`sibip:respaldar`) con prueba de restauración.
- Verificación de producción (`sibip:verificar-produccion`).
- Latidos del cron y de la cola, `/up` con chequeo de la base, y checklist de puesta en marcha.

---

## 2. Commits

| Commit | Descripción |
|---|---|
| `b2df5f1` | *(base original)* Entregable proyecto |
| `0d6b25e` | Instalar Laravel Boost y actualizar dependencias con avisos de seguridad |
| `1b251e9` | Fase 1: base de datos, roles y permisos, flujos de aprobación y auditoría |
| `f03615a` | Fase 2: código de barras Code 128 y etiquetas térmicas |
| `71cfa66` | Fase 3: toma de inventario por escaneo |
| `fc4b690` | WIP Fase 4: vistas según los mockups (en progreso) |
| `5448439` | Fase 4: ajustes visuales tras revisar las capturas con Playwright |
| `15c1c35` | Catálogos con el diseño de la Fase 4 |
| `02b122d` | Fase 5: reportes con vista previa y exportación a PDF y Excel |
| `a4f0b50` | Reportes: vista previa paginada de 25 en 25 |
| `20add6f` | Fase 6 (1/6): archivos subidos en el disco privado |
| `bfd716f` | Fase 6 (2/6): autenticación más segura |
| `2ce9a47` | Fase 6 (3/6): configuración de producción, encabezados y páginas de error |
| `25577f6` | Fase 6 (4/6): revisión de autorización |
| `e6ad41d` | Fase 6 (5/6): calidad (Pint, Larastan nivel 8, N+1, índices, auditorías) |
| `5533d41` | Fase 6 (6/6): documentación de seguridad e instalación local |
| `06b30f0` | Fase 7 (1/3): respaldos, verificación de producción y latidos (incluye `deploy.sh`) |
| `f8ea819` | Fase 7 (2/3): guía de despliegue y script de actualización |
| `8298cce` | Fase 7 (3/3): checklist de puesta en marcha para el colegio |
| *(este)* | Cierre: ENTREGA.md, versión de Larastan fijada y test de respaldo aislado |

---

## 3. Cambios de comportamiento respecto al sistema original

### Acceso y cuentas
1. **Se eliminó el registro público** (`/register`). Solo el Administrador crea cuentas.
2. **Se eliminó la tabla `users`** (estaba vacía) y el modelo `User` con su factory. Toda la autenticación usa `usuarios`.
3. Las cuentas nuevas o restablecidas reciben una **contraseña temporal** que obliga a cambiarla en el primer ingreso.
4. El login acepta el **correo o el nombre de usuario**, igual que antes, pero ahora **se bloquea un minuto tras 5 intentos fallidos** (mismo correo e IP) y registra en auditoría los ingresos, los fallos y los bloqueos.
5. **"Mantener la sesión iniciada" desactivado.** La sesión se cierra tras **120 minutos sin actividad**, con aviso 5 minutos antes.
6. **Nuevo:** recuperación de contraseña por correo. Antes había que pedirla al Administrador; eso sigue siendo posible.
7. Política de contraseñas: mínimo 8 caracteres, con letras y números, distinta de la actual o temporal.
8. Al cambiar o restablecer la contraseña **se cierran las demás sesiones**. Al desactivar una cuenta o su rol, la sesión se corta en la siguiente petición.
9. La raíz `/` **ya no muestra una página pública**: envía al login o al panel.

### Permisos
10. Los roles fijos y el middleware `admin` se reemplazaron por **permisos por módulo y acción**, editables desde *Roles y permisos*. El middleware `AdminOnly` sigue registrado pero ya no lo usa ninguna ruta (vea Pendientes).
11. Un **Usuario (responsable)** solo ve sus bienes, sus solicitudes, sus archivos y su acta, aunque escriba la dirección a mano.
12. **Separación de funciones:** el Operador registra, pero no aprueba bajas, movimientos ni requerimientos.
13. Las rutas de la toma de inventario piden el permiso de cada acción (crear, editar, aprobar para reabrir, exportar), no solo "ver".

### Bienes y códigos
14. **`qr_codigo` reemplazado por `codigo_barras`** (Code 128, formato `CAT-{sede}-{6 dígitos}`), asignado automáticamente, único e **inmutable**: no cambia al editar ni al trasladar. Los bienes existentes se migraron.
15. **El código patrimonial pasa a ser opcional** y ya no se genera automáticamente.
16. Se eliminó **JsBarcode por CDN**; el código se dibuja en el servidor (SVG/PNG/PDF).
17. Al crear un bien se abre **su ficha** (antes volvía al listado), para imprimir la etiqueta enseguida.
18. Bienes, usuarios, responsables, sedes, áreas y categorías **no se borran físicamente**: solo se eliminan (soft delete) si no tienen historial; si lo tienen, se desactivan.

### Solicitudes
19. **Bajas:** pasan por solicitud y aprobación (estado inicial *solicitada*). Se quitó el índice `UNIQUE` sobre `bien_id`: un bien puede tener varias bajas en su historial, pero solo una activa.
20. **Movimientos:** flujo *solicitado → aprobado → ejecutado / rechazado*. El bien **solo cambia de ubicación al aprobarse**.
21. **Requerimientos:** solo transiciones válidas (pendiente → en cotización → aprobado → atendido, o rechazado, con motivo obligatorio).

### Archivos
22. **Todos los archivos subidos están en el almacenamiento privado** (`storage/app/private`), con nombres aleatorios. Ya no son accesibles por URL directa; se sirven por el sistema tras comprobar los permisos. El comando `sibip:migrar-archivos` mueve los de `public/uploads`.
23. **Comprobantes: solo PDF (hasta 10 MB) o imagen JPG/PNG/WEBP (hasta 5 MB).** Ya no se aceptan Word ni Excel.
24. Fotos de bienes y de bajas: solo JPG/PNG/WEBP de hasta 5 MB (ya no GIF). **Se reducen a 1600 px y se guardan como WEBP.** Al reemplazar la foto de un bien, la anterior se borra.
25. Adjuntos de requerimientos: PDF hasta 10 MB o imagen hasta 5 MB (antes, cualquiera de esos tipos hasta 10 MB).
26. **Archivo documentario:** ahora se **sube el PDF**. Antes se escribía a mano una ruta o enlace; los registros antiguos con enlace `http(s)` se siguen mostrando.
27. Se validan por su **tipo real** (contenido), no por la extensión.

### Interfaz
28. **Vistas eliminadas:** `home.blade.php` y `welcome.blade.php` (sin uso). También se eliminó el archivo vacío `laravel` de la raíz.
29. Nuevo diseño en todas las pantallas. En los catálogos (sedes, áreas, categorías, responsables) **el alta y la edición se hacen en la misma pantalla**; antes no había edición desde la interfaz.
30. Las rutas `create/show/edit` que generaba `Route::resource` sin método ni vista (daban error 500) ya no existen: `requerimientos.edit/update`, `mantenimientos.show/edit/destroy`, `sedes|areas|categorias|responsables.create/show/edit` y `usuarios.show`. **No se perdió ninguna pantalla que funcionara.**
31. Estilos y scripts se compilan con **Vite** (Tailwind 4, Font Awesome, fuentes locales, Chart.js, html5-qrcode). No se usan CDN.
32. Notificaciones en la campana para solicitudes nuevas y resueltas, y para Excel listos.

### Seguridad y servidor
33. Encabezados de seguridad y **CSP**. Los scripts en línea necesitan el nonce de la petición (`nonce="{{ Vite::cspNonce() }}"`) y **no se pueden usar `onclick`/`onchange`** en las vistas.
34. Páginas de error propias en español (403, 404, 419, 429, 500, 503).
35. Texto que no es UTF-8 válido da **error de validación** en vez de error 500.
36. Se desactivó la ruta pública `/storage/{path}` del disco privado (`'serve' => false`).
37. `/up` también comprueba la base de datos: responde 500 si no responde.
38. Tareas programadas nuevas (requieren el cron `schedule:run`): latidos del cron y de la cola, y **respaldo diario a las 02:30**.

### Para desarrolladores
39. **`Model::preventLazyLoading()` fuera de producción:** cargar una relación sin `with()` lanza una excepción en local y en las pruebas.
40. Se quitó `HasFactory` de los modelos que no tienen factory. `Role::delSistema()` lanza una excepción si el rol base no existe (antes devolvía `null`).
41. El servidor de Vite (`npm run dev`) escucha en `127.0.0.1`, porque la CSP no admite `[::1]`.
42. Dependencias nuevas:
    - PHP: `picqer/php-barcode-generator`, `barryvdh/laravel-dompdf` y `maatwebsite/excel`.
    - Desarrollo: `laravel/boost`, `larastan/larastan` y `playwright`.
    - npm: Inter, Dancing Script, Font Awesome, Chart.js y html5-qrcode.
43. Las pruebas usan **MySQL** (`sibip_test`), no SQLite. `.env.example` también apunta a MySQL.
44. Diez migraciones nuevas, todas reversibles. Las migraciones existentes no cambiaron su esquema; en tres solo se aplicó el formato de Pint.

---

## 4. Credenciales de demostración

Solo existen con `php artisan migrate:fresh --seed` **fuera de producción** (en producción el seeder no carga datos demo). El sistema pide cambiar la contraseña en el primer ingreso.

| Rol | Correo | Contraseña inicial |
|---|---|---|
| Administrador | `admin@sibip.local` | `Admin2026!` |
| Operador | `operador@sibip.local` | `Operador2026!` |
| Auditor | `auditor@sibip.local` | `Auditor2026!` |
| Usuario (responsable) | `usuario@sibip.local` | `Usuario2026!` |

Datos demo: 3 sedes (S01–S03), 17 áreas, 8 categorías, 11 responsables, 55 bienes, una solicitud de movimiento, una de baja y un requerimiento. `sibip:verificar-produccion` marca en rojo si estas cuentas o sus contraseñas existen en un servidor.

---

## 5. Pendientes y mejoras recomendadas

**Antes de producción**
- Elegir el servidor y seguir DEPLOY.md (A o B). `deploy.sh`, Nginx, Certbot y systemd están documentados y `deploy.sh` se probó con una simulación, pero **no se ejecutaron en un servidor real**.
- Probar con la **impresora de etiquetas y la lectora reales** del colegio (página de prueba) y con la cámara de un celular sobre HTTPS.
- Configurar el correo SMTP institucional y el **copiado de respaldos fuera del servidor**.
- Si el sistema original en uso tiene archivos en `public/uploads`, ejecutar `php artisan sibip:migrar-archivos` después de desplegar.

**Limpieza que requiere aprobación** (no se borró nada sin permiso)
- `app/Http/Middleware/AdminOnly.php` y su alias `admin`: ya no los usa ninguna ruta.
- Imágenes en `public/images` que no usa el sistema (unos 5,9 MB): `cata_banner1/2.png`, `colegio1/2/3.jpg`, `logo_cata_duo.png`, `logo_cata_jerusalen.png`, `logo_cata_juliaca.png` y los **mockups `diseno_admin.png` y `diseno_referencia_ui.jpg`**. Estos últimos son públicos por URL y conviene moverlos fuera de `public/` (por ejemplo a `docs/`).

**Equipo de desarrollo**
- En la PC de desarrollo, Windows Defender bloquea el último paso de `composer install` para el `.phar` de PHPStan. Los archivos quedan instalados y el análisis funciona (`php vendor/phpstan/phpstan/phpstan.phar analyse`), pero `composer install` termina con error. Se soluciona agregando una exclusión del antivirus para la carpeta del proyecto (decisión del responsable del equipo). En Linux no ocurre.

**Mejoras futuras**
- Larastan niveles 9 y 10 (141 y 237 hallazgos, casi todos por tipar los valores de formularios y configuración).
- Verificación en dos pasos para los Administradores.
- Avisos por correo de solicitudes nuevas o resueltas (hoy solo en la campana).
- Índice FULLTEXT para el buscador si el inventario crece mucho (hoy usa `LIKE '%…%'`).
- Instalar la pantalla de escaneo como aplicación (PWA), para abrirla sin conexión desde el inicio. Hoy el modo sin conexión funciona si la página ya estaba abierta.
- Quitar los estilos en línea de las vistas para endurecer la CSP (`style-src` sin `'unsafe-inline'`).
- Cifrar los respaldos que se copian fuera del servidor.
- Cálculo de depreciación y valor en libros por bien, si contabilidad lo necesita.

---

## 6. Descripción para un Pull Request

> Los commits están en `main` local. Para abrir un PR, primero hay que subirlos a una rama nueva (por ejemplo `git push origin main:mejoras-sibip`). Eso lo decide la dueña del repositorio.

**Título**

```
SIBIP: roles y permisos, código de barras y etiquetas, toma de inventario, reportes, seguridad y despliegue
```

**Cuerpo**

```markdown
## Resumen

Lleva el sistema de inventario del colegio a un estado listo para producción, en 7 fases revisadas y aprobadas una por una. Detalle completo en `ENTREGA.md`.

- **Roles y permisos** por módulo y acción, editables; Policies para que cada responsable vea solo lo suyo; flujos de aprobación para bajas, movimientos y requerimientos; auditoría de cada cambio; soft delete para registros con historial.
- **Código de barras Code 128** (`CAT-S01-000123`, inmutable) en lugar de `qr_codigo`, y **etiquetas térmicas** (impresión directa, PDF, PNG/ZIP) con configuración y página de prueba.
- **Toma de inventario por escaneo** con lectora o cámara, para varias personas a la vez y sin conexión, con cierre, faltantes y exportación.
- **Interfaz nueva** según los mockups, responsive y sin CDN (Vite + Tailwind 4).
- **8 reportes** con vista previa, PDF institucional y Excel (en la cola cuando son grandes).
- **Seguridad:** archivos en almacenamiento privado con validación del tipo real, límite de intentos de login, recuperación de contraseña por correo, cierre de sesión por inactividad, CSP y encabezados de seguridad, páginas de error en español.
- **Despliegue:** `DEPLOY.md` (VPS Ubuntu, cPanel y red local), `deploy.sh`, respaldo diario, `sibip:verificar-produccion` y checklist para el colegio.

## Cambios que conviene revisar

- Se elimina el registro público y la tabla `users` (vacía).
- `qr_codigo` → `codigo_barras`; `codigo_patrimonial` pasa a ser opcional.
- Archivos movidos de `public/uploads` a `storage/app/private`: después de desplegar hay que ejecutar `php artisan sibip:migrar-archivos`.
- Comprobantes solo en PDF o imagen; el archivo documentario sube PDF en vez de escribir una ruta.
- Se quita "Mantener la sesión iniciada" (cierre a los 120 minutos de inactividad).
- Se eliminan las vistas `home` y `welcome`, sin uso.

La lista completa (44 puntos) está en `ENTREGA.md`, sección 3.

## Migraciones

Diez migraciones nuevas y reversibles (`2026_10_16_*` a `2026_10_20_*`). En tres migraciones existentes solo cambió el formato (Pint: espacios y orden de los `use`), sin ningún cambio de esquema. Probado con `migrate:fresh --seed` y con rollback de la última.

## Cómo probar

    composer install && npm ci && npm run build
    cp .env.example .env && php artisan key:generate
    php artisan migrate:fresh --seed
    php artisan serve      # admin@sibip.local / Admin2026!

## Verificación

- [x] `php artisan test`: 185 pruebas correctas (MySQL)
- [x] Larastan nivel 8 sin errores
- [x] Pint
- [x] `composer audit` y `npm audit` sin vulnerabilidades
- [x] Capturas con Playwright a 375, 768 y 1366 px
- [ ] Despliegue en el servidor definitivo (pendiente: el destino no está decidido)
- [ ] Prueba con la impresora de etiquetas y la lectora reales del colegio
```
