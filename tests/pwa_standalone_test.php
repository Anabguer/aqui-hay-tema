<?php declare(strict_types=1);

/**
 * PWA standalone — manifest, service worker y enlaces en play.php.
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

$manifestPath = $root . '/manifest.webmanifest';
$swPath = $root . '/sw.js';
$playPath = $root . '/play.php';
$iconPath = $root . '/assets/brand/favicon-aht.png';

ok(is_file($manifestPath), 'manifest.webmanifest existe');
$raw = is_file($manifestPath) ? file_get_contents($manifestPath) : '';
$manifest = json_decode($raw ?: '{}', true);
ok(is_array($manifest), 'manifest JSON válido');
ok(($manifest['display'] ?? '') === 'standalone', 'display = standalone');
ok(($manifest['start_url'] ?? '') === 'play.php', 'start_url = play.php');
ok(($manifest['scope'] ?? '') === './', 'scope = ./');
ok(!empty($manifest['name']) && !empty($manifest['short_name']), 'name y short_name presentes');
ok(!empty($manifest['theme_color']) && !empty($manifest['background_color']), 'colores PWA presentes');

$icons = is_array($manifest['icons'] ?? null) ? $manifest['icons'] : [];
$has192 = false;
$has512 = false;
foreach ($icons as $icon) {
    $sizes = (string) ($icon['sizes'] ?? '');
    if (strpos($sizes, '192') !== false) {
        $has192 = true;
    }
    if (strpos($sizes, '512') !== false) {
        $has512 = true;
    }
    $src = (string) ($icon['src'] ?? '');
    if ($src !== '') {
        ok(is_file($root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $src)), "icono accesible: $src");
    }
}
ok($has192 && $has512, 'iconos 192 y 512 declarados');

ok(is_file($swPath), 'sw.js existe');
$sw = is_file($swPath) ? file_get_contents($swPath) : '';
ok(strpos($sw, 'caches') === false, 'SW sin Cache API');
ok(strpos($sw, 'fetch(event.request)') !== false, 'SW estrategia red directa');

$play = is_file($playPath) ? file_get_contents($playPath) : '';
ok(strpos($play, 'rel="manifest"') !== false, 'play.php enlaza manifest');
ok(strpos($play, 'manifest.webmanifest') !== false, 'play.php ruta manifest correcta');
ok(strpos($play, 'theme-color') !== false, 'play.php theme-color');
ok(strpos($play, 'serviceWorker.register') !== false, 'play.php registra service worker');
ok(strpos($play, 'sw.js?v=') !== false, 'play.php SW con cache-buster');
ok(strpos($play, 'play-v3.js?v=') !== false, 'play.php cache-buster JS intacto');

ok(is_file($iconPath), 'favicon-aht.png existe (1024)');

echo $failures === 0 ? "pwa_standalone_test OK\n" : "pwa_standalone_test FAIL ({$failures})\n";
exit($failures > 0 ? 1 : 0);
