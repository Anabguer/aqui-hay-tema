<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Laboratorio de distribuciones. No escribe partidas. Pesos = calibración.
 */
final class SimuladorPueblos
{
    /**
     * @return array<string, mixed>
     */
    public static function ejecutar(string $projectRoot, int $pueblos = 1000, int $residentes = 16, string $seedBase = 'lab-pueblos'): array
    {
        $store = new CatalogStore($projectRoot);
        $cal = CalibracionConfig::load($projectRoot);
        $hobbiesCat = GeneradorResidente::idsGenerables($store, 'hobbies');
        $rasgosCat = GeneradorResidente::idsGenerables($store, 'rasgos');
        $visCat = GeneradorResidente::idsGenerables($store, 'indicadores_visuales');

        $hobCount = [];
        $rasCount = [];
        $prefPos = [];
        $prefNeg = [];
        $visPos = [];
        $visNeg = [];
        $compVals = [];
        $asim = [];
        $quiVals = [];
        $clonesPueblo = 0;
        $aislados = 0;
        $romanceNoElegible = 0;
        $pares = 0;
        $altos = 0;
        $medios = 0;
        $bajos = 0;

        for ($p = 0; $p < $pueblos; $p++) {
            $rng = new RngService($seedBase . '-' . $p);
            $perfiles = [];
            for ($i = 0; $i < $residentes; $i++) {
                $edad = $rng->nextInt(22, 72);
                $vis = $visCat !== [] ? $rng->pickUnique($visCat, min(3, count($visCat))) : [];
                $perfil = GeneradorResidente::generar($rng, $store, $cal, [
                    'identidad' => ['edad' => $edad],
                ], $vis);
                $perfiles[] = $perfil;
                foreach ($perfil['hobbies'] as $h) {
                    $hobCount[$h] = ($hobCount[$h] ?? 0) + 1;
                }
                foreach ($perfil['rasgos'] as $r) {
                    $rasCount[$r] = ($rasCount[$r] ?? 0) + 1;
                }
                foreach ($perfil['preferencias']['personalidad_pos'] as $x) {
                    $prefPos[$x] = ($prefPos[$x] ?? 0) + 1;
                }
                foreach ($perfil['preferencias']['personalidad_neg'] as $x) {
                    $prefNeg[$x] = ($prefNeg[$x] ?? 0) + 1;
                }
                foreach ($perfil['preferencias']['visual_pos'] as $x) {
                    $visPos[$x] = ($visPos[$x] ?? 0) + 1;
                }
                foreach ($perfil['preferencias']['visual_neg'] as $x) {
                    $visNeg[$x] = ($visNeg[$x] ?? 0) + 1;
                }
            }

            $sets = [];
            foreach ($perfiles as $pf) {
                $key = implode('|', $pf['hobbies']);
                $sets[$key] = ($sets[$key] ?? 0) + 1;
            }
            foreach ($sets as $c) {
                if ($c >= 3) {
                    $clonesPueblo++;
                    break;
                }
            }

            $maxOut = array_fill(0, $residentes, 0);
            for ($i = 0; $i < $residentes; $i++) {
                for ($j = $i + 1; $j < $residentes; $j++) {
                    $ab = CompatibilidadCalculator::aHaciaB($perfiles[$i], $perfiles[$j], $cal);
                    $ba = CompatibilidadCalculator::aHaciaB($perfiles[$j], $perfiles[$i], $cal);
                    $compVals[] = $ab['total'];
                    $compVals[] = $ba['total'];
                    $asim[] = abs($ab['total'] - $ba['total']);
                    $pares++;
                    if (!($ab['romance_elegible'] ?? true) || !($ba['romance_elegible'] ?? true)) {
                        $romanceNoElegible++;
                    }
                    $qui = $rng->nextInt(
                        (int) CalibracionConfig::get($cal, 'quimica.min', 0),
                        (int) CalibracionConfig::get($cal, 'quimica.max', 100)
                    );
                    $quiVals[] = $qui;
                    $maxOut[$i] = max($maxOut[$i], $ab['total']);
                    $maxOut[$j] = max($maxOut[$j], $ba['total']);
                    $media = ($ab['total'] + $ba['total']) / 2;
                    if ($media >= 70) {
                        $altos++;
                    } elseif ($media <= 35) {
                        $bajos++;
                    } else {
                        $medios++;
                    }
                }
            }
            foreach ($maxOut as $m) {
                if ($m < 40) {
                    $aislados++;
                }
            }
        }

        sort($compVals);
        sort($quiVals);
        sort($asim);

        return [
            '_provisional' => true,
            'pueblos' => $pueblos,
            'residentes_por_pueblo' => $residentes,
            'seed_base' => $seedBase,
            'catalogo_hobbies' => count($hobbiesCat),
            'catalogo_rasgos' => count($rasgosCat),
            'hobbies' => self::ranking($hobCount),
            'rasgos' => self::ranking($rasCount),
            'preferencias_pos' => self::ranking($prefPos),
            'preferencias_neg' => self::ranking($prefNeg),
            'visual_pos' => self::ranking($visPos),
            'visual_neg' => self::ranking($visNeg),
            'compatibilidad' => self::stats($compVals),
            'asimetria_abs' => self::stats($asim),
            'asimetria_ge20' => self::contar($asim, 20),
            'quimica' => self::stats($quiVals),
            'pares_unicos' => $pares,
            'pares_media_alta_ge70' => $altos,
            'pares_media_media' => $medios,
            'pares_media_baja_le35' => $bajos,
            'pares_romance_no_elegible_edad' => $romanceNoElegible,
            'pueblos_con_hobby_clon_ge3' => $clonesPueblo,
            'residentes_max_salida_lt40' => $aislados,
            '_nota_umbrales_informe' => '70/35/40 son cortes de informe, no reglas de juego.',
        ];
    }

    /**
     * @param array<string, int> $counts
     * @return list<array{id: string, n: int}>
     */
    private static function ranking(array $counts): array
    {
        arsort($counts);
        $out = [];
        foreach ($counts as $id => $n) {
            $out[] = ['id' => (string) $id, 'n' => (int) $n];
        }
        return $out;
    }

    /**
     * @param list<int|float> $vals
     * @return array<string, mixed>
     */
    private static function stats(array $vals): array
    {
        $n = count($vals);
        if ($n === 0) {
            return ['n' => 0];
        }
        $sum = 0;
        foreach ($vals as $v) {
            $sum += $v;
        }
        $buckets = ['0_20' => 0, '21_40' => 0, '41_60' => 0, '61_80' => 0, '81_100' => 0];
        foreach ($vals as $v) {
            if ($v <= 20) {
                $buckets['0_20']++;
            } elseif ($v <= 40) {
                $buckets['21_40']++;
            } elseif ($v <= 60) {
                $buckets['41_60']++;
            } elseif ($v <= 80) {
                $buckets['61_80']++;
            } else {
                $buckets['81_100']++;
            }
        }
        return [
            'n' => $n,
            'min' => $vals[0],
            'max' => $vals[$n - 1],
            'media' => round($sum / $n, 2),
            'p50' => $vals[(int) floor(($n - 1) * 0.5)],
            'p90' => $vals[(int) floor(($n - 1) * 0.9)],
            'buckets' => $buckets,
        ];
    }

    /**
     * @param list<int|float> $vals
     */
    private static function contar(array $vals, int $minAbs): int
    {
        $n = 0;
        foreach ($vals as $v) {
            if ($v >= $minAbs) {
                $n++;
            }
        }
        return $n;
    }
}
