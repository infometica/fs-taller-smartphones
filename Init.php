<?php
/**
 * This file is part of TallerSmartphones plugin for FacturaScripts
 */

namespace FacturaScripts\Plugins\TallerSmartphones;

use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Dinamic\Controller\SendTicket;
use FacturaScripts\Dinamic\Lib\Tickets\Service;
use FacturaScripts\Dinamic\Lib\Tickets\ServicioTicket;

class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditServicioAT());
        $this->loadExtension(new Extension\Model\ServicioAT());
        $this->loadExtension(new Extension\Lib\Tickets\Service());

        if (Plugins::isEnabled('Tickets')) {
            $this->replaceTicketFormat();
        }
    }

    /**
     * SendTicket::addFormat() no permite sustituir un formato y un segundo formato
     * para ServicioAT aparecería duplicado en pantalla, así que cambiamos el
     * className del formato de Servicios por nuestro ServicioTicket (que lo extiende).
     */
    private function replaceTicketFormat(): void
    {
        try {
            // 'formats' es privada: vive en la clase del plugin Tickets, no en la de Dinamic
            $class = new \ReflectionClass(SendTicket::class);
            while ($class && false === $class->hasProperty('formats')) {
                $class = $class->getParentClass();
            }
            if (false === $class) {
                return;
            }

            $property = $class->getProperty('formats');
            $formats = $property->getValue();
            foreach ($formats['ServicioAT'] ?? [] as $i => $format) {
                if ($format['className'] === Service::class) {
                    $formats['ServicioAT'][$i]['className'] = ServicioTicket::class;
                }
            }
            $property->setValue(null, $formats);
        } catch (\Throwable $e) {
            // sin sustitución, el ticket sigue funcionando con el formato original de Servicios
        }
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
    }
}
