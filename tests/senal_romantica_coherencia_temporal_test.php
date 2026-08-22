<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AccionRomantica;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\CopySenalRomantica;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\HayTemaVista;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\SenalRomantica;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

DomainBootstrap::boot();
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$store = $catalog->store();

$benito = 'per_ben';
$eduardo = 'per_edu';
$diana = 'per_dia';

$partida = [
    'reloj' => ['dia_pueblo' => 3, 'hora_actual' => 10],
    'buzon' => [],
    'encuentros' => [],
    'residentes' => [
        $benito => ['identidad_publica' => ['nombre' => 'Benito'], 'presencia' => 'residente', 'runtime' => []],
        $eduardo => ['identidad_publica' => ['nombre' => 'Eduardo'], 'presencia' => 'residente', 'runtime' => []],
        $diana => ['identidad_publica' => ['nombre' => 'Diana'], 'presencia' => 'residente', 'runtime' => []],
    ],
    'relaciones_romance' => [],
    'relaciones_sociales' => [],
    'features' => ['buzon_enabled' => true],
];

// Caso real A/B: señal a las 10:00, lectura a las 16:00 con Benito en el Cine con Eduardo.
RelacionEngine::registrarContacto($partida, $benito, $diana, 'normal', $cal);
RelacionEngine::registrarContacto($partida, $diana, $benito, 'normal', $cal);
RelacionEngine::setRomanceHacia($partida, $benito, $diana, SenalRomantica::umbralTilin($cal) + 2);

$senalMsg = null;
foreach ($partida['buzon'] ?? [] as $msg) {
    if (is_array($msg) && ($msg['tipo'] ?? '') === 'senal_romantica') {
        $senalMsg = $msg;
        break;
    }
}
ok(is_array($senalMsg), 'avisarSiAplica crea cotilleo de señal');
$ts = is_array($senalMsg['ts_juego'] ?? null) ? $senalMsg['ts_juego'] : [];
ok((int) ($ts['hora'] ?? -1) === 10, 'señal guarda hora de origen');

$partida['reloj']['hora_actual'] = 16;
$partida['encuentros'][] = [
    'id' => 'enc_ben_edu',
    'dia' => 3,
    'hora' => 16,
    'lugar' => 'lug_cine',
    'tipo' => 'quedar',
    'estado' => 'en_curso',
    'participantes' => [$benito, $eduardo],
    'duracion_horas' => 2,
];

$textoVista = CopySenalRomantica::textoDeMensaje($partida, $senalMsg);
ok(
    !str_contains($textoVista, 'lleva un rato demasiado pendiente'),
    'a las 16:00 no reutiliza copy presente de señal de las 10:00'
);
ok(
    str_contains($textoVista, 'hace un rato')
    || str_contains($textoVista, 'Desde hace un rato')
    || str_contains($textoVista, 'Por lo visto')
    || str_contains($textoVista, 'más temprano'),
    'copy histórico enmarca el aviso anterior'
);

$vista = HayTemaVista::resolver($partida, (string) ($senalMsg['id'] ?? ''));
ok(is_array($vista), 'HayTemaVista resuelve señal');
ok(
    (string) ($vista['texto'] ?? '') === $textoVista,
    'Aquí hay tema usa el mismo copy temporal que cotilleo'
);

// Caso C: flechazo autónomo incompatible mientras hay encuentro en curso.
$flechazo = AccionRomantica::ejecutar($partida, 'flechazo', $benito, $diana, $store, $cal, true);
ok(($flechazo['error'] ?? '') === 'ocupado_encuentro', 'flechazo bloqueado si Benito está en encuentro');
ok(EncuentroEngine::residenteOcupadoEnHorario($partida, $benito, 3, 16), 'agenda marca a Benito ocupado a las 16');

exit($failures > 0 ? 1 : 0);
