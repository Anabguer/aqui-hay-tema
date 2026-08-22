<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Filtros de acontecimiento. Edad en romance es veto duro; en ocio es peso (no veto). */
final class AcontecimientoElegibilidad
{
    /**
     * @param array<string, mixed> $item
     * @param list<string> $ids
     * @param array<string, mixed> $cal
     * @return array{ok: bool, fallos: list<string>}
     */
    public static function cumple(array $partida, array $item, array $ids, array $cal): array
    {
        $fallos = [];
        $conds = is_array($item['condiciones'] ?? null) ? $item['condiciones'] : [];
        $a = $ids[0] ?? '';
        $b = $ids[1] ?? '';
        foreach ($conds as $c) {
            $c = (string) $c;
            if ($c === 'empleado' && !self::empleado($partida, $a)) {
                $fallos[] = $c;
            }
            if ($c === 'desempleado' && self::empleado($partida, $a)) {
                $fallos[] = $c;
            }
            if ($c === 'se_conocen' && $b !== '' && !RelacionEngine::seConocen($partida, $a, $b)) {
                $fallos[] = $c;
            }
            if ($c === 'se_conocen_alguien' && !self::conoceAAlguien($partida, $a)) {
                $fallos[] = $c;
            }
            if ($c === 'romance_elegible_edad' && $b !== '') {
                $edad = EdadPolitica::clasificar(
                    PerfilPartida::edadResuelta($partida, $a),
                    PerfilPartida::edadResuelta($partida, $b),
                    $cal
                );
                if (!($edad['romance_elegible'] ?? true)) {
                    $fallos[] = $c;
                }
            }
            if ($c === 'no_parentesco_veto' && $b !== '' && ParentescoVeto::bloqueaRomance($partida, $a, $b, $cal)) {
                $fallos[] = $c;
            }
            if ($c === 'interes_o_historia' && $b !== '' && !self::interesOHistoria($partida, $a, $b)) {
                $fallos[] = $c;
            }
            if ($c === 'son_pareja' && $b !== '') {
                $est = ParejaEngine::estado($partida, $a, $b);
                if ($est !== ParejaEngine::PAREJA && $est !== ParejaEngine::CRISIS) {
                    $fallos[] = $c;
                }
            }
            if ($c === 'son_pareja_o_crisis' && $b !== '') {
                $est = ParejaEngine::estado($partida, $a, $b);
                if ($est !== ParejaEngine::PAREJA && $est !== ParejaEngine::CRISIS) {
                    $fallos[] = $c;
                }
            }
            if ($c === 'fueron_pareja' && $b !== '' && !self::fueronPareja($partida, $a, $b)) {
                $fallos[] = $c;
            }
            if ($c === 'no_son_pareja' && $b !== '') {
                $est = ParejaEngine::estado($partida, $a, $b);
                if ($est === ParejaEngine::PAREJA || $est === ParejaEngine::CRISIS) {
                    $fallos[] = $c;
                }
            }
        }
        $need = (int) ($item['participantes'] ?? 1);
        if (count(array_filter($ids, static function ($x) {
            return is_string($x) && $x !== '';
        })) < $need) {
            $fallos[] = 'participantes';
        }
        return ['ok' => $fallos === [], 'fallos' => $fallos];
    }

    public static function empleado(array $partida, string $id): bool
    {
        $oc = $partida['residentes'][$id]['runtime']['ocupacion'] ?? null;
        if (!is_string($oc) || $oc === '') {
            return false;
        }
        return $oc !== 'desempleado' && $oc !== 'jubilado' && $oc !== 'ninguna';
    }

    public static function conoceAAlguien(array $partida, string $id): bool
    {
        foreach ($partida['relaciones_sociales'] ?? [] as $rel) {
            if (!is_array($rel) || empty($rel['conocidos'])) {
                continue;
            }
            if (($rel['persona_a'] ?? '') === $id || ($rel['persona_b'] ?? '') === $id) {
                return true;
            }
        }
        return false;
    }

    public static function interesOHistoria(array $partida, string $a, string $b): bool
    {
        if (RelacionBitacora::familiaCopy($partida, $a, $b) !== 'desconocidos') {
            return true;
        }
        $v = RelacionEngine::romanceHacia($partida, $a, $b);
        return $v !== null && $v !== 0;
    }

    public static function fueronPareja(array $partida, string $a, string $b): bool
    {
        if (ParejaEngine::estado($partida, $a, $b) === ParejaEngine::EX) {
            return true;
        }
        return RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::RUPTURA)
            || RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::INICIO_PAREJA);
    }

    /**
     * Candidatos para un item. Edad en ocio no veta.
     *
     * @param array<string, mixed> $item
     * @param array<string, mixed> $cal
     * @return list<list<string>>
     */
    public static function candidatos(array $partida, array $item, array $cal): array
    {
        $need = (int) ($item['participantes'] ?? 1);
        $ids = array_keys($partida['residentes'] ?? []);
        $out = [];
        if ($need <= 1) {
            foreach ($ids as $id) {
                $r = self::cumple($partida, $item, [(string) $id], $cal);
                if ($r['ok']) {
                    $out[] = [(string) $id];
                }
            }
            return $out;
        }
        $n = count($ids);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    continue;
                }
                $r = self::cumple($partida, $item, [(string) $ids[$i], (string) $ids[$j]], $cal);
                if ($r['ok']) {
                    $out[] = [(string) $ids[$i], (string) $ids[$j]];
                }
            }
        }
        return $out;
    }
}
