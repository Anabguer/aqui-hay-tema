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
