<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$fail = [];

$lavanda = file_get_contents($root . '/assets/css/play-v3-tutorial-lavanda.css');
$review = file_get_contents($root . '/assets/css/play-v3-visual-review.css');
$tutorial = file_get_contents($root . '/assets/css/play-v3-tutorial-ds.css');
$play = file_get_contents($root . '/play.php');

if ($lavanda === false || strpos($lavanda, 'TUTORIAL-LAVANDA') === false) {
    $fail[] = 'falta play-v3-tutorial-lavanda.css';
}
if (strpos($play, 'play-v3-tutorial-lavanda.css') === false) {
    $fail[] = 'play.php no enlaza play-v3-tutorial-lavanda.css';
}
if (preg_match('/\.play-v3 \.tut-intro \.tut-papel[\s\S]*libreta_hoja/', $review)) {
    $fail[] = 'visual-review aun fuerza scrapbook en .tut-papel';
}
if (strpos($tutorial, 'libreta_hoja.png') !== false) {
    $fail[] = 'tutorial-ds aun referencia libreta_hoja.png';
}
if (strpos($tutorial, 'chincheta.png') !== false) {
    $fail[] = 'tutorial-ds aun referencia chincheta.png';
}
if (strpos($tutorial, '#e86b5a') !== false) {
    $fail[] = 'tutorial-ds aun usa CTA coral #e86b5a';
}
if (!preg_match('/\.play-v3 \.tut-intro-line[\s\S]*Nunito/', $lavanda)) {
    $fail[] = 'lavanda no define Nunito para cuerpo';
}
if (!preg_match('/\.play-v3 \.tut-titulo[\s\S]*Caveat/', $lavanda)) {
    $fail[] = 'lavanda no mantiene Caveat en titulo';
}
if (strpos($lavanda, '.capa') !== false) {
    $fail[] = 'lavanda toca selectores .capa (riesgo regresion)';
}

if ($fail) {
    fwrite(STDERR, "tutorial_lavanda_css_test FAIL:\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}

echo "tutorial_lavanda_css_test OK\n";
