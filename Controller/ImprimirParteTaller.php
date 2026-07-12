<?php
/**
 * This file is part of TallerSmartphones plugin for FacturaScripts
 */

namespace FacturaScripts\Plugins\TallerSmartphones\Controller;

use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\ServicioAT;

class ImprimirParteTaller extends Controller
{
    /** @var Agente */
    public $agente;

    /** @var Cliente */
    public $cliente;

    /** @var Empresa */
    public $empresa;

    /** @var ServicioAT */
    public $servicio;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'print-service-ticket';
        $data['icon'] = 'fa-solid fa-print';
        $data['showonmenu'] = false;
        return $data;
    }

    public function run(): void
    {
        parent::run();

        $this->servicio = new ServicioAT();
        $code = $this->request()->get('code');
        if (false === empty($code)) {
            $this->servicio->load($code);
        }

        $this->cliente = new Cliente();
        if (false === empty($this->servicio->codcliente)) {
            $this->cliente->load($this->servicio->codcliente);
        }

        $this->agente = new Agente();
        if (false === empty($this->servicio->codagente)) {
            $this->agente->load($this->servicio->codagente);
        }

        $this->empresa = Empresas::get($this->servicio->idempresa) ?? Empresas::default();

        $this->view('ImprimirParteTaller.html.twig');
    }
}
