<?php
ob_start();
include dirname(__DIR__) . '/play.php';
$html = ob_get_clean();
if (strpos($html, 'btn-debug-nueva') === false) {
    fwrite(STDERR, "play.php render: falta panel DEBUG\n");
    exit(1);
}
if (strpos($html, 'btn-debug-parejas-crear') === false) {
    fwrite(STDERR, "play.php render: falta botón crear parejas debug\n");
    exit(1);
}
if (strpos($html, 'lab-audit.js') === false) {
    fwrite(STDERR, "play.php render: falta lab-audit.js\n");
    exit(1);
}
echo "play_php_render_test OK\n";
