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

## Pendiente

- Réplica del PDF de impresión A5 a doble parte (cliente + taller con firma)
  que usa el taller actualmente en `movil-go.es` (formato ticket/parte de
  servicio). Aún no abordado.
