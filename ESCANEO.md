# Toma de inventario por escaneo

Guía rápida para escanear etiquetas en SIBIP y para probar el **modo cámara** del celular.

## Formas de escanear

| Modo | Qué se necesita | HTTPS |
|---|---|---|
| **Lectora USB o Bluetooth** (tipo teclado) | Configurada para enviar **Enter** al final de cada lectura (es lo habitual de fábrica). | No |
| **Cámara del celular o de la laptop** | Navegador moderno (Chrome, Edge, Safari). Solo lee **Code 128**. | **Sí** (o `localhost`) |

En la pantalla **Toma de inventario › Escanear**:

- El campo de lectura recupera el foco solo: basta con escanear una etiqueta tras otra.
- Con una lectora Bluetooth en el celular, marque **"Ocultar el teclado del celular"** para que el teclado en pantalla no tape el resultado.
- Elija el **área donde está contando** para detectar los bienes fuera de ubicación. En una campaña por área se usa siempre esa área.
- Colores y sonidos: verde = encontrado · ámbar = fuera de ubicación (indica dónde debería estar) · gris = ya escaneado · morado = figura dado de baja · rojo = no registrado.
- **Sin conexión:** las lecturas se guardan en el navegador (IndexedDB) y se sincronizan solas al volver la red. El indicador "pendientes de sincronizar" muestra cuántas faltan. No se puede cerrar la campaña con lecturas pendientes.

## Por qué la cámara exige HTTPS

Los navegadores solo permiten usar la cámara en un **contexto seguro**: sitios `https://` o `http://localhost`.
Si abre el sistema como `http://192.168.1.50:8000` desde el celular, la cámara no funcionará; la pantalla muestra un aviso en rojo.
En producción el sistema debe publicarse con HTTPS (Let's Encrypt; ver `DEPLOY.md`).

## Probar la cámara en local

### Opción A: la webcam de la laptop (sin configurar nada)

`localhost` cuenta como contexto seguro:

```bash
php artisan serve
```

Abra `http://localhost:8000` en la laptop, entre a una campaña, pulse **Cámara** y acerque una etiqueta impresa (o la pantalla del celular con un PNG de etiqueta).

### Opción B: el celular, con un túnel HTTPS de Cloudflare

1. Instale `cloudflared` en la laptop:
   - Windows: `winget install --id Cloudflare.cloudflared`
   - Ubuntu: `sudo apt install cloudflared` (o el `.deb` de https://github.com/cloudflare/cloudflared/releases)
2. Levante el sistema y el túnel en dos terminales:

   ```bash
   php artisan serve --port=8000
   cloudflared tunnel --url http://localhost:8000
   ```

3. `cloudflared` muestra una dirección como `https://palabras-al-azar.trycloudflare.com`. Ábrala en el celular: ya es HTTPS y la cámara funciona.
4. Mientras use el túnel, ponga esa dirección en `APP_URL` del `.env` y ejecute `php artisan config:clear`, para que los enlaces y formularios usen HTTPS.

> El túnel publica temporalmente su máquina en Internet. Úselo solo con datos de prueba y ciérrelo (Ctrl+C) al terminar.

Alternativa equivalente: `ngrok http 8000`.

### Consejos para leer con la cámara

- Ponga las barras **en horizontal**, dentro del recuadro, a 10–20 cm.
- Con poca luz, las etiquetas pequeñas (30×20 mm) cuestan más: use la lectora o mejore la iluminación.
- Si la cámara no arranca, revise que el navegador tenga permiso de cámara para el sitio.
