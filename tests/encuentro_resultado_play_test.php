<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DiscoveryEngine;
use AquiHayTema\Engine\DiscoveryVisibilityPolicy;
use AquiHayTema\Engine\EncuentroResultadoVista;
use AquiHayTema\Engine\PartidaService;

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

function setup(): array
{
    global $root;
    $service = new PartidaService($root);
    $partida = $service->nuevaPartida('test_fixtures_v0', 'resultado-play');
    $ph = $service->crearResidentePlaceholderDev($partida);
    return [$service, $partida, 'per_qa_valid', $ph['residente']['catalog_id']];
}

function terminarTrasProgramar(PartidaService $service, array &$partida, array $enc): array
{
    $dia = (int) $enc['encuentro']['dia'];
    $hora = (int) $enc['encuentro']['hora'];
    $now = ((int) $partida['reloj']['dia_pueblo']) * 24 + (int) $partida['reloj']['hora_actual'];
    $dur = max(1, (int) ($enc['encuentro']['duracion_horas'] ?? 1));
    $end = $dia * 24 + $hora + $dur;
    $horas = max(1, $end - $now);
    return $service->avanzarRelojPasoAPaso($partida, $horas);
}

function encTerminado(array $partida, string $id): ?array
{
    foreach ($partida['encuentros'] ?? [] as $e) {
        if (($e['id'] ?? '') === $id) {
            return $e;
        }
    }
    return null;
}

function patchResultado(array &$partida, string $id, array $patch): void
{
    foreach ($partida['encuentros'] as &$e) {
        if (($e['id'] ?? '') !== $id) {
            continue;
        }
        $e['resultado'] = array_merge(is_array($e['resultado'] ?? null) ? $e['resultado'] : [], $patch);
        return;
    }
    unset($e);
}

function jsonVista(array $vista): string
{
    return (string) json_encode($vista, JSON_UNESCAPED_UNICODE);
}

[$service, $partida, $ida, $idb] = setup();
$enc = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
ok($enc['ok'] ?? false, 'programa encuentro');
$adv = terminarTrasProgramar($service, $partida, $enc);
ok(($adv['ok'] ?? false) === true, 'avance termina el encuentro');
$raw = encTerminado($partida, (string) $enc['encuentro']['id']);
ok(($raw['estado'] ?? '') === 'terminado', 'estado terminado');
$vista = EncuentroResultadoVista::de($partida, $raw, $service->getCatalog(), $root);
ok(($vista['participantes_nombres'][0] ?? '') === 'QA Valid' || in_array('QA Valid', $vista['participantes_nombres'] ?? [], true), 'nombres públicos');
ok(!in_array($ida, $vista['participantes_nombres'] ?? [], true), 'no muestra id técnico como nombre');
ok(is_int($vista['resultado']['social']['delta'] ?? null), 'delta social real (entero)');
ok(($vista['resultado']['romance']['delta'] ?? null) === 0, 'romance independiente en 0 (no romántico)');
ok(($vista['resultado']['conflicto']['hay'] ?? true) === false, 'conocerse sin conflicto');
ok(!isset($vista['resultado']['delta_social']), 'DTO no expone delta_social crudo');
$js = jsonVista($vista);
ok(!str_contains($js, 'rng'), 'sin rng');
ok(!str_contains($js, 'dealbreaker'), 'sin dealbreakers');
ok(!str_contains($js, '"_placeholder"'), 'sin flags internos placeholder');

// Romance independiente
[$service, $partida, $ida, $idb] = setup();
$encR = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'romantico', 'lug_cafeteria');
terminarTrasProgramar($service, $partida, $encR);
$rawR = encTerminado($partida, (string) $encR['encuentro']['id']);
patchResultado($partida, (string) $encR['encuentro']['id'], [
    'delta_social' => ['intensidad' => -1, 'tipo' => 'conocidos'],
    'delta_romance' => ['aplicado' => true, 'vinculo' => 1],
    'conflicto' => null,
]);
$rawR = encTerminado($partida, (string) $encR['encuentro']['id']);
$vistaR = EncuentroResultadoVista::de($partida, $rawR, $service->getCatalog(), $root);
ok(($vistaR['resultado']['social']['delta'] ?? 0) === -1, 'social -1 independiente');
ok(($vistaR['resultado']['romance']['delta'] ?? 0) === 1, 'romance +1 independiente');
ok(str_contains($vistaR['resultado']['social']['texto'] ?? '', 'llevado peor'), 'copy social en lenguaje natural');
ok(str_contains($vistaR['resultado']['romance']['texto'] ?? '', 'romántico'), 'copy romance en lenguaje natural');
ok(($vistaR['resultado']['social']['delta'] ?? 0) === -1, 'delta social sigue en datos');
ok(!str_contains($vistaR['resultado']['social']['texto'] ?? '', 'reales'), 'copy sin “reales”');
ok(!str_contains(implode(' ', $vistaR['resultado']['lineas'] ?? []), 'Romance: 0'), 'lineas no incluyen romance 0');

// Ambos 0
[$service, $partida, $ida, $idb] = setup();
$enc0 = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
terminarTrasProgramar($service, $partida, $enc0);
patchResultado($partida, (string) $enc0['encuentro']['id'], [
    'delta_social' => ['intensidad' => 0, 'tipo' => 'conocidos'],
    'delta_romance' => ['aplicado' => true, 'vinculo' => 0, 'atraccion_a_hacia_b' => 0, 'atraccion_b_hacia_a' => 0],
]);
$vista0 = EncuentroResultadoVista::de($partida, encTerminado($partida, (string) $enc0['encuentro']['id']), $service->getCatalog(), $root);
ok(($vista0['resultado']['social']['delta'] ?? -99) === 0, 'ambos 0: social');
ok(($vista0['resultado']['romance']['delta'] ?? -99) === 0, 'ambos 0: romance');

// Conflicto
[$service, $partida, $ida, $idb] = setup();
$encC = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conflicto', 'lug_cafeteria');
ok($encC['ok'] ?? false, 'programa tipo conflicto');
terminarTrasProgramar($service, $partida, $encC);
$vistaC = EncuentroResultadoVista::de($partida, encTerminado($partida, (string) $encC['encuentro']['id']), $service->getCatalog(), $root);
ok(($vistaC['resultado']['conflicto']['hay'] ?? false) === true, 'conflicto/roce separado');
ok(($vistaC['resultado']['social']['tipo'] ?? '') === 'roce' || ($vistaC['resultado']['conflicto']['hay'] ?? false), 'social no se vende como compatibilidad única');

// Discovery visible vs oculto
[$service, $partida, $ida, $idb] = setup();
$encD = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
terminarTrasProgramar($service, $partida, $encD);
DiscoveryEngine::registrar($partida, $ida, 'vida.hobby_principal', 'pasear', 'encuentro', $encD['encuentro']['id']);
$configDisc = [
    'default' => DiscoveryVisibilityPolicy::SIN_POLITICA,
    'por_categoria' => [
        'vida.hobby_principal' => DiscoveryVisibilityPolicy::OCULTO,
        'vida.rasgos_ocultos' => DiscoveryVisibilityPolicy::OCULTO,
    ],
];
patchResultado($partida, (string) $encD['encuentro']['id'], [
    'descubrimientos' => [
        ['residente' => $ida, 'campo' => 'vida.hobby_principal', 'valor' => 'pasear'],
        ['residente' => $ida, 'campo' => 'vida.rasgos_ocultos', 'valor' => 'ansioso'],
    ],
]);
$vistaD = EncuentroResultadoVista::de(
    $partida,
    encTerminado($partida, (string) $encD['encuentro']['id']),
    $service->getCatalog(),
    $root,
    $configDisc
);
$camposVis = array_column($vistaD['resultado']['descubrimientos'] ?? [], 'campo');
ok(in_array('vida.hobby_principal', $camposVis, true), 'discovery visible se muestra');
ok(!in_array('vida.rasgos_ocultos', $camposVis, true), 'discovery oculto no se filtra al jugador');
$jsD = jsonVista($vistaD);
ok(!str_contains($jsD, 'ansioso'), 'secreto oculto no aparece en el DTO');
ok(str_contains(implode(' ', array_column($vistaD['resultado']['descubrimientos'], 'texto')), 'pasear'), 'texto funcional del hobby');

// Persistencia save/load
[$service, $partida, $ida, $idb] = setup();
$encP = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
terminarTrasProgramar($service, $partida, $encP);
$idP = (string) $encP['encuentro']['id'];
$partidaId = $partida['meta']['partida_id'];
$service->guardar($partida);
$cargada = $service->cargar($partidaId);
$rawP = encTerminado($cargada, $idP);
ok(($rawP['estado'] ?? '') === 'terminado', 'save/load: sigue terminado');
ok(isset($rawP['resultado']['delta_social']), 'save/load: resultado persistido');
$vistaP = EncuentroResultadoVista::de($cargada, $rawP, $service->getCatalog(), $root);
ok(is_int($vistaP['resultado']['social']['delta'] ?? null), 'save/load: vista coherente');

// Varios en un salto
[$service, $partida, $ida, $idb] = setup();
$a = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
ok($a['ok'] ?? false, 'primero 19h');
$partida['celeste']['lugares_desbloqueados'][] = 'lug_parque';
$bEnc = $service->programarEncuentro($partida, [$ida, $idb], 1, 21, 'amistad', 'lug_parque');
ok($bEnc['ok'] ?? false, 'segundo 21h');
$adv2 = $service->avanzarRelojPasoAPaso($partida, 14);
ok(($adv2['encuentros_resueltos'] ?? 0) >= 2, 'salto resuelve varios');
ok(($adv2['resumen_avance']['encuentros_terminados_count'] ?? 0) >= 2, 'resumen cuenta varios terminados');
ok(count($adv2['resumen_avance']['encuentros_terminados'] ?? []) >= 2, 'resumen trae cada vista');
$idsVista = array_column($adv2['resumen_avance']['encuentros_terminados'] ?? [], 'id');
ok(in_array($a['encuentro']['id'], $idsVista, true) && in_array($bEnc['encuentro']['id'], $idsVista, true), 'ambos ids en el resumen');

$ficha = $service->fichaResidente($partida, $ida);
ok(isset($ficha['ultimo_encuentro_vista']['resultado']['lineas']), 'ficha expone vista de último encuentro');
ok(is_array($ficha['ultimo_encuentro_vista']['resultado']['social'] ?? null), 'ficha separa social');

exit($failures > 0 ? 1 : 0);
