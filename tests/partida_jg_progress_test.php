<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaJgProgressStorage;
use AquiHayTema\Engine\PartidaPersistenceConfig;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $cond, string $msg): void
{
    global $failures;
    echo ($cond ? 'OK' : 'FAIL') . ": $msg\n";
    if (!$cond) {
        $failures++;
    }
}

function makePartida(string $id, int $padBytes = 0): array
{
    $partida = [
        'meta' => [
            'partida_id' => $id,
            'schema_version' => 1,
            'updated_at' => gmdate('c'),
        ],
        'reloj' => ['dia_pueblo' => 1, 'hora' => 8],
        'residentes' => [],
    ];
    if ($padBytes > 0) {
        $partida['_pad'] = str_repeat('x', $padBytes);
    }
    return $partida;
}

function encodePartida(array $partida): string
{
    $json = json_encode($partida, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('json_encode_fallido');
    }
    return $json;
}

// A) payload >512 KiB y <8 MB aceptado
$bigPad = 600 * 1024;
$partidaBig = makePartida('aht_big_600k', $bigPad);
$jsonBig = encodePartida($partidaBig);
ok(strlen($jsonBig) > 524288, 'fixture >512 KiB');
ok(strlen($jsonBig) < PartidaPersistenceConfig::MAX_PAYLOAD_BYTES, 'fixture <8 MB');
try {
    PartidaPersistenceConfig::assertEncodedPayloadSize($jsonBig);
    ok(true, 'save >512 KiB aceptado por capa de tamaño');
} catch (Throwable $e) {
    ok(false, 'save >512 KiB aceptado: ' . $e->getMessage());
}

// B) payload >8 MB rechazado
$tooBigPad = PartidaPersistenceConfig::MAX_PAYLOAD_BYTES + 1024;
$jsonHuge = encodePartida(makePartida('aht_huge', $tooBigPad));
try {
    PartidaPersistenceConfig::assertEncodedPayloadSize($jsonHuge);
    ok(false, 'save >8 MB debe rechazarse');
} catch (RuntimeException $e) {
    ok(str_starts_with($e->getMessage(), 'save_demasiado_grande:'), 'save >8 MB rechazado limpiamente');
}

// C) serialización/deserialización conserva JSON
$partidaRt = makePartida('aht_roundtrip', 4096);
$partidaRt['celeste'] = ['nota' => 'única', 'emoji' => '🌙'];
$jsonRt = encodePartida($partidaRt);
$decoded = json_decode($jsonRt, true, 512, JSON_THROW_ON_ERROR);
ok($decoded['meta']['partida_id'] === 'aht_roundtrip', 'round-trip partida_id');
ok(($decoded['celeste']['nota'] ?? '') === 'única', 'round-trip unicode');
ok(($decoded['_pad'] ?? '') === $partidaRt['_pad'], 'round-trip payload pad');

// D) usuario A no accede al progreso de usuario B (autorización por partida_id)
$saveA = makePartida('user_a_partida');
try {
    PartidaPersistenceConfig::assertPartidaIdMatches($saveA, 'otro_id_distinto');
    ok(false, 'id distinto no debe autorizarse');
} catch (RuntimeException $e) {
    ok($e->getMessage() === 'partida_no_autorizada', 'partida_id distinto → no autorizada');
}
ok(PartidaPersistenceConfig::GAME_SLUG === 'aqui-hay-tema', 'game slug canónico');

// Integración SQL opcional (requiere PDO sqlite o MySQL de test)
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    PartidaJgProgressStorage::ensureSchema($pdo);
    $storage = new PartidaJgProgressStorage($root, $pdo);
    $storage->guardar(100, $saveA);
    try {
        $storage->cargar(200, 'user_a_partida');
        ok(false, 'usuario B no debe cargar partida de A (SQL)');
    } catch (RuntimeException $e) {
        ok($e->getMessage() === 'partida_no_encontrada', 'usuario B: partida_no_encontrada (SQL)');
    }
    $loadedSql = $storage->cargar(100, 'user_a_partida');
    ok($loadedSql['meta']['partida_id'] === 'user_a_partida', 'round-trip SQL usuario correcto');
} else {
    echo "SKIP: integración SQL (sin driver sqlite; verificar en producción con MySQL)\n";
}

echo "\n" . ($failures === 0 ? 'TODOS OK' : "FALLOS: $failures") . "\n";
exit($failures === 0 ? 0 : 1);
