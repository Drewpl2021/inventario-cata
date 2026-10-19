# Despliegue de SIBIP

Guía para poner SIBIP en un servidor. El destino todavía no está decidido, así que se cubren las dos opciones más probables y una prueba en la red del colegio:

- **A. VPS Ubuntu 24.04** (recomendado): Nginx + PHP-FPM 8.4 + MySQL 8, con control total, cola permanente y HTTPS.
- **B. Hosting compartido con cPanel:** más simple de contratar, con algunas limitaciones (cola por cron, a veces sin SSH).
- **C. Prueba en la red local** con una PC y Laragon.

Además: [actualizar](#actualizar-el-sistema-vps), [respaldos y restauración](#respaldos), [verificación final](#verificación-final) y [monitoreo](#monitoreo). Las medidas de seguridad y las tareas del administrador están en [SEGURIDAD.md](SEGURIDAD.md); los pasos para el personal del colegio, en [CHECKLIST_PUESTA_EN_MARCHA.md](CHECKLIST_PUESTA_EN_MARCHA.md).

En los comandos, reemplace lo que está entre `<...>`:

| Marcador | Ejemplo |
|---|---|
| `<dominio>` | `inventario.cata.edu.pe` |
| `<url-del-repositorio>` | `https://github.com/<organización>/sibip.git` |
| `<contraseña-mysql>` | una contraseña larga y aleatoria (por ejemplo, la salida de `openssl rand -base64 24`) |

---

## A. VPS Ubuntu 24.04 desde cero

Se asume un servidor recién creado, con acceso SSH como un usuario con `sudo` y el dominio ya apuntando (registro DNS tipo A) a la IP del servidor.

### A.1 Paquetes

Ubuntu 24.04 trae PHP 8.3; PHP 8.4 se instala desde el repositorio de Ondřej Surý, que es el habitual para esto.

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common ca-certificates curl unzip git nginx mysql-server
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-zip \
    php8.4-gd php8.4-curl php8.4-intl php8.4-bcmath php8.4-exif php8.4-opcache

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 22 (solo para compilar estilos y scripts)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# Comprobar
php -v && php -m | grep -Ei 'gd|exif|fileinfo|mbstring|pdo_mysql|zip' && composer -V && node -v && mysql --version
```

Límites de subida (los PDF pueden pesar hasta 10 MB):

```bash
sudo tee /etc/php/8.4/fpm/conf.d/99-sibip.ini > /dev/null <<'EOF'
upload_max_filesize = 12M
post_max_size = 12M
memory_limit = 512M
max_execution_time = 120
expose_php = Off
EOF
sudo systemctl restart php8.4-fpm
```

### A.2 Cortafuegos

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

### A.3 Base de datos con usuario dedicado (nunca root)

```bash
sudo mysql_secure_installation   # responda "Y" a todo, salvo que quiera cambiar el método de autenticación de root
sudo mysql <<'EOF'
CREATE DATABASE sibip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sibip'@'localhost' IDENTIFIED BY '<contraseña-mysql>';
GRANT ALL PRIVILEGES ON sibip.* TO 'sibip'@'localhost';
FLUSH PRIVILEGES;
EOF
```

El usuario `sibip` solo tiene permisos sobre su base. MySQL escucha únicamente en `127.0.0.1` (valor por defecto en Ubuntu); no abra el puerto 3306 en el cortafuegos.

### A.4 Usuario del sistema y código

El código pertenece a un usuario `sibip`; Nginx y PHP-FPM corren como `www-data`, que solo necesita escribir en `storage` y `bootstrap/cache`.

```bash
sudo adduser --disabled-password --gecos "SIBIP" sibip
sudo usermod -aG www-data sibip
sudo mkdir -p /var/www/sibip
sudo chown sibip:www-data /var/www/sibip

sudo -iu sibip
git clone <url-del-repositorio> /var/www/sibip
cd /var/www/sibip
```

Si el repositorio es privado, cree una *deploy key* de solo lectura (`ssh-keygen -t ed25519`) y agréguela al repositorio antes de clonar.

### A.5 Configurar el .env

Siga como el usuario `sibip`, dentro de `/var/www/sibip`:

```bash
cp .env.production.example .env
chmod 640 .env
nano .env        # complete APP_URL=https://<dominio>, DB_PASSWORD, el correo y SIBIP_ADMIN_*
```

Use como guía los comentarios de `.env.production.example`. El correo SMTP (ejemplo con Gmail y contraseña de aplicación) está explicado en `.env.example`.

### A.6 Dependencias, compilación y base de datos

```bash
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force     # SOLO la primera vez: guarde una copia de APP_KEY en lugar seguro
npm ci --no-audit --no-fund
npm run build

php artisan migrate --force
php artisan db:seed --force          # roles, permisos y Administrador inicial (en producción NO carga DemoSeeder)
```

`db:seed` en producción solo ejecuta `RolesYPermisosSeeder` y `AdministradorInicialSeeder`, con el correo y la contraseña temporal de `SIBIP_ADMIN_EMAIL` y `SIBIP_ADMIN_PASSWORD`. Después **borre `SIBIP_ADMIN_PASSWORD` del `.env`**.

`storage:link` **no hace falta**: SIBIP no publica archivos en `storage/app/public`. Todos los archivos subidos están en `storage/app/private` y se sirven a través del sistema, previa verificación de permisos.

Si viene de una versión anterior con archivos en `public/uploads`, cópielos a esa misma ruta y ejecute `php artisan sibip:migrar-archivos`.

### A.7 Cachés

```bash
php artisan optimize      # configuración, rutas, vistas y eventos
```

Vuelva a ejecutarlo cada vez que cambie el `.env` (el script `deploy.sh` lo hace solo).

### A.8 Permisos

Vuelva a su usuario con `sudo` (`exit`) y ejecute:

```bash
cd /var/www/sibip
sudo chown -R sibip:www-data .
sudo find . -type d -exec chmod 750 {} \;
sudo find . -type f -exec chmod 640 {} \;
sudo chmod -R ug+rwX storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} \;   # lo nuevo hereda el grupo www-data
sudo chmod 750 deploy.sh artisan
```

### A.9 Nginx

```bash
sudo tee /etc/nginx/sites-available/sibip > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name <dominio>;

    root /var/www/sibip/public;
    index index.php;
    charset utf-8;

    # Subidas: PDF hasta 10 MB (más margen del formulario)
    client_max_body_size 12M;

    server_tokens off;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Archivos compilados por Vite: llevan una huella en el nombre, se pueden guardar mucho tiempo
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 120;
        internal;
    }

    # Ningún otro .php ni archivo oculto (.env, .git) es accesible
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }

    error_page 404 /index.php;
    access_log /var/log/nginx/sibip.access.log;
    error_log  /var/log/nginx/sibip.error.log;
}
EOF
sudo ln -s /etc/nginx/sites-available/sibip /etc/nginx/sites-enabled/sibip
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Los encabezados de seguridad (CSP, HSTS, X-Frame-Options, etc.) los envía el propio sistema; no hace falta repetirlos en Nginx.

### A.10 HTTPS con Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d <dominio> --redirect --agree-tos -m <correo-del-administrador>
sudo certbot renew --dry-run      # comprueba que la renovación automática funciona
```

Certbot agrega el bloque `listen 443 ssl` y la redirección de HTTP a HTTPS, y deja programada la renovación (temporizador `certbot.timer`). Sin HTTPS, la cámara del celular no funciona para escanear y las cookies seguras no se envían.

### A.11 Worker de la cola (systemd)

Procesa los Excel grandes y el latido de la cola.

```bash
sudo tee /etc/systemd/system/sibip-cola.service > /dev/null <<'EOF'
[Unit]
Description=SIBIP - worker de la cola
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/sibip
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=900
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
sudo systemctl daemon-reload
sudo systemctl enable --now sibip-cola
sudo systemctl status sibip-cola --no-pager
```

`deploy.sh` ejecuta `php artisan queue:restart`: el worker termina el trabajo en curso, se cierra y systemd lo vuelve a levantar con el código nuevo.

### A.12 Cron de tareas programadas

Ejecuta cada minuto el programador de Laravel (latidos, respaldo diario a las 02:30).

```bash
echo '* * * * * cd /var/www/sibip && php artisan schedule:run >> /dev/null 2>&1' | sudo crontab -u www-data -
sudo crontab -u www-data -l
```

### A.13 Primera verificación

```bash
sudo -u www-data php artisan sibip:respaldar          # primer respaldo de prueba
sudo -u www-data php artisan sibip:verificar-produccion
```

Espere 5 minutos después de iniciar el worker y el cron para que los latidos aparezcan en verde. Luego siga [CHECKLIST_PUESTA_EN_MARCHA.md](CHECKLIST_PUESTA_EN_MARCHA.md).

---

## Actualizar el sistema (VPS)

```bash
sudo -iu sibip
cd /var/www/sibip
./deploy.sh                # o RAMA=otra-rama ./deploy.sh
```

El script pone el sitio en mantenimiento, descarga la rama (solo avance rápido, sin fusiones), instala las dependencias, compila, migra, regenera las cachés, reinicia el worker, reabre el sitio y ejecuta la verificación.

- **Se detiene en el primer error** y deja el sitio en mantenimiento, para que nadie vea una versión a medias. Corrija el problema y ejecute `./deploy.sh` de nuevo. Para reabrir sin actualizar: `php artisan up`.
- No corre si hay cambios sin confirmar en el servidor, ni como `root`.
- Antes de una actualización grande, haga un respaldo manual: `php artisan sibip:respaldar`.

---

## B. Hosting compartido con cPanel

Requisitos del plan: PHP 8.4 (selector *MultiPHP Manager* o *Select PHP Version*), MySQL, cron y, de preferencia, acceso SSH o *Terminal* en cPanel.

### B.1 Preparar los archivos (en su PC)

Si el hosting no tiene SSH, Composer ni Node, prepare todo en una PC con el proyecto:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

Luego comprima la carpeta del proyecto **incluyendo `vendor/` y `public/build/`**, y **sin** `.env`, `node_modules/`, `.git/` ni `storage/logs/*`.

### B.2 Subir y apuntar el dominio a /public

1. En el *Administrador de archivos*, suba el .zip a su carpeta personal (por ejemplo `/home/<usuario>/sibip`, **fuera** de `public_html`) y extráigalo.
2. Apunte el dominio a la carpeta `public` del proyecto:
   - **Subdominio o dominio adicional:** en *Dominios*, edite la *Raíz del documento* y póngala en `sibip/public`.
   - **Dominio principal** (cuya raíz es fija en `public_html`): renombre `public_html` como respaldo y cree un enlace simbólico, si tiene Terminal: `ln -s ~/sibip/public ~/public_html`. Si no se puede, pida al soporte del hosting que cambie la raíz del documento a `sibip/public`.
3. Nunca deje el proyecto completo dentro de `public_html`: el `.env` y los archivos privados quedarían expuestos.

### B.3 Base de datos desde cPanel

1. *Bases de datos MySQL* → cree la base (cPanel le agrega un prefijo, por ejemplo `cuenta_sibip`).
2. Cree un usuario con contraseña larga y agréguelo a la base con **todos los privilegios** sobre ella.
3. Anote los nombres completos con prefijo para el `.env`.

### B.4 El .env

Cree `sibip/.env` a partir de `.env.production.example` (el *Administrador de archivos* permite crear y editar archivos). Ponga `APP_URL=https://<dominio>`, los datos de la base del paso anterior y el correo. En cPanel suele funcionar el SMTP del propio hosting (por ejemplo `mail.<dominio>`, puerto 465 con `MAIL_SCHEME=smtps`) o Gmail con contraseña de aplicación.

Genere `APP_KEY` en su PC con `php artisan key:generate --show` y cópiela al `.env`.

### B.5 Migraciones y primer administrador

**Con SSH o Terminal:**

```bash
cd ~/sibip
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
```

**Sin SSH:** use una de estas dos vías.

- *Cron de una sola vez:* en *Trabajos de cron*, cree una tarea para dentro de unos minutos con el comando
  `cd /home/<usuario>/sibip && /usr/local/bin/php artisan migrate --force && /usr/local/bin/php artisan db:seed --force && /usr/local/bin/php artisan optimize`
  Cuando haya corrido (revise que las tablas aparezcan en phpMyAdmin), **borre la tarea**.
- *Importar en phpMyAdmin:* en su PC, con una base vacía, ejecute `php artisan migrate` y `php artisan db:seed --class=RolesYPermisosSeeder`. Exporte la base con `mysqldump`, impórtela en phpMyAdmin y cree el Administrador con el cron de una sola vez: `php artisan db:seed --class=AdministradorInicialSeeder --force`.

La ruta de PHP en cPanel suele ser `/usr/local/bin/php` o `/opt/cpanel/ea-php84/root/usr/bin/php`; búsquela en *MultiPHP Manager* o pregunte al soporte. Para actualizar sin SSH, repita B.1, suba solo los archivos cambiados y ejecute las migraciones con el cron de una sola vez.

### B.6 Cron: tareas programadas y cola

En *Trabajos de cron*, dos tareas **cada minuto** (`* * * * *`):

```
cd /home/<usuario>/sibip && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
cd /home/<usuario>/sibip && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

En un hosting compartido no hay supervisor ni systemd, así que la cola se procesa por cron: cada minuto se arranca un worker que atiende lo pendiente y termina. `--max-time=55` evita que se acumulen workers. Un Excel grande puede tardar hasta un minuto más en empezar.

### B.7 SSL gratuito

En *SSL/TLS Status* (o *AutoSSL*), active el certificado gratuito para el dominio y espere a que quede en verde. Luego, en *Dominios*, active **Forzar redirección HTTPS**.

### B.8 Respaldos y verificación en cPanel

- El respaldo diario de SIBIP necesita `mysqldump`. Si el hosting no lo permite, el comando falla y lo registra; use entonces los respaldos de cPanel (*Respaldo* → descargar la base y la carpeta personal) y descárguelos con frecuencia.
- Con el cron funcionando, ejecute la verificación (por Terminal o con el cron de una sola vez, guardando la salida: `... sibip:verificar-produccion > ~/verificacion.txt`).
- En cPanel, `.env` no puede tener `chmod 640` si el servidor web corre con su mismo usuario; use `600`.

---

## C. Prueba en la red local del colegio (Laragon)

Sirve para probar con varias personas antes de contratar un servidor. **No es para uso definitivo** (sin HTTPS ni respaldos fuera del equipo).

1. Instale el sistema en una PC con Laragon siguiendo el [README](README.md) (base, `migrate --seed`, `npm run build`).
2. Busque la IP de la PC: `ipconfig` → *Dirección IPv4* (por ejemplo `192.168.1.50`). Conviene fijarla en el router para que no cambie.
3. En `.env`: `APP_URL=http://192.168.1.50:8000` y luego `php artisan config:clear`.
4. Inicie el servidor para toda la red:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
   En otra terminal deje corriendo la cola: `php artisan queue:work`.
5. Permita el puerto en el Firewall de Windows (PowerShell como administrador):
   ```powershell
   New-NetFirewallRule -DisplayName "SIBIP 8000" -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow -Profile Private
   ```
6. Desde otro equipo o celular de la misma red, abra `http://192.168.1.50:8000`.

> **Importante:** sin HTTPS, el navegador **no permite usar la cámara del celular** para escanear (solo funciona en `https://` o en `localhost`). La **lectora USB o Bluetooth sí funciona**, porque escribe el código como un teclado. Para probar la cámara del celular, use el túnel HTTPS descrito en [ESCANEO.md](ESCANEO.md).

---

## Respaldos

### Qué se respalda y cuándo

`php artisan sibip:respaldar` corre todos los días a las **02:30** (hora de Lima) mediante el cron de A.12/B.6, y genera en `storage/app/respaldos/`:

| Archivo | Contenido |
|---|---|
| `sibip-base-AAAAMMDD-HHMMSS.sql.gz` | La base completa (`mysqldump --single-transaction`, comprimido) |
| `sibip-archivos-AAAAMMDD-HHMMSS.zip` | `storage/app/private`: fotos, comprobantes, documentos y adjuntos |

Se conservan **14 días** (`SIBIP_RESPALDOS_DIAS`); la hora se cambia con `SIBIP_RESPALDOS_HORA`. La contraseña de MySQL no aparece en la lista de procesos. Ejecución manual: `php artisan sibip:respaldar` (o `--solo-base`).

### Copiarlos fuera del servidor

Un respaldo que solo está en el mismo servidor se pierde con él. Elija al menos una opción:

**Opción 1. Desde una PC o servidor del colegio, que los trae cada día (recomendada):**

```bash
# En el equipo que guarda las copias (Linux, macOS o WSL), con una llave SSH autorizada para el usuario sibip:
rsync -av sibip@<dominio>:/var/www/sibip/storage/app/respaldos/ ~/respaldos-sibip/
```

Programe ese comando en el equipo de destino (cron o el *Programador de tareas* de Windows con WSL).

**Opción 2. El servidor los sube a la nube con [rclone](https://rclone.org)** (Google Drive institucional, OneDrive, etc.):

```bash
sudo apt install -y rclone
sudo -u www-data rclone config          # cree un destino llamado "nube"
(sudo crontab -u www-data -l; echo '0 4 * * * rclone copy /var/www/sibip/storage/app/respaldos nube:respaldos-sibip --max-age 48h') | sudo crontab -u www-data -
sudo crontab -u www-data -l      # debe mostrar el schedule:run y la línea de rclone
```

`rclone copy` no borra nada en la nube: allí las copias se acumulan; configure la retención en el propio servicio si lo necesita.

**cPanel:** descargue los archivos de `sibip/storage/app/respaldos` desde el *Administrador de archivos* o use los respaldos de cPanel.

### Restaurar un respaldo, paso a paso

Pruebe este procedimiento al menos una vez por trimestre, de preferencia en una base aparte (paso 3, opción a).

1. **Elija el respaldo** (el `.sql.gz` y el `.zip` con la misma fecha):
   ```bash
   cd /var/www/sibip
   ls -lh storage/app/respaldos/
   R=storage/app/respaldos/sibip-base-20261020-023000.sql.gz
   Z=storage/app/respaldos/sibip-archivos-20261020-023000.zip
   ```
2. **Ponga el sitio en mantenimiento y haga un respaldo del estado actual**, por si necesita volver atrás:
   ```bash
   php artisan down
   php artisan sibip:respaldar
   ```
3. **Restaure la base.**
   a) *Para probar* sin tocar la base real:
   ```bash
   sudo mysql -e "CREATE DATABASE sibip_prueba CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON sibip_prueba.* TO 'sibip'@'localhost';"
   gunzip -c "$R" | mysql -u sibip -p sibip_prueba
   mysql -u sibip -p -e "SELECT COUNT(*) AS bienes FROM sibip_prueba.bienes;"
   ```
   b) *Para recuperar de verdad* (reemplaza los datos actuales):
   ```bash
   sudo mysql -e "DROP DATABASE sibip; CREATE DATABASE sibip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON sibip.* TO 'sibip'@'localhost';"
   gunzip -c "$R" | mysql -u sibip -p sibip
   ```
4. **Restaure los archivos subidos:**
   ```bash
   mv storage/app/private storage/app/private-antes-de-restaurar
   unzip -q "$Z" -d storage/app/          # crea storage/app/private
   sudo chown -R sibip:www-data storage/app/private
   sudo chmod -R ug+rwX storage/app/private
   ```
5. **Vuelva a abrir y compruebe:**
   ```bash
   php artisan optimize:clear && php artisan optimize
   php artisan up
   php artisan sibip:verificar-produccion
   ```
   Entre al sistema, abra algunos bienes con foto y un comprobante. Si todo está bien, borre `storage/app/private-antes-de-restaurar` y la base `sibip_prueba`.

En Windows (Laragon) los pasos son los mismos: use `mysql.exe` de `C:\laragon\bin\mysql\...\bin`, descomprima el `.sql.gz` con 7-Zip y el `.zip` con el Explorador.

---

## Verificación final

```bash
php artisan sibip:verificar-produccion
```

Muestra en verde o rojo, con la indicación de qué hacer:

- **Configuración:** `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL` con `https://`, sesión cifrada y solo por HTTPS, configuración en caché, `SIBIP_ADMIN_PASSWORD` quitada.
- **Base y cuentas:** conexión, migraciones, que no existan las cuentas demo, que ninguna cuenta activa use una contraseña escrita en el código (seeders o pruebas) y que haya un Administrador activo.
- **Carpetas:** permisos de escritura en `storage` y `bootstrap/cache`, sin `public/uploads`, assets compilados y `.env` no legible por otros usuarios.
- **Correo:** envío real (no `log`) y remitente configurado.
- **Cola y cron:** cola en segundo plano, latido del cron (menos de 3 minutos), latido de la cola (menos de 12), sin trabajos atascados ni fallidos, y respaldo de las últimas 24 horas.

Termina con código de error si hay algún punto en rojo, así que también sirve en scripts.

---

## Monitoreo

`https://<dominio>/up` responde **200** con una página mínima cuando el sistema y la base de datos funcionan, y **500** si la base no responde. No muestra versiones, datos ni configuración. Sirve para servicios gratuitos de monitoreo (UptimeRobot, Better Stack, etc.): configúrelos para revisar esa URL cada 5 minutos y avisar por correo.

Además, revise de vez en cuando:

- `storage/logs/laravel-AAAA-MM-DD.log` (en producción solo advertencias y errores; se guardan 30 días).
- `sudo journalctl -u sibip-cola --since today` (worker de la cola).
- La pantalla *Auditoría* del sistema, en especial los ingresos fallidos y los bloqueos.
