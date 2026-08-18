<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class DiscoveryEngine
{
    public const DESCONOCIDO = 'desconocido';
    public const DESCUBIERTO = 'descubierto';

    public static function ensure(array &$partida): void
    {
        $partida['descubrimientos'] ??= [];
    }

    public static function registrar(
        array &$partida,
        string $residenteId,
        string $campo,
        mixed $valor,
        string $origen,
        ?string $correlacionId = null,
        ?string $eventoRelacionado = null
    ): array {
        self::ensure($partida);
        $entry = [
            'id' => 'disc_' . bin2hex(random_bytes(4)),
            'residente_id' => $residenteId,
            'campo' => $campo,
            'valor' => $valor,
            'estado' => self::DESCUBIERTO,
            'origen' => $origen,
            'evento_relacionado' => $eventoRelacionado,
            'correlacion_id' => $correlacionId,
            'ts_juego' => [
                'dia' => $partida['reloj']['dia_pueblo'] ?? null,
                'hora' => $partida['reloj']['hora_actual'] ?? null,
            ],
        ];
        $partida['descubrimientos'][] = $entry;
        DomainEventDispatcher::emit($partida, DomainEvents::DESCUBRIMIENTO_REGISTRADO, [
            'residente_id' => $residenteId,
            'campo' => $campo,
            'origen' => $origen,
            'actores' => [$residenteId],
        ]);
        PersistenciaCaps::recortarLista(
            $partida,
            'descubrimientos',
            PersistenciaCaps::cap($partida, 'descubrimientos_cap', 400),
            'descubrimientos_archivo'
        );
        return $entry;
    }

    public static function estado(array $partida, string $residenteId, string $campo): string
    {
        foreach (array_reverse($partida['descubrimientos'] ?? []) as $d) {
            if ($d['residente_id'] === $residenteId && $d['campo'] === $campo) {
                return $d['estado'] ?? self::DESCUBIERTO;
            }
        }
        return self::DESCONOCIDO;
    }

    public static function listarPorResidente(array $partida, string $residenteId): array
    {
        return array_values(array_filter(
            $partida['descubrimientos'] ?? [],
            static fn(array $d) => ($d['residente_id'] ?? '') === $residenteId
        ));
    }
}
