<?php
declare(strict_types=1);
$stage = null;
foreach (glob(dirname(__DIR__) . '/dev/prod_fetch_f2a_*', GLOB_ONLYDIR) ?: [] as $d) {
    if ($stage === null || $d > $stage) {
        $stage = $d;
    }
}
foreach (['src__Engine__MotorVidaDiaria.php', 'src__Engine__IniciativaRomantica.php', 'src__Engine__EncuentroResolver.php', 'src__Engine__EncuentroEngine.php'] as $f) {
    $c = file_get_contents($stage . '/' . $f);
    printf("%-45s crlf=%d lf=%d bom=%s\n", $f, substr_count($c, "\r\n"), substr_count($c, "\n"), var_export(str_starts_with($c, "\xEF\xBB\xBF"), true));
}
