<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class EncuentroEngine
{
    public const ESTADOS = ['programado', 'en_curso', 'terminado', 'cancelado'];
    public const TIPOS = ['conocerse', 'amistad', 'romantico', 'conflicto', 'otro'];

    public static function list(array $partida): array
    {
        return $partida['encuentros'] ?? $partida['citas'] ?? [];
    }

    public static function programar(
        array &$partida,
        array $participantes,
        int $dia,
        int $hora,
        string $tipo = 'conocerse',
        ?string $lugarId = null,
        ?string $actividad = null,
        ?GameLogger $logger = null
    ): array {
        $participantes = array_values(array_unique(array_filter($participantes)));
        if (count($participantes) < 2) {
            return ['ok' => false, 'error' => 'participantes_insuficientes'];
        }

        foreach ($participantes as $rid) {
            if (!isset($partida['residentes'][$rid])) {
                return array_merge(GameError::respuesta(GameError::PARTICIPANTE_INEXISTENTE, ['residente' => $rid]), ['residente' => $rid]);
            }
            if (($partida['residentes'][$rid]['presencia'] ?? '') !== 'residente') {
                return array_merge(GameError::respuesta(GameError::RESIDENTE_NO_ACTIVO, ['residente' => $rid]), ['residente' => $rid]);
            }
        }

        if (!in_array($tipo, self::TIPOS, true)) {
            return ['ok' => false, 'error' => 'tipo_invalido'];
        }

        $limite = self::limiteIntervencionesDia($partida);
        if ($limite !== null) {
            $usadas = (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
            if ($usadas >= $limite) {
                $logger?->log($partida, 'encuentro_rechazado', [
                    'regla' => 'limite_intervenciones_celeste',
                    'usadas' => $usadas,
                    'limite' => $limite,
                ]);
                return array_merge(GameError::respuesta(GameError::LIMITE_INTERVENCIONES, ['limite' => $limite]), ['limite' => $limite]);
            }
        }

        $lugarId ??= 'lug_cafeteria';
        $operativos = $partida['celeste']['lugares_desbloqueados'] ?? [];
        if (!in_array($lugarId, $operativos, true)) {
            $logger?->log($partida, 'encuentro_rechazado', ['regla' => 'lugar_no_operativo', 'lugar' => $lugarId]);
            return array_merge(GameError::respuesta(GameError::LUGAR_NO_OPERATIVO, ['lugar' => $lugarId]), ['lugar' => $lugarId]);
        }

        foreach ($participantes as $rid) {
            $disp = AgendaEngine::estaDisponible($partida, $rid, $dia, $hora);
            if (!$disp['disponible']) {
                $logger?->log($partida, 'agenda_rechazo', [
                    'residente' => $rid,
                    'regla' => $disp['motivo'] ?? 'ocupado',
                    'detalle' => $disp,
                    '_placeholder_rechazo_narrativo' => true,
                ]);
                return array_merge(
                    GameError::respuesta(GameError::AGENDA_SLOT_OCUPADO, ['residente' => $rid, 'detalle' => $disp]),
                    ['residente' => $rid, 'detalle' => $disp]
                );
            }
        }

        if (self::hayConflictoHorario($partida, $participantes, $dia, $hora)) {
            return GameError::respuesta(GameError::DOBLE_RESERVA);
        }

        $rng = RngService::fromPartida($partida);
        $encId = 'enc_' . bin2hex(substr(pack('N', $rng->next()), 0, 4));
        $rng->persistToPartida($partida);

        $encuentro = [
            'id' => $encId,
            'tipo' => $tipo,
            'intencion' => 'celeste_organizado',
            'participantes' => $participantes,
            'lugar' => $lugarId,
            'hora' => $hora,
            'dia' => $dia,
            'actividad' => $actividad,
            'estado' => 'programado',
            'reserva_agenda' => ['tipo' => 'encuentro', 'origen' => 'celeste'],
            'resultado' => null,
            '_placeholder_resultado' => true,
        ];

        $partida['encuentros'] ??= [];
        $partida['encuentros'][] = $encuentro;
        $partida['celeste']['intervenciones_organizadas_usadas_hoy'] =
            (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0) + 1;

        $logger?->log($partida, 'encuentro_programado', [
            'encuentro_id' => $encId,
            'tipo' => $tipo,
            'participantes' => $participantes,
        ]);

        return ['ok' => true, 'encuentro' => $encuentro];
    }

    public static function limiteIntervencionesDia(array $partida): ?int
    {
        $max = $partida['celeste']['intervenciones_organizadas_max_dia'] ?? null;
        if ($max !== null) {
            return (int) $max;
        }
        $dev = $partida['celeste']['_config_limite_diario']['valor_dev'] ?? null;
        return $dev !== null ? (int) $dev : null;
    }

    public static function hayConflictoHorario(array $partida, array $participantes, int $dia, int $hora): bool
    {
        foreach (self::list($partida) as $enc) {
            if ((int) ($enc['dia'] ?? -1) !== $dia || (int) ($enc['hora'] ?? -1) !== $hora) {
                continue;
            }
            if (!in_array($enc['estado'] ?? '', ['programado', 'en_curso'], true)) {
                continue;
            }
            foreach ($enc['participantes'] ?? [] as $p) {
                if (in_array($p, $participantes, true)) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function cambiarEstado(array &$partida, string $encuentroId, string $nuevoEstado): array
    {
        if (!in_array($nuevoEstado, self::ESTADOS, true)) {
            return ['ok' => false, 'error' => 'estado_invalido'];
        }
        foreach ($partida['encuentros'] as &$enc) {
            if ($enc['id'] !== $encuentroId) {
                continue;
            }
            $prev = $enc['estado'] ?? '';
            if (!self::transicionValida($prev, $nuevoEstado)) {
                return array_merge(
                    GameError::respuesta(GameError::TRANSICION_INVALIDA, ['desde' => $prev, 'hacia' => $nuevoEstado]),
                    ['desde' => $prev, 'hacia' => $nuevoEstado]
                );
            }
            $enc['estado'] = $nuevoEstado;
            return ['ok' => true, 'encuentro' => $enc];
        }
        return ['ok' => false, 'error' => 'encuentro_no_encontrado'];
    }

    public static function transicionValida(string $desde, string $hacia): bool
    {
        if ($desde === $hacia) {
            return true;
        }
        return match ($desde) {
            'programado' => in_array($hacia, ['en_curso', 'cancelado'], true),
            'en_curso' => in_array($hacia, ['terminado', 'cancelado'], true),
            default => false,
        };
    }

    public static function cancelar(array &$partida, string $encuentroId): array
    {
        return self::cambiarEstado($partida, $encuentroId, 'cancelado');
    }

    public static function listarActivos(array $partida): array
    {
        return array_values(array_filter(
            self::list($partida),
            static fn(array $e) => in_array($e['estado'] ?? '', ['programado', 'en_curso'], true)
        ));
    }
}
