<?php
declare(strict_types=1);

/** Validacion minima inventario.listar en produccion (partida lab, no Neni). */
$base = 'https://intocables13.com/juegos/aqui-hay-tema/api/index.php';
$partida = $argv[1] ?? 'part_d88e5094c565e1db';

function get(string $url): array
{
    $ctx = stream_context_create(['http' => ['timeout' => 60, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
        $code = (int) $m[0];
    }
    $json = is_string($raw) ? json_decode($raw, true) : null;
    return ['code' => $code, 'json' => $json, 'raw' => $raw];
}

$inv = get($base . '?action=inventario.listar&partida_id=' . rawurlencode($partida));
$ok = $inv['code'] === 200
    && is_array($inv['json'])
    && ($inv['json']['ok'] ?? false) === true
    && is_array($inv['json']['inventario'] ?? null);
echo ($ok ? 'OK' : 'FAIL') . ': inventario.listar prod HTTP ' . $inv['code'] . PHP_EOL;
if (!$ok) {
    echo substr((string) ($inv['raw'] ?? ''), 0, 300) . PHP_EOL;
    exit(1);
}

$ref = get($base . '?action=partida.refresh&partida_id=' . rawurlencode($partida));
$okRef = $ref['code'] === 200 && is_array($ref['json']) && ($ref['json']['ok'] ?? false) === true;
echo ($okRef ? 'OK' : 'FAIL') . ': partida.refresh prod HTTP ' . $ref['code'] . PHP_EOL;
exit($okRef ? 0 : 1);
