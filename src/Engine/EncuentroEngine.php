<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class EncuentroEngine
{
    public const ESTADOS = ['programado', 'en_curso', 'terminado', 'cancelado'];
    public const TIPOS = [
        'conocerse',
        'quedar',
        'amistad',
        'primera_cita',
        'cita',
        'romantico',
        'conflicto',
        'otro',
        'individual',
    ];

    public static function list(array $partida): array
    {
        return $partida['encuentros'] ?? $partida['citas'] ?? [];
    }

    /**
     * Validación compartida (participantes, tipo, límite, lugar).
     * No evalúa agenda ni voluntad: eso lo hace programar (legacy) o PropuestaEncuentroEngine.
     *
     * @param string[] $participantes
     * @return array<string, mixed>
     */
    public static function validarContexto(
        array $partida,
        array $participantes,
        string $tipo = 'conocerse',
        ?string $lugarId = null,
        ?GameLogger $logger = null,
        bool $intervencionCeleste = true
    ): array {
        $crudos = [];
        foreach ($participantes as $rid) {
            if (is_string($rid) && $rid !== '') {
                $crudos[] = $rid;
            }
        }
        $unicos = array_values(array_unique($crudos));
        if (count($crudos) >= 2 && count($unicos) === 1) {
            return GameError::respuesta(GameError::MISMA_PERSONA, [
                'causa' => OrganizarMotivo::MISMA_PERSONA,
            ]);
        }
        $participantes = $unicos;
        $min = $tipo === 'individual' ? 1 : 2;
        if (count($participantes) < $min) {
            return ['ok' => false, 'error' => 'participantes_insuficientes'];
        }
        if ($tipo === 'individual' && count($participantes) !== 1) {
            return ['ok' => false, 'error' => 'individual_un_participante'];
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

        $limite = $intervencionCeleste ? self::limiteIntervencionesDia($partida) : null;
        if ($limite !== null) {
            $usadas = (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
            if ($usadas >= $limite) {
                \aht_log_optional($logger, $partida, 'encuentro_rechazado', [
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
            \aht_log_optional($logger, $partida, 'encuentro_rechazado', ['regla' => 'lugar_no_operativo', 'lugar' => $lugarId]);
            return array_merge(GameError::respuesta(GameError::LUGAR_NO_OPERATIVO, ['lugar' => $lugarId]), ['lugar' => $lugarId]);
        }

        return ['ok' => true, 'participantes' => $participantes, 'lugar' => $lugarId, 'tipo' => $tipo];
    }

    public static function programar(
        array &$partida,
        array $participantes,
        int $dia,
        int $hora,
        string $tipo = 'conocerse',
        ?string $lugarId = null,
        ?string $actividad = null,
        ?GameLogger $logger = null,
        bool $intervencionCeleste = true
    ): array {
        $ctx = self::validarContexto($partida, $participantes, $tipo, $lugarId, $logger, $intervencionCeleste);
        if (!($ctx['ok'] ?? false)) {
            return $ctx;
        }
        if (!Reloj::esFuturo($partida['reloj'] ?? [], $dia, $hora)) {
            return GameError::respuesta(GameError::HORA_PASADA, ['dia' => $dia, 'hora' => $hora]);
        }
        $participantes = $ctx['participantes'];
        $lugarId = $ctx['lugar'];

        if (!ComplejoCatalog::estaAbierto((string) $lugarId, $hora)) {
            \aht_log_optional($logger, $partida, 'encuentro_rechazado', [
                'regla' => 'lugar_cerrado',
                'lugar' => $lugarId,
                'hora' => $hora,
            ]);
            return array_merge(
                GameError::respuesta(GameError::LUGAR_CERRADO, ['lugar' => $lugarId, 'hora' => $hora]),
                ['lugar' => $lugarId, 'hora' => $hora]
            );
        }

        $attr = LugarAtributos::de($lugarId);
        $rest = ComplejoCatalog::horasRestantesAbiertas((string) $lugarId, $hora);
        if ($rest < 1) {
            return array_merge(
                GameError::respuesta(GameError::LUGAR_CERRADO, ['lugar' => $lugarId, 'hora' => $hora]),
                ['lugar' => $lugarId, 'hora' => $hora]
            );
        }
        $durH = min(max(1, (int) ($attr['horas'] ?? 1)), $rest);

        foreach ($participantes as $rid) {
            $disp = AgendaEngine::estaDisponibleIntervalo($partida, $rid, $dia, $hora, $durH);
            if (!$disp['disponible']) {
                \aht_log_optional($logger, $partida, 'agenda_rechazo', [
                    'residente' => $rid,
                    'regla' => $disp['motivo'] ?? 'ocupado',
                    'detalle' => $disp,
                    '_placeholder_rechazo_narrativo' => true,
                ]);
                $nombre = IdentidadPublica::nombre($partida, $rid);
                return array_merge(
                    GameError::respuesta(GameError::AGENDA_SLOT_OCUPADO, [
                        'residente' => $rid,
                        'residente_nombre' => $nombre,
                        'detalle' => $disp,
                    ]),
                    ['residente' => $rid, 'residente_nombre' => $nombre, 'detalle' => $disp]
                );
            }
        }

        if (self::hayConflictoHorario($partida, $participantes, $dia, $hora, $durH)) {
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
            'duracion_minutos' => min((int) ($attr['duracion_minutos'] ?? 60), $durH * 60),
            'duracion_horas' => $durH,
            'estado' => 'programado',
            'reserva_agenda' => ['tipo' => 'encuentro', 'origen' => 'celeste'],
            'resultado' => null,
            '_placeholder_resultado' => true,
        ];

        $partida['encuentros'] ??= [];
        $partida['encuentros'][] = $encuentro;
        if ($intervencionCeleste && $tipo !== 'individual') {
            $partida['celeste']['intervenciones_organizadas_usadas_hoy'] =
                (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0) + 1;
        }

        \aht_log_optional($logger, $partida, 'encuentro_programado', [
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

    public static function hayConflictoHorario(
        array $partida,
        array $participantes,
        int $dia,
        int $hora,
        int $duracionHoras = 1
    ): bool {
        foreach ($participantes as $rid) {
            if (self::residenteOcupadoEnHorario($partida, (string) $rid, $dia, $hora, $duracionHoras)) {
                return true;
            }
        }
        return false;
    }

    /**
     * ¿El residente está en un encuentro programado o en curso que ocupa esta franja?
     */
    public static function residenteOcupadoEnHorario(
        array $partida,
        string $residenteId,
        int $dia,
        int $hora,
        int $duracionHoras = 1
    ): bool {
        if ($residenteId === '') {
            return false;
        }
        $duracionHoras = max(1, $duracionHoras);
        for ($offset = 0; $offset < $duracionHoras; $offset++) {
            $h = $hora + $offset;
            $d = $dia;
            while ($h >= 24) {
                $h -= 24;
                $d++;
            }
            foreach (self::list($partida) as $enc) {
                if (!LugarAtributos::ocupaHora($enc, $d, $h)) {
                    continue;
                }
                if (!in_array($enc['estado'] ?? '', ['programado', 'en_curso'], true)) {
                    continue;
                }
                if (in_array($residenteId, $enc['participantes'] ?? [], true)) {
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
        if ($desde === 'programado') {
            return in_array($hacia, ['en_curso', 'cancelado'], true);
        }
        if ($desde === 'en_curso') {
            return in_array($hacia, ['terminado', 'cancelado'], true);
        }
        return false;
    }

    public static function cancelar(array &$partida, string $encuentroId, ?GameLogger $logger = null): array
    {
        $r = self::cambiarEstado($partida, $encuentroId, 'cancelado');
        if ($r['ok'] ?? false) {
            DomainEventDispatcher::emit($partida, DomainEvents::ENCUENTRO_CANCELADO, [
                'encuentro' => $r['encuentro'],
                'actores' => $r['encuentro']['participantes'] ?? [],
            ], $logger, 'EncuentroEngine::cancelar', $r['encuentro']['participantes'] ?? []);
            \aht_log_optional($logger, $partida, 'encuentro_cancelado', [
                'encuentro_id' => $r['encuentro']['id'] ?? $encuentroId,
            ]);
        }
        return $r;
    }

    public static function listarActivos(array $partida): array
    {
        return array_values(array_filter(
            self::list($partida),
            static fn(array $e) => in_array($e['estado'] ?? '', ['programado', 'en_curso'], true)
        ));
    }
}
