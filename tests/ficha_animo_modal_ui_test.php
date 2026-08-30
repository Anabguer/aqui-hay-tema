<?php
declare(strict_types=1);

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

$root = dirname(__DIR__);
$js = file_get_contents($root . '/assets/js/play-v3.js');
$php = file_get_contents($root . '/play.php');
$css = file_get_contents($root . '/assets/css/play-v3-ficha.css');

ok(strpos($php, 'Volver a ficha') !== false, 'play.php volver copy');
ok(strpos($js, 'data-animo-org') === false, 'sin CTA organizar en modal animo');
ok(strpos($js, 'data-animo-diario') === false, 'sin enlace diario en modal animo');
ok(strpos($js, "root.getAttribute('data-capa') !== 'ficha_animo'") !== false, 'cerrarAnimoOverlay acotado a capa animo');
ok(strpos($js, "animoVolverCapa = 'ficha'") !== false, 'volver fija capa ficha');
ok(strpos($css, 'FICHA-ANIMO-MODAL-v157') !== false, 'css v157 presente');
ok(strpos($css, '.capa-ficha-animo .fani-volver') !== false, 'estilo volver');

echo "ficha_animo_modal_ui_test: todo OK\n";
