<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PresenciaEngine;
use AquiHayTema\Engine\VistaPuebloV3;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$p = (new PartidaService($root))->nuevaPartida('juego_v1', 'presencia_rutina');
$hora = (int) ($p['reloj']['hora_actual'] ?? 9);
foreach ($p['residentes'] as $rid => $res) {
    if (isset($p['residentes'][$rid]['runtime']['perfil_partida'])) {
        $prefsAbiertos = array_values(array_filter(
            ['lug_cafeteria', 'lug_gimnasio', 'lug_parque'],
            static fn(string $lug): bool => \AquiHayTema\Engine\ComplejoCatalog::estaAbierto($lug, $hora)
        ));
        if ($prefsAbiertos !== []) {
            $p['residentes'][$rid]['runtime']['perfil_partida']['lugares_preferentes'] = array_slice($prefsAbiertos, 0, 2);
        }
    }
}
$mapa = PresenciaEngine::resolver($p, $root);
$n = 0;
foreach ($mapa['lugares'] as $lug) {
    $n += count($lug['residentes_presentes'] ?? []);
}
assert($n >= 3, 'al menos 3 residentes visibles en mapa al inicio');
$vista = VistaPuebloV3::de($p, $mapa, $root);
$vis = 0;
foreach ($vista['complejos'] as $cx) {
    $vis += count($cx['visibles'] ?? []);
}
assert($vis >= 3, 'vista pueblo con tokens');
echo "presencia_rutina_test OK ($n presentes, $vis visibles)\n";
