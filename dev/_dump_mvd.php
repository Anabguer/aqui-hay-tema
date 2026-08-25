<?php
declare(strict_types=1);
$stage = null;
foreach (glob(dirname(__DIR__) . '/dev/prod_fetch_f2a_*', GLOB_ONLYDIR) ?: [] as $d) {
    if ($stage === null || $d > $stage) {
        $stage = $d;
    }
}
$c = file_get_contents($stage . '/src__Engine__MotorVidaDiaria.php');
$l = explode("\n", $c);
for ($i = 143; $i < 163; $i++) {
    echo str_pad((string) ($i + 1), 4, ' ', STR_PAD_LEFT), '|', $l[$i], "|\n";
}
