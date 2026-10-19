# Seguridad de SIBIP

Este documento resume cómo protege SIBIP los datos del colegio y qué debe hacer quien administra el servidor. La primera parte describe lo que ya hace el sistema; la segunda, las tareas que **no** puede hacer el sistema por sí solo.

---

## 1. Medidas incluidas en el sistema

### Acceso y contraseñas

- **Sin registro público.** Solo el Administrador crea cuentas. Cada cuenta nueva (o con contraseña restablecida por el Administrador) recibe una **contraseña temporal** que el sistema obliga a cambiar en el primer ingreso.
- **Política de contraseñas:** mínimo 8 caracteres, con letras y números, y distinta de la actual o de la temporal. Se guardan cifradas con bcrypt (nunca en texto plano).
- **Límite de intentos:** 5 intentos fallidos por minuto para el mismo correo desde la misma IP. Luego el ingreso se bloquea y se indica el tiempo de espera.
- **Recuperación por correo:** enlace de un solo uso que vence a los 60 minutos, solo para cuentas activas. La respuesta es la misma exista o no el correo, para no revelar qué cuentas existen.
- **Sesiones:** se cierran tras **120 minutos sin actividad**, con aviso 5 minutos antes. No existe la opción "mantener la sesión iniciada". Al cambiar o restablecer la contraseña se cierran las demás sesiones de esa cuenta; al desactivar una cuenta o su rol, la sesión se corta en la siguiente petición.
- **Auditoría de accesos:** se registran los ingresos correctos, los fallidos y los bloqueos, con fecha, IP y usuario (pantalla *Auditoría*).

### Permisos

- Cada pantalla y acción exige **sesión y un permiso** del módulo (`bienes.ver`, `bajas.aprobar`, etc.), asignado por rol. Una prueba automática recorre todas las rutas y falla si alguna queda sin protección (solo el ingreso y la recuperación de contraseña son públicos).
- **Propiedad de los datos:** un Usuario (responsable) solo ve sus bienes, sus solicitudes, sus archivos y su acta. Las *Policies* lo verifican aunque se escriba la dirección a mano; hay pruebas automáticas de esto.
- **Separación de funciones:** el Operador registra, pero no aprueba bajas, movimientos ni requerimientos, tampoco con peticiones directas.

### Archivos subidos

- Fotos, comprobantes, documentos, fotos de bajas y adjuntos se guardan en **`storage/app/private`**, fuera de la carpeta pública. Solo se sirven a través del sistema, después de comprobar el permiso del usuario sobre ese registro.
- Se valida el **tipo real** del archivo (por su contenido, no por la extensión): imágenes JPG/PNG/WEBP hasta 5 MB y PDF hasta 10 MB. Las imágenes se reducen a 1600 px y se guardan como WEBP; con eso se descarta cualquier contenido oculto en la imagen original.
- Los archivos se guardan con **nombres aleatorios**, que no revelan nada del documento.

### Protección del navegador

- Encabezados en todas las respuestas: `X-Frame-Options`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy` (la cámara solo para el propio sitio) y, en producción con HTTPS, `Strict-Transport-Security` (HSTS).
- **Content-Security-Policy:** solo se ejecutan scripts del propio sistema (y los bloques en línea autorizados con un *nonce* por petición). Los estilos en línea se permiten porque los usan los gráficos y el lector de códigos; no ejecutan código.
- Todos los formularios llevan **token CSRF**; las cookies de sesión son `HttpOnly`, `SameSite=Lax` y, en producción, cifradas y solo por HTTPS.
- Las **páginas de error** (403, 404, 419, 429, 500, 503) no muestran detalles técnicos. Un texto con caracteres inválidos se rechaza como error del formulario, no como error del servidor.

### Datos e historial

- Usuarios, bienes, responsables, sedes y áreas **no se borran físicamente**: se desactivan o se marcan como eliminados (soft delete) para conservar el historial.
- La auditoría guarda quién creó, cambió, desactivó o restauró cada registro, con los datos anteriores y nuevos (sin contraseñas ni tokens), además de exportaciones de reportes e impresión de etiquetas.

### Calidad del código

- Pruebas automáticas (`php artisan test`), análisis estático con Larastan nivel 8 y revisión de dependencias con `composer audit` y `npm audit`.

---

## 2. Responsabilidades del administrador del servidor

El sistema no puede protegerse solo si el servidor está mal configurado. Antes de ponerlo en producción y durante su uso:

### Configuración

- [ ] Usar como base **`.env.production.example`**: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`. **Nunca** dejar `APP_DEBUG=true` en producción: mostraría datos internos ante un error.
- [ ] Generar `APP_KEY` **una sola vez** (`php artisan key:generate`) y guardarla en un lugar seguro. Si se pierde o se cambia, se cierran todas las sesiones.
- [ ] El archivo `.env` no debe ser accesible desde la web ni subirse al repositorio. Permisos sugeridos: `chmod 640 .env`, propietario el usuario del servidor web.
- [ ] El sitio debe apuntar a la carpeta **`public/`**, nunca a la raíz del proyecto.
- [ ] Después de cada cambio de configuración o actualización: `php artisan config:cache`, `php artisan route:cache` y `php artisan view:cache`.
- [ ] Borrar `SIBIP_ADMIN_PASSWORD` del `.env` después de crear el Administrador inicial.
- [ ] No instalar las dependencias de desarrollo en producción: `composer install --no-dev --optimize-autoloader`.

### HTTPS

- [ ] Instalar un certificado (por ejemplo, Let's Encrypt con `certbot`) y redirigir todo el tráfico HTTP a HTTPS. Sin HTTPS, las cookies seguras no funcionan y el navegador no permite usar la cámara para escanear.
- [ ] Renovar el certificado antes de que venza (certbot lo hace solo si se deja programado).

### Base de datos

- [ ] Crear un **usuario de MySQL exclusivo** para SIBIP, con permisos solo sobre su base de datos. No usar `root`.
- [ ] MySQL no debe aceptar conexiones desde Internet (escuchar solo en `127.0.0.1`).

### Copias de seguridad

- [ ] Respaldar **todos los días** la base de datos (`mysqldump`) y la carpeta **`storage/app/private`** (allí están todos los archivos subidos).
- [ ] Guardar las copias fuera del servidor (otro equipo o la nube) y conservar al menos 30 días.
- [ ] **Probar la restauración** al menos una vez por trimestre: una copia que nunca se probó puede no servir.

### Correo

- [ ] Configurar SMTP con una cuenta institucional y **contraseña de aplicación** (ver ejemplo de Gmail en `.env.example`), no la contraseña personal de la cuenta.

### Mantenimiento

- [ ] Mantener el servidor actualizado (sistema operativo, PHP, MySQL, Nginx).
- [ ] Actualizar las dependencias del sistema periódicamente y revisar `composer audit` y `npm audit`.
- [ ] Revisar los registros en `storage/logs/` (en producción solo se registran advertencias y errores) y la pantalla *Auditoría*, en especial los intentos de ingreso fallidos y los bloqueos repetidos.
- [ ] Mantener en marcha el **worker de la cola** (`php artisan queue:work`, con supervisor o systemd) para los Excel grandes.
- [ ] Usar el modo mantenimiento para las actualizaciones: `php artisan down` y luego `php artisan up`.
- [ ] Limitar el acceso SSH al servidor (llaves en lugar de contraseñas, sin ingreso directo de `root`) y tener un cortafuegos que solo abra los puertos 22, 80 y 443.

### Cuentas y permisos

- [ ] Revisar cada cierto tiempo las cuentas activas y **desactivar** las del personal que ya no trabaja en el colegio.
- [ ] Mantener al menos **dos Administradores** activos, para no perder el acceso si uno no está disponible.
- [ ] Dar a cada persona solo los permisos que necesita para su trabajo.

### Ante un incidente

Si sospecha que una cuenta fue usada por otra persona: desactívela o restablezca su contraseña desde *Usuarios* (eso cierra sus sesiones), revise la *Auditoría* de esa cuenta y, si el servidor pudo verse comprometido, cambie `APP_KEY` y las contraseñas de la base de datos y del correo.
