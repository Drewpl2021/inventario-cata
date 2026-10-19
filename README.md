# SIBIP · Sistema Integrado de Bienes Patrimoniales

Sistema web del **Colegio Adventista Túpac Amaru** (Juliaca) para registrar y controlar los bienes patrimoniales de sus tres sedes: etiquetas con código de barras, tomas de inventario por escaneo (con cámara o lectora, también sin conexión), movimientos, bajas y requerimientos con aprobación, reportes en PDF y Excel, y auditoría de cada cambio.

- **Stack:** Laravel 13 · PHP 8.4 · MySQL 8 · Vite + Tailwind CSS 4 · Chart.js · html5-qrcode.
- **Roles:** Administrador, Operador, Auditor y Usuario (responsable de bienes), con permisos por módulo editables.
- **Con Docker (sin instalar PHP ni MySQL):** [DOCKER.md](DOCKER.md).
- **Documentos relacionados:** [DEPLOY.md](DEPLOY.md) (instalación en un servidor, actualización y respaldos) · [CHECKLIST_PUESTA_EN_MARCHA.md](CHECKLIST_PUESTA_EN_MARCHA.md) (primeros pasos del colegio) · [SEGURIDAD.md](SEGURIDAD.md) (medidas de seguridad y tareas del administrador del servidor) · [ESCANEO.md](ESCANEO.md) (cámara, lectoras y HTTPS para escanear).

---

## Instalación local paso a paso

### 1. Requisitos

| Programa | Versión | Comprobar con |
|---|---|---|
| PHP | 8.4 con las extensiones `gd` (con WebP y FreeType), `exif`, `fileinfo`, `mbstring`, `pdo_mysql`, `xml`, `zip` | `php -v` y `php -m` |
| Composer | 2.x | `composer -V` |
| MySQL | 8.0 o superior (o MariaDB 10.6+) | `mysql --version` |
| Node.js | 20 o superior, con npm | `node -v` |

En Windows, [Laragon](https://laragon.org) trae PHP, MySQL y Composer listos. En `php.ini`, `upload_max_filesize` y `post_max_size` deben ser de al menos **12M** (los PDF pueden pesar hasta 10 MB).

### 2. Obtener el código e instalar dependencias

```bash
git clone <url-del-repositorio> sibip
cd sibip
composer install
npm install
```

### 3. Configurar el entorno

```bash
cp .env.example .env        # en Windows (cmd): copy .env.example .env
php artisan key:generate
```

Revise en `.env` los datos de MySQL (`DB_DATABASE=sibip`, `DB_USERNAME`, `DB_PASSWORD`). En local el correo queda en el registro (`MAIL_MAILER=log`): los enlaces de recuperación de contraseña aparecen en `storage/logs/laravel.log`.

### 4. Crear las bases de datos

En MySQL (Laragon: *Database* → HeidiSQL, o la consola):

```sql
CREATE DATABASE sibip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE sibip_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- para las pruebas
```

### 5. Tablas y datos de demostración

```bash
php artisan migrate --seed
```

Crea los roles y permisos, y (fuera de producción) datos de demostración: 3 sedes, áreas, categorías, responsables, 55 bienes y estas cuentas. El sistema pide cambiar la contraseña en el primer ingreso.

| Rol | Correo | Contraseña inicial |
|---|---|---|
| Administrador | `admin@sibip.local` | `Admin2026!` |
| Operador | `operador@sibip.local` | `Operador2026!` |
| Auditor | `auditor@sibip.local` | `Auditor2026!` |
| Usuario (responsable) | `usuario@sibip.local` | `Usuario2026!` |

Para volver a empezar con la base limpia: `php artisan migrate:fresh --seed`.

### 6. Compilar los estilos y scripts

```bash
npm run build      # una sola vez
# o, mientras se programa (recarga automática):
npm run dev
```

### 7. Levantar el sistema

```bash
php artisan serve
```

Abra <http://localhost:8000> e ingrese con una de las cuentas. Con `composer run dev` se inician juntos el servidor, Vite, la cola y los registros.

**Cola de trabajos:** los Excel de más de 1000 filas se generan en segundo plano. En local, deje abierta otra terminal con:

```bash
php artisan queue:work
```

### 8. Archivos de versiones anteriores (solo si existen)

Si el sistema tenía archivos en `public/uploads`, muévalos al almacenamiento privado (se puede ejecutar varias veces sin dañar nada; con `--simular` solo muestra lo que haría):

```bash
php artisan sibip:migrar-archivos --simular
php artisan sibip:migrar-archivos
```

---

## Pruebas y calidad

```bash
php artisan test                                   # pruebas automáticas (usa la base sibip_test)
vendor/bin/pint                                    # formato del código
vendor/bin/phpstan analyse --memory-limit=2G       # análisis estático (Larastan, nivel 8)
composer audit && npm audit                        # dependencias con vulnerabilidades conocidas
```

Capturas de pantalla para revisar el diseño (con el servidor en marcha y la base recién sembrada): `node tests/visual/capturas.mjs`. Quedan en `storage/app/capturas/`.

## Problemas frecuentes

- **"Unable to locate file in Vite manifest":** falta compilar; ejecute `npm run build` (o `npm run dev`).
- **No llega el correo de recuperación:** en local está en `storage/logs/laravel.log`. Para enviarlo de verdad, configure SMTP siguiendo el ejemplo de Gmail en `.env.example`.
- **La cámara no abre al escanear:** el navegador exige `https://` o `localhost`; vea [ESCANEO.md](ESCANEO.md).
- **"Demasiados intentos fallidos":** el login se bloquea un minuto tras 5 intentos fallidos con el mismo correo desde la misma red.
- **La sesión se cierra sola:** por seguridad, tras 120 minutos sin actividad (se avisa 5 minutos antes).
