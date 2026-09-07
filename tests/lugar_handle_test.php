<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\LugarHandle;

$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

// --- 1. Places from lugares.json derive handles algorithmically ---
ok(LugarHandle::de('lug_cafeteria') === '@cafeteriadelpueblo', 'cafeteria → @cafeteriadelpueblo');
ok(LugarHandle::de('lug_biblioteca') === '@bibliotecadelpueblo', 'biblioteca → @bibliotecadelpueblo');
ok(LugarHandle::de('lug_gimnasio') === '@gimnasiodelpueblo', 'gimnasio → @gimnasiodelpueblo');
ok(LugarHandle::de('lug_restaurante') === '@restaurantedelpueblo', 'restaurante → @restaurantedelpueblo');
ok(LugarHandle::de('lug_parque') === '@parquedelpueblo', 'parque → @parquedelpueblo');
ok(LugarHandle::de('lug_bar') === '@bardelpueblo', 'bar → @bardelpueblo');
ok(LugarHandle::de('lug_cine') === '@cinedelpueblo', 'cine → @cinedelpueblo');
ok(LugarHandle::de('lug_discoteca') === '@discotecadelpueblo', 'discoteca → @discotecadelpueblo');
ok(LugarHandle::de('lug_bingo') === '@bingodelpueblo', 'bingo → @bingodelpueblo');
ok(LugarHandle::de('lug_plaza') === '@plazadelpueblo', 'plaza → @plazadelpueblo');
ok(LugarHandle::de('lug_arcade') === '@arcadedelpueblo', 'arcade → @arcadedelpueblo');
ok(LugarHandle::de('lug_tienda_ropa') === '@tiendaderopadelpueblo', 'tienda_ropa → @tiendaderopadelpueblo (nombre con espacios)');
ok(LugarHandle::de('lug_mirador') === '@miradordelpueblo', 'mirador → @miradordelpueblo');
ok(LugarHandle::de('lug_casa') === '@casadelpueblo', 'casa → @casadelpueblo');

// --- 2. Fallback cases ---
ok(LugarHandle::de(null) === '@puebloconfidencial', 'null → @puebloconfidencial');
ok(LugarHandle::de('') === '@puebloconfidencial', 'empty string → @puebloconfidencial');
ok(LugarHandle::de('lug_inexistente') === '@puebloconfidencial', 'unknown id → @puebloconfidencial');
ok(LugarHandle::de('lug_museo') === '@puebloconfidencial', 'future place not in json → @puebloconfidencial');

// --- 3. Verify NO hardcoded MAP exists in source ---
$src = file_get_contents(__DIR__ . '/../src/Engine/LugarHandle.php');
ok(
    !str_contains($src, "=> '@") && !str_contains($src, "=> \"@"),
    'NO hardcoded lug_id → @handle mapping in source'
);
ok(
    !str_contains($src, 'private const MAP'),
    'NO const MAP table in source'
);
ok(
    str_contains($src, "lugares.json") && str_contains($src, "nombre"),
    'Derives from lugares.json nombre field'
);
ok(
    str_contains($src, 'self::$cache'),
    'Uses static cache for performance'
);

// --- 4. Edge case: normalizer handles special characters ---
// The normalizer uses iconv transliteration — spaces and basic latin work.
// Verify a name with space produces handle without spaces.
ok(
    LugarHandle::de('lug_tienda_ropa') === '@tiendaderopadelpueblo',
    'Spaces in nombre stripped from handle'
);

// --- Summary ---
echo "\n" . ($failures === 0 ? 'ALL TESTS PASSED' : "FAILURES: $failures") . "\n";
exit($failures === 0 ? 0 : 1);
