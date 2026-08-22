<?php
declare(strict_types=1);

$play = file_get_contents(dirname(__DIR__) . '/play.php');
$fail = [];
if (strpos($play, 'play-v3-bloques-residencias.css') === false) {
    $fail[] = 'falta CSS bloques-residencias';
}
if (strpos($play, '.tut-caras .cara') === false || strpos($play, '52px') === false) {
    $fail[] = 'falta tamaño tut-caras';
}
if (strpos($play, '.caras-clip .cara') === false) {
    $fail[] = 'falta tamaño caras-clip';
}
$app = file_get_contents(dirname(__DIR__) . '/assets/css/play-v3-app.css');
if (strpos($app, 'capa-misiones') === false) {
    $fail[] = 'falta capa-misiones en play-v3-app.css';
}
if ($fail) {
    fwrite(STDERR, "portrait_overflow_static_test FAIL:\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}
echo "portrait_overflow_static_test OK\n";
