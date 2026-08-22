<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\PartidaService;
$s = new PartidaService(dirname(__DIR__));
$p = $s->nuevaPartida('juego_v1', 'estado-intro');
$e = $s->estadoResumido($p);
$tit = $e['tutorial']['intro']['pasos'][0]['tit'] ?? 'MISSING';
$id = $e['tutorial']['id'] ?? 'MISSING';
echo "id=$id tit=$tit\n";
exit($tit === 'Bienvenida al pueblo' ? 0 : 1);
