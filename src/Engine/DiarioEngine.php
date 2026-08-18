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

        $partida['diario'] ??= [];
        $partida['diario'][] = $entry;
        DomainEventDispatcher::emit($partida, DomainEvents::DIARIO_ENTRADA, [
            'entrada_id' => $entry['id'],
            'tipo' => $entry['tipo'] ?? null,
        ]);
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
