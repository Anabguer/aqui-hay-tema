<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class BuzonEngine
{
    public const ESTADOS = ['pendiente', 'leido', 'resuelto'];

    public static function crear(array &$partida, array $mensaje): array
    {
        $id = $mensaje['id'] ?? 'msg_' . bin2hex(random_bytes(4));
        $entry = array_merge([
            'id' => $id,
            'de_persona' => null,
            'tipo' => 'peticion',
            'texto' => '',
            'dia' => $partida['reloj']['dia_pueblo'] ?? 1,
            'estado' => 'pendiente',
            'origen' => [
                'evento_id' => null,
                'tipo_evento' => null,
                'es_narrativo' => false,
                'informacion_revelada' => [],
                '_placeholder' => true,
            ],
            '_placeholder_contenido' => true,
        ], $mensaje);

        $partida['buzon'] ??= [];
        $partida['buzon'][] = $entry;
        DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
            'mensaje_id' => $entry['id'],
            'de_persona' => $entry['de_persona'] ?? null,
            'actores' => array_values(array_filter([$entry['de_persona'] ?? null])),
        ]);
        return ['ok' => true, 'mensaje' => $entry];
    }

    public static function marcarLeido(array &$partida, string $mensajeId): array
    {
        foreach ($partida['buzon'] as &$m) {
            if ($m['id'] === $mensajeId) {
                $m['estado'] = 'leido';
                return ['ok' => true, 'mensaje' => $m];
            }
        }
        return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
    }

    public static function listar(array $partida, ?string $estado = null): array
    {
        $items = $partida['buzon'] ?? [];
        if ($estado !== null) {
            $items = array_values(array_filter($items, static fn($m) => ($m['estado'] ?? '') === $estado));
        }
        return $items;
    }
}
