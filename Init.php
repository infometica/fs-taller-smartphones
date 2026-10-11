<?php
/**
 * This file is part of TallerSmartphones plugin for FacturaScripts
 */

namespace FacturaScripts\Plugins\TallerSmartphones;

use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditServicioAT());
        $this->loadExtension(new Extension\Model\ServicioAT());
        $this->loadExtension(new Extension\Lib\Tickets\Service());
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
    }
}
