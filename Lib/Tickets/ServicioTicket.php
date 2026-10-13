<?php
/**
 * This file is part of TallerSmartphones plugin for FacturaScripts
 */

namespace FacturaScripts\Plugins\TallerSmartphones\Lib\Tickets;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Tickets\Service;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;

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
    /**
     * Igual que la cabecera de Tickets, pero con el logo de Infometica (Assets/ticket-logo.png,
     * 1 bit, 384 px) en lugar del nombre corto, y una línea en blanco entre el nombre completo
     * de la empresa y su dirección. Si el logo no se puede cargar, imprime el nombre corto.
     */
    protected static function setHeader(ModelClass $model, TicketPrinter $printer, string $title): void
    {
        $extensionVar = new static();
        $extensionVar->pipe('setHeaderBefore', $model, $printer);

        $company = $model->getCompany();

        static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
        if (false === static::printLogo()) {
            static::$escpos->setTextSize($printer->title_font_size, $printer->title_font_size);
            static::$escpos->text(static::sanitize($company->nombrecorto) . "\n");
        }

        static::$escpos->setTextSize($printer->head_font_size, $printer->head_font_size);
        static::$escpos->text(static::sanitize($company->nombre) . "\n\n");
        static::$escpos->setJustification();

        static::$escpos->text(static::sanitize($company->direccion) . "\n");
        static::$escpos->text(static::sanitize('CP: ' . $company->codpostal . ', ' . $company->ciudad) . "\n");
        static::$escpos->text(static::sanitize($company->tipoidfiscal . ': ' . $company->cifnif) . "\n\n");

        if ($printer->print_comp_tlf) {
            $phones = array_filter([$company->telefono1, $company->telefono2]);
            if ($phones) {
                static::$escpos->text(static::sanitize(implode(' / ', $phones)) . "\n");
            }
        }

        static::$escpos->text(static::sanitize($title) . "\n");
        static::setHeaderTPV($model, $printer);

        if ($printer->head) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            static::$escpos->text(static::sanitize($printer->head) . "\n\n");
            static::$escpos->setJustification();
        }

        $extensionVar->pipe('setHeaderAfter', $model, $printer);
    }

    protected static function printLogo(): bool
    {
        try {
            $file = dirname(__DIR__, 2) . '/Assets/ticket-logo.png';
            static::$escpos->bitImage(EscposImage::load($file, false));
            static::$escpos->feed();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected static function setBody(ModelClass $model, TicketPrinter $printer): void
    {
        $width = max(24, (int)$printer->linelen);
        $half = intdiv($width, 2);
        $third = intdiv($width, 3);
        $customer = $model->getCustomer();

        static::$escpos->selectCharacterTable(0);
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
                Tools::trans('ts-brand'),
                Tools::trans('model'),
                Tools::trans('color'),
            ], $cols));
            static::line(static::columns([(string)$model->ts_marca, (string)$model->ts_modelo, (string)$model->ts_color], $cols));
            if (!empty($model->ts_imei)) {
                static::line(Tools::trans('ts-imei') . ': ' . $model->ts_imei);
            }
        }

        // cliente
        static::line('');
        static::bold(Tools::trans('customer'));
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

    /**
     * El sanitize de Tickets quita todos los acentos para no depender de la página de
     * códigos de la impresora. Aquí conservamos los caracteres que existen en CP437
     * (la tabla que traen por defecto las impresoras térmicas: á é í ó ú ñ ü ¿ ¡ ...)
     * y la librería escpos los codifica sola; el resto (Á Í Ó Ú, símbolos...) se
     * degradan a ASCII como siempre.
     */
    protected static function sanitize(?string $txt): string
    {
        if ($txt === null || $txt === '') {
            return '';
        }

        if (!mb_check_encoding($txt, 'UTF-8')) {
            $txt = mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1');
        }

        $keep = 'ÇüéâäàåçêëèïîìÄÅÉæÆôöòûùÿÖÜáíóúñÑªº¿¡';
        $out = '';
        foreach (preg_split('//u', $txt, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $out .= (strlen($char) === 1 || mb_strpos($keep, $char) !== false) ? $char : parent::sanitize($char);
        }

        return $out;
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
        static::bold(static::sanitize($title));
        foreach (explode("\n", wordwrap(static::sanitize($text), $width, "\n", true)) as $line) {
            static::line($line);
        }
    }
}
