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
            'texto' => '',
            'origen' => [
                'evento_id' => null,
                'tipo_evento' => null,
                'es_narrativo' => false,
                'informacion_revelada' => [],
                '_placeholder' => true,
            ],
            '_placeholder_contenido' => true,
        ], $entrada);
        $reloj = $partida['reloj'] ?? [];
        $diaMsg = (int) ($entry['dia'] ?? ($reloj['dia_pueblo'] ?? 1));
        $entry['dia'] = $diaMsg;
        $entry['fecha_corta'] = Reloj::fechaCorta($reloj, $diaMsg);
        $entry['fecha_iso'] = Reloj::fechaIso($reloj, $diaMsg);
        $entry['dia_semana_ui'] = Reloj::diaSemanaUi($diaMsg, $reloj);

        $partida['diario'] ??= [];
        $partida['diario'][] = $entry;
        return ['ok' => true, 'entrada' => $entry];
    }

    public static function listarPorDia(array $partida, ?int $dia = null): array
    {
        $dia ??= (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        return array_values(array_filter(
            $partida['diario'] ?? [],
            static fn($e) => (int) ($e['dia'] ?? -1) === $dia
        ));
    }
}
