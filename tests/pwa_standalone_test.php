<?php declare(strict_types=1);

/**
 * PWA standalone — manifest, service worker, iconos reales e installability local.
 */

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

function iconDimensions(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $info = @getimagesize($path);
    if (!is_array($info)) {
        return null;
    }
    return [(int) $info[0], (int) $info[1]];
}

$manifestPath = $root . '/manifest.webmanifest';
$swPath = $root . '/sw.js';
$playPath = $root . '/play.php';

ok(is_file($manifestPath), 'manifest.webmanifest existe');
$raw = is_file($manifestPath) ? file_get_contents($manifestPath) : '';
$manifest = json_decode($raw ?: '{}', true);
ok(is_array($manifest), 'manifest JSON válido');
ok(($manifest['display'] ?? '') === 'standalone', 'display = standalone');
ok(($manifest['start_url'] ?? '') === '/juegos/aqui-hay-tema/play.php', 'start_url absoluta correcta');
ok(($manifest['scope'] ?? '') === '/juegos/aqui-hay-tema/', 'scope absoluto correcto');
ok(!empty($manifest['id']), 'manifest id presente');
ok(($manifest['short_name'] ?? '') === 'Aquí Hay Tema', 'short_name = Aquí Hay Tema');
ok(!empty($manifest['name']) && !empty($manifest['theme_color']) && !empty($manifest['background_color']), 'name y colores PWA presentes');

$icons = is_array($manifest['icons'] ?? null) ? $manifest['icons'] : [];
$requiredSizes = ['192x192' => false, '512x512' => false];
foreach ($icons as $icon) {
    $sizes = (string) ($icon['sizes'] ?? '');
    $src = (string) ($icon['src'] ?? '');
    if ($src === '') {
        continue;
    }
    $iconPath = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $src);
    ok(is_file($iconPath), "icono accesible: $src");
    $dims = iconDimensions($iconPath);
    if ($dims !== null && isset($requiredSizes[$sizes])) {
        $expected = explode('x', $sizes);
        $match = ((int) $expected[0] === $dims[0] && (int) $expected[1] === $dims[1]);
        ok($match, "icono $src coincide tamaño declarado $sizes (físico {$dims[0]}x{$dims[1]})");
        if ($match) {
            $requiredSizes[$sizes] = true;
        }
    }
}
ok($requiredSizes['192x192'] && $requiredSizes['512x512'], 'iconos 192 y 512 con dimensiones físicas correctas');

ok(is_file($swPath), 'sw.js existe');
$sw = is_file($swPath) ? file_get_contents($swPath) : '';
ok(strpos($sw, 'caches') === false, 'SW sin Cache API');
ok(strpos($sw, 'respondWith') !== false, 'SW intercepta fetch con respondWith');

$play = is_file($playPath) ? file_get_contents($playPath) : '';
ok(strpos($play, 'rel="manifest"') !== false, 'play.php enlaza manifest');
ok(strpos($play, 'manifest.webmanifest') !== false, 'play.php ruta manifest correcta');
ok(strpos($play, 'theme-color') !== false, 'play.php theme-color');
ok(strpos($play, 'serviceWorker.register') !== false, 'play.php registra service worker');
ok(strpos($play, 'sw.js?v=') === false, 'play.php SW sin query string (installability)');
ok(strpos($play, '$ahtPwaBase') !== false, 'play.php base PWA dinámica');
ok(strpos($play, 'pwa-icon-192.png') !== false, 'play.php favicon PWA 192');
ok(strpos($play, 'play-v3.js?v=') !== false, 'play.php cache-buster JS intacto');

echo $failures === 0 ? "pwa_standalone_test OK\n" : "pwa_standalone_test FAIL ({$failures})\n";
exit($failures > 0 ? 1 : 0);
