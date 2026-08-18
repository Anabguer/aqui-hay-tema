<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Resumen técnico de lo ocurrido en un intervalo de reloj.
 * Fuente: audit_trail (EventBus/DomainEventDispatcher). No es diario narrativo.
 */
final class AvanceResumen
{
    /** Tipos con valor jugable visible. TIEMPO_AVANZADO queda fuera a propósito. */
    public const TIPOS_JUGABLES = [
        DomainEvents::ENCUENTRO_INICIADO,
        DomainEvents::ENCUENTRO_TERMINADO,
        DomainEvents::ENCUENTRO_CANCELADO,
        DomainEvents::COINCIDENCIA_RESIDENTES,
        DomainEvents::ESTADO_EMOCIONAL_CAMBIADO,
        DomainEvents::RELACION_MODIFICADA,
    ];

    /** @return array{audit: int} */
    public static function snapshot(array $partida): array
    {
        return ['audit' => count($partida['audit_trail'] ?? [])];
    }

    /**
     * @param array{audit: int} $snapshot
     * @return array{lineas: list<array>, total: int}
     */
    public static function desdeSnapshot(array $partida, array $snapshot, int $maxLineas = 10): array
    {
        $offset = max(0, (int) ($snapshot['audit'] ?? 0));
        $nuevos = array_slice($partida['audit_trail'] ?? [], $offset);
        $lineas = [];
        $coincidencias = 0;
        $vistos = [];

        foreach ($nuevos as $e) {
            if (!is_array($e)) {
                continue;
            }
            $tipo = (string) ($e['tipo'] ?? '');
            if ($tipo === DomainEvents::TIEMPO_AVANZADO || $tipo === 'tiempo_avanzado') {
                continue;
            }
            if (!in_array($tipo, self::TIPOS_JUGABLES, true)) {
                continue;
            }
            if ($tipo === DomainEvents::COINCIDENCIA_RESIDENTES) {
                $coincidencias++;
                if ($coincidencias > 2) {
                    continue;
                }
            }
            $cid = (string) ($e['correlacion_id'] ?? '');
            $clave = $tipo . '|' . $cid;
            if ($cid !== '' && isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $lineas[] = self::linea($partida, $e);
            if (count($lineas) >= $maxLineas) {
                break;
            }
        }

        return [
            'lineas' => $lineas,
            'total' => count($lineas),
        ];
    }

    /**
     * @param array<string, mixed> $e
     * @return array{tipo: string, texto: string, ts_juego: mixed}
     */
    private static function linea(array $partida, array $e): array
    {
        $tipo = (string) ($e['tipo'] ?? '');
        $despues = is_array($e['despues'] ?? null) ? $e['despues'] : [];
        $enc = is_array($despues['encuentro'] ?? null) ? $despues['encuentro'] : [];
        $nombres = self::nombresActores($partida, $e['actores'] ?? ($enc['participantes'] ?? []));
        $quien = $nombres !== [] ? implode(' + ', $nombres) : 'Encuentro';
        $hora = self::horaTexto($e['ts_juego'] ?? null);

        $texto = match ($tipo) {
            DomainEvents::ENCUENTRO_INICIADO => "Encuentro {$quien} comenzó ({$hora}).",
            DomainEvents::ENCUENTRO_TERMINADO => "Encuentro {$quien} terminó ({$hora}).",
            DomainEvents::ENCUENTRO_CANCELADO => "Encuentro {$quien} se canceló.",
            DomainEvents::COINCIDENCIA_RESIDENTES => $quien . ' coincidieron en el mismo lugar (' . $hora . ').',
            DomainEvents::ESTADO_EMOCIONAL_CAMBIADO => ($nombres[0] ?? 'Alguien') . ' cambió de estado emocional.',
            DomainEvents::RELACION_MODIFICADA => 'Relación actualizada: ' . $quien . '.',
            default => $tipo,
        };

        return [
            'tipo' => $tipo,
            'texto' => $texto,
            'ts_juego' => $e['ts_juego'] ?? null,
        ];
    }

    /** @param list<mixed> $ids */
    private static function nombresActores(array $partida, array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            $out[] = IdentidadPublica::nombre($partida, $id);
        }
        return $out;
    }

    private static function horaTexto(mixed $ts): string
    {
        if (!is_array($ts)) {
            return '—';
        }
        $dia = (int) ($ts['dia'] ?? 0);
        $hora = (int) ($ts['hora'] ?? 0);
        return 'D' . $dia . ' · ' . str_pad((string) $hora, 2, '0', STR_PAD_LEFT) . ':00';
    }
}
