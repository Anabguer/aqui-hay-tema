<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\Voluntad\VoluntadEvaluator;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $condition, string $message): void
{
    global $failures;
    echo ($condition ? 'OK' : 'FAIL') . ": {$message}\n";
    if (!$condition) {
        $failures++;
    }
}

DomainBootstrap::boot();
$service = new PartidaService($root);
$partida = $service->nuevaPartida('playtest_01', 'mensajitos-integridad');

$antes = count($partida['buzon'] ?? []);
$vacio = BuzonEngine::crear($partida, [
    'id' => 'msg_vacio_no_persistir',
    'texto' => " \n ",
    'clasificacion' => BuzonEngine::IMPORTANTE,
]);
ok(($vacio['ok'] ?? true) === false, 'rechaza Mensajito sin texto');
ok(count($partida['buzon'] ?? []) === $antes, 'el Mensajito vacío no se persiste');

$partida['buzon'][] = [
    'id' => 'msg_legacy_vacio',
    'texto' => '',
    'estado' => 'pendiente',
    'canal' => BuzonEngine::CANAL_BUZON,
];
$conVacio = BuzonEngine::contarNoLeidos($partida);
array_pop($partida['buzon']);
ok($conVacio === BuzonEngine::contarNoLeidos($partida), 'legacy vacío no entra en el contador');

$rechazo = new class implements VoluntadEvaluator {
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        if ($residenteId !== 'per_p002') {
            return [
                'decision' => PropuestaEncuentro::DECISION_ACEPTA,
                'clase' => null,
                'motivo_tecnico' => 'test_acepta',
                'copy_id' => null,
                '_bloqueado_decision' => false,
            ];
        }
        return [
            'decision' => PropuestaEncuentro::DECISION_RECHAZA,
            'clase' => PropuestaEncuentro::CLASE_VOLUNTAD,
            'motivo_tecnico' => 'test_rechazo',
            'copy_id' => 'lavadora',
            '_bloqueado_decision' => false,
        ];
    }
};

$partida['residentes']['per_p002']['identidad_publica']['nombre'] = 'Óscar';
$resultado = PropuestaEncuentroEngine::proponer(
    $partida,
    ['per_p001', 'per_p002'],
    1,
    18,
    'conocerse',
    'lug_cafeteria',
    null,
    $rechazo
);
ok(!empty($resultado['rechazada']), 'el caso de prueba produce rechazo');

$respuesta = null;
foreach ($partida['buzon'] ?? [] as $mensaje) {
    if (is_array($mensaje) && ($mensaje['tipo'] ?? '') === 'respuesta_plan') {
        $respuesta = $mensaje;
        break;
    }
}
ok(is_array($respuesta), 'rechazo produce respuesta de Mensajitos');
if (is_array($respuesta)) {
    ok(($respuesta['de_persona'] ?? '') === 'per_p002', 'la respuesta conserva al residente que rechaza');
    ok(str_starts_with((string) ($respuesta['texto'] ?? ''), 'Óscar '), 'el remitente visible es Óscar');
    ok(str_contains((string) ($respuesta['texto'] ?? ''), 'Tengo que poner la lavadora.'), 'conserva el copy real del rechazo');
    ok(!str_contains((string) ($respuesta['texto'] ?? ''), 'tenían plan'), 'no genera copy Frankenstein');
    ok(trim((string) ($respuesta['texto'] ?? '')) !== '', 'respuesta tiene cuerpo');
}

exit($failures > 0 ? 1 : 0);
