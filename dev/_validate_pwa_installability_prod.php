<?php declare(strict_types=1);

/**
 * Auditoría HTTP de installability PWA en producción (focal).
 * Uso: php dev/_validate_pwa_installability_prod.php
 */

$base = 'https://intocables13.com/juegos/aqui-hay-tema';
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function httpGet(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => true,
    ]);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [
        'status' => $status,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
    ];
}

$play = httpGet("$base/play.php");
ok($play['status'] === 200, 'play.php HTTP 200');
ok(strpos($play['body'], 'rel="manifest"') !== false, 'play.php enlaza manifest');
ok(strpos($play['body'], 'manifest.webmanifest') !== false, 'play.php href manifest');
ok(strpos($play['body'], 'sw.js?v=') === false, 'play.php SW sin query string');
ok(preg_match('/serviceWorker\.register\([^)]*sw\.js[^)]*scope:\s*[\'"][^\'"]+[\'"]/', $play['body']) === 1, 'play.php registra SW con scope explícito');

$manifest = httpGet("$base/manifest.webmanifest");
ok($manifest['status'] === 200, 'manifest HTTP 200');
ok(stripos($manifest['headers'], 'application/manifest+json') !== false, 'manifest Content-Type correcto');
$data = json_decode($manifest['body'], true);
ok(is_array($data), 'manifest JSON válido en prod');
ok(($data['display'] ?? '') === 'standalone', 'manifest display standalone');
ok(!empty($data['id']), 'manifest id presente en prod');
ok(($data['short_name'] ?? '') === 'Aquí Hay Tema', 'manifest short_name correcto en prod');

$icons = is_array($data['icons'] ?? null) ? $data['icons'] : [];
foreach ($icons as $icon) {
    $sizes = (string) ($icon['sizes'] ?? '');
    $src = (string) ($icon['src'] ?? '');
    if ($src === '' || $sizes === '') {
        continue;
    }
    $iconUrl = rtrim($base, '/') . '/' . ltrim($src, '/');
    $resp = httpGet($iconUrl);
    ok($resp['status'] === 200, "icono $src HTTP 200");
    $tmp = tempnam(sys_get_temp_dir(), 'pwaicon');
    if ($tmp !== false) {
        file_put_contents($tmp, $resp['body']);
        $info = @getimagesize($tmp);
        if (is_array($info)) {
            [$w, $h] = $info;
            $expected = explode('x', $sizes);
            $match = ((int) $expected[0] === $w && (int) $expected[1] === $h);
            ok($match, "icono $src tamaño físico {$w}x{$h} = declarado $sizes");
        } else {
            ok(false, "icono $src no es imagen válida");
        }
        @unlink($tmp);
    }
}

$sw = httpGet("$base/sw.js");
ok($sw['status'] === 200, 'sw.js HTTP 200');
ok(strpos($sw['body'], 'respondWith') !== false, 'sw.js tiene fetch handler');

echo $failures === 0 ? "pwa_installability_prod OK\n" : "pwa_installability_prod FAIL ({$failures})\n";
exit($failures > 0 ? 1 : 0);
