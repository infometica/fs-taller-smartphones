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

    /**
     * Patrón de desbloqueo guardado por el panel admin en `ts_codigo_desbloqueo` como
     * «Patrón 1-5-9-6[ · texto]» (puntos 1-9 de una rejilla 3×3, como un teclado). Devuelve las
     * coordenadas (columna, fila 0-2) de cada punto para dibujarlo, o null si no hay patrón.
     */
    public function patronDesbloqueo(): ?array
    {
        $valor = trim((string)$this->servicio->ts_codigo_desbloqueo);
        if (!preg_match('/^patr[oó]n\s*:?\s*([1-9](?:\s*-\s*[1-9])+)\s*(?:[·|,;]\s*)?(.*)$/iu', $valor, $m)) {
            return null;
        }
        $puntos = array_map('intval', preg_split('/\s*-\s*/', $m[1]));
        if (count(array_unique($puntos)) !== count($puntos)) {
            return null;
        }
        return [
            'secuencia' => implode('-', $puntos),
            'texto' => trim($m[2]),
            'puntos' => array_map(fn(int $p) => ['x' => ($p - 1) % 3, 'y' => intdiv($p - 1, 3)], $puntos),
        ];
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
