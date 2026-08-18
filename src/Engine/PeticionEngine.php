<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Residente → necesidad → petición → buzón → el jugador actúa o ignora.
 * Sin catálogo narrativo ni consecuencias numéricas.
 */
final class PeticionEngine
{
    public const TIPOS = ['lugar', 'conocer', 'tiempo', 'relacion', 'actividad', 'servicio', 'objeto', 'otro'];
    public const ESTADOS = ['abierta', 'atendida', 'ignorada', 'caducada', 'resuelta'];

    public static function ensure(array &$partida): void
    {
        $partida['peticiones'] ??= [];
    }

    public static function listar(array $partida, ?string $estado = null): array
    {
        $items = $partida['peticiones'] ?? [];
        if ($estado !== null) {
            $items = array_values(array_filter(
                $items,
                static function ($p) use ($estado) {
                    return ($p['estado'] ?? '') === $estado;
                }
            ));
        }
        return $items;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public static function crear(array &$partida, string $residenteId, string $tipo, array $datos = [], ?GameLogger $logger = null): array
    {
        self::ensure($partida);
        if (!isset($partida['residentes'][$residenteId])) {
            return GameError::respuesta(GameError::PARTICIPANTE_INEXISTENTE, ['residente' => $residenteId]);
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            return GameError::respuesta(GameError::VALIDACION_FALLIDA, ['tipo' => $tipo]);
        }

        $rng = RngService::fromPartida($partida);
        $id = 'pet_' . bin2hex(substr(pack('N', $rng->next()), 0, 4));
        $rng->persistToPartida($partida);

        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $peticion = [
            'id' => $id,
            'residente_id' => $residenteId,
            'tipo' => $tipo,
            'estado' => 'abierta',
            'dia_creada' => $dia,
            'hora_creada' => $hora,
            'plazo_dia' => $datos['plazo_dia'] ?? null,
            'plazo_hora' => $datos['plazo_hora'] ?? null,
            'objetivo' => $datos['objetivo'] ?? null,
            'texto_placeholder' => $datos['texto_placeholder'] ?? null,
            'buzon_id' => null,
            'evolucion_si_ignora' => null,
            'resultado' => null,
            '_bloqueado_decision' => ['catalogo_narrativo', 'consecuencia_si_ignora', 'cantidad_generada'],
            '_placeholder_copy' => true,
        ];

        $partida['peticiones'][] = $peticion;

        $buzon = BuzonEngine::crear($partida, [
            'de_persona' => $residenteId,
            'tipo' => 'peticion',
            'texto' => $peticion['texto_placeholder'] ?? '[PLACEHOLDER] Petición de residente',
            'peticion_id' => $id,
            'origen' => [
                'evento_id' => $id,
                'tipo_evento' => 'peticion',
                'es_narrativo' => false,
                'informacion_revelada' => [],
                '_placeholder' => true,
            ],
        ]);
        $buzonId = $buzon['mensaje']['id'] ?? null;
        $last = count($partida['peticiones']) - 1;
        $partida['peticiones'][$last]['buzon_id'] = $buzonId;
        $peticion['buzon_id'] = $buzonId;

        DomainEventDispatcher::emit($partida, DomainEvents::PETICION_CREADA, [
            'peticion' => $peticion,
            'actores' => [$residenteId],
        ], $logger, 'PeticionEngine::crear', [$residenteId]);

        return ['ok' => true, 'peticion' => $peticion];
    }

    /** @return array<string, mixed> */
    public static function atender(array &$partida, string $peticionId, ?GameLogger $logger = null): array
    {
        return self::cambiarEstado($partida, $peticionId, 'atendida', $logger);
    }

    /** @return array<string, mixed> */
    public static function ignorar(array &$partida, string $peticionId, ?GameLogger $logger = null): array
    {
        $r = self::cambiarEstado($partida, $peticionId, 'ignorada', $logger);
        if ($r['ok'] ?? false) {
            $r['peticion']['evolucion_si_ignora'] = null;
            $r['_bloqueado_decision'] = ['consecuencia_si_ignora'];
        }
        return $r;
    }

    /** @return array<string, mixed> */
    public static function resolver(array &$partida, string $peticionId, ?GameLogger $logger = null): array
    {
        return self::cambiarEstado($partida, $peticionId, 'resuelta', $logger);
    }

    public static function caducarVencidas(array &$partida, ?GameLogger $logger = null): int
    {
        self::ensure($partida);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $now = $dia * 24 + $hora;
        $n = 0;
        foreach ($partida['peticiones'] as &$p) {
            if (($p['estado'] ?? '') !== 'abierta') {
                continue;
            }
            if ($p['plazo_dia'] === null) {
                continue;
            }
            $plazoHora = $p['plazo_hora'] !== null ? (int) $p['plazo_hora'] : 0;
            $t = ((int) $p['plazo_dia']) * 24 + $plazoHora;
            if ($now >= $t) {
                $p['estado'] = 'caducada';
                $n++;
                DomainEventDispatcher::emit($partida, DomainEvents::PETICION_CADUCADA, [
                    'peticion' => $p,
                    'actores' => [$p['residente_id'] ?? null],
                ], $logger, 'PeticionEngine::caducar', [$p['residente_id'] ?? '']);
            }
        }
        unset($p);
        return $n;
    }

    /**
     * @return array<string, mixed>
     */
    private static function cambiarEstado(array &$partida, string $peticionId, string $hacia, ?GameLogger $logger = null): array
    {
        self::ensure($partida);
        if (!in_array($hacia, self::ESTADOS, true)) {
            return GameError::respuesta(GameError::TRANSICION_INVALIDA, ['hacia' => $hacia]);
        }
        foreach ($partida['peticiones'] as &$p) {
            if (($p['id'] ?? '') !== $peticionId) {
                continue;
            }
            $desde = $p['estado'] ?? '';
            if ($desde !== 'abierta' && $hacia !== $desde) {
                return GameError::respuesta(GameError::TRANSICION_INVALIDA, [
                    'desde' => $desde,
                    'hacia' => $hacia,
                ]);
            }
            $p['estado'] = $hacia;
            \aht_log_optional($logger, $partida, 'peticion_estado', [
                'peticion_id' => $peticionId,
                'desde' => $desde,
                'hacia' => $hacia,
            ]);
            return ['ok' => true, 'peticion' => $p];
        }
        return GameError::respuesta(GameError::PETICION_NO_ENCONTRADA, ['peticion_id' => $peticionId]);
    }
}
