<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

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
        if ($eventoId === 'perder_trabajo') {
            $id = $participantes[0];
            $oc = $partida['residentes'][$id]['runtime']['ocupacion'] ?? null;
            $partida['residentes'][$id]['runtime']['ocupacion_anterior'] = $oc;
            $partida['residentes'][$id]['runtime']['ocupacion'] = 'desempleado';
            $reloj = $partida['reloj'] ?? [];
            $partida['residentes'][$id]['runtime']['estado_emocional'] = EstadoEmocional::estructura(
                'triste',
                null,
                'perder_trabajo',
                EstadoEmocional::marcaReloj($reloj)
            );
            $efectos[] = 'desempleado';
            $efectos[] = 'estado_triste';
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

        MemoriaEventos::registrar(
            $partida,
            (string) ($item['familia'] ?? $eventoId),
            $participantes,
            null,
            $eventoId
        );
        $vis = (string) ($item['visibilidad_jugador'] ?? 'ninguna');
        $msg = null;
        if ($vis !== 'ninguna') {
            $msg = BuzonEngine::crear($partida, [
                'de_persona' => $participantes[0] ?? null,
                'tipo' => $vis === 'peticion' ? 'peticion' : 'novedad',
                'clasificacion' => self::clasificacionDeVisibilidad($vis),
                'texto' => '',
                'copy_id' => null,
                'importancia' => $item['importancia'] ?? 'relevante',
                '_placeholder_contenido' => true,
            ]);
        }
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
        return ['ok' => true, 'evento' => $row, 'mensaje' => $msg, 'romance_tocado' => false];
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

    private static function clasificacionDeVisibilidad(string $vis): string
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
