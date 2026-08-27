<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaLifecycle;
use AquiHayTema\Engine\PartidaRepository;
use AquiHayTema\Engine\PartidaSchema;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\Reloj;

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

Reloj::fijarAhora(new \DateTimeImmutable('2026-08-17 08:30:00', Reloj::zona()));

$partida = PartidaSchema::nueva($root, 'debug_v0', 'hora-local-cli', [
    'fecha' => '2026-12-25',
    'hora' => 17,
]);
ok(($partida['reloj']['fecha_ancla'] ?? '') === '2026-12-25', 'creación usa fecha local del cliente');
ok((int) ($partida['reloj']['dia_pueblo'] ?? -1) === 1, 'creación fija día pueblo 1');
ok((int) ($partida['reloj']['hora_actual'] ?? -1) === Reloj::HORA_INICIO_PARTIDA, 'creación fija hora de juego 09:00 (ignora hora local del cliente)');
ok((int) ($partida['reloj']['minuto_actual'] ?? -1) === 0, 'granularidad horaria: minuto 0 al crear');
ok(Reloj::resolverHoraInicialCreacion(['fecha' => '2026-12-25', 'hora' => 17])['origen'] === 'cliente', 'origen cliente cuando dato válido');
ok((int) Reloj::resolverHoraInicialCreacion(['fecha' => '2026-12-25', 'hora' => 17])['hora'] === Reloj::HORA_INICIO_PARTIDA, 'resolverHoraInicialCreacion siempre devuelve hora de inicio canónica');

$fallback = Reloj::resolverHoraInicialCreacion(['fecha' => '2026-02-30', 'hora' => 17]);
ok($fallback['origen'] === 'fallback', 'fecha inválida → fallback');
ok($fallback['fecha'] === '2026-08-17' && (int) $fallback['hora'] === Reloj::HORA_INICIO_PARTIDA, 'fallback usa reloj fijado y hora canónica 09:00');

$fbHora = Reloj::resolverHoraInicialCreacion(['fecha' => '2026-12-25', 'hora' => 99]);
ok($fbHora['origen'] === 'fallback', 'hora fuera de rango → fallback');

$repo = new PartidaRepository($root);
$repo->guardar($partida);
$partidaId = $partida['meta']['partida_id'];

Reloj::fijarAhora(new \DateTimeImmutable('2026-12-26 10:00:00', Reloj::zona()));

$catalog = new \AquiHayTema\Engine\Catalog($root);
$logger = new \AquiHayTema\Engine\GameLogger($root);
$residentes = new \AquiHayTema\Engine\ResidenteOperations($catalog, $logger);
$lifecycle = new PartidaLifecycle($root, $catalog, $repo, $logger, $residentes);

$trasCargar = $lifecycle->cargar($partidaId);
ok((int) ($trasCargar['reloj']['hora_actual'] ?? -1) === Reloj::HORA_INICIO_PARTIDA, 'cargar conserva hora del pueblo');
ok(($trasCargar['reloj']['fecha_ancla'] ?? '') === '2026-12-25', 'cargar conserva fecha ancla');

$trasRefresh = $lifecycle->cargarParaRefresh($partidaId);
ok((int) ($trasRefresh['reloj']['hora_actual'] ?? -1) === Reloj::HORA_INICIO_PARTIDA, 'refresh conserva hora del pueblo');

$trasCargar['reloj']['ultima_sesion_iso'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
    ->sub(new \DateInterval('P2D'))
    ->format(DATE_ATOM);
$repo->guardar($trasCargar);
$trasCatchUp = $lifecycle->cargar($partidaId);
ok((int) ($trasCatchUp['reloj']['hora_actual'] ?? -1) === Reloj::HORA_INICIO_PARTIDA, 'catch-up al cargar no resincroniza hora del pueblo');
ok(isset($trasCatchUp['reloj']['catch_up_pendiente']['plan']), 'catch-up offline sigue planificando ausencia');

$otra = PartidaSchema::nueva($root, 'debug_v0', 'hora-local-otra', [
    'fecha' => '2026-01-01',
    'hora' => 22,
]);
ok((int) ($otra['reloj']['hora_actual'] ?? -1) === Reloj::HORA_INICIO_PARTIDA, 'nueva partida siempre nace a las 09:00 aunque el cliente envíe otra hora');

$svc = new PartidaService($root);
$nuevaJuego = $svc->nuevaPartida('juego_v1', 'inicio-canonical-' . time(), [
    'fecha' => '2026-06-15',
    'hora' => 21,
]);
ok((int) ($nuevaJuego['reloj']['dia_pueblo'] ?? -1) === 1, 'nuevaPartida juego_v1: día 1');
ok((int) ($nuevaJuego['reloj']['hora_actual'] ?? -1) === Reloj::HORA_INICIO_PARTIDA, 'nuevaPartida juego_v1: 09:00 tras bootstrap completo');
$idJuego = (string) ($nuevaJuego['meta']['partida_id'] ?? '');
$trasBootstrap = $svc->cargarParaRefresh($idJuego);
ok((int) ($trasBootstrap['reloj']['dia_pueblo'] ?? -1) === 1, 'refresh inmediato no cambia día pueblo');
ok((int) ($trasBootstrap['reloj']['hora_actual'] ?? -1) === Reloj::HORA_INICIO_PARTIDA, 'refresh inmediato no cambia hora de inicio');

Reloj::fijarAhora(null);

exit($failures > 0 ? 1 : 0);
