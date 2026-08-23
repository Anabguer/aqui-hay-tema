<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

/**
 * Motor diario separado de encuentros.
 * Presupuesto limitado y variable. No tira todos los eventos sobre todos.
 * Inactivo en play mientras presupuesto/activo_en_play no estén cerrados.
 */
final class AcontecimientoDiario
{
    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function planificar(array $partida, CatalogStore $store, array $cal, RngService $rng): array
    {
        $activo = (bool) CalibracionConfig::get($cal, 'acontecimientos_dia.activo_en_play', false);
        $plan = [
            '_provisional' => true,
            'activo' => $activo,
            'presupuesto' => null,
            'huecos' => [],
            'candidatos_por_id' => [],
        ];
        foreach ($store->items('acontecimientos') as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $plan['candidatos_por_id'][$id] = count(AcontecimientoElegibilidad::candidatos($partida, $item, $cal));
        }
        $n = count($partida['residentes'] ?? []);
        if ($n > 0) {
            $plan['presupuesto'] = MotorVidaDiaria::presupuesto($n, $cal, $rng);
            $plan['huecos'] = MotorVidaDiaria::repartirHuecos((int) $plan['presupuesto'], $cal, $rng);
        }
        return $plan;
    }

    /**
     * Ejecuta un acontecimiento concreto (tests / hito). No usa probabilidad inventada.
     *
     * @param list<string> $participantes
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function ejecutar(
        array &$partida,
        string $eventoId,
        array $participantes,
        CatalogStore $store,
        array $cal,
        ?GameLogger $logger = null
    ): array {
        $item = $store->item('acontecimientos', $eventoId);
        if ($item === null) {
            return ['ok' => false, 'error' => 'evento_desconocido'];
        }
        if (MemoriaEventos::enCooldown($partida, (string) ($item['familia'] ?? $eventoId), $participantes, $cal)) {
            return ['ok' => false, 'error' => 'cooldown'];
        }
        $el = AcontecimientoElegibilidad::cumple($partida, $item, $participantes, $cal);
        if (!$el['ok']) {
            return ['ok' => false, 'error' => 'no_elegible', 'fallos' => $el['fallos']];
        }

        $efectos = [];
        $romanceTocado = false;

        if ($eventoId === 'perder_trabajo') {
            $id = (string) $participantes[0];
            $oc = $partida['residentes'][$id]['runtime']['ocupacion'] ?? null;
            $partida['residentes'][$id]['runtime']['ocupacion_anterior'] = $oc;
            $partida['residentes'][$id]['runtime']['ocupacion'] = 'desempleado';
            TrabajoHorario::limpiarHorario($partida['residentes'][$id]['runtime']);
            $reloj = $partida['reloj'] ?? [];
            $dur = (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas_default.triste', 8);
            $hasta = EstadoEmocional::hastaDesdeDuracion($reloj, $dur);
            $root = dirname(__DIR__, 2);
            $emoSvc = new EmotionalStateService(
                new VisualPackStore($root),
                $store,
                $logger
            );
            $emoSvc->aplicar(
                $partida,
                $id,
                EstadoEmocional::TRISTE,
                'perder_trabajo',
                null,
                $hasta,
                [],
                $dur
            );
            $partida['residentes'][$id]['runtime']['busqueda_trabajo_cd_hasta'] = null;
            $efectos[] = 'desempleado';
            $efectos[] = 'estado_triste';
        }

        if ($eventoId === 'buscar_trabajo') {
            $id = (string) $participantes[0];
            $cdH = (int) CalibracionConfig::get($cal, 'cooldowns.por_familia.trabajo', 72);
            $now = EstadoEmocional::horaAbsoluta($partida['reloj'] ?? []);
            $hastaCd = (int) ($partida['residentes'][$id]['runtime']['busqueda_trabajo_cd_hasta'] ?? 0);
            if ($hastaCd > $now) {
                return ['ok' => false, 'error' => 'cooldown_buscar_trabajo'];
            }
            $partida['residentes'][$id]['runtime']['busqueda_trabajo_cd_hasta'] = $now + max(24, $cdH);
            $partida['residentes'][$id]['runtime']['busqueda_trabajo_estado'] = 'espera';
            $efectos[] = 'busqueda_registrada';
        }

        if ($eventoId === 'encontrar_trabajo') {
            $id = (string) $participantes[0];
            $rng = RngService::fromPartida($partida);
            $profesiones = $store->items('ocupaciones');
            $preferida = $partida['residentes'][$id]['runtime']['ocupacion_anterior'] ?? null;
            $oc = TrabajoHorario::elegirOcupacion(
                $profesiones,
                is_string($preferida) ? $preferida : null,
                $rng
            );
            TrabajoHorario::asignarEmpleo($partida['residentes'][$id]['runtime'], $oc, $rng);
            $rng->persistToPartida($partida);
            $reloj = $partida['reloj'] ?? [];
            $dur = (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas_default.alegre', 4);
            $hasta = EstadoEmocional::hastaDesdeDuracion($reloj, $dur);
            $root = dirname(__DIR__, 2);
            $emoSvc = new EmotionalStateService(
                new VisualPackStore($root),
                $store,
                $logger
            );
            $emoSvc->aplicar(
                $partida,
                $id,
                EstadoEmocional::ALEGRE,
                'encontrar_trabajo',
                null,
                $hasta,
                [],
                $dur
            );
            $efectos[] = 'empleado';
            $efectos[] = 'estado_alegre';
            $efectos[] = 'horario_generado';
        }

        if ($eventoId === 'flechazo' && count($participantes) >= 2) {
            $r = AccionRomantica::ejecutar(
                $partida,
                'flechazo',
                (string) $participantes[0],
                (string) $participantes[1],
                $store,
                $cal,
                true
            );
            if (!($r['ok'] ?? false)) {
                return $r;
            }
            $efectos[] = 'flechazo';
            $romanceTocado = true;
        }

        if (($eventoId === 'mandar_flores' || $eventoId === 'mandar_mensaje') && count($participantes) >= 2) {
            $desde = (string) $participantes[0];
            $hacia = (string) $participantes[1];
            $r = AccionRomantica::ejecutar($partida, $eventoId, $desde, $hacia, $store, $cal, true);
            if (!($r['ok'] ?? false)) {
                return $r;
            }
            $calidad = $eventoId === 'mandar_flores' ? ContactoCalidad::NORMAL : ContactoCalidad::LEVE;
            RelacionEngine::registrarContacto($partida, $desde, $hacia, $calidad, $cal, 1);
            RelacionEngine::registrarContacto($partida, $hacia, $desde, ContactoCalidad::LEVE, $cal, 1);
            if ($eventoId === 'mandar_flores') {
                $act = RelacionEngine::romanceHacia($partida, $desde, $hacia) ?? 0;
                RelacionEngine::setRomanceHacia($partida, $desde, $hacia, $act + 2);
                $romanceTocado = true;
            }
            $efectos[] = $eventoId;
            self::intentarAgendarQuedada($partida, $desde, $hacia, $cal, $logger);
        }

        if ($eventoId === 'declaracion' && count($participantes) >= 2) {
            $a = (string) $participantes[0];
            $b = (string) $participantes[1];
            $vol = new VoluntadPonderadaEvaluator($cal);
            $prop = [
                'participantes' => [$a, $b],
                'tipo' => 'romantico',
                'lugar' => null,
                'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                'hora' => (int) ($partida['reloj']['hora_actual'] ?? 12),
            ];
            $ra = $vol->evaluar($partida, $prop, $a);
            $rb = $vol->evaluar($partida, $prop, $b);
            $aceptaA = ($ra['decision'] ?? '') === PropuestaEncuentro::DECISION_ACEPTA;
            $aceptaB = ($rb['decision'] ?? '') === PropuestaEncuentro::DECISION_ACEPTA;
            $r = ParejaEngine::formar($partida, $a, $b, $aceptaA, $aceptaB, RelacionBitacora::DECLARACION, $cal);
            if (!($r['ok'] ?? false)) {
                if (($ra['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA) {
                    RechazoMemoria::registrar($partida, $a, $b, 'relacional', $cal, 'romantico');
                }
                if (($rb['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA) {
                    RechazoMemoria::registrar($partida, $b, $a, 'relacional', $cal, 'romantico');
                    $rom = RelacionEngine::romanceHacia($partida, $a, $b) ?? 0;
                    $delta = (int) CalibracionConfig::get($cal, 'rechazos.delta_romance_hacia_quien_rechaza', -3);
                    if ($delta !== 0) {
                        RelacionEngine::setRomanceHacia($partida, $a, $b, max(0, $rom + $delta));
                        $romanceTocado = true;
                    }
                }
                $efectos[] = 'declaracion_rechazada';
            } else {
                $efectos[] = !empty($r['vuelta']) ? 'vuelta_pareja' : 'pareja_formada';
            }
        }

        if ($eventoId === 'crisis_pareja' && count($participantes) >= 2) {
            $r = ParejaEngine::crisis($partida, $participantes[0], $participantes[1]);
            if (!($r['ok'] ?? false)) {
                return $r;
            }
            $efectos[] = 'crisis';
        }
        if ($eventoId === 'ruptura' && count($participantes) >= 2) {
            $r = ParejaEngine::romper($partida, $participantes[0], $participantes[1], 'acontecimiento');
            if (!($r['ok'] ?? false)) {
                return $r;
            }
            $efectos[] = 'ruptura';
        }
        if ($eventoId === 'reconciliacion' && count($participantes) >= 2) {
            $a = (string) $participantes[0];
            $b = (string) $participantes[1];
            $vol = new VoluntadPonderadaEvaluator($cal);
            $prop = [
                'participantes' => [$a, $b],
                'tipo' => 'romantico',
                'lugar' => null,
                'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                'hora' => (int) ($partida['reloj']['hora_actual'] ?? 12),
            ];
            $aceptaA = ($vol->evaluar($partida, $prop, $a)['decision'] ?? '') === PropuestaEncuentro::DECISION_ACEPTA;
            $aceptaB = ($vol->evaluar($partida, $prop, $b)['decision'] ?? '') === PropuestaEncuentro::DECISION_ACEPTA;
            $r = ParejaEngine::reconciliar($partida, $a, $b, $aceptaA, $aceptaB, $cal);
            if (!($r['ok'] ?? false)) {
                $efectos[] = 'reconciliacion_fallida';
            } else {
                $efectos[] = 'reconciliacion';
            }
        }

        if ($eventoId === 'actividad_individual' && count($participantes) >= 1) {
            $id = (string) $participantes[0];
            $emo = (string) ($partida['residentes'][$id]['runtime']['estado_emocional']['id'] ?? 'neutro');
            if ($emo === EstadoEmocional::NEUTRO && ((int) ($partida['reloj']['hora_actual'] ?? 0) % 7) === 0) {
                $reloj = $partida['reloj'] ?? [];
                $dur = (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas_default.alegre', 4);
                $hasta = EstadoEmocional::hastaDesdeDuracion($reloj, $dur);
                $root = dirname(__DIR__, 2);
                $emoSvc = new EmotionalStateService(
                    new VisualPackStore($root),
                    $store,
                    $logger
                );
                $emoSvc->aplicar(
                    $partida,
                    $id,
                    EstadoEmocional::ALEGRE,
                    'actividad_individual',
                    null,
                    $hasta,
                    [],
                    $dur
                );
                $efectos[] = 'alegre_breve';
            }
        }

        if ($eventoId === 'consejo_celestine' && count($participantes) >= 1) {
            ConsejoEngine::responder($partida, (string) $participantes[0], 'queda_mas');
            $efectos[] = 'consejo';
        }

        MemoriaEventos::registrar(
            $partida,
            (string) ($item['familia'] ?? $eventoId),
            $participantes,
            null,
            $eventoId
        );
        $vis = (string) ($item['visibilidad_jugador'] ?? 'ninguna');
        $msg = VidaNarrativaBridge::alAcontecimiento(
            $partida,
            $eventoId,
            $participantes,
            $item,
            $efectos,
            $logger
        );
        $partida['acontecimientos_log'] ??= [];
        $row = [
            'id' => $eventoId,
            'familia' => $item['familia'] ?? null,
            'participantes' => $participantes,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'efectos' => $efectos,
            'visibilidad' => $vis,
        ];
        $partida['acontecimientos_log'][] = $row;
        \aht_log_optional($logger, $partida, 'acontecimiento_diario', $row);
        VidaPuebloEngine::aplicarAcontecimientoVida($partida, $eventoId, $item, $cal, $logger);
        return ['ok' => true, 'evento' => $row, 'mensaje' => $msg, 'romance_tocado' => $romanceTocado];
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function intentarAgendarQuedada(
        array &$partida,
        string $a,
        string $b,
        array $cal,
        ?GameLogger $logger
    ): void {
        $ops = $partida['celeste']['lugares_desbloqueados'] ?? [];
        if ($ops === []) {
            $ops = ['lug_cafeteria', 'lug_parque'];
        }
        $lugar = is_array($ops) ? (string) $ops[array_rand($ops)] : 'lug_cafeteria';
        $attr = LugarAtributos::de($lugar);
        $franja = AgendaConjunta::primeraFranja(
            $partida,
            [$a, $b],
            max(1, (int) ($attr['horas'] ?? 1)),
            9,
            22,
            (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            3,
            $lugar
        );
        if (!($franja['ok'] ?? false)) {
            return;
        }
        $tipo = 'conocerse';
        $rom = max(
            (int) (RelacionEngine::romanceHacia($partida, $a, $b) ?? 0),
            (int) (RelacionEngine::romanceHacia($partida, $b, $a) ?? 0)
        );
        if ($rom >= 22) {
            $tipo = 'romantico';
        }
        $r = EncuentroEngine::programar(
            $partida,
            [$a, $b],
            (int) $franja['dia'],
            (int) $franja['hora'],
            $tipo,
            $lugar,
            null,
            $logger
        );
        if (($r['ok'] ?? false) && isset($r['encuentro']['id'])) {
            foreach ($partida['encuentros'] as $i => $enc) {
                if (($enc['id'] ?? '') === $r['encuentro']['id']) {
                    $partida['encuentros'][$i]['duracion_minutos'] = $attr['duracion_minutos'];
                    $partida['encuentros'][$i]['duracion_horas'] = $attr['horas'];
                    $partida['encuentros'][$i]['intencion'] = 'autonomo_relacion';
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function alCerrarDia(
        array &$partida,
        Catalog $catalog,
        array $cal,
        ?GameLogger $logger = null
    ): array {
        if (!(bool) CalibracionConfig::get($cal, 'acontecimientos_dia.activo_en_play', false)) {
            return ['ok' => true, 'omitido' => 'inactivo_en_play'];
        }
        $rng = RngService::fromPartida($partida);
        $plan = self::planificar($partida, $catalog->store(), $cal, $rng);
        $rng->persistToPartida($partida);
        if ($plan['presupuesto'] === null) {
            return ['ok' => true, 'omitido' => 'presupuesto_no_calibrado', 'plan' => $plan];
        }
        return ['ok' => true, 'plan' => $plan, 'ejecutados' => []];
    }

    public static function clasificacionVisibilidad(string $vis): string
    {
        if ($vis === 'importante' || $vis === 'aviso') {
            return BuzonEngine::IMPORTANTE;
        }
        if ($vis === 'oportunidad') {
            return BuzonEngine::OPORTUNIDAD;
        }
        if ($vis === 'peticion') {
            return BuzonEngine::PETICION;
        }
        return BuzonEngine::COTILLEO;
    }
}
