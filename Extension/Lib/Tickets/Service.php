<?php
/**
 * This file is part of TallerSmartphones plugin for FacturaScripts
 */

namespace FacturaScripts\Plugins\TallerSmartphones\Extension\Lib\Tickets;

use Closure;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\TicketPrinter;

/**
 * Añade al ticket ESC/POS de un ServicioAT (impreso por el plugin Tickets)
 * los datos del dispositivo y el checklist de estado físico de
 * TallerSmartphones, cada uno activable por separado desde
 * Admin > Servicios > Imprimir ticket (ver Extension/XMLView/ConfigServicios.xml).
 *
 * Se engancha en 'setFooterBefore' porque Servicios\Lib\Tickets\Service no
 * expone ningún hook propio en su setBody(): setFooter() sí llama a
 * parent::setFooter(), que es quien dispara este pipe(). El resultado es que
 * esta sección se imprime justo antes del pie del ticket.
 *
 * @method sanitize(?string $txt)
 */
class Service
{
    public function setFooterBefore(): Closure
    {
        return function (ModelClass $model, TicketPrinter $printer) {
            if ($model->modelClassName() !== 'ServicioAT') {
                return;
            }

            if (Tools::settings('servicios', 'print_ticket_ts_device_data', true)) {
                $lines = [];

                if (!empty($model->ts_marca)) {
                    $lines[] = Tools::trans('ts-brand') . ': ' . $model->ts_marca;
                }
                if (!empty($model->ts_modelo)) {
                    $lines[] = Tools::trans('model') . ': ' . $model->ts_modelo;
                }
                if (!empty($model->ts_color)) {
                    $lines[] = Tools::trans('color') . ': ' . $model->ts_color;
                }
                if (!empty($model->ts_imei)) {
                    $lines[] = Tools::trans('ts-imei') . ': ' . $model->ts_imei;
                }
                if (!empty($model->ts_condicion_equipo)) {
                    $lines[] = Tools::trans('ts-device-condition') . ': ' . $model->ts_condicion_equipo;
                }

                if ($lines) {
                    static::$escpos->text("\n" . static::sanitize(Tools::trans('device-data')) . "\n");
                    foreach ($lines as $line) {
                        static::$escpos->text(static::sanitize($line) . "\n");
                    }
                }
            }

            if (Tools::settings('servicios', 'print_ticket_ts_checklist', false)) {
                $checklist = [
                    'ts-lcd-screen' => $model->ts_lcd,
                    'ts-touch-screen' => $model->ts_touch,
                    'ts-fingerprint-face' => $model->ts_huella_face,
                    'ts-cameras' => $model->ts_camaras,
                    'ts-wifi-bluetooth' => $model->ts_wifi_bluetooth,
                    'ts-speakers' => $model->ts_altavoces,
                    'ts-network' => $model->ts_red,
                    'ts-earpiece' => $model->ts_auricular,
                    'ts-back-cover' => $model->ts_tapa,
                ];

                $lines = [];
                foreach ($checklist as $label => $value) {
                    if (!empty($value)) {
                        $lines[] = Tools::trans($label) . ': ' . $value;
                    }
                }

                if ($lines) {
                    static::$escpos->text("\n" . static::sanitize(Tools::trans('device-checklist')) . "\n");
                    foreach ($lines as $line) {
                        static::$escpos->text(static::sanitize($line) . "\n");
                    }
                }
            }
        };
    }
}
