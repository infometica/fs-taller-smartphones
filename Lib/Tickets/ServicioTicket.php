<?php
/**
 * This file is part of TallerSmartphones plugin for FacturaScripts
 */

namespace FacturaScripts\Plugins\TallerSmartphones\Lib\Tickets;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Tickets\Service;
use FacturaScripts\Dinamic\Model\TicketPrinter;

/**
 * Ticket de servicio con el diseño "tres columnas" del taller:
 *
 *   ====
 *   Servicio      Fecha
 *   MARCA   MODELO   COLOR
 *   CLIENTE / CONDICION
 *   ----
 *   PROBLEMA / SOLUCION
 *   ====
 *
 * Sustituye solo el cuerpo (setBody) del ticket de Servicios; cabecera, pie,
 * máquinas y trabajos siguen siendo los de Servicios. Se registra en Init.php
 * en lugar del formato original, así que en SendTicket sigue habiendo un
 * único formato "service".
 */
class ServicioTicket extends Service
{
    protected static function setBody(ModelClass $model, TicketPrinter $printer): void
    {
        $width = max(24, (int)$printer->linelen);
        $half = intdiv($width, 2);
        $third = intdiv($width, 3);
        $customer = $model->getCustomer();

        static::$escpos->setTextSize($printer->font_size, $printer->font_size);
        static::line(str_repeat('=', $width));

        // servicio y fecha
        static::bold(static::columns([Tools::trans('service'), Tools::trans('date')], [$half, $half]));
        static::line(static::columns([(string)($model->codigo ?? $model->id()), trim($model->fecha . ' ' . $model->hora)], [$half, $half]));

        if (Tools::settings('servicios', 'print_ticket_agent', false)) {
            static::line(Tools::trans('agent') . ': ' . $model->getAgent()->nombre);
        }
        if (Tools::settings('servicios', 'print_ticket_assigned', false)) {
            static::line(Tools::trans('assigned') . ': ' . $model->getAsignado()->nick);
        }

        // marca, modelo y color en tres columnas
        if (Tools::settings('servicios', 'print_ticket_ts_device_data', true)) {
            $cols = [$third, $third, $width - 2 * $third];
            static::line('');
            static::bold(static::columns([
                mb_strtoupper(Tools::trans('ts-brand')),
                mb_strtoupper(Tools::trans('model')),
                mb_strtoupper(Tools::trans('color')),
            ], $cols));
            static::line(static::columns([(string)$model->ts_marca, (string)$model->ts_modelo, (string)$model->ts_color], $cols));
            if (!empty($model->ts_imei)) {
                static::line(Tools::trans('ts-imei') . ': ' . $model->ts_imei);
            }
        }

        // cliente
        static::line('');
        static::bold(mb_strtoupper(Tools::trans('customer')));
        static::line((string)$customer->razonsocial);
        $phones = array_filter([
            $model->telefono1 ?: $customer->telefono1,
            $model->telefono2 ?: $customer->telefono2,
        ]);
        if ($phones) {
            static::line(Tools::trans('phone') . ': ' . implode(' / ', $phones));
        }

        // condición del equipo
        if (!empty($model->ts_condicion_equipo) && Tools::settings('servicios', 'print_ticket_ts_device_data', true)) {
            static::line('');
            static::section(Tools::trans('ts-device-condition'), $model->ts_condicion_equipo, $width);
        }

        static::line('');
        static::line(str_repeat('-', $width));

        if ($model->descripcion && (bool)Tools::settings('servicios', 'print_ticket_description', false)) {
            static::section(Tools::trans('description'), $model->descripcion, $width);
            static::line('');
        }
        if ($model->material && (bool)Tools::settings('servicios', 'print_ticket_material', false)) {
            static::section(Tools::trans('material'), $model->material, $width);
            static::line('');
        }
        if ($model->solucion && (bool)Tools::settings('servicios', 'print_ticket_solution', false)) {
            static::section(Tools::trans('solution'), $model->solucion, $width);
            static::line('');
        }
        if ($model->observaciones && (bool)Tools::settings('servicios', 'print_ticket_observations', false)) {
            static::section(Tools::trans('observations'), $model->observaciones, $width);
            static::line('');
        }

        static::setMachines($model, $printer);
        static::setWorks($model, $printer);

        static::line(str_repeat('=', $width));
    }

    protected static function bold(string $text): void
    {
        static::$escpos->setEmphasis(true);
        static::line($text);
        static::$escpos->setEmphasis(false);
    }

    /** Une celdas rellenando/cortando cada una a su ancho (la última sin recortar de más). */
    protected static function columns(array $cells, array $widths): string
    {
        $out = '';
        foreach ($cells as $i => $cell) {
            $cell = static::sanitize((string)$cell);
            $w = $widths[$i];
            $out .= $i === array_key_last($cells) ? $cell : str_pad(mb_substr($cell, 0, $w - 1), $w);
        }
        return rtrim($out);
    }

    protected static function line(string $text): void
    {
        static::$escpos->text($text . "\n");
    }

    protected static function section(string $title, string $text, int $width): void
    {
        static::bold(mb_strtoupper(static::sanitize($title)));
        foreach (explode("\n", wordwrap(static::sanitize($text), $width, "\n", true)) as $line) {
            static::line($line);
        }
    }
}
