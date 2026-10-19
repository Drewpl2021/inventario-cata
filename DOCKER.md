# SIBIP con Docker

Con Docker, SIBIP funciona en cualquier computadora (Windows, Linux o Mac) sin instalar PHP, MySQL ni Node: solo **Docker** y **Git**. Todo el sistema corre en "contenedores", que son como pequeñas computadoras dentro de la suya:

| Servicio | Qué hace |
|---|---|
| `app` | El sitio web (lo que se abre en el navegador) |
| `db` | La base de datos MySQL 8.4 |
| `queue` | Trabajos en segundo plano (Excel grandes) |
| `scheduler` | Tareas programadas: respaldo diario a las 02:30 y controles |
| `phpmyadmin` | *(opcional)* Ver la base de datos desde el navegador |

Los datos se guardan en **volúmenes** de Docker, que no se borran al apagar o actualizar: `db-datos` (la base), `archivos` (fotos y documentos subidos), `respaldos` y `sistema` (la clave de la aplicación).

> En los comandos, **Windows** usa PowerShell y **Linux/Mac** la terminal. Cuando el comando es el mismo, aparece una sola vez.

---

## 1. Instalar Docker y Git

**Windows 10/11**
1. Descargue **Docker Desktop** de <https://www.docker.com/products/docker-desktop/> e instálelo con las opciones por defecto (usa WSL 2; si lo pide, acepte instalarlo y reinicie).
2. Abra Docker Desktop y espere a que diga *Engine running* (abajo a la izquierda).
3. Instale **Git** de <https://git-scm.com/download/win>.

**Linux (Ubuntu/Debian)**
```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER      # cierre la sesión y vuelva a entrar
sudo apt install -y git
```

Compruebe que funciona: `docker --version` y `docker compose version`.

Requisitos del equipo: 4 GB de RAM libres y unos 3 GB de disco.

---

## 2. Descargar y configurar

```bash
git clone <url-del-repositorio> sibip
cd sibip
```

Cree el archivo de configuración a partir del ejemplo:

```powershell
# Windows
copy .env.docker.example .env
notepad .env
```
```bash
# Linux
cp .env.docker.example .env
nano .env
```

En el `.env`, cambie como mínimo lo que está entre `<...>`:

- `DB_PASSWORD` y `DB_ROOT_PASSWORD`: dos contraseñas largas, inventadas. **No las cambie después** del primer arranque: MySQL las guarda la primera vez.
- `SIBIP_ADMIN_EMAIL` y `SIBIP_ADMIN_PASSWORD`: el correo y una contraseña temporal para el primer Administrador. El sistema pedirá cambiarla al entrar.
- `APP_PORT`: el puerto del sistema (por defecto `8080`). Si ese puerto ya está ocupado en su equipo, use otro, por ejemplo `8090`, y ponga el mismo número en `APP_URL`.

Puede dejar `APP_KEY` vacía: el sistema la genera al primer arranque (vea el paso 3).

---

## 3. Levantar el sistema

```bash
docker compose up -d --build
```

La primera vez tarda unos minutos (descarga y construye todo). Cada vez que arranca, el sistema solo:
1. espera a que la base de datos responda,
2. genera la `APP_KEY` si está vacía,
3. crea o actualiza las tablas (migraciones),
4. crea los roles, los permisos y el Administrador inicial si no existen,
5. prepara las cachés.

Compruebe el estado: `docker compose ps`. Los servicios deben estar *Up*, y `app` y `db` además *(healthy)*.

Abra **http://localhost:8080** (o el puerto que eligió) e ingrese con `SIBIP_ADMIN_EMAIL` y `SIBIP_ADMIN_PASSWORD`.

**Guarde la APP_KEY.** Si la dejó vacía, el sistema la generó y la guardó en el volumen `sistema`. Cópiela a su `.env` para tenerla a mano:
```bash
docker compose exec app cat storage/app/sistema/app_key
```
Pegue el resultado en `APP_KEY=` y guarde una copia en un lugar seguro. Si se pierde, todas las sesiones se cierran.

---

## 4. Ver qué está pasando (logs)

```bash
docker compose logs -f app             # el sitio web (Ctrl+C para salir)
docker compose logs -f queue scheduler # trabajos en segundo plano y tareas programadas
docker compose logs --tail=100 db      # la base de datos
docker compose exec app php artisan sibip:verificar-produccion   # revisión completa en verde/rojo
```

---

## 5. Uso diario

| Para… | Comando |
|---|---|
| Detener (los datos se conservan) | `docker compose stop` |
| Volver a encender | `docker compose start` |
| Apagar y quitar los contenedores (los datos se conservan) | `docker compose down` |
| Encender después de un `down` | `docker compose up -d` |
| Reiniciar solo la web | `docker compose restart app` |
| Abrir phpMyAdmin en http://localhost:8081 | `docker compose --profile tools up -d phpmyadmin` |
| Apagar phpMyAdmin | `docker compose --profile tools stop phpmyadmin` |

En phpMyAdmin, el servidor es `db`, con el usuario `DB_USERNAME` y la contraseña `DB_PASSWORD` del `.env`.

> ⚠️ `docker compose down -v` **borra los volúmenes**, es decir, la base, los archivos y los respaldos. No lo use salvo que quiera empezar de cero y ya tenga un respaldo fuera del equipo.

---

## 6. Actualizar a una versión nueva

```bash
docker compose exec app php artisan sibip:respaldar   # respaldo antes de actualizar
git pull
docker compose up -d --build
```

Las migraciones nuevas se aplican solas al arrancar. Los datos se conservan.

---

## 7. Respaldos

El servicio `scheduler` hace un respaldo **todos los días a las 02:30** (hora de Lima) y conserva 14 días. Solo funciona mientras el equipo y Docker estén encendidos a esa hora. Para hacer un respaldo en cualquier momento:

```bash
docker compose exec app php artisan sibip:respaldar
docker compose exec app ls -lh storage/app/respaldos
```

Cada respaldo son dos archivos con la misma fecha: `sibip-base-…sql.gz` (la base) y `sibip-archivos-…zip` (fotos y documentos).

**Copiarlos fuera del contenedor** (a una carpeta `respaldos` junto al proyecto, para llevarlos a un USB, a otro equipo o a la nube):

```bash
docker compose cp app:/var/www/html/storage/app/respaldos ./respaldos
```

Haga esto con frecuencia: si se daña el disco del equipo, los respaldos que quedaron dentro de Docker se pierden con él.

---

## 8. Restaurar un respaldo

1. **Elija el respaldo** y anote su fecha:
   ```bash
   docker compose exec app ls storage/app/respaldos
   ```
   Si el respaldo está en su equipo (por ejemplo en `./respaldos`), primero súbalo al contenedor:
   ```bash
   docker compose cp ./respaldos/sibip-base-20261020-023000.sql.gz app:/var/www/html/storage/app/respaldos/
   docker compose cp ./respaldos/sibip-archivos-20261020-023000.zip app:/var/www/html/storage/app/respaldos/
   ```
2. **Ponga el sistema en mantenimiento y respalde el estado actual**, por si necesita volver atrás:
   ```bash
   docker compose exec app php artisan down
   docker compose exec app php artisan sibip:respaldar
   ```
3. **Restaure la base** (cambie la fecha por la suya). Reemplaza los datos actuales:
   ```bash
   docker compose exec app sh -c 'gunzip -c storage/app/respaldos/sibip-base-20261020-023000.sql.gz | mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"'
   ```
4. **Restaure las fotos y los documentos:**
   ```bash
   docker compose exec app unzip -oq storage/app/respaldos/sibip-archivos-20261020-023000.zip -d storage/app/
   ```
5. **Vuelva a abrir el sistema y compruebe:**
   ```bash
   docker compose exec app php artisan optimize:clear
   docker compose restart app queue scheduler
   docker compose exec app php artisan up
   ```
   Entre al sistema y abra algunos bienes con foto.

En PowerShell de Windows estos comandos funcionan igual (las comillas simples van dentro del contenedor).

---

## 9. Datos de demostración

Para probar el sistema con 3 sedes, 55 bienes y una cuenta por rol, ponga en el `.env`:
```
SIBIP_SEED_DEMO=true
```
y reinicie: `docker compose up -d`. Las cuentas son las del [README](README.md#5-tablas-y-datos-de-demostración) (por ejemplo `admin@sibip.local` / `Admin2026!`).

- Solo crea lo que falta: si lo deja activado, al reiniciar **no** duplica nada ni deshace los cambios que haya hecho.
- **No lo use con datos reales:** las cuentas demo tienen contraseñas conocidas. Para volver a una instalación sin demo hay que empezar de cero (`docker compose down -v`, después de respaldar lo que necesite).

---

## 10. Usarlo desde otros equipos de la red

1. Averigüe la IP del equipo donde corre Docker:
   - Windows: `ipconfig` → *Dirección IPv4* (por ejemplo `192.168.1.50`)
   - Linux: `hostname -I`

   Conviene fijar esa IP en el router (reserva DHCP) para que no cambie.
2. En el `.env`, ponga `APP_URL=http://192.168.1.50:8080` (con su IP y puerto) y aplique el cambio: `docker compose up -d`.
3. Permita el puerto en el cortafuegos:
   ```powershell
   # Windows (PowerShell como administrador)
   New-NetFirewallRule -DisplayName "SIBIP" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow -Profile Private
   ```
   ```bash
   # Linux con ufw
   sudo ufw allow 8080/tcp
   ```
4. Desde otra PC o un celular conectado a la misma red, abra `http://192.168.1.50:8080`.

La **lectora de códigos USB o Bluetooth funciona** en cualquier equipo, porque escribe como un teclado.

---

## 11. La cámara del celular necesita HTTPS

Por seguridad, los navegadores **solo permiten usar la cámara** en sitios `https://` o en `localhost`. En `http://192.168.1.50:8080` el botón *Cámara* de la toma de inventario no funcionará en el celular. La lectora sí.

Para usar la cámara sin comprar un dominio, se puede abrir un **túnel HTTPS gratuito con Cloudflare** (`cloudflared`):

1. Instale cloudflared:
   - Windows: `winget install --id Cloudflare.cloudflared`
   - Linux: vea <https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/>
2. Con el sistema encendido, ejecute (use su puerto):
   ```bash
   cloudflared tunnel --url http://localhost:8080
   ```
   Mostrará una dirección como `https://palabras-al-azar.trycloudflare.com`.
3. En el `.env`, ajuste estas variables y aplique con `docker compose up -d`:
   ```
   APP_URL=https://palabras-al-azar.trycloudflare.com
   SIBIP_PROXIES_CONFIABLES=*
   SESSION_SECURE_COOKIE=true
   ```
4. Abra esa dirección `https://` en el celular: la cámara ya funciona.

Tenga en cuenta que el túnel rápido **cambia de dirección cada vez que se inicia** y deja el sistema **accesible desde Internet** mientras está abierto. Úselo solo para probar, ciérrelo (Ctrl+C) al terminar y vuelva a poner el `APP_URL` de la red. Para uso permanente con HTTPS, lo indicado es un servidor con dominio y certificado ([DEPLOY.md](DEPLOY.md)) o un túnel con nombre de Cloudflare.

---

## 12. Problemas frecuentes

- **"port is already allocated" o el sitio no abre:** otro programa usa ese puerto. Cambie `APP_PORT` (y `APP_URL`) en el `.env` y ejecute `docker compose up -d`.
- **`app` no llega a *healthy*:** mire `docker compose logs app`. Si dice que la base no respondió, revise `docker compose logs db`. Lo más común es haber cambiado `DB_PASSWORD` después del primer arranque: vuelva a la contraseña original.
- **"Defina DB_PASSWORD en el .env":** falta crear el `.env` (paso 2) o esa línea está vacía.
- **Windows: Docker no arranca:** abra Docker Desktop y espere a *Engine running*; si pide actualizar WSL, ejecute `wsl --update` en PowerShell como administrador.
- **Revisión completa:** `docker compose exec app php artisan sibip:verificar-produccion`. En una red local es normal ver en rojo *APP_URL con https* y *sesión solo por HTTPS*; con datos demo, también las cuentas demo; y sin SMTP configurado, el correo.
