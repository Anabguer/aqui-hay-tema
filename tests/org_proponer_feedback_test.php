<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

$root = dirname(__DIR__);
$fail = [];

$js = file_get_contents($root . '/assets/js/play-v3.js');
$required = [
    'let orgProponiendo = false',
    'const ORG_BTN_BUSY',
    'async function aplicarRespuestaProponer',
    'if (orgProponiendo) return',
    'orgProponiendo = true',
    'No se ha podido organizar el plan',
    'mostrarOrgAviso(msgRech)',
];
foreach ($required as $needle) {
    if (strpos($js, $needle) === false) {
        $fail[] = 'play-v3.js falta: ' . $needle;
    }
}

$nodeOut = shell_exec('node ' . escapeshellarg($root . '/tests/org_proponer_feedback_test.js') . ' 2>&1');
if (!is_string($nodeOut) || strpos($nodeOut, 'org_proponer_feedback_test.js OK') === false) {
    $fail[] = 'org_proponer_feedback_test.js: ' . trim((string) $nodeOut);
}

$regOut = shell_exec('php ' . escapeshellarg($root . '/tests/org_max_participantes_test.php') . ' 2>&1');
if (!is_string($regOut) || strpos($regOut, 'org_max_participantes_test OK') === false) {
    $fail[] = 'regresión org_max_participantes: ' . trim((string) $regOut);
}

$nodeReg = shell_exec('node ' . escapeshellarg($root . '/tests/org_form_validacion_test.js') . ' 2>&1');
if (!is_string($nodeReg) || strpos($nodeReg, 'org_form_validacion_test.js OK') === false) {
    $fail[] = 'regresión org_form_validacion js: ' . trim((string) $nodeReg);
}

if ($fail) {
    fwrite(STDERR, "org_proponer_feedback_test FAIL:\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "org_proponer_feedback_test OK\n";
