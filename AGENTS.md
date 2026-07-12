## Qué es este plugin

Extensión de FacturaScripts que añade a `ServicioAT` (plugin **Servicios**) los
campos que necesita un taller de reparación de smartphones/tablets: datos del
dispositivo y checklist de recepción. No es un plugin independiente: sin
Servicios instalado no hace nada (`require = 'Servicios'` en `facturascripts.ini`).

## Historia / por qué está así

El plugin original (`Taller_Smartphones`, ver `git log` de commits anteriores
a "Reimplementar como extensión moderna...") era de la era FacturaScripts 2017:
sobreescribía por completo el `Servicios` de aquella época (modelo
`servicio_cliente`, tabla `servicioscli`, controladores/vistas propios en
`controller/`, `model/`, `view/`).

Esa base ya no existe. El `Servicios` actual es una reescritura total
(modelo `ServicioAT`, tabla `serviciosat`, arquitectura con namespaces,
`Extension/*`, `XMLView`). Por eso este repo no es un "port" línea a línea del
original: es una reimplementación mínima usando el mecanismo de extensión de
FacturaScripts, añadiendo solo los campos que Servicios no trae de fábrica.

## Decisiones a respetar

- **No usar `MaquinaAT`/`idmaquina`.** Se decidió explícitamente no modelar el
  móvil como una "máquina" persistente (ese concepto está pensado para activos
  fijos de un cliente con mantenimiento recurrente, no para un dispositivo que
  entra y sale del taller). Los datos del dispositivo van como campos propios
  en `ServicioAT`.
- **No duplicar campos que `ServicioAT` ya trae**: `codigo`, `descripcion`,
  `solucion`, `observaciones`, `telefono1`, `telefono2`, `codagente`/`nick`
  se reutilizan tal cual; no crear equivalentes con prefijo `ts_`.
- **Prefijo `ts_`** en todas las columnas/campos añadidos, siguiendo el mismo
  patrón que usa el plugin POS (`pos_`) para no colisionar con otras
  extensiones de `serviciosat`.
- Los campos del checklist (`ts_lcd`, `ts_touch`, etc.) son **texto libre**,
  no listas de estados fijos — así lo usa el taller en sus partes en papel.
- `ts_imei` es un solo campo (no `imei1`/`imei2` como en el original): el
  parte real del taller solo tiene un IMEI.
- `ts_codigo_desbloqueo` = código/patrón de desbloqueo del equipo, no un
  código de inventario.

## Estructura

```
facturascripts.ini
Extension/Table/serviciosat.xml      # columnas nuevas en la tabla compartida
Extension/Model/ServicioAT.php       # solo @property docblocks (sin lógica)
Extension/XMLView/EditServicioAT.xml # 2 grupos: ts-device-data, ts-device-checklist
Translation/es_ES.json
```

`Extension/Model/ServicioAT.php` no necesita propiedades PHP reales: el
`ModelClass` de FacturaScripts usa `__get`/`__set` mágicos respaldados por la
estructura real de la tabla, así que basta con que la columna exista en
`Extension/Table` para poder leerla/escribirla desde el modelo.

## Servidor de producción

- Instalado en `infometica.com` (acceso por `ssh infometica.com`), en
  `/var/www/facturascripts/Plugins/TallerSmartphones`.
- La tabla `serviciosat` es compartida con el plugin Servicios: cualquier
  cambio de esquema aquí afecta datos reales de producción. Hacer backup
  (`mysqldump ... serviciosat`) antes de tocar `Extension/Table`.
- No hay consola/CLI de FacturaScripts en este servidor. Para activar un
  plugin sin pasar por el panel web se puede invocar
  `FacturaScripts\Core\Plugins::enable('NombrePlugin')` desde un script PHP
  que bootstree igual que `index.php` (autoload + `config.php` + `Kernel::init()`).
  El `ALTER TABLE` real de las columnas nuevas no ocurre en el `enable()`,
  sino la primera vez que se instancia el modelo (`Dinamic\Model\ServicioAT`)
  tras el deploy — hay que forzarlo si se activa por script.

## Impresión del parte de servicio

- `Controller/ImprimirParteTaller.php` + `View/ImprimirParteTaller.html.twig`:
  página imprimible (no PDF generado por librería, igual que hacía el plugin
  original) con dos copias lado a lado — cliente (izquierda, sin checklist) y
  taller (derecha, con el checklist de estado físico y línea de firma del
  cliente). Extiende `Master/MicroTemplate.html.twig` (layout sin menú/sidebar
  de FacturaScripts) con CSS `@page { size: A5 landscape }` y
  `window.print()` automático al cargar.
- Botón "Imprimir parte" añadido a `EditServicioAT` vía
  `Extension/Controller/EditServicioAT.php` (`loadData`, botón tipo `link`
  a `ImprimirParteTaller?code=<id>`, siguiendo el mismo patrón que el botón
  "copy" del propio `Servicios`).
- FacturaScripts en este servidor usa `Cezpdf`/`Core/Lib/PDF` (coordenadas
  fijas) para las facturas, no HTML→PDF con Twig. Para un ticket con este
  layout es mucho más simple una vista imprimible por navegador (como hacía
  el plugin original) que generar el PDF a bajo nivel con esa librería.
- Para previsualizar el render sin sesión de navegador (no hay credenciales
  del panel disponibles para el agente): instanciar el controller directamente
  en un script PHP, poner `requiresAuth=false` por Reflection, capturar el
  `ob_start()`/`run()`, y volcar el HTML. Los assets estáticos
  (`/fs/node_modules/...`) son públicos sin login, así que añadiendo
  `<base href="https://infometica.com/">` se puede renderizar con un
  Chromium headless local para sacar una captura de verificación.

## Pendiente

- Nada bloqueante conocido. Revisar con el usuario si el diseño del parte
  (campos mostrados, orden, texto legal) necesita ajustes tras verlo en un
  caso real desde el panel.
