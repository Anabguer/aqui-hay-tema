<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
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

function diag(string $m): void
{
    echo "  DIAG: $m\n";
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

echo "=== TESTS DE INTEGRACIÓN: HISTORIA BRIDGES VIA EVENTBUS ===\n\n";

// ====================================================================
// === 1. Verificar que boot() registra los bridges en el EventBus  ===
// ====================================================================

DomainBootstrap::resetForTests();

$nAntes = EventBus::listenerCount(DomainEvents::ESTADO_EMOCIONAL_CAMBIADO);
ok($nAntes === 0, 'I01. sin listeners antes de boot()');

DomainBootstrap::boot();

$nDespues = EventBus::listenerCount(DomainEvents::ESTADO_EMOCIONAL_CAMBIADO);
ok($nDespues >= 1, "I02. al menos 1 listener tras boot() (triste=enf=alegre compartido) [" . $nDespues . ']');

$nEncuentro = EventBus::listenerCount(DomainEvents::ENCUENTRO_TERMINADO);
ok($nEncuentro >= 2, "I03. ENCUENTRO_TERMINADO tiene ≥2 listeners (EventosPuebloBridge + HistoriaEncuentroBridge + ...) [" . $nEncuentro . ']');

$nBuzon = EventBus::listenerCount(DomainEvents::BUZON_MENSAJE);
ok($nBuzon >= 2, "I04. BUZON_MENSAJE tiene ≥2 listeners (BuzonPlayBridge + HistoriaCotilleoBridge + HistoriaRegaloBridge) [" . $nBuzon . ']');

$nRelacion = EventBus::listenerCount(DomainEvents::RELACION_MODIFICADA);
ok($nRelacion >= 1, "I05. RELACION_MODIFICADA tiene ≥1 listener (HistoriaConfianzaBridge + HistoriaVinculoBridge) [" . $nRelacion . ']');

$nAutonomo = EventBus::listenerCount(DomainEvents::NPC_AUTONOMO_PLAN);
ok($nAutonomo >= 1, "I06. NPC_AUTONOMO_PLAN tiene ≥1 listener (HistoriaAutonomiaBridge) [" . $nAutonomo . ']');

$nMarcha = EventBus::listenerCount(DomainEvents::MARCHA_EFECTIVA);
ok($nMarcha >= 1, "I07. MARCHA_EFECTIVA tiene ≥1 listener (HistoriaMarchaBridge) [" . $nMarcha . ']');

$nSenal = EventBus::listenerCount(DomainEvents::SENAL_ROMANTICA);
ok($nSenal >= 1, "I08. SENAL_ROMANTICA tiene ≥1 listener (HistoriaInteresMutuoBridge) [" . $nSenal . ']');

// ====================================================================
// === 2. Idempotencia: boot() dos veces NO duplica listeners       ===
// ====================================================================

$nBefore = EventBus::listenerCount(DomainEvents::ESTADO_EMOCIONAL_CAMBIADO);
DomainBootstrap::boot();  // second call — should be no-op
$nAfter = EventBus::listenerCount(DomainEvents::ESTADO_EMOCIONAL_CAMBIADO);
ok($nBefore === $nAfter, "I09. boot() idempotente: listeners no se duplican ($nBefore → $nAfter)");

$nEncuentro2 = EventBus::listenerCount(DomainEvents::ENCUENTRO_TERMINADO);
ok($nEncuentro2 === $nEncuentro, "I10. ENCUENTRO_TERMINADO no se duplica ($nEncuentro → $nEncuentro2)");

// ====================================================================
// === 3. Integración real: ESTADO_EMOCIONAL_CAMBIADO → Historia    ===
// ====================================================================

$service = new PartidaService($root);
$p = $service->nuevaPartida('juego_v1', 'bridge-integ-' . microtime(true));
$p['tutorial']['jugable_completado'] = true;
TutorialPrimerosPasos::marcarFinaleVisto($p);

// Asegurar al menos 3 residentes para Historia
$ids = array_keys($p['residentes'] ?? []);
ok(count($ids) >= 3, 'I11. al menos 3 residentes');
$pRes = $ids[0];
$p['residentes'][$pRes]['presencia'] = 'residente';

// --- TRISTE ---
ok(!hitoExiste($p, 'hito_17'), 'I12. hito_17 no existe antes de aplicar triste');

$packs = new VisualPackStore($root);
$emo = new EmotionalStateService($packs, $service->getCatalog()->store());
$resultado = $emo->aplicar($p, $pRes, EstadoEmocional::TRISTE, 'test_integracion');

ok($resultado['ok'] === true, 'I13. EmotionalStateService::aplicar retorna ok');
ok(hitoExiste($p, 'hito_17'), 'I14. hito_17 registrado tras aplicar triste via EventBus');

// --- ENFADADO ---
$pRes2 = $ids[1] ?? $ids[0];
$p['residentes'][$pRes2]['presencia'] = 'residente';
ok(!hitoExiste($p, 'hito_18'), 'I15. hito_18 no existe antes de aplicar enfadado');

$emo->aplicar($p, $pRes2, EstadoEmocional::ENFADADO, 'test_integracion');
ok(hitoExiste($p, 'hito_18'), 'I16. hito_18 registrado tras aplicar enfadado via EventBus');

// --- ALEGRE ---
$pRes3 = $ids[2] ?? $ids[0];
$p['residentes'][$pRes3]['presencia'] = 'residente';
ok(!hitoExiste($p, 'hito_19'), 'I17. hito_19 no existe antes de aplicar alegre');

$emo->aplicar($p, $pRes3, EstadoEmocional::ALEGRE, 'test_integracion');
ok(hitoExiste($p, 'hito_19'), 'I18. hito_19 registrado tras aplicar alegre via EventBus');

// --- Idempotencia: segundo triste no duplica ---
$nH17 = 0;
foreach ($p['historia_pueblo'] as $e) {
    if (($e['hito_id'] ?? '') === 'hito_17') {
        $nH17++;
    }
}
$emo->aplicar($p, $pRes, EstadoEmocional::TRISTE, 'test_integracion');
$nH17After = 0;
foreach ($p['historia_pueblo'] as $e) {
    if (($e['hito_id'] ?? '') === 'hito_17') {
        $nH17After++;
    }
}
ok($nH17After === $nH17, "I19. hito_17 idempotente: $nH17 → $nH17After");

// ====================================================================
// === 4. celebracionesPendientes() retorna los hitos nuevos         ===
// ====================================================================

$pendientes = HistoriaPuebloEngine::celebracionesPendientes($p, $root, $p['meta']['partida_id'] ?? null);
$pendIds = array_column($pendientes, 'hito_id');
ok(in_array('hito_17', $pendIds, true), 'I20. hito_17 en celebraciones pendientes');
ok(in_array('hito_18', $pendIds, true), 'I21. hito_18 en celebraciones pendientes');
ok(in_array('hito_19', $pendIds, true), 'I22. hito_19 en celebraciones pendientes');

echo "\n" . ($failures === 0 ? 'TODOS LOS TESTS PASARON' : "$failures tests FALLARON") . "\n";
exit($failures > 0 ? 1 : 0);
