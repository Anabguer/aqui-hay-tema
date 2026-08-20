<?php
$f = 'src/Engine/HitoRelacionalEngine.php';
$c = file_get_contents($f);
$start = strpos($c, 'private static function crushTerceros');
$end = strpos($c, 'private static function paresCandidatos');
if ($start === false || $end === false) {
    fwrite(STDERR, "markers missing\n");
    exit(1);
}
$new = <<<'PHP'
    private static function crushTerceros(array &$partida, array $cal, RngService $rng, array &$ocurridos): void
    {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.crush_tercero', []);
        if (!is_array($cfg)) {
            return;
        }
        $pBump = (float) ($cfg['p_bump_base'] ?? 0.015);
        $maxIntentos = (int) ($cfg['max_intentos_por_dia'] ?? 12);
        $emparejados = [];
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $est = (string) ($rel['estado_pareja'] ?? '');
            if ($est !== ParejaEngine::PAREJA && $est !== ParejaEngine::CRISIS) {
                continue;
            }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            if ($a !== '') {
                $emparejados[$a] = $b;
            }
            if ($b !== '') {
                $emparejados[$b] = $a;
            }
        }
        if ($emparejados === []) {
            return;
        }
        $intentos = 0;
        foreach ($emparejados as $id => $pareja) {
            foreach ($partida['relaciones_sociales'] ?? [] as $soc) {
                if ($intentos >= $maxIntentos) {
                    return;
                }
                if (!is_array($soc) || empty($soc['conocidos'])) {
                    continue;
                }
                $x = (string) ($soc['persona_a'] ?? '');
                $y = (string) ($soc['persona_b'] ?? '');
                $otro = null;
                if ($x === $id && $y !== $pareja) {
                    $otro = $y;
                } elseif ($y === $id && $x !== $pareja) {
                    $otro = $x;
                }
                if ($otro === null) {
                    continue;
                }
                $intentos++;
                if (ParentescoVeto::bloqueaRomance($partida, $id, $otro, $cal)) {
                    continue;
                }
                $p = $pBump * TerceroRomantico::multiplicador($partida, $id, $otro, $cal);
                if ($rng->nextFloat() > $p) {
                    continue;
                }
                $d = HitoRelacionalContexto::randRango($rng, is_array($cfg['romance_bump'] ?? null) ? $cfg['romance_bump'] : [2, 5]);
                HitoRelacionalContexto::bumpRomance($partida, $id, $otro, $d);
                $ocurridos[] = [
                    'tipo' => 'crush_tercero',
                    'desde' => $id,
                    'hacia' => $otro,
                    'pareja' => $pareja,
                    'delta' => $d,
                ];
            }
        }
    }

PHP;
$c = substr($c, 0, $start) . $new . substr($c, $end);
file_put_contents($f, $c);
echo "patched crush\n";
