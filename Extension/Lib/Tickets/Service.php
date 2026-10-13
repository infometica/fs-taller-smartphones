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
 * el checklist de estado físico de TallerSmartphones (los datos del
 * dispositivo los imprime Lib/Tickets/ServicioTicket), activable desde
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
