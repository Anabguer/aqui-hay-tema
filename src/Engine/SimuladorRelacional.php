<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Laboratorio relacional. Mide; no canoniza tasas.
 * No escribe partidas.
 */
final class SimuladorRelacional
{
    /**
     * @return array<string, mixed>
     */
    public static function ejecutar(string $projectRoot, int $pueblos = 20, array $tamanos = [16, 32], int $dias = 14, string $seedBase = 'lab-rel'): array
    {
        $store = new CatalogStore($projectRoot);
        $cal = CalibracionConfig::load($projectRoot);
        $out = [
            '_provisional' => true,
            '_nota' => 'Cortes de informe, no reglas. Play no tira acontecimientos (activo_en_play=false).',
            'pueblos' => $pueblos,
            'dias' => $dias,
            'por_tamano' => [],
        ];
        foreach ($tamanos as $n) {
            $out['por_tamano'][$n] = self::tamano($store, $cal, $pueblos, (int) $n, $dias, $seedBase);
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function tamano(CatalogStore $store, array $cal, int $pueblos, int $n, int $dias, string $seedBase): array
    {
        $eleg = [
            'perder_trabajo' => 0,
            'flechazo' => 0,
            'mandar_flores' => 0,
            'declaracion' => 0,
            'reconciliacion' => 0,
        ];
        $vetos = 0;
        $pares = 0;
        $msgsTeoricos = ['importante' => 0, 'oportunidad' => 0, 'peticion' => 0, 'cotilleo' => 0, 'ninguna' => 0];
        $parejasRapidas = 0;
        $rupturasRapidas = 0;
        $sinVinculo = 0;
        $aisladosElegibilidad = 0;

        for ($p = 0; $p < $pueblos; $p++) {
            $rng = new RngService($seedBase . '-' . $n . '-' . $p);
            $partida = self::puebloSintetico($n, $rng, $cal, $store);
            $pares += (int) ($n * ($n - 1) / 2);
            foreach ($partida['parentesco'] as $lazo) {
                if (ParentescoVeto::bloqueaRomance($partida, $lazo['persona_a'], $lazo['persona_b'], $cal)) {
                    $vetos++;
                }
            }
            foreach ($store->items('acontecimientos') as $item) {
                $id = (string) ($item['id'] ?? '');
                if (!isset($eleg[$id])) {
                    continue;
                }
                $cands = AcontecimientoElegibilidad::candidatos($partida, $item, $cal);
                $eleg[$id] += count($cands);
                $vis = (string) ($item['visibilidad_jugador'] ?? 'ninguna');
                if ($vis === 'ninguna') {
                    $msgsTeoricos['ninguna'] += count($cands);
                } elseif ($vis === 'importante' || $vis === 'aviso') {
                    $msgsTeoricos['importante'] += count($cands);
                } elseif ($vis === 'oportunidad') {
                    $msgsTeoricos['oportunidad'] += count($cands);
                } elseif ($vis === 'peticion') {
                    $msgsTeoricos['peticion'] += count($cands);
                } else {
                    $msgsTeoricos['cotilleo'] += count($cands);
                }
            }
            $ids = array_keys($partida['residentes']);
            foreach ($ids as $id) {
                if (!AcontecimientoElegibilidad::conoceAAlguien($partida, (string) $id)) {
                    $sinVinculo++;
                }
            }
            $rapida = (int) CalibracionConfig::get($cal, 'laboratorio_relacional.pareja_rapida_dias', 2);
            if ($n >= 2) {
                $a = (string) $ids[0];
                $b = (string) $ids[1];
                RelacionEngine::upsertSocial($partida, $a, $b, 'conocido', 1);
                $partida['reloj']['dia_pueblo'] = 1;
                ParejaEngine::formar($partida, $a, $b, true, true, RelacionBitacora::DECLARACION, $cal);
                $ini = (int) ($partida['relaciones_romanticas'][0]['fecha_inicio']['dia'] ?? 1);
                if ($ini <= $rapida) {
                    $parejasRapidas++;
                }
                $partida['reloj']['dia_pueblo'] = $ini + 1;
                ParejaEngine::crisis($partida, $a, $b);
                ParejaEngine::romper($partida, $a, $b, 'lab');
                $fin = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
                $limR = (int) CalibracionConfig::get($cal, 'laboratorio_relacional.ruptura_rapida_dias', 3);
                if (($fin - $ini) <= $limR) {
                    $rupturasRapidas++;
                }
            }
        }

        return [
            'residentes' => $n,
            'elegibilidad_media_pueblo' => [
                'perder_trabajo' => round($eleg['perder_trabajo'] / max(1, $pueblos), 2),
                'flechazo' => round($eleg['flechazo'] / max(1, $pueblos), 2),
                'mandar_flores' => round($eleg['mandar_flores'] / max(1, $pueblos), 2),
                'declaracion' => round($eleg['declaracion'] / max(1, $pueblos), 2),
                'reconciliacion' => round($eleg['reconciliacion'] / max(1, $pueblos), 2),
            ],
            'vetos_parentesco_total' => $vetos,
            'techo_mensajes_si_todo_candidato_avisara' => [
                'importante_por_pueblo' => round($msgsTeoricos['importante'] / max(1, $pueblos), 1),
                'oportunidad_por_pueblo' => round($msgsTeoricos['oportunidad'] / max(1, $pueblos), 1),
                'peticion_por_pueblo' => round($msgsTeoricos['peticion'] / max(1, $pueblos), 1),
                'cotilleo_por_pueblo' => round($msgsTeoricos['cotilleo'] / max(1, $pueblos), 1),
            ],
            'informe_scripted' => [
                'parejas_dia1_marcadas_rapidas' => $parejasRapidas,
                'rupturas_lab_marcadas_rapidas' => $rupturasRapidas,
                '_nota' => 'inyección de hitos para probar detectores, no tasa de juego',
            ],
            'residentes_sin_vinculo_inicial' => $sinVinculo,
            'dias_simulados_sin_tirar_eventos' => $dias,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function puebloSintetico(int $n, RngService $rng, array $cal, CatalogStore $store): array
    {
        $partida = [
            'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 8],
            'residentes' => [],
            'relaciones_sociales' => [],
            'relaciones_romanticas' => [],
            'relaciones_conflicto' => [],
            'parentesco' => [],
            'bitacora_relaciones' => [],
            'buzon' => [],
            'memoria_eventos' => [],
            'historial_relaciones' => [],
        ];
        for ($i = 0; $i < $n; $i++) {
            $id = 'lab_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $emp = $rng->nextInt(0, 4) > 0;
            $partida['residentes'][$id] = [
                'catalog_id' => $id,
                'presencia' => 'residente',
                'runtime' => [
                    'ocupacion' => $emp ? 'empleado' : 'desempleado',
                    'perfil_partida' => [
                        'edad' => $rng->nextInt(22, 72),
                        'hobbies' => [],
                        'rasgos' => [],
                    ],
                ],
            ];
        }
        SchemaFields::ensure($partida);
        $ids = array_keys($partida['residentes']);
        if ($n >= 2 && $rng->nextInt(0, 9) === 0) {
            $partida['parentesco'][] = [
                'persona_a' => $ids[0],
                'persona_b' => $ids[1],
                'tipo' => 'hermano',
            ];
        }
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($rng->nextInt(0, 2) === 0) {
                    RelacionEngine::upsertSocial($partida, $ids[$i], $ids[$j], 'conocido', 1);
                }
            }
        }
        return $partida;
    }
}
