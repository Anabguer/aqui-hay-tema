<?php
declare(strict_types=1);

/*
 * Test: SeguimientoConsejoEngine — F9 para consejos de Celestine.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\ConsejoEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitosCadenciaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\SeguimientoConsejoEngine;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) { $failures++; }
}

DomainBootstrap::boot();
$cal = CalibracionConfig::load($root);
$svc = new PartidaService($root);

// --- 1) Registrar seguimiento ---
$p = $svc->nuevaPartida('juego_v1', 'seguimiento-test-' . time());
$p['reloj']['dia_pueblo'] = 1;
$p['reloj']['hora_actual'] = 12;
$p['features']['buzon_enabled'] = true;

SeguimientoConsejoEngine::registrar($p, 'per_p001', 'consejo_amor', 'romance');
ok(!empty($p['seguimientos_consejo_pendientes']), 'seguimiento registrado');
ok(count($p['seguimientos_consejo_pendientes']) === 1, 'un seguimiento pendiente');

// --- 2) No evaluar antes de tiempo ---
$antes = count($p['buzon'] ?? []);
$resultado = SeguimientoConsejoEngine::evaluarPendientes($p, $cal);
ok($resultado === [], 'sin dias suficientes: sin seguimiento');
ok(count($p['buzon'] ?? []) === $antes, 'no crea mensajito premature');

// --- 3) Avanzar dias y evaluar ---
$p['reloj']['dia_pueblo'] = 5;
$resultado2 = SeguimientoConsejoEngine::evaluarPendientes($p, $cal);
ok(count($resultado2) >= 1, 'tras 4 dias: seguimiento generado');
$mensajito = null;
foreach ($p['buzon'] ?? [] as $m) {
    if (($m['tipo'] ?? '') === 'seguimiento_consejo') {
        $mensajito = $m;
        break;
    }
}
ok($mensajito !== null, 'mensajito de seguimiento existe en buzon');
ok(($mensajito['de_persona'] ?? '') === 'per_p001', 'remitente correcto');
ok(trim((string) ($mensajito['texto'] ?? '')) !== '', 'tiene texto');

// --- 4) No re-evaluar el mismo seguimiento ---
$segPendAfter = $p['seguimientos_consejo_pendientes'][0] ?? [];
ok(($segPendAfter['resuelto'] ?? false) === true, 'marcado como resuelto');

// --- 5) ConsejoEngine registra seguimiento automáticamente ---
$p2 = $svc->nuevaPartida('juego_v1', 'consejo-auto-' . time());
$p2['reloj']['dia_pueblo'] = 1;
$p2['reloj']['hora_actual'] = 10;
$p2['features']['buzon_enabled'] = true;
ConsejoEngine::responder($p2, 'per_p001', 'lanzate', 'per_p002', 'amistad');
ok(!empty($p2['seguimientos_consejo_pendientes']), 'ConsejoEngine registra seguimiento');

// --- 6) Sin buzón_enabled: no registra seguimiento ---
$p3 = $svc->nuevaPartida('juego_v1', 'consejo-nobuzon-' . time());
$p3['reloj']['dia_pueblo'] = 1;
$p3['reloj']['hora_actual'] = 10;
unset($p3['features']['buzon_enabled']);
ConsejoEngine::responder($p3, 'per_p001', 'prueba', null, 'romance');
ok(empty($p3['seguimientos_consejo_pendientes'] ?? []), 'sin buzón: no registra seguimiento');

echo "\n";
echo $failures === 0 ? "OK mensajitos_seguimiento\n" : "FAIL mensajitos_seguimiento ({$failures})\n";
exit($failures > 0 ? 1 : 0);