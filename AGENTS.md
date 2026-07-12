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

## Cuidado: scripts puntuales siempre como www-data

El 2026-07-12 un script de verificación (`render_test.php`) se ejecutó como
`root` (`php script.php`) en vez de `sudo -u www-data php script.php`. Eso
hizo que FacturaScripts creara `MyFiles/Cache/Twig/` con propietario
`root:root`, y durante un rato **cualquier página real** que necesitara una
subcarpeta de caché nueva fallaba para `www-data` con
`Unable to create the cache directory`. Se detectó por los crash reports en
`MyFiles/crash_*.json` y se arregló con
`chown -R www-data:www-data /var/www/facturascripts/MyFiles/Cache`.

**Regla:** cualquier script PHP puntual en este servidor que toque
FacturaScripts (`Kernel::init()`, instanciar modelos, renderizar vistas, etc.)
debe ejecutarse siempre como `sudo -u www-data php ...`, nunca como root.
Si algo se ejecutó por error como root, comprobar
`find /var/www/facturascripts -not -user www-data` para detectar y corregir
archivos/carpetas con dueño incorrecto.

## Init.php es obligatorio para que las Extension/Controller funcionen

`Extension/Table` y `Extension/XMLView` se fusionan automáticamente por
`PluginsDeploy` (mecanismo por archivos). **`Extension/Controller` y
`Extension/Model` NO**: requieren que el plugin tenga un `Init.php`
(`class Init extends \FacturaScripts\Core\Template\InitClass`) cuyo
método `init()` llame explícitamente a
`$this->loadExtension(new Extension\Controller\EditServicioAT())` (ver
`Core/Template/InitClass::loadExtension()`). Sin esto, la clase de extensión
existe pero nunca se registra vía `addExtension()`, y sus métodos
(`createViews`, `loadData`, etc.) nunca se llaman — pasó exactamente esto:
el botón "Imprimir parte" no aparecía en ningún servicio hasta que se añadió
`Init.php` (commit `c714eee`).

Para comprobar si una extensión de controlador está realmente activa:
`\FacturaScripts\Dinamic\Controller\EditServicioAT::getExtensions()` debe
incluir el nombre del método (`loadData`, `createViews`, etc.).

## El export genérico (`action=export&option=PDF`) no es nuestra plantilla

Servicios registra su propio exportador para `ServicioAT`
(`ExportManager::addOptionModel('PDFserviciosExport', 'PDF', 'ServicioAT')`
en su `Init.php`), que dibuja el PDF a bajo nivel con `Cezpdf` y solo conoce
los campos que él mismo controla (`descripcion`, `material`, `solucion` según
los settings `print_pdf_*`). No sabe nada de nuestros campos `ts_*`, así que
nunca aparecerá el checklist ahí. Lo mismo aplica al botón **"Imprimir
ticket"** (plugin `PrintTicket`, ya buggeado de antes, no relacionado con
nosotros). El único sitio donde vive nuestro diseño de dos copias es el
botón **"Imprimir parte"** (`ImprimirParteTaller`).

## Pendiente

- Confirmar con el usuario, ya con `Init.php` desplegado, que el botón
  "Imprimir parte" aparece en `EditServicioAT` y que el resultado le
  convence (campos, orden, texto legal).
