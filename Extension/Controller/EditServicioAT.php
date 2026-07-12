<?php
/**
 * This file is part of TallerSmartphones plugin for FacturaScripts
 */

namespace FacturaScripts\Plugins\TallerSmartphones\Extension\Controller;

use Closure;

/**
 * @method addButton(string $string, string[] $array)
 * @method getMainViewName()
 */
class EditServicioAT
{
    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName === $this->getMainViewName() && $view->model->exists()) {
                $this->addButton($viewName, [
                    'action' => 'ImprimirParteTaller?code=' . $view->model->id(),
                    'color' => 'info',
                    'icon' => 'fa-solid fa-print',
                    'label' => 'print-service-ticket',
                    'target' => '_blank',
                    'type' => 'link'
                ]);
            }
        };
    }
}
