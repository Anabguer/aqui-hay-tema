<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\DomainEventDispatcher;
use AquiHayTema\Engine\DomainEvents;
use AquiHayTema\Engine\EmotionalStateService;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\EventBus;
use AquiHayTema\Engine\HistoriaPuebloEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\TutorialPrimerosPasos;
use AquiHayTema\Engine\VisualPackStore;

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

function hitoExiste(array $p, string $hitoId): bool
{
    foreach ($p['historia_pueblo'] ?? [] as $e) {
        if (($e['hito_id'] ?? '') === $hitoId) {
            return true;
        }
    }
    return false;
}

echo "=== VALIDACIÓN: Bypass paths emiten ESTADO_EMOCIONAL_CAMBIADO ===\n\n";

DomainBootstrap::resetForTests();
DomainBootstrap::boot();

$service = new PartidaService($root);

// --- EncuentroIntervencion::aplicarEmocion bypass ---
echo "--- Bypass: EncuentroIntervencion ---\n";

$p1 = $service->nuevaPartida('juego_v1', 'bypass-enc-' . microtime(true));
$p1['tutorial']['jugable_completado'] = true;
TutorialPrimerosPasos::marcarFinaleVisto($p1);
$ids1 = array_keys($p1['residentes'] ?? []);
$p1R = $ids1[0];
$p1['residentes'][$p1R]['presencia'] = 'residente';

ok(!hitoExiste($p1, 'hito_17'), 'B01. hito_17 no existe antes de bypass');

EstadoEmocional::ensureResidente($p1['residentes'][$p1R], $p1['reloj'] ?? null);
$antes = $p1['residentes'][$p1R]['runtime']['estado_emocional'];
$p1['residentes'][$p1R]['runtime']['estado_emocional'] = EstadoEmocional::estructura(
    EstadoEmocional::TRISTE, null, 'encuentro_intervencion',
    EstadoEmocional::marcaReloj($p1['reloj'] ?? null),
    EstadoEmocional::hastaDesdeDuracion($p1['reloj'] ?? [], 3),
    [], 3
);
$p1['residentes'][$p1R]['runtime']['animo'] = EstadoEmocional::TRISTE;
DomainEventDispatcher::emit($p1, DomainEvents::ESTADO_EMOCIONAL_CAMBIADO, [
    'residente_id' => $p1R,
    'antes' => $antes,
    'despues' => $p1['residentes'][$p1R]['runtime']['estado_emocional'],
    'actores' => [$p1R],
]);
ok(hitoExiste($p1, 'hito_17'), 'B02. hito_17 registrado tras bypass → triste');

// --- MensajitoConsejoEngine::microEmocion bypass ---
echo "\n--- Bypass: MensajitoConsejoEngine ---\n";

$p2 = $service->nuevaPartida('juego_v1', 'bypass-cons-' . microtime(true));
$p2['tutorial']['jugable_completado'] = true;
TutorialPrimerosPasos::marcarFinaleVisto($p2);
$ids2 = array_keys($p2['residentes'] ?? []);
$p2R = $ids2[0];
$p2['residentes'][$p2R]['presencia'] = 'residente';

ok(!hitoExiste($p2, 'hito_19'), 'B03. hito_19 no existe antes de bypass consejo');

$antes2 = $p2['residentes'][$p2R]['runtime']['estado_emocional'];
$p2['residentes'][$p2R]['runtime']['estado_emocional'] = EstadoEmocional::estructura(
    EstadoEmocional::ALEGRE, 1, 'consejo_celestine',
    EstadoEmocional::marcaReloj($p2['reloj'] ?? []),
    EstadoEmocional::hastaDesdeDuracion($p2['reloj'] ?? [], 12),
    ['fuente' => 'mensajito_consejo'], 12
);
$p2['residentes'][$p2R]['runtime']['animo'] = EstadoEmocional::ALEGRE;
DomainEventDispatcher::emit($p2, DomainEvents::ESTADO_EMOCIONAL_CAMBIADO, [
    'residente_id' => $p2R,
    'antes' => $antes2,
    'despues' => $p2['residentes'][$p2R]['runtime']['estado_emocional'],
    'actores' => [$p2R],
]);
ok(hitoExiste($p2, 'hito_19'), 'B04. hito_19 registrado tras bypass consejo → alegre');

// --- MensajitoContextualEngine cumple_felicidad bypass ---
echo "\n--- Bypass: MensajitoContextualEngine ---\n";

$p3 = $service->nuevaPartida('juego_v1', 'bypass-cumple-' . microtime(true));
$p3['tutorial']['jugable_completado'] = true;
TutorialPrimerosPasos::marcarFinaleVisto($p3);
$ids3 = array_keys($p3['residentes'] ?? []);
$p3R = $ids3[0];
$p3['residentes'][$p3R]['presencia'] = 'residente';

ok(!hitoExiste($p3, 'hito_19'), 'B05. hito_19 no existe antes de bypass cumple');

$antes3 = $p3['residentes'][$p3R]['runtime']['estado_emocional'];
$p3['residentes'][$p3R]['runtime']['estado_emocional'] = EstadoEmocional::estructura(
    EstadoEmocional::ALEGRE, 1, 'cumple_felicidad',
    EstadoEmocional::marcaReloj($p3['reloj'] ?? []),
    EstadoEmocional::hastaDesdeDuracion($p3['reloj'] ?? [], 12),
    ['fuente' => 'f10_cumpleanos'], 12
);
$p3['residentes'][$p3R]['runtime']['animo'] = EstadoEmocional::ALEGRE;
DomainEventDispatcher::emit($p3, DomainEvents::ESTADO_EMOCIONAL_CAMBIADO, [
    'residente_id' => $p3R,
    'antes' => $antes3,
    'despues' => $p3['residentes'][$p3R]['runtime']['estado_emocional'],
    'actores' => [$p3R],
]);
ok(hitoExiste($p3, 'hito_19'), 'B06. hito_19 registrado tras bypass cumple → alegre');

// --- Verificar celebraciones pendientes ---
echo "\n--- Celebraciones pendientes ---\n";

$pend1 = HistoriaPuebloEngine::celebracionesPendientes($p1, $root, $p1['meta']['partida_id'] ?? null);
ok(in_array('hito_17', array_column($pend1, 'hito_id'), true), 'B07. hito_17 en celebraciones pendientes');

$pend2 = HistoriaPuebloEngine::celebracionesPendientes($p2, $root, $p2['meta']['partida_id'] ?? null);
ok(in_array('hito_19', array_column($pend2, 'hito_id'), true), 'B08. hito_19 en celebraciones pendientes (consejo)');

$pend3 = HistoriaPuebloEngine::celebracionesPendientes($p3, $root, $p3['meta']['partida_id'] ?? null);
ok(in_array('hito_19', array_column($pend3, 'hito_id'), true), 'B09. hito_19 en celebraciones pendientes (cumple)');

// --- Verificar que ESTADO_EMOCIONAL_CAMBIADO tiene listener ---
echo "\n--- Verificar EventBus wiring ---\n";

$nEmo = EventBus::listenerCount(DomainEvents::ESTADO_EMOCIONAL_CAMBIADO);
ok($nEmo >= 1, "B10. ESTADO_EMOCIONAL_CAMBIADO tiene ≥1 listener [$nEmo]");

echo "\n" . ($failures === 0 ? 'TODOS LOS TESTS PASARON' : "$failures tests FALLARON") . "\n";
exit($failures > 0 ? 1 : 0);
