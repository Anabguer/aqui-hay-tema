<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Memoria reciente / cooldowns. Registrar sí; suprimir repetición solo si hay ventana configurada.
 */
final class MemoriaEventos
{
    public static function ensure(array &$partida): void
    {
        $partida['memoria_eventos'] ??= [];
    }

    /**
     * Compacta memoria_eventos preservando hitos narrativos.
     *
     * Hito narrativo = entrada con intensidad >= 3 O resultado_experiencia no nulo O familia significativa.
     * Se preservan los últimos N hitos + los últimos entries que no son hito hasta llegar al cap.
     */
    public static function compactar(array &$partida, int $cap): void
    {
        self::ensure($partida);
        $items = $partida['memoria_eventos'];
        if (count($items) <= $cap) {
            return;
        }
        $hitoFamilias = [
            'encuentro', 'conflicto', 'reconciliacion', 'declaracion',
            'ruptura', 'boda', 'llegada', 'marcha', 'promesa',
        ];
        $hitos = [];
        $noHitos = [];
        foreach ($items as $i => $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $esHito = ((int) ($ev['intensidad'] ?? 0)) >= 3
                || ($ev['resultado_experiencia'] ?? null) !== null
                || in_array($ev['familia'] ?? '', $hitoFamilias, true);
            if ($esHito) {
                $hitos[$i] = $ev;
            } else {
                $noHitos[$i] = $ev;
            }
        }
        $maxHitos = (int) ceil($cap * 0.6);
        $maxNoHitos = $cap - $maxHitos;
        $lastHitos = array_slice($hitos, -$maxHitos, preserve_keys: true);
        $lastNoHitos = array_slice($noHitos, -$maxNoHitos, preserve_keys: true);
        $mantener = array_merge($lastNoHitos, $lastHitos);
        usort($mantener, static function (array $a, array $b): int {
            $ta = ((int) ($a['dia'] ?? 0)) * 24 + (int) ($a['hora'] ?? 0);
            $tb = ((int) ($b['dia'] ?? 0)) * 24 + (int) ($b['hora'] ?? 0);
            return $ta <=> $tb;
        });
        $partida['memoria_eventos'] = array_values($mantener);
    }

    /**
     * @param list<string> $participantes
     * @return array<string, mixed>
     */
    public static function registrar(
        array &$partida,
        string $familia,
        array $participantes,
        ?int $intensidad = null,
        ?string $tipo = null,
        ?string $resultadoExperiencia = null
    ): array {
        self::ensure($partida);
        $reloj = $partida['reloj'] ?? [];
        $entry = [
            'familia' => $familia,
            'tipo' => $tipo ?? $familia,
            'participantes' => array_values($participantes),
            'dia' => (int) ($reloj['dia_pueblo'] ?? 1),
            'hora' => (int) ($reloj['hora_actual'] ?? 0),
            'intensidad' => $intensidad,
            'resultado_experiencia' => $resultadoExperiencia,
        ];
        $partida['memoria_eventos'][] = $entry;
        // Mantener ultimo_contacto_social_dia en runtime para acceso rápido (excluye actividad_individual)
        if ($familia !== 'actividad_individual' && count($participantes) >= 2) {
            $diaCont = (int) ($reloj['dia_pueblo'] ?? 1);
            foreach ($participantes as $pid) {
                $pid = (string) $pid;
                if (isset($partida['residentes'][$pid]['runtime'])) {
                    $actual = (int) ($partida['residentes'][$pid]['runtime']['ultimo_contacto_social_dia'] ?? 0);
                    if ($diaCont > $actual) {
                        $partida['residentes'][$pid]['runtime']['ultimo_contacto_social_dia'] = $diaCont;
                    }
                }
            }
        }
        return $entry;
    }

    /**
     * @param list<string> $participantes
     * @return list<array<string, mixed>>
     */
    public static function recientes(array $partida, array $participantes, int $limite = 5): array
    {
        $items = $partida['memoria_eventos'] ?? [];
        $out = [];
        foreach (array_reverse($items) as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $p = $ev['participantes'] ?? [];
            $hit = false;
            foreach ($participantes as $id) {
                if (in_array($id, $p, true)) {
                    $hit = true;
                    break;
                }
            }
            if ($hit) {
                $out[] = $ev;
            }
            if (count($out) >= $limite) {
                break;
            }
        }
        return $out;
    }

    /**
     * True si la familia está en cooldown configurado. Sin ventanas = nunca suprime.
     *
     * @param list<string> $participantes
     */
    public static function enCooldown(array $partida, string $familia, array $participantes, array $cal): bool
    {
        $ventanas = CalibracionConfig::get($cal, 'cooldowns.por_familia', []);
        if (!is_array($ventanas) || !isset($ventanas[$familia]) || $ventanas[$familia] === null) {
            return false;
        }
        $horas = (int) $ventanas[$familia];
        if ($horas <= 0) {
            return false;
        }
        $now = ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24 + (int) ($partida['reloj']['hora_actual'] ?? 0);
        foreach ($partida['memoria_eventos'] ?? [] as $ev) {
            if (($ev['familia'] ?? '') !== $familia) {
                continue;
            }
            $overlap = false;
            foreach ($participantes as $id) {
                if (in_array($id, $ev['participantes'] ?? [], true)) {
                    $overlap = true;
                    break;
                }
            }
            if (!$overlap) {
                continue;
            }
            $t = ((int) ($ev['dia'] ?? 0)) * 24 + (int) ($ev['hora'] ?? 0);
            if ($now - $t < $horas) {
                return true;
            }
        }
        return false;
    }
}
