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

$diag = DisponibilidadEngine::diagnosticarBloqueos($partida, [$ida, $idb], 1, 10, 1);
ok(is_string($diag['resumen'] ?? null), 'diagnóstico tiene resumen técnico');
ok(str_contains($diag['resumen'], $ida), 'resumen técnico conserva ID');
ok(!str_contains($diag['resumen_ui'] ?? '', $ida), 'resumen_ui no muestra el ID técnico');
ok(str_contains($diag['resumen_ui'] ?? '', 'QA Valid'), 'resumen_ui usa nombre público');
ok(($diag['nombres'][$ida] ?? '') === 'QA Valid', 'mapa nombres incluye QA Valid');

$trabajo = $service->programarEncuentro($partida, [$ida, $idb], 1, 12, 'conocerse');
ok(!($trabajo['ok'] ?? true), '12h rechazado (trabajo)');
ok(($trabajo['residente_nombre'] ?? '') !== ($trabajo['residente'] ?? 'x'), 'rechazo expone nombre distinto del id');
ok(($trabajo['residente_nombre'] ?? '') === IdentidadPublica::nombre($partida, (string) ($trabajo['residente'] ?? '')), 'nombre de rechazo coincide con identidad pública');
ok(($trabajo['residente'] ?? '') === $ida || ($trabajo['residente'] ?? '') === $idb, 'rechazo conserva id técnico');

exit($failures > 0 ? 1 : 0);
