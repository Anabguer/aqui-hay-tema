<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadEvaluator;
use AquiHayTema\Engine\Voluntad\VoluntadPendienteEvaluator;
use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

/**
 * El jugador propone; el residente decide. No programa hasta aceptación de ambos.
 * La fórmula de voluntad está BLOQUEADO_DECISION (evaluator por defecto deja pendiente).
 */
final class PropuestaEncuentroEngine
{
    public static function listar(array $partida): array
    {
        return $partida['propuestas_encuentro'] ?? [];
    }

    public static function obtener(array $partida, string $propuestaId): ?array
    {
        foreach (self::listar($partida) as $p) {
            if (($p['id'] ?? '') === $propuestaId) {
                return $p;
            }
        }
        return null;
    }

    /**
     * @param string[] $participantes
     * @return array<string, mixed>
     */
    public static function proponer(
        array &$partida,
        array $participantes,
        int $dia,
        int $hora,
        string $tipo = 'conocerse',
        ?string $lugarId = null,
        ?string $actividad = null,
        ?VoluntadEvaluator $voluntad = null,
        ?GameLogger $logger = null
    ): array {
        $ctx = EncuentroEngine::validarContexto($partida, $participantes, $tipo, $lugarId, $logger);
        if (!($ctx['ok'] ?? false)) {
            return $ctx;
        }
        $participantes = $ctx['participantes'];
        $lugarId = $ctx['lugar'];
        $franja = self::resolverFranja($partida, $participantes, $dia, $hora);
        if ($franja === null) {
            return GameError::respuesta(GameError::ENCUENTRO_RECHAZADO_INDISPONIBILIDAD, [
                'motivo' => 'sin_franja_libre',
            ]);
        }
        $dia = (int) $franja['dia'];
        $hora = (int) $franja['hora'];
        if ($voluntad === null) {
            $calDef = CalibracionConfig::load(dirname(__DIR__, 2));
            $voluntad = new VoluntadPonderadaEvaluator($calDef);
        }

        $rng = RngService::fromPartida($partida);
        $id = 'prop_' . bin2hex(substr(pack('N', $rng->next()), 0, 4));
        $rng->persistToPartida($partida);

        $propuesta = [
            'id' => $id,
            'estado' => 'propuesta',
            'tipo' => $tipo,
            'intencion' => 'jugador_propone',
            'participantes' => $participantes,
            'lugar' => $lugarId,
            'hora' => $hora,
            'dia' => $dia,
            'actividad' => $actividad,
            'reacciones' => [],
            'encuentro_id' => null,
            'origen' => 'jugador',
            '_placeholder_copy' => true,
        ];

        foreach ($participantes as $rid) {
            $propuesta['reacciones'][] = self::evaluarParticipante(
                $partida,
                $propuesta,
                $rid,
                $dia,
                $hora,
                $participantes,
                $voluntad
            );
        }

        $propuesta = self::cerrarEstado($propuesta);
        $partida['propuestas_encuentro'] ??= [];
        $partida['propuestas_encuentro'][] = $propuesta;

        DomainEventDispatcher::emit($partida, DomainEvents::PROPUESTA_ENCUENTRO, [
            'propuesta' => $propuesta,
            'actores' => $participantes,
        ], $logger, 'PropuestaEncuentroEngine::proponer', $participantes);

        \aht_log_optional($logger, $partida, 'encuentro_propuesto', [
            'propuesta_id' => $id,
            'estado' => $propuesta['estado'],
            'participantes' => $participantes,
        ]);

        if (($propuesta['estado'] ?? '') === 'aceptada') {
            return self::confirmarSiProcede($partida, $id, $logger);
        }

        return self::respuestaPropuesta($propuesta);
    }

    /**
     * Decisión explícita (tests / UI futura). No inventa fórmula.
     * No puede anular un rechazo por indisponibilidad.
     *
     * @return array<string, mixed>
     */
    public static function registrarDecision(
        array &$partida,
        string $propuestaId,
        string $residenteId,
        bool $acepta,
        ?GameLogger $logger = null
    ): array {
        $idx = self::indice($partida, $propuestaId);
        if ($idx === null) {
            return GameError::respuesta(GameError::PROPUESTA_NO_ENCONTRADA, ['propuesta_id' => $propuestaId]);
        }
        $prop = $partida['propuestas_encuentro'][$idx];

        $encontrado = false;
        foreach ($prop['reacciones'] as &$reac) {
            if (($reac['residente_id'] ?? '') !== $residenteId) {
                continue;
            }
            $encontrado = true;
            if (($reac['clase'] ?? null) === PropuestaEncuentro::CLASE_INDISPONIBILIDAD
                && ($reac['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA) {
                return GameError::respuesta(GameError::ENCUENTRO_RECHAZADO_INDISPONIBILIDAD, [
                    'residente' => $residenteId,
                    'propuesta_id' => $propuestaId,
                ]);
            }
        }
        unset($reac);
        if (!$encontrado) {
            return GameError::respuesta(GameError::PARTICIPANTE_INEXISTENTE, ['residente' => $residenteId]);
        }

        if (!in_array($prop['estado'] ?? '', ['propuesta', 'aceptada'], true)) {
            return GameError::respuesta(GameError::TRANSICION_INVALIDA, [
                'desde' => $prop['estado'] ?? null,
                'hacia' => 'decision',
            ]);
        }

        foreach ($prop['reacciones'] as &$reac) {
            if (($reac['residente_id'] ?? '') !== $residenteId) {
                continue;
            }
            $reac['decision'] = $acepta
                ? PropuestaEncuentro::DECISION_ACEPTA
                : PropuestaEncuentro::DECISION_RECHAZA;
            $reac['clase'] = $acepta ? null : PropuestaEncuentro::CLASE_VOLUNTAD;
            $reac['motivo_tecnico'] = $acepta ? 'decision_explicita_acepta' : 'decision_explicita_rechaza';
            $reac['copy_id'] = null;
            $reac['_bloqueado_decision'] = false;
        }
        unset($reac);

        $prop = self::cerrarEstado($prop);
        $partida['propuestas_encuentro'][$idx] = $prop;

        \aht_log_optional($logger, $partida, 'propuesta_decision', [
            'propuesta_id' => $propuestaId,
            'residente' => $residenteId,
            'acepta' => $acepta,
            'estado' => $prop['estado'],
        ]);

        if (($prop['estado'] ?? '') === 'aceptada') {
            return self::confirmarSiProcede($partida, $propuestaId, $logger);
        }
        return self::respuestaPropuesta($prop);
    }

    /**
     * Programa solo si ambos participantes han aceptado.
     *
     * @return array<string, mixed>
     */
    public static function confirmarSiProcede(array &$partida, string $propuestaId, ?GameLogger $logger = null): array
    {
        $idx = self::indice($partida, $propuestaId);
        if ($idx === null) {
            return GameError::respuesta(GameError::PROPUESTA_NO_ENCONTRADA, ['propuesta_id' => $propuestaId]);
        }
        $prop = $partida['propuestas_encuentro'][$idx];
        $prop = self::cerrarEstado($prop);
        $partida['propuestas_encuentro'][$idx] = $prop;

        if (($prop['estado'] ?? '') === 'rechazada') {
            $clase = self::claseRechazo($prop);
            $codigo = $clase === PropuestaEncuentro::CLASE_INDISPONIBILIDAD
                ? GameError::ENCUENTRO_RECHAZADO_INDISPONIBILIDAD
                : GameError::ENCUENTRO_RECHAZADO_VOLUNTAD;
            return array_merge(self::respuestaPropuesta($prop), GameError::respuesta($codigo, [
                'propuesta_id' => $propuestaId,
                'rechazo_clase' => $clase,
            ]));
        }
        if (($prop['estado'] ?? '') !== 'aceptada') {
            return array_merge(self::respuestaPropuesta($prop), GameError::respuesta(GameError::PROPUESTA_PENDIENTE, [
                'propuesta_id' => $propuestaId,
            ]));
        }

        $r = EncuentroEngine::programar(
            $partida,
            $prop['participantes'],
            (int) $prop['dia'],
            (int) $prop['hora'],
            (string) $prop['tipo'],
            isset($prop['lugar']) ? (string) $prop['lugar'] : null,
            isset($prop['actividad']) ? (string) $prop['actividad'] : null,
            $logger
        );
        if (!($r['ok'] ?? false)) {
            $alt = self::resolverFranja(
                $partida,
                $prop['participantes'],
                (int) $prop['dia'],
                (int) $prop['hora'] + 1
            );
            if ($alt !== null) {
                $r = EncuentroEngine::programar(
                    $partida,
                    $prop['participantes'],
                    (int) $alt['dia'],
                    (int) $alt['hora'],
                    (string) $prop['tipo'],
                    isset($prop['lugar']) ? (string) $prop['lugar'] : null,
                    isset($prop['actividad']) ? (string) $prop['actividad'] : null,
                    $logger
                );
                if ($r['ok'] ?? false) {
                    $prop['dia'] = (int) $alt['dia'];
                    $prop['hora'] = (int) $alt['hora'];
                }
            }
        }
        if (!($r['ok'] ?? false)) {
            return $r;
        }

        $prop['estado'] = 'programada';
        $prop['encuentro_id'] = $r['encuentro']['id'] ?? null;
        $partida['propuestas_encuentro'][$idx] = $prop;
        $r['propuesta'] = $prop;
        $r['programado'] = true;
        $nombres = [];
        foreach ($prop['participantes'] ?? [] as $pid) {
            $nombres[] = IdentidadPublica::nombre($partida, (string) $pid);
        }
        $quien = implode(' y ', $nombres);
        $diaSem = Reloj::diaSemana((int) $prop['dia']);
        $hh = str_pad((string) (int) $prop['hora'], 2, '0', STR_PAD_LEFT);
        $lugar = (string) ($prop['lugar'] ?? '');
        $sitio = $lugar !== '' ? str_replace('lug_', '', $lugar) : 'el pueblo';
        $r['mensaje_ui'] = $quien . ' han quedado el ' . $diaSem . ' a las ' . $hh . ':00 en ' . $sitio . '.';
        return $r;
    }

    public static function caducarVencidas(array &$partida): int
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $now = $dia * 24 + $hora;
        $n = 0;
        foreach ($partida['propuestas_encuentro'] ?? [] as &$prop) {
            $estado = $prop['estado'] ?? '';
            if (!in_array($estado, ['propuesta', 'aceptada'], true)) {
                continue;
            }
            $t = ((int) ($prop['dia'] ?? 0)) * 24 + (int) ($prop['hora'] ?? 0);
            if ($t < $now) {
                $prop['estado'] = 'caducada';
                $n++;
            }
        }
        unset($prop);
        return $n;
    }

    /**
     * @param string[] $participantes
     * @return array<string, mixed>
     */
    private static function evaluarParticipante(
        array &$partida,
        array $propuesta,
        string $residenteId,
        int $dia,
        int $hora,
        array $participantes,
        VoluntadEvaluator $voluntad
    ): array {
        $disp = AgendaEngine::estaDisponible($partida, $residenteId, $dia, $hora);
        if (!($disp['disponible'] ?? false)) {
            return [
                'residente_id' => $residenteId,
                'decision' => PropuestaEncuentro::DECISION_RECHAZA,
                'clase' => PropuestaEncuentro::CLASE_INDISPONIBILIDAD,
                'motivo_tecnico' => (string) ($disp['motivo'] ?? 'ocupado'),
                'copy_id' => null,
                'detalle' => $disp,
                '_bloqueado_decision' => false,
            ];
        }
        if (EncuentroEngine::hayConflictoHorario($partida, $participantes, $dia, $hora)) {
            return [
                'residente_id' => $residenteId,
                'decision' => PropuestaEncuentro::DECISION_RECHAZA,
                'clase' => PropuestaEncuentro::CLASE_INDISPONIBILIDAD,
                'motivo_tecnico' => 'doble_reserva',
                'copy_id' => null,
                '_bloqueado_decision' => false,
            ];
        }

        $ev = $voluntad->evaluar($partida, $propuesta, $residenteId);
        if (($ev['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA
            && ($ev['clase'] ?? '') !== PropuestaEncuentro::CLASE_INDISPONIBILIDAD
        ) {
            $ids = $propuesta['participantes'] ?? [];
            $otro = '';
            foreach ($ids as $oid) {
                if ((string) $oid !== $residenteId) {
                    $otro = (string) $oid;
                    break;
                }
            }
            RechazoMemoria::registrar(
                $partida,
                $residenteId,
                $otro,
                (string) ($ev['motivo_tipo'] ?? 'banal'),
                [],
                (string) ($propuesta['tipo'] ?? 'conocerse')
            );
        }
        return [
            'residente_id' => $residenteId,
            'decision' => (string) ($ev['decision'] ?? PropuestaEncuentro::DECISION_PENDIENTE),
            'clase' => $ev['clase'] ?? null,
            'motivo_tecnico' => (string) ($ev['motivo_tecnico'] ?? 'voluntad'),
            'motivo_tipo' => $ev['motivo_tipo'] ?? null,
            'copy_id' => $ev['copy_id'] ?? null,
            'score' => $ev['score'] ?? null,
            'p' => $ev['p'] ?? null,
            '_bloqueado_decision' => (bool) ($ev['_bloqueado_decision'] ?? true),
        ];
    }

    /** @return array<string, mixed> */
    private static function cerrarEstado(array $propuesta): array
    {
        $reacs = $propuesta['reacciones'] ?? [];
        $hayRechazo = false;
        $hayPendiente = false;
        $todosAceptan = $reacs !== [];
        foreach ($reacs as $r) {
            $d = $r['decision'] ?? '';
            if ($d === PropuestaEncuentro::DECISION_RECHAZA) {
                $hayRechazo = true;
                $todosAceptan = false;
            } elseif ($d === PropuestaEncuentro::DECISION_PENDIENTE) {
                $hayPendiente = true;
                $todosAceptan = false;
            } elseif ($d !== PropuestaEncuentro::DECISION_ACEPTA) {
                $todosAceptan = false;
                $hayPendiente = true;
            }
        }
        if ($hayRechazo) {
            $propuesta['estado'] = 'rechazada';
        } elseif ($todosAceptan) {
            $propuesta['estado'] = 'aceptada';
        } elseif ($hayPendiente) {
            $propuesta['estado'] = 'propuesta';
        }
        return $propuesta;
    }

    private static function claseRechazo(array $propuesta): ?string
    {
        foreach ($propuesta['reacciones'] ?? [] as $r) {
            if (($r['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA) {
                if (($r['clase'] ?? '') === PropuestaEncuentro::CLASE_INDISPONIBILIDAD) {
                    return PropuestaEncuentro::CLASE_INDISPONIBILIDAD;
                }
            }
        }
        foreach ($propuesta['reacciones'] ?? [] as $r) {
            if (($r['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA) {
                return PropuestaEncuentro::CLASE_VOLUNTAD;
            }
        }
        return null;
    }

    /** @return array<string, mixed> */
    private static function respuestaPropuesta(array $propuesta): array
    {
        $programado = ($propuesta['estado'] ?? '') === 'programada';
        $rechazada = ($propuesta['estado'] ?? '') === 'rechazada';
        $clase = $rechazada ? self::claseRechazo($propuesta) : null;
        $out = [
            'ok' => true,
            'propuesta' => $propuesta,
            'programado' => $programado,
            'rechazada' => $rechazada,
            'rechazo_clase' => $clase,
        ];
        if ($rechazada && $clase === PropuestaEncuentro::CLASE_INDISPONIBILIDAD) {
            $out['error'] = GameError::ENCUENTRO_RECHAZADO_INDISPONIBILIDAD;
            $out['mensaje_ui'] = GameError::mensajeUi(GameError::ENCUENTRO_RECHAZADO_INDISPONIBILIDAD);
        } elseif ($rechazada && $clase === PropuestaEncuentro::CLASE_VOLUNTAD) {
            $out['error'] = GameError::ENCUENTRO_RECHAZADO_VOLUNTAD;
            $copyId = null;
            foreach ($propuesta['reacciones'] ?? [] as $reac) {
                if (($reac['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA && !empty($reac['copy_id'])) {
                    $copyId = (string) $reac['copy_id'];
                    break;
                }
            }
            $out['mensaje_ui'] = CopyVoluntad::texto($copyId);
            $out['copy_id'] = $copyId;
        }
        return $out;
    }

    /**
     * Si la hora pedida está ocupada, busca la siguiente franja libre conjunta.
     *
     * @param list<string> $participantes
     * @return array{dia:int,hora:int}|null
     */
    private static function resolverFranja(array $partida, array $participantes, int $dia, int $hora): ?array
    {
        $libre = true;
        foreach ($participantes as $rid) {
            $disp = AgendaEngine::estaDisponible($partida, (string) $rid, $dia, $hora);
            if (!($disp['disponible'] ?? false)) {
                $libre = false;
                break;
            }
        }
        if ($libre && !EncuentroEngine::hayConflictoHorario($partida, $participantes, $dia, $hora)) {
            return ['dia' => $dia, 'hora' => $hora];
        }
        $slots = DisponibilidadEngine::slotsCompatibles($partida, $participantes, 'conocerse', $dia, $hora, 7, 24);
        $first = $slots['slots'][0] ?? null;
        if (!is_array($first)) {
            return null;
        }
        return ['dia' => (int) $first['dia'], 'hora' => (int) $first['hora']];
    }

    private static function indice(array $partida, string $propuestaId): ?int
    {
        foreach ($partida['propuestas_encuentro'] ?? [] as $i => $p) {
            if (($p['id'] ?? '') === $propuestaId) {
                return (int) $i;
            }
        }
        return null;
    }
}
