<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$fail = [];
$lavanda = file_get_contents($root . '/assets/css/play-v3-tutorial-lavanda.css');
$tutorial = file_get_contents($root . '/assets/css/play-v3-tutorial-ds.css');
$play = file_get_contents($root . '/play.php');
if ($lavanda === false || strpos($lavanda, 'TUTORIAL-LAVANDA') === false) { $fail[] = 'falta lavanda css'; }
if (strpos($play, 'play-v3-tutorial-lavanda.css') === false) { $fail[] = 'play sin lavanda'; }
if (strpos($play, 'play-v3-visual-review.css') !== false) { $fail[] = 'play enlaza visual-review'; }
if (strpos($tutorial, 'libreta_hoja.png') !== false) { $fail[] = 'tutorial-ds libreta'; }
if (strpos($lavanda, 'Nunito') === false) { $fail[] = 'sin Nunito'; }
if (strpos($lavanda, 'Caveat') === false) { $fail[] = 'sin Caveat'; }
$lavNoComments = preg_replace('/\/\*[\s\S]*?\*\//', '', $lavanda);
if (preg_match('/\.capa\b/', $lavNoComments)) { $fail[] = 'lavanda usa .capa'; }
if ($fail) { fwrite(STDERR, implode("\n", $fail) . "\n"); exit(1); }
echo "tutorial_lavanda_css_test OK\n";