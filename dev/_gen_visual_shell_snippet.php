<?php
declare(strict_types=1);
/** Genera dev/_visual_validate_shell_snippet.php desde play.php (una vez / tras cambios de markup). */
$play = file_get_contents(dirname(__DIR__) . '/play.php');
if (!preg_match('#<div class="game-shell">(.*)</div>\s*<script src="assets/js/lab-audit#s', $play, $m)) {
    fwrite(STDERR, "No se pudo extraer game-shell\n");
    exit(1);
}
$inner = $m[1];
// Quitar game-shell wrapper externo — el harness ya lo tiene
$inner = preg_replace('#^\s*<div class="game-shell">\s*#s', '', $inner);
$inner = preg_replace('#\s*</div>\s*$#s', '', $inner);
$out = "<?php\n// AUTO-GENERADO — no editar a mano. Regenerar: php dev/_gen_visual_shell_snippet.php\ndeclare(strict_types=1);\n?>\n" . $inner;
file_put_contents(__DIR__ . '/_visual_validate_shell_snippet.php', $out);
echo "OK bytes=" . strlen($out) . "\n";
