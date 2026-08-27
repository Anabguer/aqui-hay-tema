<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class DiarioEngine
{
    public static function crear(array &$partida, array $entrada): array
    {
        $id = $entrada['id'] ?? 'dia_' . bin2hex(random_bytes(4));
        $entry = array_merge([
            'id' => $id,
            'dia' => $partida['reloj']['dia_pueblo'] ?? 1,
            'tipo' => 'ruido',
            'titulo' => null,
            'texto' => '',
            'consecuencias' => [],
            'actores' => [],
            'origen' => [
                'evento_id' => null,
                'tipo_evento' => null,
                'es_narrativo' => false,
                'informacion_revelada' => [],
                '_placeholder' => true,
            ],
            '_placeholder_contenido' => true,
        ], $entrada);
        $eventoId = (string) ($entry['origen']['evento_id'] ?? '');
        if ($eventoId !== '') {
            $existente = self::entradaPorEvento($partida, $eventoId);
            if ($existente !== null) {
                return ['ok' => true, 'duplicado' => true, 'entrada' => $existente];
            }
        }
        $reloj = $partida['reloj'] ?? [];
        $diaMsg = (int) ($entry['dia'] ?? ($reloj['dia_pueblo'] ?? 1));
        $entry['dia'] = $diaMsg;
        $entry['fecha_corta'] = Reloj::fechaCorta($reloj, $diaMsg);
        $entry['fecha_iso'] = Reloj::fechaIso($reloj, $diaMsg);
        $entry['dia_semana_ui'] = Reloj::diaSemanaUi($diaMsg, $reloj);

        $partida['diario'] ??= [];
        $partida['diario'][] = $entry;
        return ['ok' => true, 'duplicado' => false, 'entrada' => $entry];
    }

    public static function listarPorDia(array $partida, ?int $dia = null): array
    {
        $dia ??= (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        return array_values(array_filter(
            $partida['diario'] ?? [],
            static fn($e) => (int) ($e['dia'] ?? -1) === $dia
        ));
    }

    /** Entrada por origen.evento_id (idempotencia). */
    public static function entradaPorEvento(array $partida, string $eventoId): ?array
    {
        if ($eventoId === '') {
            return null;
        }
        foreach ($partida['diario'] ?? [] as $e) {
            if (is_array($e) && (($e['origen']['evento_id'] ?? null)) === $eventoId) {
                return $e;
            }
        }
        return null;
    }

    /** Acontecimientos del vecino, más reciente primero. Solo lectura. */
    public static function listarPorResidente(array $partida, string $residenteId): array
    {
        $out = [];
        foreach ($partida['diario'] ?? [] as $e) {
            if (!is_array($e)) {
                continue;
            }
            if (in_array($residenteId, is_array($e['actores'] ?? null) ? $e['actores'] : [], true)) {
                $out[] = $e;
            }
        }
        usort($out, static fn(array $a, array $b) => ($b['dia'] ?? 0) <=> ($a['dia'] ?? 0));
        return $out;
    }
}
