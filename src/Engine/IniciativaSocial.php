<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

/**
 * A1 · H2 — Iniciativa social deliberada NPC→NPC (no romántica).
 *
 * NO es un planner paralelo: compone piezas canónicas existentes, espejo
 * no romántico de IniciativaRomantica (F1).
 *   - Elegibilidad:   se conocen + banda social >= umbral canónico
 *                     (`social.cortes_positivo[umbral_banda]`, sin abs():
 *                     negativo NUNCA ayuda) + sin canal conflicto activo.
 *   - Objetivo:       ponderado por relación firmada + contacto reciente
 *                     positivo (MemoriaEventos) - repetición excesiva.
 *   - Voluntad:       SOLO el invitado decide (el iniciador YA quiso);
 *                     VoluntadPonderadaEvaluator tipo canónico 'quedar',
 *                     p del evaluador + tirada única propia.
 *   - Lugar:          LugarAutonomo::elegir CON conocimiento del otro
 *                     (cierra H5) + AgendaConjunta::primeraFranja +
 *                     apertura/aforo/duración del lugar.
 *   - Encuentro:      EncuentroEngine::programar tipo CANÓNICO 'quedar'
 *                     con intencion='autonomo_social': NO cuenta como
 *                     intervención de Celestine ni cumple misiones/
 *                     peticiones/Vida (MisionDiariaEngine::esEncuentroCelestine).
 *   - Cadencia:       cooldown por par vía MemoriaEventos familia
 *                     'iniciativa_social' + cupo diario por iniciador +
 *                     PropuestaCooldown tras rechazo (canon rechazos).
 *   - Efectos:        NINGUNO al proponer. La relación solo cambia cuando
 *                     el encuentro se resuelve por el pipeline normal
 *                     (EncuentroLifecycle → EncuentroResolver).
 */
final class IniciativaSocial
{
    public const INTENCION = 'autonomo_social';
    private const TIPO = 'quedar';
    private const FAMILIA = 'iniciativa_social';
    private const LOG_MAX = 500;

    private const TIPOS_PLAN_EQUIVALENTE = ['conocerse', 'quedar', 'amistad'];

    public static function ensure(array &$partida): void
    {
        $partida['iniciativa_social_log'] ??= [];
    }

    /**
     * Punto de enganche horario (MotorVidaDiaria::tickHora). Nunca lanza.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    public static function tick(
        array &$partida,
        Catalog $catalog,
        array $cal,
        RngService $rng,
        ?GameLogger $logger = null
    ): ?array {
        if (!(bool) CalibracionConfig::get($cal, 'iniciativa_social.activa', false)) {
            return null;
        }
        // Precheck PURO (sin RNG): si nadie puede intentar nada esta hora,
        // el tick no consume stream aleatorio ni decide nada.
        if (!self::hayIntentoPosible($partida, $cal)) {

            return null;
        }
        $prob = (float) CalibracionConfig::get($cal, 'iniciativa_social.prob_intento_hueco', 0.12);
        if ($rng->nextFloat() >= $prob) {

            return null;
        }
        $desde = self::elegirIniciador($partida, $cal, $rng);
        if ($desde === null) {

            return null;
        }
        $hacia = self::elegirObjetivo($partida, $desde, $cal, $rng);
        if ($hacia === null) {

            return null;
        }
        return self::intentarQuedada($partida, $desde, $hacia, $cal, $catalog, $logger);
    }

    /**
     * Intento de quedada social deliberada de $desde hacia $hacia.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function intentarQuedada(
        array &$partida,
        string $desde,
        string $hacia,
        array $cal,
        ?Catalog $catalog = null,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);

        // ---- gates de elegibilidad (sin RNG) ----
        $gate = 'ok';
        while (true) {
            if ($desde === '' || $hacia === '' || $desde === $hacia) {
                $gate = 'par_invalido';
                break;
            }
            foreach ([$desde, $hacia] as $id) {
                if (!isset($partida['residentes'][$id])
                    || (($partida['residentes'][$id]['presencia'] ?? '') !== 'residente')) {
                    $gate = 'residente_no_activo';
                    break 2;
                }
            }
            if (!RelacionEngine::seConocen($partida, $desde, $hacia)) {
                $gate = 'sin_conocerse';
                break;
            }
            if (!self::pasaUmbralSocial($partida, $desde, $hacia, $cal)) {
                $gate = 'social_insuficiente';
                break;
            }
            if (self::conflictoActivo($partida, $desde, $hacia)) {
                $gate = 'conflicto_activo';
                break;
            }
            if (self::hayPlanSocialEquivalente($partida, $desde, $hacia)) {
                $gate = 'plan_ya';
                break;
            }
            if (PropuestaCooldown::activo($partida, $desde, $hacia, self::TIPO, $cal)
                || PropuestaCooldown::activo($partida, $hacia, $desde, self::TIPO, $cal)) {
                $gate = 'cooldown_propuesta';
                break;
            }
            if (MemoriaEventos::enCooldown($partida, self::FAMILIA, [$desde, $hacia], $cal)) {
                $gate = 'cooldown_familia';
                break;
            }
            if (self::intentosHoy($partida, $desde) >= self::maxDiario($cal)) {
                $gate = 'cupo_diario';
                break;
            }
            break;
        }
        if ($gate !== 'ok') {
            return self::fin($partida, 'gate_' . $gate, $desde, $hacia);
        }

        // ---- voluntad del INVITADO (el iniciador ya expresó su ganas) ----
        $prop = [
            'participantes' => [$desde, $hacia],
            'tipo' => self::TIPO,
            'lugar' => null,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 12),
        ];
        $vol = new VoluntadPonderadaEvaluator($cal);
        $rInv = $vol->evaluar($partida, $prop, $hacia);
        if (($rInv['clase'] ?? '') === PropuestaEncuentro::CLASE_COOLDOWN
            || ($rInv['motivo_tecnico'] ?? '') === 'cooldown_propuesta') {
            return self::fin($partida, 'cooldown_en_voluntad', $desde, $hacia, ['quien_rechaza' => $hacia]);
        }
        $pInvitado = (float) ($rInv['p'] ?? 0.0);
        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        if (!($tirada < $pInvitado)) {
            $motivo = (string) ($rInv['motivo_tipo']
                ?? VoluntadPonderadaEvaluator::motivoRechazoPublic($partida, $hacia, $desde, $cal));
            RechazoMemoria::registrar($partida, $hacia, $desde, $motivo, $cal, self::TIPO);
            return self::fin($partida, 'rechazo_voluntad_' . $motivo, $desde, $hacia, [
                'quien_rechaza' => $hacia,
                'p_invitado' => round($pInvitado, 4),
            ]);
        }

        // ---- lugar real (con conocimiento NPC→NPC del invitado) ----
        $ops = $partida['celeste']['lugares_desbloqueados'] ?? [];
        if (!is_array($ops) || $ops === []) {
            $ops = ['lug_cafeteria', 'lug_parque'];
        }
        $lugarElegido = LugarAutonomo::elegir($partida, $desde, $hacia, $ops, $rng, $catalog, $cal);
        if ($lugarElegido === null) {
            $lugarElegido = (string) $ops[0];
        }
        $orden = array_merge([$lugarElegido], array_values(array_diff($ops, [$lugarElegido])));
        $franja = null;
        $lugarFinal = null;
        foreach ($orden as $lid) {
            if (!is_string($lid) || $lid === '' || $lid === 'lug_casa') {
                continue;
            }
            $attr = LugarAtributos::de($lid);
            $f = AgendaConjunta::primeraFranja(
                $partida,
                [$desde, $hacia],
                max(1, (int) ($attr['horas'] ?? 1)),
                9,
                22,
                (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                3,
                $lid
            );
            if (!empty($f['ok'])) {
                $horaF = (int) ($f['hora'] ?? -1);
                if ($horaF < 0 || !ComplejoCatalog::estaAbierto($lid, $horaF)) {
                    continue;
                }
                $franja = $f;
                $lugarFinal = $lid;
                break;
            }
        }
        if ($franja === null) {
            return self::fin($partida, 'sin_franja_agenda', $desde, $hacia, ['lugar' => $lugarElegido]);
        }

        // ---- encuentro tipo CANÓNICO 'quedar' ----
        // La autonomía NO consume intervención de Celestine: el contador
        // queda EXACTO (y si el límite dev está activo, la iniciativa no
        // compite con ese presupuesto).
        $usadasPre = (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
        $limite = EncuentroEngine::limiteIntervencionesDia($partida);
        $bypassLimite = $limite !== null && $usadasPre >= $limite;
        if ($bypassLimite) {
            $partida['celeste']['intervenciones_organizadas_usadas_hoy'] = $limite - 1;
        }
        $r = EncuentroEngine::programar(
            $partida,
            [$desde, $hacia],
            (int) $franja['dia'],
            (int) $franja['hora'],
            self::TIPO,
            $lugarFinal,
            null,
            $logger
        );
        if ($bypassLimite || ($r['ok'] ?? false)) {
            $partida['celeste']['intervenciones_organizadas_usadas_hoy'] = $usadasPre;
        }
        if (!($r['ok'] ?? false)) {
            return self::fin($partida, 'error_programar_' . (string) ($r['error'] ?? '?'), $desde, $hacia);
        }
        $attr = LugarAtributos::de((string) $lugarFinal);
        if (isset($r['encuentro']['id'])) {
            foreach ($partida['encuentros'] as $i => $enc) {
                if (($enc['id'] ?? '') === $r['encuentro']['id']) {
                    $partida['encuentros'][$i]['intencion'] = self::INTENCION;
                    $partida['encuentros'][$i]['duracion_minutos'] = $attr['duracion_minutos'];
                    $partida['encuentros'][$i]['duracion_horas'] = $attr['horas'];
                    $partida['encuentros'][$i]['reserva_agenda'] = ['tipo' => 'encuentro', 'origen' => 'autonomo'];
                }
            }
        }
        // Memoria/cooldown por par (ventana en cooldowns.por_familia.iniciativa_social).
        MemoriaEventos::registrar($partida, self::FAMILIA, [$desde, $hacia]);


        return self::fin($partida, 'quedada_agendada', $desde, $hacia, [
            'programado_dia' => (int) $franja['dia'],
            'programado_hora' => (int) $franja['hora'],
            'lugar' => $lugarFinal,
            'p_invitado' => round($pInvitado, 4),
        ]);
    }

    /**
     * Umbral por BANDA canónica (sin números): la banda actual del valor
     * firmado debe alcanzar o superar la banda configurada. Un valor
     * negativo cae en bandas_negativas y NO pasa nunca.
     *
     * @param array<string, mixed> $cal
     */
    public static function pasaUmbralSocial(array $partida, string $desde, string $hacia, array $cal): bool
    {
        $valor = RelacionEngine::valorSocialHacia($partida, $desde, $hacia);
        $bandas = CalibracionConfig::get($cal, 'social.bandas_positivas', []);
        $bandas = is_array($bandas) ? $bandas : [];
        $objetivo = (string) CalibracionConfig::get($cal, 'iniciativa_social.umbral_banda', 'cae_bien');
        $idxObjetivo = array_search($objetivo, $bandas, true);
        if ($idxObjetivo === false) {
            // Sin escala canónica: exigir al menos valor positivo.
            return $valor > 0;
        }
        $actual = RelacionBandas::social($valor, true, $cal);
        $idxActual = array_search($actual, $bandas, true);
        return $idxActual !== false && $idxActual >= $idxObjetivo;
    }

    /**
     * ¿Ya hay plan futuro/en curso equivalente (social) de este par?
     */
    public static function hayPlanSocialEquivalente(array $partida, string $a, string $b): bool
    {
        foreach (EncuentroEngine::list($partida) as $e) {
            if (!is_array($e) || !in_array((string) ($e['tipo'] ?? ''), self::TIPOS_PLAN_EQUIVALENTE, true)) {
                continue;
            }
            if (!in_array((string) ($e['estado'] ?? ''), ['programado', 'en_curso', 'pendiente'], true)) {
                continue;
            }
            $parts = is_array($e['participantes'] ?? null) ? $e['participantes'] : [];
            if (in_array($a, $parts, true) && in_array($b, $parts, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * ¿Existe ALGUIEN que pueda intentar algo esta hora? Chequeo barato sin RNG.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     */
    private static function hayIntentoPosible(array $partida, array $cal): bool
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        foreach (array_keys($partida['residentes'] ?? []) as $id) {
            $id = (string) $id;
            $res = $partida['residentes'][$id];
            if (!is_array($res) || (($res['presencia'] ?? '') !== 'residente')) {
                continue;
            }
            if (self::intentosHoy($partida, $id) >= self::maxDiario($cal)) {
                continue;
            }
            $disp = AgendaEngine::estaDisponible($partida, $id, $dia, $hora);
            if (!($disp['disponible'] ?? false)) {
                continue;
            }
            if (self::candidatosDe($partida, $id, $cal) !== []) {
                return true;
            }
        }
        return false;
    }

    /**
     * Objetivos elegibles para $desde: TODOS los gates de par, sin RNG ni pesos.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return list<string>
     */
    private static function candidatosDe(array $partida, string $desde, array $cal): array
    {
        $out = [];
        foreach ($partida['residentes'] ?? [] as $id => $res) {
            $id = (string) $id;
            if ($id === $desde || !is_array($res) || (($res['presencia'] ?? '') !== 'residente')) {
                continue;
            }
            if (!RelacionEngine::seConocen($partida, $desde, $id)
                || !self::pasaUmbralSocial($partida, $desde, $id, $cal)
                || self::conflictoActivo($partida, $desde, $id)
                || self::hayPlanSocialEquivalente($partida, $desde, $id)) {
                continue;
            }
            if (PropuestaCooldown::activo($partida, $desde, $id, self::TIPO, $cal)
                || PropuestaCooldown::activo($partida, $id, $desde, self::TIPO, $cal)
                || MemoriaEventos::enCooldown($partida, self::FAMILIA, [$desde, $id], $cal)) {
                continue;
            }
            $out[] = $id;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     */
    private static function elegirIniciador(array $partida, array $cal, RngService $rng): ?string
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $maxDia = self::maxDiario($cal);
        $pocoActivo = (float) CalibracionConfig::get($cal, 'autonomia.poco_activo_bonus', 1.6);
        $pesos = [];
        foreach ($partida['residentes'] ?? [] as $id => $res) {
            $id = (string) $id;
            if (!is_array($res) || (($res['presencia'] ?? '') !== 'residente')) {
                continue;
            }
            if (self::intentosHoy($partida, $id) >= $maxDia) {
                continue;
            }
            $disp = AgendaEngine::estaDisponible($partida, $id, $dia, $hora);
            if (!($disp['disponible'] ?? false)) {
                continue;
            }
            if (self::candidatosDe($partida, $id, $cal) === []) {
                continue;
            }
            $w = 1.0;
            $emo = (string) ($res['runtime']['estado_emocional']['id'] ?? EstadoEmocional::NEUTRO);
            $w += ((int) EstadoEmocional::modificadores($emo, $cal)['iniciativa_social']) / 40.0;
            $ult = (int) ($res['runtime']['ultimo_protagonismo_dia'] ?? 0);
            if ($ult === 0 || ($dia - $ult) >= 3) {
                $w *= $pocoActivo;
            }
            $pesos[] = ['id' => $id, 'w' => max(0.05, $w)];
        }
        return self::pickPeso($pesos, $rng);
    }

    /**
     * Objetivo social: relaciones positivas primero, contacto reciente
     * positivo suma poco, repetición reciente del par penaliza.
     * SIN química romántica ni señales románticas.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     */
    private static function elegirObjetivo(array $partida, string $desde, array $cal, RngService $rng): ?string
    {
        $bonusReciente = (float) CalibracionConfig::get($cal, 'iniciativa_social.bonus_contacto_reciente', 0.6);
        $repDias = (int) CalibracionConfig::get($cal, 'iniciativa_social.penal_repeticion_dias', 7);
        $repFactor = (float) CalibracionConfig::get($cal, 'iniciativa_social.penal_repeticion_factor', 0.35);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $pesos = [];
        foreach (self::candidatosDe($partida, $desde, $cal) as $id) {
            $soc = RelacionEngine::valorSocialHacia($partida, $desde, $id);
            // Semántica SIEMPRE firmada: positivo ayuda, negativo jamás suma.
            $w = 1.0 + max(0, $soc) / 25.0;
            if ($bonusReciente > 0.0 && self::ultimoContactoRecientePositivo($partida, $desde, $id, $dia)) {
                $w += $bonusReciente;
            }
            if ($repDias > 0 && $repFactor > 0.0 && self::intentoRecienteDelPar($partida, $desde, $id, $dia, $repDias)) {
                $w *= $repFactor;
            }
            $pesos[] = ['id' => $id, 'w' => max(0.05, $w)];
        }
        return self::pickPeso($pesos, $rng);
    }

    /**
     * Último encuentro resuelto del par fue bien/muy_bien (MemoriaEventos).
     */
    private static function ultimoContactoRecientePositivo(array $partida, string $a, string $b, int $diaHoy): bool
    {
        foreach (array_reverse($partida['memoria_eventos'] ?? []) as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $parts = is_array($ev['participantes'] ?? null) ? $ev['participantes'] : [];
            if (count($parts) < 2 || !in_array($a, $parts, true) || !in_array($b, $parts, true)) {
                continue;
            }
            $res = (string) ($ev['resultado_experiencia'] ?? '');
            if ($res === '') {
                continue;
            }
            return $res === 'bien' || $res === 'muy_bien';
        }
        return false;
    }

    /**
     * ¿Intento previo (pasó gates) de $desde hacia $hacia en los últimos N días?
     */
    private static function intentoRecienteDelPar(array $partida, string $desde, string $hacia, int $diaHoy, int $dias): bool
    {
        foreach (array_reverse($partida['iniciativa_social_log'] ?? []) as $row) {
            if (!is_array($row) || ($row['desde'] ?? '') !== $desde || ($row['hacia'] ?? '') !== $hacia) {
                continue;
            }
            $res = (string) ($row['resultado'] ?? '');
            if ($res === '' || str_starts_with($res, 'gate_')) {
                continue;
            }
            if (($diaHoy - (int) ($row['dia'] ?? 0)) <= $dias) {
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * Intentos que PASARON gates hoy por el iniciador (los gates baratos no gastan cupo).
     */
    public static function intentosHoy(array $partida, string $desde): int
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $n = 0;
        foreach ($partida['iniciativa_social_log'] ?? [] as $row) {
            if (!is_array($row) || ($row['desde'] ?? '') !== $desde || (int) ($row['dia'] ?? 0) !== $dia) {
                continue;
            }
            $res = (string) ($row['resultado'] ?? '');
            if ($res === 'quedada_agendada' || str_starts_with($res, 'rechazo_voluntad_')
                || str_starts_with($res, 'plan_geom_rechazado_')) {
                $n++;
            }
        }
        return $n;
    }

    private static function maxDiario(array $cal): int
    {
        return max(1, (int) CalibracionConfig::get($cal, 'iniciativa_social.max_por_residente_dia', 1));
    }

    /**
     * Canal conflicto estructural abierto entre el par (cualquier dirección).
     */
    private static function conflictoActivo(array $partida, string $a, string $b): bool
    {
        $conf = RelacionEngine::obtenerEntre($partida, $a, $b)['conflicto']['intensidad'] ?? null;
        return is_numeric($conf) && (int) $conf > 0;
    }

    /**
     * @param list<array{id:string,w:float|int}> $pesos
     */
    private static function pickPeso(array $pesos, RngService $rng): ?string
    {
        if ($pesos === []) {
            return null;
        }
        $sum = 0.0;
        foreach ($pesos as $p) {
            $sum += (float) ($p['w'] ?? 0);
        }
        if ($sum <= 0) {
            return (string) $pesos[0]['id'];
        }
        $pick = $rng->nextFloat() * $sum;
        $acc = 0.0;
        foreach ($pesos as $p) {
            $acc += (float) $p['w'];
            if ($pick <= $acc) {
                return (string) $p['id'];
            }
        }
        return (string) $pesos[count($pesos) - 1]['id'];
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    private static function fin(array &$partida, string $resultado, string $desde, string $hacia, array $extra = []): array
    {
        self::ensure($partida);
        $row = array_merge([
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            'desde' => $desde,
            'hacia' => $hacia,
            'resultado' => $resultado,
        ], $extra);
        $partida['iniciativa_social_log'][] = $row;
        if (count($partida['iniciativa_social_log']) > self::LOG_MAX) {
            $partida['iniciativa_social_log'] = array_slice($partida['iniciativa_social_log'], -self::LOG_MAX);
        }
        return array_merge(['ok' => $resultado === 'quedada_agendada'], $row);
    }
}
