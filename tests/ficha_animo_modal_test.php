<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\EmocionalNarrativa;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\PartidaService;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

$root = dirname(__DIR__);
$svc = new PartidaService($root);
$partida = $svc->nuevaPartida('test_fixtures_v0', 'ficha-animo-modal-' . time());
$rid = array_key_first($partida['residentes'] ?? []);
ok(is_string($rid) && $rid !== '', 'partida con residente');

$cal = CalibracionConfig::load($root);
$estado = [
    'id' => EstadoEmocional::ENFADADO,
    'origen' => 'encuentro',
    'contexto' => ['resultado_experiencia' => 'mal', 'encuentro_id' => 'enc_test_missing'],
    'desde' => ['dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1)],
];
$modal = EmocionalNarrativa::vistaModalAnimo($partida, (string) $rid, $estado, $cal);
ok(is_array($modal), 'vistaModalAnimo devuelve payload');
ok(($modal['estado_id'] ?? '') === EstadoEmocional::ENFADADO, 'estado_id enfadado');
ok(($modal['texto_estado'] ?? '') !== '', 'texto_estado presente');
ok(($modal['explicacion'] ?? '') !== '', 'explicacion presente');
ok(is_array($modal['consecuencias'] ?? null) && count($modal['consecuencias']) > 0, 'consecuencias motor');

$partida['residentes'][$rid]['runtime']['estado_emocional'] = $estado;
$ficha = $svc->fichaResidente($partida, (string) $rid, true);
$vista = $ficha['vista_play'] ?? [];
ok(isset($vista['animo_explicacion']) && is_array($vista['animo_explicacion']), 'fichaResidente incluye animo_explicacion');

$partida['residentes'][$rid]['runtime']['estado_emocional'] = EstadoEmocional::estructura(
    EstadoEmocional::NEUTRO,
    null,
    'inicial',
    EstadoEmocional::marcaReloj($partida['reloj'] ?? null),
    null
);
$fichaNeutro = $svc->fichaResidente($partida, (string) $rid, true);
$vistaNeutro = $fichaNeutro['vista_play'] ?? [];
ok(!isset($vistaNeutro['animo_explicacion']), 'neutro sin animo_explicacion');

echo "ficha_animo_modal_test: todo OK\n";
