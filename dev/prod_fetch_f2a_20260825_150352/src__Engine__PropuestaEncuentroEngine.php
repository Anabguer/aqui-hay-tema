<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadEvaluator;
use AquiHayTema\Engine\Voluntad\VoluntadPendienteEvaluator;
use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

/**
 * El jugador propone; el residente decide. No programa hasta aceptación de ambos.
 * Resolución de plan (cal `voluntad.resolucion_plan`): media_geometrica (canon 20/08)
 * o producto (legado pA×pB).
 */
final class PropuestaEncuentroEngine
{
    /** Marcador que escribe la resolución geom: esa persona habría aceptado a nivel individual. */
    public const MARCA_HABRIA_ACEPTADO_PLAN = 'voluntad_ok_pero_plan_rechazado';

    /** Marcador B4: acepta porque el ES el plan que pidió por Mensajitos. */
    public const MARCA_COMPROMISO_PETICION = 'compromiso_peticion_propia';

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
        $diaPedido = $dia;
        $horaPedida = $hora;
        if (!Reloj::esFuturo($partida['reloj'] ?? [], $dia, $hora)) {
            return GameError::respuesta(GameError::HORA_PASADA, ['dia' => $dia, 'hora' => $hora]);
        }
        $tipo = PropuestaNivel::aliasTipo($tipo);
        $calDef = CalibracionConfig::load(dirname(__DIR__, 2));
        if (count($participantes) >= 2
            && $tipo !== 'individual'
            && !PropuestaNivel::permite($partida, (string) $participantes[0], (string) $participantes[1], $tipo, $calDef)
        ) {
            $motivo = OrganizarMotivo::de(
                $partida,
                (string) $participantes[0],
                (string) $participantes[1],
                $tipo,
                $calDef
            );
            $msg = OrganizarMotivo::mensajeUi($motivo['codigo']);
            $r = GameError::respuesta(GameError::TIPO_ENCUENTRO_NO_DISPONIBLE, [
                'tipo' => $tipo,
                'tipos_permitidos' => $motivo['tipos'],
                'causa' => $motivo['codigo'],
                'tipo_sugerido' => $motivo['tipo_sugerido'],
            ]);
            if ($msg !== '') {
                $r['mensaje_ui'] = $msg;
            }
            return $r;
        }
        if (count($participantes) >= 2 && $tipo !== 'individual') {
            foreach ($participantes as $rid) {
                $otro = self::otroParticipante($participantes, (string) $rid);
                if ($otro === '') {
                    continue;
                }
                if (PropuestaCooldown::activo($partida, (string) $rid, $otro, $tipo, $calDef)) {
                    $err = GameError::respuesta(GameError::ENCUENTRO_RECHAZADO_COOLDOWN, ['motivo' => 'cooldown_propuesta']);
                    $err['mensaje_ui'] = CopyRechazoPropuesta::mensajeCooldownPar($partida, $participantes, $tipo);
                    return $err;
                }
            }
        }
        $franja = self::resolverFranja($partida, $participantes, $dia, $hora, (string) $lugarId);
        if ($franja === null) {
            $propCtx = [
                'participantes' => $participantes,
                'tipo' => $tipo,
                'lugar' => $lugarId,
                'dia' => $diaPedido,
                'hora' => $horaPedida,
            ];
            $contrap = null;
            if (self::aceptariaSocialmente($partida, $propCtx, $calDef)) {
                $contrap = self::construirContrapropuesta(
                    $partida,
                    $participantes,
                    $tipo,
                    (string) $lugarId,
                    $diaPedido,
                    $horaPedida
                );
            }
            $motivoUi = CopyRechazoPropuesta::motivoBloqueoFranjaUi(
                $partida,
                $participantes,
                $diaPedido,
                $horaPedida,
                (string) $lugarId
            );
            $msg = $motivoUi !== ''
                ? 'No puede quedar a esa hora (' . $motivoUi . ').'
                : GameError::mensajeUi(GameError::ENCUENTRO_RECHAZADO_INDISPONIBILIDAD);
            if ($contrap !== null && !empty($contrap['texto'])) {
                $msg .= ' ' . (string) $contrap['texto'];
            }
            $err = GameError::respuesta(GameError::ENCUENTRO_RECHAZADO_INDISPONIBILIDAD, [
                'motivo' => 'sin_franja_libre',
            ]);
            $err['mensaje_ui'] = $msg;
            $err['rechazo_clase'] = PropuestaEncuentro::CLASE_INDISPONIBILIDAD;
            $err['rechazo_tipo'] = CopyRechazoPropuesta::TIPO_NO_PUEDO;
            if ($contrap !== null) {
                $err['contrapropuesta'] = $contrap;
            }
            return $err;
        }
        $dia = (int) $franja['dia'];
        $hora = (int) $franja['hora'];
        $horaAjustada = $dia !== $diaPedido || $hora !== $horaPedida;
        if ($voluntad === null) {
            $voluntad = new VoluntadPonderadaEvaluator($calDef);
        }

        // B4: ¿esta propuesta cubre una petición abierta? 'exacta' compromete al
        // peticionario (no repite RNG por lo ya pedido); 'nucleo' (Celestine añadió
        // compañía no pedida) solo da un bonus fuerte configurable. Agenda y
        // cooldown siguen mandando para todos.
        $petCubre = PeticionPuebloEngine::activa($partida)
            ? PeticionPuebloEngine::peticionQueCubre($partida, $participantes, $tipo, (string) $lugarId)
            : null;

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
            'hora_solicitada' => ['dia' => $diaPedido, 'hora' => $horaPedida],
            'hora_ajustada' => $horaAjustada,
            'actividad' => $actividad,
            'reacciones' => [],
            'encuentro_id' => null,
            'origen' => 'jugador',
            '_placeholder_copy' => true,
        ];

        if ($petCubre !== null && $petCubre['nivel'] === 'nucleo') {
            $bonus = (int) CalibracionConfig::get($calDef, 'peticiones_pueblo.bonus_nucleo_modificado', 30);
            if ($bonus > 0) {
                $propuesta['_bonus_voluntad'] = [
                    (string) ($petCubre['peticion']['residente_id'] ?? '') => $bonus,
                ];
            }
        }

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

        if ($petCubre !== null && $petCubre['nivel'] === 'exacta') {
            self::aplicarCompromisoPeticionario($partida, $propuesta, (string) ($petCubre['peticion']['residente_id'] ?? ''), (string) ($petCubre['peticion']['id'] ?? ''));
        }

        self::aplicarResolucionPlan($partida, $propuesta, $calDef);
        $propuesta = self::cerrarEstado($propuesta);
        if (($propuesta['estado'] ?? '') === 'rechazada') {
            self::anotarRechazoNarrativo(
                $partida,
                $propuesta,
                (string) $lugarId,
                $diaPedido,
                $horaPedida,
                $calDef
            );
            self::feedbackRechazoTerceroSiProcede($partida, $propuesta, $logger);
        }
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
            $out = self::confirmarSiProcede($partida, $id, $logger);
        } else {
            $out = self::respuestaPropuesta($propuesta);
        }
        TutorialPrimerosPasos::alProponer($partida, $out, new Catalog(dirname(__DIR__, 2)));
        if (($partida['tutorial']['id'] ?? '') !== TutorialPrimerosPasos::ID) {
            TutorialBucle::registrar($partida, TutorialBucle::HECHO_PLAN);
            $out['tutorial'] = TutorialBucle::vista($partida);
        } else {
            $v = TutorialPrimerosPasos::vistaPublica($partida);
            if ($v !== []) {
                $out['tutorial'] = $v;
            }
        }
        self::registrarDiag($partida, $participantes, $tipo, (string) $lugarId, $dia, $hora, $out, $propuesta);
        $out['playtest_diag'] = PlaytestDiag::vista($partida);
        if ($horaAjustada) {
            $out['hora_ajustada'] = true;
            $out['dia_pedido'] = $diaPedido;
            $out['hora_pedido'] = $horaPedida;
            $out['dia_asignado'] = $dia;
            $out['hora_asignado'] = $hora;
        }
        return $out;
    }

    /**
     * @param list<string> $participantes
     * @param array<string, mixed> $out
     * @param array<string, mixed> $propuesta
     */
    private static function registrarDiag(
        array &$partida,
        array $participantes,
        string $tipo,
        string $lugarId,
        int $dia,
        int $hora,
        array $out,
        array $propuesta
    ): void {
        $rechazada = !empty($out['rechazada']) || ($propuesta['estado'] ?? '') === 'rechazada';
        $programado = !empty($out['programado']) || ($propuesta['estado'] ?? '') === 'programada';
        $resultado = $programado ? 'ACEPTADO_Y_PROGRAMADO' : ($rechazada ? 'RECHAZADO' : strtoupper((string) ($propuesta['estado'] ?? 'PENDIENTE')));
        $motivos = [];
        foreach ($propuesta['reacciones'] ?? [] as $reac) {
            if (!is_array($reac)) {
                continue;
            }
            if (($reac['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA) {
                $motivos[] = ($reac['nombre'] ?? '?') . ': ' . ($reac['motivo_tecnico'] ?? '') . ' (clase=' . ($reac['clase'] ?? '') . ')';
            }
        }
        if ($motivos === [] && !empty($out['error'])) {
            $motivos[] = (string) $out['error'] . (!empty($out['causa']) ? ' causa=' . $out['causa'] : '');
        }
        $factoresUi = [];
        foreach ($propuesta['reacciones'] ?? [] as $reac) {
            if (!is_array($reac) || empty($reac['factores']) || !is_array($reac['factores'])) {
                continue;
            }
            $f = $reac['factores'];
            $factoresUi[] = ($reac['nombre'] ?? '?') . ' score=' . json_encode($f['score'] ?? null)
                . ' p=' . json_encode($f['p'] ?? $reac['p'] ?? null)
                . ' tirada=' . json_encode($f['tirada_rng'] ?? null)
                . ' conocidos=' . json_encode($f['relacion_previa_se_conocen'] ?? null)
                . ' emo=' . ($f['estado_emocional'] ?? '')
                . ' social=' . json_encode($f['social'] ?? null);
        }
        PlaytestDiag::push($partida, 'PLAN_PROPUESTO', [
            'residente_a' => (string) ($participantes[0] ?? ''),
            'residente_b' => (string) ($participantes[1] ?? ''),
            'tipo_encuentro' => $tipo,
            'lugar' => $lugarId,
            'dia_plan' => $dia,
            'hora_plan' => $hora,
            'resultado' => $resultado,
            'motivo_motor' => $motivos !== [] ? implode("\n", $motivos) : (string) ($out['mensaje_ui'] ?? ''),
            'factores' => $factoresUi,
            'reacciones' => $propuesta['reacciones'] ?? [],
            'mensaje_ui' => $out['mensaje_ui'] ?? null,
            'error' => $out['error'] ?? null,
            'rechazo_clase' => $out['rechazo_clase'] ?? null,
            'rechazo_tipo' => $out['rechazo_tipo'] ?? null,
            'contrapropuesta' => $out['contrapropuesta'] ?? null,
        ]);
    }

    /**
     * B4 exacta: el peticionario no vuelve a decidir si quiere SU plan.
     * Solo la agenda/cooldown pueden tumbarle; el RNG de voluntad, no.
     *
     * @param array<string, mixed> $propuesta
     */
    private static function aplicarCompromisoPeticionario(
        array &$partida,
        array &$propuesta,
        string $ridPet,
        string $petId
    ): void {
        if ($ridPet === '') {
            return;
        }
        foreach ($propuesta['reacciones'] as $i => $reac) {
            if (!is_array($reac) || (string) ($reac['residente_id'] ?? '') !== $ridPet) {
                continue;
            }
            $clase = (string) ($reac['clase'] ?? '');
            if (($reac['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA
                && ($clase === PropuestaEncuentro::CLASE_INDISPONIBILIDAD
                    || $clase === PropuestaEncuentro::CLASE_COOLDOWN)
            ) {
                continue;
            }
            $pAntes = isset($reac['p']) ? $reac['p'] : null;
            $propuesta['reacciones'][$i]['decision'] = PropuestaEncuentro::DECISION_ACEPTA;
            $propuesta['reacciones'][$i]['clase'] = null;
            $propuesta['reacciones'][$i]['motivo_tecnico'] = self::MARCA_COMPROMISO_PETICION;
            $propuesta['reacciones'][$i]['motivo_tipo'] = null;
            $propuesta['reacciones'][$i]['copy_id'] = null;
            $propuesta['reacciones'][$i]['_bloqueado_decision'] = false;
            if (!isset($propuesta['reacciones'][$i]['factores']) || !is_array($propuesta['reacciones'][$i]['factores'])) {
                $propuesta['reacciones'][$i]['factores'] = [];
            }
            if ($pAntes !== null) {
                $propuesta['reacciones'][$i]['factores']['p_sin_compromiso'] = $pAntes;
            }
            $propuesta['reacciones'][$i]['factores']['compromiso_peticion'] = $petId;
            unset($propuesta['reacciones'][$i]['_joint_plan']);
        }
    }

    /**
     * Si el plan cubría una petición abierta y lo tumbó un TERCERO mientras el
     * peticionario aceptaba, el Mensajito lo cuenta: "yo sí quería, pero X no".
     * La petición permanece abierta.
     *
     * @param array<string, mixed> $propuesta
     */
    private static function feedbackRechazoTerceroSiProcede(array &$partida, array $propuesta, ?GameLogger $logger): void
    {
        if (!PeticionPuebloEngine::activa($partida)
            || ($propuesta['estado'] ?? '') !== 'rechazada'
        ) {
            return;
        }
        $canon = self::rechazoCanonico($propuesta);
        $hablante = is_array($canon['hablante'] ?? null) ? $canon['hablante'] : null;
        $comprometido = is_array($canon['habria_aceptado'] ?? null) ? $canon['habria_aceptado'] : null;
        if ($hablante === null || $comprometido === null) {
            return;
        }
        $ridPet = (string) ($comprometido['residente_id'] ?? '');
        $tercero = (string) ($hablante['residente_id'] ?? '');
        if ($ridPet === '' || $tercero === '' || $ridPet === $tercero) {
            return;
        }
        $parts = is_array($propuesta['participantes'] ?? null) ? $propuesta['participantes'] : [];
        $tipo = PropuestaNivel::aliasTipo((string) ($propuesta['tipo'] ?? ''));
        $cubre = PeticionPuebloEngine::peticionQueCubre(
            $partida,
            $parts,
            $tipo,
            (string) ($propuesta['lugar'] ?? '')
        );
        if ($cubre === null
            || (string) ($cubre['peticion']['residente_id'] ?? '') !== $ridPet
        ) {
            return;
        }
        PeticionFeedback::alRechazoTercero($partida, $cubre['peticion'], $tercero, $logger);
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
        if (($prop['estado'] ?? '') === 'rechazada') {
            $hp = is_array($prop['hora_solicitada'] ?? null) ? $prop['hora_solicitada'] : [];
            self::anotarRechazoNarrativo(
                $partida,
                $prop,
                (string) ($prop['lugar'] ?? ''),
                (int) ($hp['dia'] ?? $prop['dia'] ?? 0),
                (int) ($hp['hora'] ?? $prop['hora'] ?? 0),
                CalibracionConfig::load(dirname(__DIR__, 2))
            );
            $partida['propuestas_encuentro'][$idx] = $prop;
            self::feedbackRechazoTerceroSiProcede($partida, $prop, $logger);
        }

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
            $out = self::respuestaPropuesta($prop);
            return array_merge($out, GameError::respuesta(self::codigoRechazo($out['rechazo_clase'] ?? null), [
                'propuesta_id' => $propuestaId,
                'rechazo_clase' => $out['rechazo_clase'] ?? null,
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
                (int) $prop['hora'] + 1,
                isset($prop['lugar']) ? (string) $prop['lugar'] : ''
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
        $fechaTxt = Reloj::fechaCorta($partida['reloj'] ?? [], (int) $prop['dia']);
        $diaSem = Reloj::diaSemanaUi((int) $prop['dia'], $partida['reloj'] ?? []);
        $hh = str_pad((string) (int) $prop['hora'], 2, '0', STR_PAD_LEFT);
        $lugar = (string) ($prop['lugar'] ?? '');
        $sitio = $lugar !== '' ? MisionPlantillas::nombreLugar($lugar) : 'el pueblo';
        $verboQuedado = count($nombres) === 1 ? 'ha quedado' : 'han quedado';
        $r['mensaje_ui'] = $quien . ' ' . $verboQuedado . ' el ' . $diaSem . ' ' . $fechaTxt . ' a las ' . $hh . ':00 en ' . $sitio . '.';
        if (!empty($prop['hora_ajustada']) && is_array($prop['hora_solicitada'] ?? null)) {
            $hp = str_pad((string) (int) ($prop['hora_solicitada']['hora'] ?? 0), 2, '0', STR_PAD_LEFT);
            $dp = (int) ($prop['hora_solicitada']['dia'] ?? 0);
            if ($dp !== (int) $prop['dia'] || (int) ($prop['hora_solicitada']['hora'] ?? -1) !== (int) $prop['hora']) {
                $r['mensaje_ui'] = $quien . ' han quedado el ' . $diaSem . ' ' . $fechaTxt . ' a las ' . $hh . ':00 en ' . $sitio
                    . ' (la hora pedida, ' . $hp . ':00 del día ' . $dp . ', no encajaba; el motor buscó el siguiente hueco libre).';
            }
        }
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
     * Canon 20/08: p_plan = √(pA·pB). Atribuye el rechazo UI al participante con menor p.
     *
     * @param array<string, mixed> $propuesta
     * @param array<string, mixed> $cal
     */
    private static function aplicarResolucionPlan(array &$partida, array &$propuesta, array $cal): void
    {
        $modo = (string) CalibracionConfig::get($cal, 'voluntad.resolucion_plan', 'media_geometrica');
        if ($modo !== 'media_geometrica') {
            return;
        }
        $idxs = [];
        foreach ($propuesta['reacciones'] ?? [] as $i => $r) {
            if (!is_array($r)) {
                continue;
            }
            if (empty($r['_joint_plan'])) {
                continue;
            }
            if (($r['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA
                && ($r['clase'] ?? '') === PropuestaEncuentro::CLASE_INDISPONIBILIDAD
            ) {
                return; // agenda ya tumba el plan
            }
            if (($r['clase'] ?? '') === 'cooldown') {
                return;
            }
            $idxs[] = (int) $i;
        }
        if (count($idxs) === 1) {
            $i = $idxs[0];
            $p = (float) ($propuesta['reacciones'][$i]['p'] ?? 0);
            $rng = RngService::fromPartida($partida);
            $tirada = $rng->nextFloat();
            $rng->persistToPartida($partida);
            $acepta = $tirada < $p;
            $propuesta['resolucion_plan'] = [
                'modo' => 'individual',
                'p' => $p,
                'tirada' => $tirada,
                'acepta' => $acepta,
            ];
            $propuesta['reacciones'][$i]['decision'] = $acepta
                ? PropuestaEncuentro::DECISION_ACEPTA
                : PropuestaEncuentro::DECISION_RECHAZA;
            $propuesta['reacciones'][$i]['clase'] = $acepta ? null : PropuestaEncuentro::CLASE_VOLUNTAD;
            $propuesta['reacciones'][$i]['motivo_tecnico'] = $acepta ? 'voluntad_acepta_solo' : 'voluntad_rechaza_solo';
            return;
        }
        if (count($idxs) < 2) {
            return;
        }
        $pA = (float) ($propuesta['reacciones'][$idxs[0]]['p'] ?? 0);
        $pB = (float) ($propuesta['reacciones'][$idxs[1]]['p'] ?? 0);
        $pPlan = sqrt(max(0.0, $pA) * max(0.0, $pB));
        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        $acepta = $tirada < $pPlan;

        $weak = $idxs[0];
        $strong = $idxs[1];
        if ($pB < $pA) {
            $weak = $idxs[1];
            $strong = $idxs[0];
        }

        $propuesta['resolucion_plan'] = [
            'modo' => 'media_geometrica',
            'pA' => $pA,
            'pB' => $pB,
            'p_plan' => $pPlan,
            'tirada' => $tirada,
            'acepta' => $acepta,
        ];

        if ($acepta) {
            foreach ($idxs as $i) {
                $propuesta['reacciones'][$i]['decision'] = PropuestaEncuentro::DECISION_ACEPTA;
                $propuesta['reacciones'][$i]['clase'] = null;
                $propuesta['reacciones'][$i]['motivo_tecnico'] = 'voluntad_acepta_plan_geom';
                $propuesta['reacciones'][$i]['motivo_tipo'] = null;
                $propuesta['reacciones'][$i]['copy_id'] = null;
                $propuesta['reacciones'][$i]['factores']['p_plan'] = $pPlan;
                $propuesta['reacciones'][$i]['factores']['tirada_plan'] = $tirada;
                unset($propuesta['reacciones'][$i]['_joint_plan']);
            }
            return;
        }

        // Rechazo: el de menor p “planta”; el otro habría aceptado a nivel individual.
        $copy = VoluntadPonderadaEvaluator::copyBanalPublic($rng, $cal);
        $motivo = VoluntadPonderadaEvaluator::motivoRechazoPublic(
            $partida,
            (string) $propuesta['reacciones'][$weak]['residente_id'],
            (string) $propuesta['reacciones'][$strong]['residente_id'],
            $cal
        );
        $propuesta['reacciones'][$weak]['decision'] = PropuestaEncuentro::DECISION_RECHAZA;
        $propuesta['reacciones'][$weak]['clase'] = PropuestaEncuentro::CLASE_VOLUNTAD;
        $propuesta['reacciones'][$weak]['motivo_tecnico'] = 'voluntad_rechaza_plan_geom_' . $motivo;
        $propuesta['reacciones'][$weak]['motivo_tipo'] = $motivo;
        $propuesta['reacciones'][$weak]['copy_id'] = $motivo;
        $propuesta['reacciones'][$weak]['factores']['p_plan'] = $pPlan;
        $propuesta['reacciones'][$weak]['factores']['tirada_plan'] = $tirada;
        unset($propuesta['reacciones'][$weak]['_joint_plan']);

        $propuesta['reacciones'][$strong]['decision'] = PropuestaEncuentro::DECISION_ACEPTA;
        $propuesta['reacciones'][$strong]['clase'] = null;
        $propuesta['reacciones'][$strong]['motivo_tecnico'] = 'voluntad_ok_pero_plan_rechazado';
        $propuesta['reacciones'][$strong]['factores']['p_plan'] = $pPlan;
        unset($propuesta['reacciones'][$strong]['_joint_plan']);

        RechazoMemoria::registrar(
            $partida,
            (string) $propuesta['reacciones'][$weak]['residente_id'],
            (string) $propuesta['reacciones'][$strong]['residente_id'],
            $motivo,
            [],
            (string) ($propuesta['tipo'] ?? 'conocerse')
        );
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
                'nombre' => IdentidadPublica::nombre($partida, $residenteId),
                'decision' => PropuestaEncuentro::DECISION_RECHAZA,
                'clase' => PropuestaEncuentro::CLASE_INDISPONIBILIDAD,
                'motivo_tecnico' => (string) ($disp['motivo'] ?? 'ocupado'),
                'copy_id' => null,
                'detalle' => $disp,
                'factores' => [
                    'agenda_disponible' => false,
                    'motivo_agenda' => (string) ($disp['motivo'] ?? 'ocupado'),
                    'detalle_agenda' => $disp,
                ],
                '_bloqueado_decision' => false,
            ];
        }
        if (EncuentroEngine::hayConflictoHorario($partida, $participantes, $dia, $hora)) {
            return [
                'residente_id' => $residenteId,
                'nombre' => IdentidadPublica::nombre($partida, $residenteId),
                'decision' => PropuestaEncuentro::DECISION_RECHAZA,
                'clase' => PropuestaEncuentro::CLASE_INDISPONIBILIDAD,
                'motivo_tecnico' => 'doble_reserva',
                'copy_id' => null,
                'factores' => [
                    'agenda_disponible' => true,
                    'conflicto_horario' => true,
                    'motivo_agenda' => 'doble_reserva',
                ],
                '_bloqueado_decision' => false,
            ];
        }

        $ev = $voluntad->evaluar($partida, $propuesta, $residenteId);
        if (($ev['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA
            && ($ev['clase'] ?? '') !== PropuestaEncuentro::CLASE_INDISPONIBILIDAD
            && ($ev['clase'] ?? '') !== PropuestaEncuentro::CLASE_COOLDOWN
            && empty($ev['_joint_plan'])
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
        $row = [
            'residente_id' => $residenteId,
            'nombre' => IdentidadPublica::nombre($partida, $residenteId),
            'decision' => (string) ($ev['decision'] ?? PropuestaEncuentro::DECISION_PENDIENTE),
            'clase' => $ev['clase'] ?? null,
            'motivo_tecnico' => (string) ($ev['motivo_tecnico'] ?? 'voluntad'),
            'motivo_tipo' => $ev['motivo_tipo'] ?? null,
            'copy_id' => $ev['copy_id'] ?? null,
            'score' => $ev['score'] ?? null,
            'p' => $ev['p'] ?? null,
            '_bloqueado_decision' => (bool) ($ev['_bloqueado_decision'] ?? true),
        ];
        if (!empty($ev['_joint_plan'])) {
            $row['_joint_plan'] = true;
        }
        if (isset($ev['factores']) && is_array($ev['factores'])) {
            $row['factores'] = $ev['factores'];
        }
        return $row;
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
            if (($r['decision'] ?? '') !== PropuestaEncuentro::DECISION_RECHAZA) {
                continue;
            }
            $clase = (string) ($r['clase'] ?? '');
            if ($clase === PropuestaEncuentro::CLASE_INDISPONIBILIDAD) {
                return PropuestaEncuentro::CLASE_INDISPONIBILIDAD;
            }
            if ($clase === PropuestaEncuentro::CLASE_COOLDOWN) {
                return PropuestaEncuentro::CLASE_COOLDOWN;
            }
        }
        foreach ($propuesta['reacciones'] ?? [] as $r) {
            if (($r['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA) {
                return PropuestaEncuentro::CLASE_VOLUNTAD;
            }
        }
        return null;
    }

    private static function codigoRechazo(?string $clase): string
    {
        if ($clase === PropuestaEncuentro::CLASE_INDISPONIBILIDAD) {
            return GameError::ENCUENTRO_RECHAZADO_INDISPONIBILIDAD;
        }
        if ($clase === PropuestaEncuentro::CLASE_COOLDOWN) {
            return GameError::ENCUENTRO_RECHAZADO_COOLDOWN;
        }
        return GameError::ENCUENTRO_RECHAZADO_VOLUNTAD;
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
        if ($rechazada) {
            $out['error'] = self::codigoRechazo($clase);
            $out['rechazo_tipo'] = $propuesta['rechazo_tipo'] ?? CopyRechazoPropuesta::tipoDeClase($clase);
            if (!empty($propuesta['contrapropuesta']) && is_array($propuesta['contrapropuesta'])) {
                $out['contrapropuesta'] = $propuesta['contrapropuesta'];
            }
            if (!empty($propuesta['mensaje_rechazo_ui'])) {
                $out['mensaje_ui'] = (string) $propuesta['mensaje_rechazo_ui'];
            } elseif ($clase === PropuestaEncuentro::CLASE_INDISPONIBILIDAD) {
                $out['mensaje_ui'] = GameError::mensajeUi(GameError::ENCUENTRO_RECHAZADO_INDISPONIBILIDAD);
            } else {
                $hablante = self::rechazoCanonico($propuesta)['hablante'];
                $nombre = is_array($hablante) ? (string) ($hablante['nombre'] ?? '') : '';
                $copyId = is_array($hablante) && !empty($hablante['copy_id']) ? (string) $hablante['copy_id'] : null;
                $out['mensaje_ui'] = CopyVoluntad::rechazoConHablante(
                    $nombre !== '' ? $nombre : 'Alguien',
                    $copyId
                );
                $out['copy_id'] = $copyId;
                $out['rechazado_por'] = [
                    'residente_id' => is_array($hablante) ? ((string) ($hablante['residente_id'] ?? '') ?: null) : null,
                    'nombre' => $nombre,
                    'copy_id' => $copyId,
                ];
            }
        }
        return $out;
    }

    /**
     * FUENTE CANÓNICA de atribución de rechazo (única; no duplicar).
     *
     * - hablante: la reacción rechazadora REAL elegida por severidad de clase
     *   (indisponibilidad > cooldown > voluntad), mismo orden que claseRechazo().
     *   Empate dentro de una clase → primera aparición en reacciones.
     *   NUNCA depende de participants[0]/participants[1] ni del orden A/B.
     * - habria_aceptado: reacción con el marcador MARCA_HABRIA_ACEPTADO_PLAN
     *   (decisión ACEPTA + 'voluntad_ok_pero_plan_rechazado'), o null.
     *
     * @param array<string, mixed> $propuesta
     * @return array{
     *   rechazadores: list<array<string, mixed>>,
     *   hablante: array<string, mixed>|null,
     *   habria_aceptado: array<string, mixed>|null
     * }
     */
    public static function rechazoCanonico(array $propuesta): array
    {
        $rechazadores = [];
        foreach ($propuesta['reacciones'] ?? [] as $reac) {
            if (!is_array($reac)) {
                continue;
            }
            if (($reac['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA) {
                $rechazadores[] = $reac;
            }
        }
        $hablante = null;
        $prioridad = [
            PropuestaEncuentro::CLASE_INDISPONIBILIDAD,
            PropuestaEncuentro::CLASE_COOLDOWN,
            PropuestaEncuentro::CLASE_VOLUNTAD,
        ];
        foreach ($prioridad as $clase) {
            foreach ($rechazadores as $r) {
                $rc = (string) ($r['clase'] ?? '');
                $coincide = $clase === PropuestaEncuentro::CLASE_VOLUNTAD
                    ? ($rc === '' || $rc === PropuestaEncuentro::CLASE_VOLUNTAD)
                    : ($rc === $clase);
                if ($coincide) {
                    $hablante = $r;
                    break 2;
                }
            }
        }
        $habriaAceptado = null;
        if ($hablante !== null) {
            foreach ($propuesta['reacciones'] ?? [] as $reac) {
                if (!is_array($reac)) {
                    continue;
                }
                $mt = (string) ($reac['motivo_tecnico'] ?? '');
                if (($reac['decision'] ?? '') === PropuestaEncuentro::DECISION_ACEPTA
                    && ($mt === self::MARCA_HABRIA_ACEPTADO_PLAN || $mt === self::MARCA_COMPROMISO_PETICION)
                ) {
                    $habriaAceptado = $reac;
                    break;
                }
            }
        }
        return [
            'rechazadores' => $rechazadores,
            'hablante' => $hablante,
            'habria_aceptado' => $habriaAceptado,
        ];
    }

    /**
     * Si la hora pedida está ocupada, busca la siguiente franja libre conjunta.
     *
     * @param list<string> $participantes
     * @return array{dia:int,hora:int}|null
     */
    private static function resolverFranja(array $partida, array $participantes, int $dia, int $hora, string $lugarId): ?array
    {
        $libre = true;
        foreach ($participantes as $rid) {
            $disp = AgendaEngine::estaDisponible($partida, (string) $rid, $dia, $hora);
            if (!($disp['disponible'] ?? false)) {
                $libre = false;
                break;
            }
        }
        if ($libre
            && ComplejoCatalog::estaAbierto($lugarId, $hora)
            && !EncuentroEngine::hayConflictoHorario($partida, $participantes, $dia, $hora)
        ) {
            return ['dia' => $dia, 'hora' => $hora];
        }
        $slots = DisponibilidadEngine::slotsCompatibles($partida, $participantes, 'conocerse', $dia, $hora, 7, 24);
        foreach ($slots['slots'] ?? [] as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $h = (int) ($slot['hora'] ?? -1);
            if (!ComplejoCatalog::estaAbierto($lugarId, $h)) {
                continue;
            }
            return ['dia' => (int) $slot['dia'], 'hora' => $h];
        }
        return null;
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

    /**
     * Anota causa real, copy coherente y contrapropuesta (solo disponibilidad) en la propuesta.
     *
     * @param array<string, mixed> $propuesta
     * @param array<string, mixed> $cal
     */
    private static function anotarRechazoNarrativo(
        array &$partida,
        array &$propuesta,
        string $lugarId,
        int $diaPedido,
        int $horaPedida,
        array $cal
    ): void {
        $clase = self::claseRechazo($propuesta);
        $propuesta['rechazo_tipo'] = CopyRechazoPropuesta::tipoDeClase($clase);
        foreach ($propuesta['reacciones'] ?? [] as $i => $reac) {
            if (!is_array($reac) || ($reac['decision'] ?? '') !== PropuestaEncuentro::DECISION_RECHAZA) {
                continue;
            }
            $familia = CopyRechazoPropuesta::familiaDeReaccion(
                $reac,
                $lugarId !== '' ? $lugarId : null,
                (int) ($propuesta['hora'] ?? $horaPedida)
            );
            $propuesta['reacciones'][$i]['rechazo_tipo'] = CopyRechazoPropuesta::tipoDeClase((string) ($reac['clase'] ?? ''));
            $propuesta['reacciones'][$i]['rechazo_familia'] = $familia;
            $propuesta['reacciones'][$i]['copy_id'] = $familia;
        }
        $contrap = null;
        if ($clase === PropuestaEncuentro::CLASE_INDISPONIBILIDAD
            && self::soloRechazoIndisponibilidad($propuesta)
            && self::aceptariaSocialmente($partida, $propuesta, $cal)
        ) {
            $parts = is_array($propuesta['participantes'] ?? null) ? $propuesta['participantes'] : [];
            $contrap = self::construirContrapropuesta(
                $partida,
                $parts,
                (string) ($propuesta['tipo'] ?? 'conocerse'),
                $lugarId,
                $diaPedido,
                $horaPedida
            );
        }
        if ($contrap !== null) {
            $propuesta['contrapropuesta'] = $contrap;
        }
        $propuesta['mensaje_rechazo_ui'] = CopyRechazoPropuesta::mensajeRechazo($partida, $propuesta, $contrap);
    }

    /**
     * @param array<string, mixed> $propuesta
     */
    private static function soloRechazoIndisponibilidad(array $propuesta): bool
    {
        foreach ($propuesta['reacciones'] ?? [] as $reac) {
            if (!is_array($reac) || ($reac['decision'] ?? '') !== PropuestaEncuentro::DECISION_RECHAZA) {
                continue;
            }
            if (($reac['clase'] ?? '') !== PropuestaEncuentro::CLASE_INDISPONIBILIDAD) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string, mixed> $propuesta
     * @param array<string, mixed> $cal
     */
    private static function aceptariaSocialmente(array $partida, array $propuesta, array $cal): bool
    {
        $parts = is_array($propuesta['participantes'] ?? null) ? $propuesta['participantes'] : [];
        $tipo = (string) ($propuesta['tipo'] ?? 'conocerse');
        foreach ($parts as $rid) {
            if (!is_string($rid) || $rid === '') {
                continue;
            }
            $otro = self::otroParticipante($parts, $rid);
            if (PropuestaCooldown::activo($partida, $rid, $otro, $tipo, $cal)) {
                return false;
            }
            $emo = EstadoEmocional::canonId(
                (string) ($partida['residentes'][$rid]['runtime']['estado_emocional']['id'] ?? 'neutro')
            );
            if ($emo === EstadoEmocional::ENFADADO) {
                return false;
            }
            if ($otro !== '') {
                $conf = RelacionEngine::obtenerEntre($partida, $rid, $otro)['conflicto']['intensidad'] ?? null;
                if (is_numeric($conf) && (int) $conf >= 8) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param list<string> $participantes
     * @return array<string, mixed>|null
     */
    private static function construirContrapropuesta(
        array $partida,
        array $participantes,
        string $tipo,
        string $lugarId,
        int $diaPedido,
        int $horaPedida
    ): ?array {
        if (count($participantes) < 1) {
            return null;
        }
        $slot = DisponibilidadEngine::siguienteSlotTras(
            $partida,
            $participantes,
            $tipo,
            $diaPedido,
            $horaPedida,
            $lugarId !== '' ? $lugarId : null
        );
        if ($slot === null) {
            return null;
        }
        $dia = (int) $slot['dia'];
        $hora = (int) $slot['hora'];
        if ($dia === $diaPedido && $hora <= $horaPedida) {
            return null;
        }
        $reloj = $partida['reloj'] ?? [];
        $slotUi = [
            'dia' => $dia,
            'hora' => $hora,
            'dia_semana_ui' => Reloj::diaSemanaUi($dia, $reloj),
            'etiqueta_hora' => str_pad((string) $hora, 2, '0', STR_PAD_LEFT) . ':00',
        ];
        return [
            'dia' => $dia,
            'hora' => $hora,
            'lugar' => $lugarId !== '' ? $lugarId : null,
            'tipo' => $tipo,
            'etiqueta_ui' => CopyRechazoPropuesta::etiquetaSlotUi($partida, $slotUi),
            'texto' => CopyRechazoPropuesta::lineaContrapropuesta($partida, $slotUi, $diaPedido, $horaPedida),
        ];
    }

    /**
     * @param list<string> $participantes
     */
    private static function otroParticipante(array $participantes, string $rid): string
    {
        foreach ($participantes as $id) {
            if ((string) $id !== $rid) {
                return (string) $id;
            }
        }
        return '';
    }
}
