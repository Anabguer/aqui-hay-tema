<?php
declare(strict_types=1);
/**
 * Verifica que play-v3.js no use tutorial legacy ni ?lab=1 como activador.
 */
$js = file_get_contents(dirname(__DIR__) . '/assets/js/play-v3.js');
$php = file_get_contents(dirname(__DIR__) . '/play.php');
$fail = [];

if (preg_match('/const\s+TUT_PASOS\s*=/', $js)) {
    $fail[] = 'play-v3.js aún define TUT_PASOS legacy';
}
if (strpos($js, 'return TUT_PASOS') !== false) {
    $fail[] = 'tutPasosActuales() aún hace fallback a TUT_PASOS';
}
if (strpos($js, "tutorial.id === 'primeros_pasos'") === false) {
    $fail[] = 'falta tieneTutorialV3 / id primeros_pasos';
}
if (strpos($js, 'Bienvenida al pueblo') !== false) {
    $fail[] = 'copy canónico del tutorial no debe estar hardcodeado en JS';
}
if (strpos($js, '__AHT_LAB__') !== false || preg_match('/\bIS_LAB\b/', $js)) {
    $fail[] = 'play-v3.js aún referencia modo lab URL';
}
if (strpos($js, 'function initDebugPanel') === false) {
    $fail[] = 'falta panel DEBUG integrado en play-v3.js';
}
if (strpos($php, '$ahtLab') !== false) {
    $fail[] = 'play.php aún usa $ahtLab';
}
if (strpos($php, 'data-debug-toggle') === false) {
    $fail[] = 'play.php sin botón DEBUG';
}
if (strpos($php, 'lab-audit.js') === false) {
    $fail[] = 'play.php no carga lab-audit.js';
}
if (strpos($js, 'setCapa(\'misiones\')') === false) {
    $fail[] = 'cerrar tutorial debe abrir misiones, no cotilleos';
}
if (strpos($js, 'resetOrgForm') === false) {
    $fail[] = 'falta resetOrgForm para Nuevo plan limpio';
}
if (strpos($js, 'setOrgModo(btn.getAttribute') === false) {
    $fail[] = 'faltan listeners data-org-modo (Por su cuenta)';
}
if (strpos($js, 'nuevo_mensajito') === false) {
    $fail[] = 'falta feedback nuevo_mensajito tras plan tutorial';
}
if (strpos($js, 'agenda.slots_compatibles') === false) {
    $fail[] = 'falta agenda.slots_compatibles en Nuevo plan';
}
if (strpos($js, 'function buzonNoLeidos') === false) {
    $fail[] = 'falta buzonNoLeidos para badge canónico';
}
if (strpos($js, 'function enTutorialPrimerosPasos') === false) {
    $fail[] = 'falta enTutorialPrimerosPasos para misiones tutorial';
}
if (strpos($js, 'quizaMostrarTutFinale') === false) {
    $fail[] = 'falta quizaMostrarTutFinale';
}

if ($fail) {
    fwrite(STDERR, "play_v3_tutorial_source_test FAIL:\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}
echo "play_v3_tutorial_source_test OK\n";
