<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DisponibilidadEngine;
use AquiHayTema\Engine\IdentidadPublica;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'nombres-ui');
$ph = $service->crearResidentePlaceholderDev($partida);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$ida = 'per_qa_valid';
$idb = $ph['residente']['catalog_id'];

ok(IdentidadPublica::nombre($partida, $ida) === 'QA Valid', 'nombre público QA Valid');
ok(IdentidadPublica::nombre($partida, $idb) === 'Placeholder Dev 1', 'nombre público placeholder');
ok(IdentidadPublica::nombre($partida, 'per_inexistente') === 'per_inexistente', 'id como fallback si no hay nombre');

$service->programarEncuentro($partida, [$ida, $idb], 1, 14, 'conocerse');
$rSlots = DisponibilidadEngine::slotsCompatibles($partida, [$ida, $idb], 'conocerse', 1, 14, 1, 24, null, 'lug_cafeteria');
$diag = $rSlots['diagnostico'] ?? DisponibilidadEngine::diagnosticarBloqueos($partida, [$ida, $idb], 1, 14, 1);
ok(is_string($diag['resumen'] ?? null), 'diagnóstico tiene resumen técnico');
ok(str_contains($diag['resumen'] ?? '', 'encuentro') || str_contains($diag['resumen'] ?? '', $ida), 'resumen técnico con motivo o id');
ok(($diag['resumen_ui'] ?? '') === DisponibilidadEngine::COPY_SIN_HUECOS_PAREJA, 'resumen_ui usa copy jugador');
ok(!str_contains($diag['resumen_ui'] ?? '', $ida), 'resumen_ui no muestra el ID técnico');

$trabajo = $service->programarEncuentro($partida, [$ida, $idb], 1, 12, 'conocerse');
ok(!($trabajo['ok'] ?? true), '12h rechazado (trabajo)');
ok(($trabajo['residente_nombre'] ?? '') !== ($trabajo['residente'] ?? 'x'), 'rechazo expone nombre distinto del id');
ok(($trabajo['residente_nombre'] ?? '') === IdentidadPublica::nombre($partida, (string) ($trabajo['residente'] ?? '')), 'nombre de rechazo coincide con identidad pública');
ok(($trabajo['residente'] ?? '') === $ida || ($trabajo['residente'] ?? '') === $idb, 'rechazo conserva id técnico');

exit($failures > 0 ? 1 : 0);
