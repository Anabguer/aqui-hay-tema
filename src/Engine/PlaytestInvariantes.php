<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Invariantes del pueblo. Cualquier fallo = bug de integración.
 */
final class PlaytestInvariantes
{
    /**
     * @return list<string> códigos de fallo
     */
    public static function auditar(array $partida, string $root = ''): array
    {
        $fallos = [];
        $residentes = is_array($partida['residentes'] ?? null) ? $partida['residentes'] : [];
        $ids = [];
        foreach ($residentes as $id => $r) {
            if (!is_string($id) || $id === '') {
                $fallos[] = 'residente_id_vacio';
                continue;
            }
            if (isset($ids[$id])) {
                $fallos[] = 'residente_duplicado:' . $id;
            }
            $ids[$id] = true;
        }

        $viviendas = [];
        foreach ($residentes as $id => $r) {
            if (!is_array($r)) {
                continue;
            }
            $vid = (string) ($r['vivienda_id'] ?? $r['vivienda'] ?? '');
            if ($vid === '') {
                continue;
            }
            if (isset($viviendas[$vid])) {
                $fallos[] = 'vivienda_doble:' . $vid . ':' . $viviendas[$vid] . '+' . $id;
            }
            $viviendas[$vid] = (string) $id;
        }

        // Una persona en dos encuentros activos a la vez
        $ocupados = [];
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc)) {
                continue;
            }
            $est = (string) ($enc['estado'] ?? '');
            if (!in_array($est, ['proximo', 'en_curso', 'programado'], true)) {
                continue;
            }
            $dia = (int) ($enc['dia'] ?? 0);
            $hora = (int) ($enc['hora'] ?? -1);
            foreach ($enc['participantes'] ?? [] as $pid) {
                $pid = (string) $pid;
                $key = $pid . '@' . $dia . ':' . $hora;
                if (isset($ocupados[$key])) {
                    $fallos[] = 'persona_doble_encuentro:' . $key;
                }
                $ocupados[$key] = (string) ($enc['id'] ?? '');
            }
            $partes = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
            if (count($partes) >= 2 && (string) $partes[0] === (string) $partes[1]) {
                $fallos[] = 'encuentro_misma_persona:' . ($enc['id'] ?? '');
            }
        }

        // Presencia: misma persona en dos lugares ahora
        $pres = [];
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        if ($root !== '') {
            try {
                $mapa = PresenciaEngine::resolver($partida, $root, $dia, $hora);
                foreach ($mapa['lugares'] ?? [] as $lugRow) {
                    if (!is_array($lugRow)) {
                        continue;
                    }
                    $lugar = (string) ($lugRow['id'] ?? '');
                    foreach ($lugRow['residentes_presentes'] ?? [] as $rp) {
                        $pid = is_array($rp) ? (string) ($rp['id'] ?? '') : (string) $rp;
                        if ($pid === '' || $lugar === '') {
                            continue;
                        }
                        if (isset($pres[$pid])) {
                            $fallos[] = 'persona_dos_sitios:' . $pid . ':' . $pres[$pid] . '+' . $lugar;
                        }
                        $pres[$pid] = $lugar;
                    }
                }
            } catch (\Throwable $e) {
                $fallos[] = 'presencia_resolver_error:' . $e->getMessage();
            }

            // Aforos vía motor canónico
            foreach (ComplejoCatalog::destinos() as $lugarId => $meta) {
                if (!is_array($meta)) {
                    continue;
                }
                $n = AforoEngine::ocupacion($partida, (string) $lugarId, $dia, $hora);
                $aforo = (int) ($meta['aforo'] ?? 0);
                if ($aforo > 0 && $n > $aforo) {
                    $fallos[] = 'aforo_destino:' . $lugarId . ':' . $n . '>' . $aforo;
                }
            }
            foreach (['cafe_libros', 'rincon_lola', 'cine_game', 'mala_idea', 'parque', 'gimnasio_spa'] as $cid) {
                $n = AforoEngine::ocupacionComplejo($partida, $cid, $dia, $hora);
                $techo = ComplejoCatalog::aforoComplejo($cid);
                if ($techo > 0 && $n > $techo) {
                    $fallos[] = 'aforo_complejo:' . $cid . ':' . $n . '>' . $techo;
                }
            }
        }

        // Dinero NaN
        $bal = $partida['economia']['dinero']['balance'] ?? $partida['celeste']['dinero'] ?? null;
        if (is_float($bal) && (is_nan($bal) || is_infinite($bal))) {
            $fallos[] = 'dinero_nan';
        }

        // Reloj coherente
        if ($hora < 0 || $hora > 23) {
            $fallos[] = 'reloj_hora_invalida:' . $hora;
        }
        if ($dia < 1) {
            $fallos[] = 'reloj_dia_invalido:' . $dia;
        }

        // Mensajes buzón duplicados por mismo origen+texto+día
        $seen = [];
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            $k = ($m['dia'] ?? '') . '|' . ($m['tipo'] ?? '') . '|' . ($m['texto'] ?? '') . '|' . ($m['origen']['evento_id'] ?? '');
            if (isset($seen[$k])) {
                $fallos[] = 'mensaje_duplicado:' . substr($k, 0, 80);
            }
            $seen[$k] = true;
        }

        // Relaciones fuera de rango
        $rangosRel = [
            'relaciones_sociales' => ['a_hacia_b.valor', 'b_hacia_a.valor', 'intensidad'],
            'relaciones_romanticas' => ['romance_a_hacia_b', 'romance_b_hacia_a', 'atraccion_a_hacia_b', 'atraccion_b_hacia_a'],
        ];
        foreach ($rangosRel as $bag => $campos) {
            foreach ($partida[$bag] ?? [] as $rel) {
                if (!is_array($rel)) {
                    continue;
                }
                foreach ($campos as $campo) {
                    $parts = explode('.', $campo);
                    $v = $rel;
                    foreach ($parts as $p) {
                        $v = $v[$p] ?? null;
                    }
                    if (is_numeric($v) && ((float) $v < -100 || (float) $v > 100)) {
                        $fallos[] = 'relacion_fuera_rango:' . $bag . ':' . $campo;
                    }
                }
            }
        }

        return array_values(array_unique($fallos));
    }
}
