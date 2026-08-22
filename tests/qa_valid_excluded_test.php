<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$cat = new Catalog($root);

if (in_array('per_qa_valid', $cat->listPersonajeIdsJugables(), true)) {
    fwrite(STDERR, 'FAIL: per_qa_valid en pool jugable\n');
    exit(1);
}
foreach (['per_i02', 'per_i03'] as $piloto) {
    if (in_array($piloto, $cat->listPersonajeIdsJugables(), true)) {
        fwrite(STDERR, "FAIL: $piloto en pool jugable\n");
        exit(1);
    }
}
if ($cat->loadPersonaje('per_qa_valid') === []) {
    fwrite(STDERR, 'FAIL: fichero per_qa_valid vacío\n');
    exit(1);
}
try {
    $loaded = $cat->loadPersonaje('per_qa_valid');
    if (($loaded['id'] ?? '') !== 'per_qa_valid') {
        fwrite(STDERR, 'FAIL: id per_qa_valid incorrecto\n');
        exit(1);
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'FAIL: loadPersonaje per_qa_valid: ' . $e->getMessage() . "\n");
    exit(1);
}

$svc = new PartidaService($root);
for ($i = 0; $i < 30; $i++) {
    $p = $svc->nuevaPartida('juego_v1', 'qa-excl-' . $i);
    if (isset($p['residentes']['per_qa_valid'])) {
        fwrite(STDERR, "FAIL: per_qa_valid en nueva partida seed qa-excl-$i\n");
        exit(1);
    }
    foreach (array_keys($p['residentes']) as $rid) {
        if ($rid === 'per_qa_valid' || $rid === 'per_i02' || $rid === 'per_i03') {
            fwrite(STDERR, "FAIL: $rid residente en seed qa-excl-$i\n");
            exit(1);
        }
    }
}

echo "qa_valid_excluded_test OK\n";
