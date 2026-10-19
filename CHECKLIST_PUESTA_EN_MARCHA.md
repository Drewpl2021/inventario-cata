# Puesta en marcha de SIBIP · Lista de pasos

Esta lista es para el personal del colegio que empezará a usar el sistema. Vaya en orden y marque cada paso al terminarlo. Necesita la dirección del sistema (por ejemplo `https://inventario.cata.edu.pe`) y el correo de administrador que le dio la persona que lo instaló.

> Antes de empezar, quien instaló el sistema debe haber ejecutado `php artisan sibip:verificar-produccion` con todo en verde (vea DEPLOY.md).

---

## 1. Administrador

- [ ] Entre al sistema con el **correo de administrador** y la **contraseña temporal** que le entregaron.
- [ ] El sistema le pedirá **cambiar la contraseña**. Elija una nueva con al menos 8 caracteres, letras y números, que no use en otros sitios. Guárdela en un lugar seguro.
- [ ] Vaya a **Usuarios → Nuevo usuario** y cree una **segunda cuenta de Administrador** para otra persona de confianza. Así nunca se pierde el acceso si alguien no está.
  - El sistema muestra una **contraseña temporal una sola vez**: anótela y entréguela en persona. Al entrar, esa persona deberá cambiarla.
- [ ] Pruebe **"¿Olvidaste tu contraseña?"** en la pantalla de ingreso con su correo y confirme que llega el mensaje (revise también el correo no deseado). Si no llega, avise a quien instaló el sistema: falta configurar el correo.

## 2. Datos del colegio

Cargue los datos en este orden, porque cada uno usa el anterior. Todo está en el menú **Configuración** de la barra lateral.

- [ ] **Sedes:** las sedes reales (nombre y código corto, por ejemplo `S01`). El código forma parte de cada etiqueta (`CAT-S01-000123`), así que elíjalo bien desde el inicio.
- [ ] **Áreas:** los ambientes de cada sede (aulas, laboratorios, dirección, almacén…), cada una en su sede.
- [ ] **Categorías:** los tipos de bienes (cómputo, mobiliario, equipos de laboratorio…).
- [ ] **Responsables:** las personas a cargo de los bienes, con su DNI, cargo, sede y área.
- [ ] **Usuarios:** cree las cuentas del personal según su función:
  - *Operador:* registra bienes, imprime etiquetas y hace las tomas de inventario.
  - *Auditor:* solo consulta y descarga reportes.
  - *Usuario:* responsables de bienes; ven solo lo que tienen a su cargo y hacen solicitudes (bajas, traslados, requerimientos).

## 3. Etiquetas e impresora

- [ ] Conecte la **impresora de etiquetas** a la PC y cargue el rollo de etiquetas.
- [ ] Vaya a **Configuración → Formato de etiquetas**. Elija el tamaño de su etiqueta (por ejemplo 50 × 25 mm) y la resolución de su impresora (203 o 300 dpi, viene en la caja o en el manual). Guarde.
- [ ] En la misma pantalla, en **Página de prueba**, pulse **Imprimir**. En el diálogo de impresión elija su impresora de etiquetas, márgenes **Ninguno**, escala **100 %** y sin encabezados ni pies de página.
- [ ] Revise que la etiqueta salga **completa y centrada**, con las barras nítidas. Si sale cortada o corrida, ajuste el tamaño en la configuración y vuelva a imprimir la prueba.

## 4. Lectora de códigos

- [ ] Conecte la **lectora USB** (o empareje la Bluetooth). Funciona como un teclado: no necesita instalar nada.
- [ ] Abra el Bloc de notas y **escanee la página de prueba**: debe aparecer el código completo, sin letras de más ni de menos. Si salen caracteres raros, revise que la distribución del teclado de la PC sea la misma que la de la lectora (consulte el manual de la lectora).

## 5. Primeros bienes

- [ ] Vaya a **Bienes → Nuevo bien** y registre **2 o 3 bienes reales**: nombre, categoría, sede, área, responsable, estado y, si tiene, la foto y el comprobante (PDF o foto).
- [ ] Al guardar, el sistema le asigna su código (`CAT-S01-…`) y abre su ficha. Pulse el botón **Etiqueta** (imprime en la impresora de etiquetas) y péguela en el bien, en un lugar visible y plano.
- [ ] Abra la ficha del bien y compruebe que se vean la foto, el comprobante y el historial.

## 6. Toma de inventario de prueba

- [ ] Vaya a **Toma de inventario → Nueva campaña**, elija la sede (y el área, si quiere contar solo una) y créela.
- [ ] En la pantalla de escaneo, con la lectora, **escanee las etiquetas** de los bienes que registró. Cada lectura debe salir en verde (*encontrado*), con sonido.
- [ ] Escanee también una etiqueta de **otra área** y la **página de prueba**, para ver cómo avisa el sistema cuando algo está fuera de lugar o no está registrado.
- [ ] Use **Deshacer** en una lectura para practicar cómo corregir un error.
- [ ] **Cierre la campaña** y revise el resultado: los bienes encontrados, los faltantes (los que no escaneó) y los sobrantes. Descargue el **PDF** del resultado.
- [ ] Si va a escanear con la **cámara del celular**, compruebe que el sistema se abra con `https://`. Sin eso, el celular no permite usar la cámara (la lectora sí funciona siempre).

## 7. Antes de usarlo "en serio"

- [ ] Confirme con quien instaló el sistema que el **respaldo diario** funciona y que las copias se guardan **fuera del servidor** (DEPLOY.md, sección Respaldos).
- [ ] Revise en **Auditoría** que aparezcan sus ingresos y los bienes que creó.
- [ ] Explique al personal: no compartir contraseñas, cerrar sesión en PCs compartidas, y que la sesión se cierra sola tras **2 horas sin actividad**.
- [ ] Si registró bienes solo de prueba que no existen, elimínelos desde el listado de **Bienes** con el ícono de la papelera (solo se puede mientras no tengan movimientos ni bajas). Los demás datos de prueba que sí correspondan a la realidad pueden quedarse.

Si algo no funciona como se describe aquí, anote qué estaba haciendo y en qué pantalla, y avise a quien administra el sistema.
