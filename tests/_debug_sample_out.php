<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\LabAudit;
use AquiHayTema\Engine\PartidaService;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$p = (new PartidaService($root))->nuevaPartida('juego_v1', 'debug_sample_out');
LabAudit::reset();
LabAudit::eventoNuevaPartida($p, new Catalog($root));
foreach (LabAudit::flush() as $e) {
    echo $e['prefijo'] . ' ' . $e['tag'] . PHP_EOL;
    echo json_encode($e['datos'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL . "---\n";
}
