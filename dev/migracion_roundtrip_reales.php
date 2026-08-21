<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaRepository;
use AquiHayTema\Engine\SchemaMigrator;

$root = dirname(__DIR__);
$repo = new PartidaRepository($root);
$dir = $root . '/data/partidas';
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$files = glob($dir . '/*.json') ?: [];
$tested = 0;
foreach ($files as $path) {
    if ($tested >= 3) {
        break;
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        continue;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['meta']['partida_id'])) {
        continue;
    }
    $id = (string) $data['meta']['partida_id'];
    $beforeVer = (int) ($data['meta']['schema_version'] ?? 1);
    $beforeSlots = isset($data['viviendas']['slots']) ? count($data['viviendas']['slots']) : null;
    $migrated = SchemaMigrator::migrate($data);
    $afterVer = (int) ($migrated['meta']['schema_version'] ?? 0);
    ok($afterVer === SchemaMigrator::CURRENT_VERSION, "$id migra a v3");
    CapacidadViviendas::ensure($migrated);
    ok(count($migrated['viviendas']['slots'] ?? []) === 24, "$id pool 24 tras migrate");
    $encoded = json_encode($migrated);
    $reloaded = json_decode($encoded, true);
    $round = SchemaMigrator::migrate($reloaded);
    ok(json_encode($round) === $encoded, "$id round-trip estable");
    $tested++;
}

if ($tested < 3) {
    echo "INFO: solo $tested saves utilizables en data/partidas (se pidieron 3)\n";
} else {
    ok(true, "validados $tested saves reales sin modificar originales");
}

echo $failures === 0 ? "OK migracion_roundtrip_reales\n" : "FAIL migracion_roundtrip_reales ($failures)\n";
exit($failures > 0 ? 1 : 0);
