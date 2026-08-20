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
        $terminadosIds = [];
        foreach ($nuevos as $e) {
            if (!is_array($e)) {
                continue;
            }
            if ((string) ($e['tipo'] ?? '') !== DomainEvents::ENCUENTRO_TERMINADO) {
                continue;
            }
            $encId = self::encuentroIdDeEvento($e);
            if ($encId !== null && !in_array($encId, $terminadosIds, true)) {
                $terminadosIds[] = $encId;
            }
        }

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
            if ($tipo === DomainEvents::ENCUENTRO_INICIADO || $tipo === DomainEvents::ENCUENTRO_TERMINADO) {
                $despues = is_array($e['despues'] ?? null) ? $e['despues'] : [];
                $payload = is_array($e['payload'] ?? null) ? $e['payload'] : [];
                $enc = is_array($despues['encuentro'] ?? null)
                    ? $despues['encuentro']
                    : (is_array($payload['encuentro'] ?? null) ? $payload['encuentro'] : []);
                if (($enc['tipo'] ?? '') === 'individual' && ($enc['intencion'] ?? '') === 'autonomo') {
                    continue;
                }
            }
            if ($tipo === DomainEvents::COINCIDENCIA_RESIDENTES) {
                $coincidencias++;
                if (!CotilleoNarrativo::coincidenciaDigna($partida, $e, [])) {
                    continue;
                }
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
            'encuentros_terminados_ids' => $terminadosIds,
            'encuentros_terminados_count' => count($terminadosIds),
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
        $hora = self::horaTexto($partida, $e['ts_juego'] ?? null);

        switch ($tipo) {
            case DomainEvents::ENCUENTRO_INICIADO:
                $texto = "Encuentro {$quien} comenzó ({$hora}).";
                break;
            case DomainEvents::ENCUENTRO_TERMINADO:
                $texto = "Encuentro {$quien} terminó ({$hora}).";
                break;
            case DomainEvents::ENCUENTRO_CANCELADO:
                $texto = "Encuentro {$quien} se canceló.";
                break;
            case DomainEvents::COINCIDENCIA_RESIDENTES:
                $texto = $quien . ' coincidieron en el mismo lugar (' . $hora . ').';
                break;
            case DomainEvents::ESTADO_EMOCIONAL_CAMBIADO:
                $texto = ($nombres[0] ?? 'Alguien') . ' cambió de estado emocional.';
                break;
            case DomainEvents::RELACION_MODIFICADA:
                $texto = 'Relación actualizada: ' . $quien . '.';
                break;
            default:
                $texto = $tipo;
        }

        $ts = is_array($e['ts_juego'] ?? null) ? $e['ts_juego'] : [];
        return [
            'tipo' => $tipo,
            'texto' => $texto,
            'ts_juego' => $e['ts_juego'] ?? null,
            'encuentro_id' => self::encuentroIdDeEvento($e),
            'debug' => [
                'dia_pueblo' => (int) ($ts['dia'] ?? 0),
                'hora' => (int) ($ts['hora'] ?? 0),
            ],
        ];
    }

    /** @param array<string, mixed> $e */
    private static function encuentroIdDeEvento(array $e): ?string
    {
        $despues = is_array($e['despues'] ?? null) ? $e['despues'] : [];
        $enc = is_array($despues['encuentro'] ?? null) ? $despues['encuentro'] : [];
        $id = $enc['id'] ?? $despues['encuentro_id'] ?? null;
        return is_string($id) && $id !== '' ? $id : null;
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

    private static function horaTexto(array $partida, $ts): string
    {
        if (!is_array($ts)) {
            return '—';
        }
        $dia = (int) ($ts['dia'] ?? 0);
        $hora = (int) ($ts['hora'] ?? 0);
        if ($dia < 1) {
            return '—';
        }
        return Reloj::formatearDiaHora($partida['reloj'] ?? [], $dia, $hora);
    }
}
