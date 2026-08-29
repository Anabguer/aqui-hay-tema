<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * B1 — Núcleo de eventos colectivos del pueblo (sistema, no Celestine).
 *
 * Programa un encuentro multi-participante vía EncuentroEngine (tipo otro),
 * intencion=evento_pueblo, sin presupuesto de intervenciones Celestine.
 * Resolución: lifecycle/EncuentroResolver canónicos.
 */
final class EventosPuebloEngine
{
    public const INTENCION = 'evento_pueblo';
    public const ORIGEN = 'sistema';
    public const TIPO_ENCUENTRO = 'otro';
    private const LOG_MAX = 200;

    public static function ensure(array &$partida): void
    {
        $partida['eventos_pueblo'] ??= ['programados' => [], 'log' => []];
        $partida['eventos_pueblo']['programados'] ??= [];
        $partida['eventos_pueblo']['log'] ??= [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function catalogItems(Catalog $catalog): array
    {
        return $catalog->store()->items('eventos_pueblo');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function catalogItem(Catalog $catalog, string $eventoId): ?array
    {
        return $catalog->store()->item('eventos_pueblo', $eventoId);
    }

    /**
     * Elige una definicion del catalogo segun campo opcional `peso` (default 1).
     *
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    public static function elegirItemCatalogo(array $items, RngService $rng): ?array
    {
        if ($items === []) {
            return null;
        }
        $total = 0;
        foreach ($items as $item) {
            $total += max(1, (int) ($item['peso'] ?? 1));
        }
        if ($total <= 0) {
            return $items[0];
        }
        $roll = $rng->nextInt(1, $total);
        $acc = 0;
        foreach ($items as $item) {
            $acc += max(1, (int) ($item['peso'] ?? 1));
            if ($roll <= $acc) {
                return $item;
            }
        }

        return $items[count($items) - 1];
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function alComenzarDia(
        array &$partida,
        array $cal,
        RngService $rng,
        Catalog $catalog,
        ?GameLogger $logger = null
    ): ?array {
        if (!self::activo($partida, $cal)) {
            return null;
        }
        $prob = (float) CalibracionConfig::get($cal, 'eventos_pueblo.prob_planificar_dia', 0.22);
        if ($rng->nextFloat() > $prob) {
            return null;
        }
        $items = self::catalogItems($catalog);
        $def = self::elegirItemCatalogo($items, $rng);
        if ($def === null) {
            return null;
        }
        $id = (string) ($def['id'] ?? '');
        if ($id === '') {
            return null;
        }
        return self::planificar($partida, $id, $cal, $rng, $catalog, $logger);
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function planificar(
        array &$partida,
        string $eventoId,
        array $cal,
        RngService $rng,
        Catalog $catalog,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);
        $def = self::catalogItem($catalog, $eventoId);
        if ($def === null) {
            return self::fin($partida, 'gate_catalogo_desconocido', $eventoId);
        }
        if (self::eventoActivo($partida, $eventoId)) {
            return self::fin($partida, 'gate_evento_activo', $eventoId);
        }
        $activos = self::contarActivos($partida);
        $maxActivos = (int) CalibracionConfig::get($cal, 'eventos_pueblo.max_activos', 1);
        if ($activos >= $maxActivos) {
            return self::fin($partida, 'gate_cupo_activos', $eventoId);
        }

        $lugar = self::elegirLugar($partida, $def, $rng);
        if ($lugar === null) {
            return self::fin($partida, 'gate_sin_lugar', $eventoId);
        }
        $attr = LugarAtributos::de($lugar);
        $durH = max(1, (int) ($def['duracion_horas'] ?? $attr['horas'] ?? 2));
        $franja = self::buscarFranja($partida, $def, $lugar, $durH);
        if ($franja === null) {
            return self::fin($partida, 'sin_franja_valida', $eventoId, ['lugar' => $lugar]);
        }

        $diaEvt = (int) $franja['dia'];
        $horaEvt = (int) $franja['hora'];
        $disponibles = self::residentesDisponibles($partida, $diaEvt, $horaEvt, $durH);
        $minP = (int) ($def['participantes_min'] ?? 3);
        if (count($disponibles) < $minP) {
            return self::fin($partida, 'participantes_insuficientes', $eventoId, [
                'disponibles' => count($disponibles),
                'min' => $minP,
            ]);
        }
        $aforo = self::aforoCanonico($partida, $def, $lugar, $diaEvt, $horaEvt);
        if ($aforo < $minP) {
            return self::fin($partida, 'aforo_insuficiente', $eventoId, ['lugar' => $lugar, 'aforo' => $aforo]);
        }

        $usadasAntes = (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
        $evtId = self::nuevoId($partida, $rng);

        $row = [
            'id' => $evtId,
            'catalogo_id' => $eventoId,
            'nombre' => (string) ($def['nombre'] ?? $eventoId),
            'familia' => (string) ($def['familia'] ?? 'ocio_colectivo'),
            'encuentro_id' => '',
            'dia' => $diaEvt,
            'hora' => $horaEvt,
            'lugar' => $lugar,
            'duracion_horas' => $durH,
            'estado' => 'programado',
            'seleccion_estado' => 'pendiente_asistentes',
            'participantes' => [],
            'aforo' => $aforo,
            'participantes_min' => $minP,
            'participantes_max' => (int) ($def['participantes_max'] ?? 8),
            'origen' => self::ORIGEN,
        ];
        $partida['eventos_pueblo']['programados'][] = $row;

        EventosPuebloAnuncioEngine::anunciarTrasProgramar($partida, $row, $catalog, $cal, $rng, $logger);

        return self::fin($partida, 'evento_programado', $eventoId, [
            'ok' => true,
            'evento' => $row,
            'encuentro_id' => '',
            'intervenciones_celeste_antes' => $usadasAntes,
            'intervenciones_celeste_despues' => (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buscarProgramadoPorId(array $partida, string $eventoId): ?array
    {
        self::ensure($partida);
        foreach ($partida['eventos_pueblo']['programados'] as $ev) {
            if (!is_array($ev) || (string) ($ev['id'] ?? '') !== $eventoId) {
                continue;
            }
            return $ev;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $ev
     */
    public static function seleccionEstado(array $ev): string
    {
        $st = (string) ($ev['seleccion_estado'] ?? '');
        if ($st !== '') {
            return $st;
        }
        if ((string) ($ev['encuentro_id'] ?? '') !== '') {
            return 'confirmado';
        }

        return 'pendiente_asistentes';
    }

    /**
     * @param array<string, mixed> $ev
     * @param array<string, mixed>|null $def
     */
    public static function aforoEvento(array $partida, array $ev, ?array $def = null, ?Catalog $catalog = null): int
    {
        $stored = (int) ($ev['aforo'] ?? 0);
        if ($stored > 0) {
            return $stored;
        }
        $catalogoId = (string) ($ev['catalogo_id'] ?? '');
        if ($def === null && $catalog !== null && $catalogoId !== '') {
            $def = self::catalogItem($catalog, $catalogoId);
        }
        if (!is_array($def)) {
            return max(1, (int) ($ev['participantes_max'] ?? 8));
        }
        $lugar = (string) ($ev['lugar'] ?? '');
        $dia = (int) ($ev['dia'] ?? 0);
        $hora = (int) ($ev['hora'] ?? 0);
        if ($lugar === '' || $dia <= 0) {
            return max(1, (int) ($def['participantes_max'] ?? 8));
        }

        return self::aforoCanonico($partida, $def, $lugar, $dia, $hora);
    }

    /**
     * @param array<string, mixed> $ev
     */
    public static function plazasDisponibles(array $partida, array $ev, ?array $def = null, ?Catalog $catalog = null): int
    {
        $aforo = self::aforoEvento($partida, $ev, $def, $catalog);
        $confirmados = is_array($ev['participantes'] ?? null) ? count($ev['participantes']) : 0;

        return max(0, $aforo - $confirmados);
    }

    /**
     * @param array<string, mixed> $def
     */
    public static function aforoCanonico(array $partida, array $def, string $lugar, int $dia, int $hora): int
    {
        $maxCat = max(1, (int) ($def['participantes_max'] ?? 8));
        $attr = LugarAtributos::de($lugar);
        $libre = max(0, (int) $attr['aforo'] - AforoEngine::ocupacion($partida, $lugar, $dia, $hora));

        return min($maxCat, $libre);
    }

    /**
     * Vecinos elegibles para que Celestine apunte asistentes (sin auto-selección del motor).
     *
     * @return array<string, mixed>
     */
    public static function vecinosElegibles(
        array $partida,
        string $eventoId,
        ?array $cal,
        Catalog $catalog
    ): array {
        self::ensure($partida);
        $ev = self::buscarProgramadoPorId($partida, $eventoId);
        if ($ev === null) {
            return ['ok' => false, 'error' => 'evento_no_encontrado'];
        }
        if (self::seleccionEstado($ev) === 'confirmado') {
            return [
                'ok' => true,
                'evento_id' => $eventoId,
                'seleccion_estado' => 'confirmado',
                'aforo' => self::aforoEvento($partida, $ev, null, $catalog),
                'plazas_disponibles' => 0,
                'participantes' => is_array($ev['participantes'] ?? null) ? $ev['participantes'] : [],
                'vecinos' => [],
            ];
        }
        $def = self::catalogItem($catalog, (string) ($ev['catalogo_id'] ?? '')) ?? [];
        $durH = max(1, (int) ($ev['duracion_horas'] ?? ($def['duracion_horas'] ?? 2)));
        $dia = (int) ($ev['dia'] ?? 0);
        $hora = (int) ($ev['hora'] ?? 0);
        $aforo = self::aforoEvento($partida, $ev, $def, $catalog);
        $plazas = self::plazasDisponibles($partida, $ev, $def, $catalog);
        $yaSel = is_array($ev['participantes'] ?? null)
            ? array_fill_keys(array_map('strval', $ev['participantes']), true)
            : [];
        $disponibles = array_fill_keys(self::residentesDisponibles($partida, $dia, $hora, $durH), true);
        $recomendados = self::ordenRecomendados($partida, $def, array_keys($disponibles), $cal ?? []);
        $vecinos = [];
        foreach (array_keys($partida['residentes'] ?? []) as $id) {
            $id = (string) $id;
            $res = $partida['residentes'][$id] ?? null;
            if (!is_array($res)) {
                continue;
            }
            $motivo = null;
            $elegible = true;
            if (($res['presencia'] ?? '') !== 'residente') {
                $elegible = false;
                $motivo = 'no_residente';
            } elseif (isset($yaSel[$id])) {
                $elegible = false;
                $motivo = 'ya_seleccionado';
            } elseif (!isset($disponibles[$id])) {
                $elegible = false;
                $motivo = 'agenda_ocupada';
            }
            $vecinos[] = [
                'id' => $id,
                'nombre' => IdentidadPublica::nombre($partida, $id),
                'elegible' => $elegible,
                'motivo' => $motivo,
                'recomendado' => in_array($id, $recomendados, true),
            ];
        }
        usort($vecinos, static function (array $a, array $b) use ($recomendados): int {
            if (($a['elegible'] ?? false) !== ($b['elegible'] ?? false)) {
                return ($b['elegible'] ?? false) <=> ($a['elegible'] ?? false);
            }
            $ra = array_search($a['id'], $recomendados, true);
            $rb = array_search($b['id'], $recomendados, true);
            $ra = $ra === false ? 9999 : (int) $ra;
            $rb = $rb === false ? 9999 : (int) $rb;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            return strcmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
        });

        return [
            'ok' => true,
            'evento_id' => $eventoId,
            'seleccion_estado' => self::seleccionEstado($ev),
            'aforo' => $aforo,
            'plazas_disponibles' => $plazas,
            'participantes_min' => (int) ($ev['participantes_min'] ?? ($def['participantes_min'] ?? 3)),
            'vecinos' => $vecinos,
        ];
    }

    /**
     * Celestine confirma asistentes: crea el encuentro canónico (idempotente).
     *
     * @param list<string> $participantesIds
     * @return array<string, mixed>
     */
    public static function confirmarAsistentes(
        array &$partida,
        string $eventoId,
        array $participantesIds,
        array $cal,
        Catalog $catalog,
        ?GameLogger $logger = null,
        string $seleccionOrigen = 'celestine'
    ): array {
        self::ensure($partida);
        $idx = self::indiceProgramado($partida, $eventoId);
        if ($idx === null) {
            return ['ok' => false, 'error' => 'evento_no_encontrado'];
        }
        $ev = $partida['eventos_pueblo']['programados'][$idx];
        if (!is_array($ev)) {
            return ['ok' => false, 'error' => 'evento_no_encontrado'];
        }
        $def = self::catalogItem($catalog, (string) ($ev['catalogo_id'] ?? ''));
        if ($def === null) {
            return ['ok' => false, 'error' => 'catalogo_desconocido'];
        }

        $ids = [];
        foreach ($participantesIds as $rid) {
            $rid = (string) $rid;
            if ($rid !== '') {
                $ids[] = $rid;
            }
        }
        $ids = array_values(array_unique($ids));

        $estadoSel = self::seleccionEstado($ev);
        $encIdPrev = (string) ($ev['encuentro_id'] ?? '');
        $prev = is_array($ev['participantes'] ?? null) ? array_values(array_map('strval', $ev['participantes'])) : [];
        if ($estadoSel === 'confirmado' && $encIdPrev !== '') {
            sort($ids);
            $prevSorted = $prev;
            sort($prevSorted);
            if ($ids === $prevSorted) {
                return [
                    'ok' => true,
                    'idempotente' => true,
                    'evento_id' => $eventoId,
                    'encuentro_id' => $encIdPrev,
                    'participantes' => $prev,
                ];
            }
            return ['ok' => false, 'error' => 'seleccion_ya_confirmada'];
        }

        $minP = (int) ($ev['participantes_min'] ?? ($def['participantes_min'] ?? 3));
        if (count($ids) < $minP) {
            return ['ok' => false, 'error' => 'participantes_insuficientes', 'min' => $minP];
        }
        $aforo = self::aforoEvento($partida, $ev, $def, $catalog);
        if (count($ids) > $aforo) {
            return GameError::respuesta(GameError::PARTICIPANTES_EXCESO, ['max' => $aforo, 'aforo' => $aforo]);
        }

        $dia = (int) ($ev['dia'] ?? 0);
        $hora = (int) ($ev['hora'] ?? 0);
        $lugar = (string) ($ev['lugar'] ?? '');
        $durH = max(1, (int) ($ev['duracion_horas'] ?? ($def['duracion_horas'] ?? 2)));
        $catalogoId = (string) ($ev['catalogo_id'] ?? '');

        $elegibles = self::vecinosElegibles($partida, $eventoId, $cal, $catalog);
        $mapEleg = [];
        foreach ($elegibles['vecinos'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mapEleg[(string) ($row['id'] ?? '')] = (bool) ($row['elegible'] ?? false);
        }
        foreach ($ids as $rid) {
            if (!($mapEleg[$rid] ?? false)) {
                return ['ok' => false, 'error' => 'vecino_no_elegible', 'residente' => $rid];
            }
        }
        if (!AforoEngine::cabe($partida, $lugar, $dia, $hora, count($ids))) {
            return GameError::respuesta(GameError::AFORO_COMPLETO, ['lugar' => $lugar, 'aforo' => $aforo]);
        }

        $usadasAntes = (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
        $nowD = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $nowH = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $enSlotEvento = ($dia === $nowD && $hora === $nowH);
        $r = EncuentroEngine::programar(
            $partida,
            $ids,
            $dia,
            $hora,
            self::TIPO_ENCUENTRO,
            $lugar,
            $catalogoId,
            $logger,
            false,
            $enSlotEvento
        );
        if (!($r['ok'] ?? false)) {
            return $r;
        }

        $encId = (string) ($r['encuentro']['id'] ?? '');
        $attr = LugarAtributos::de($lugar);
        foreach ($partida['encuentros'] as $i => $enc) {
            if (($enc['id'] ?? '') !== $encId) {
                continue;
            }
            $partida['encuentros'][$i]['intencion'] = self::INTENCION;
            $partida['encuentros'][$i]['duracion_horas'] = $durH;
            $partida['encuentros'][$i]['duracion_minutos'] = min(
                (int) ($attr['duracion_minutos'] ?? 120),
                $durH * 60
            );
            $partida['encuentros'][$i]['reserva_agenda'] = ['tipo' => 'encuentro', 'origen' => self::ORIGEN];
            $partida['encuentros'][$i]['evento_pueblo_id'] = $eventoId;
            $partida['encuentros'][$i]['evento_pueblo_catalogo_id'] = $catalogoId;
        }

        $partida['eventos_pueblo']['programados'][$idx]['participantes'] = $ids;
        $partida['eventos_pueblo']['programados'][$idx]['encuentro_id'] = $encId;
        $partida['eventos_pueblo']['programados'][$idx]['seleccion_estado'] = 'confirmado';
        $partida['eventos_pueblo']['programados'][$idx]['seleccion_origen'] = $seleccionOrigen;
        $partida['eventos_pueblo']['programados'][$idx]['seleccion_confirmada_en'] = [
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
        ];

        return [
            'ok' => true,
            'evento_id' => $eventoId,
            'encuentro_id' => $encId,
            'participantes' => $ids,
            'aforo' => $aforo,
            'seleccion_estado' => 'confirmado',
            'seleccion_origen' => $seleccionOrigen,
            'intervenciones_celeste_antes' => $usadasAntes,
            'intervenciones_celeste_despues' => (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0),
        ];
    }

    /**
     * Al cruzar la hora del evento: confirma asistentes automáticamente o cancela si no hay cupo mínimo.
     *
     * @return array<string, mixed>
     */
    public static function resolverAsistentesPendientesConReloj(
        array &$partida,
        array $cal,
        Catalog $catalog,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);
        $now = ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24 + (int) ($partida['reloj']['hora_actual'] ?? 0);
        $detalle = [];

        foreach ($partida['eventos_pueblo']['programados'] as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if (self::seleccionEstado($ev) !== 'pendiente_asistentes') {
                continue;
            }
            if ((string) ($ev['estado'] ?? 'programado') === 'cancelado') {
                continue;
            }
            $start = ((int) ($ev['dia'] ?? 0)) * 24 + (int) ($ev['hora'] ?? 0);
            if ($now < $start) {
                continue;
            }

            $evtId = (string) ($ev['id'] ?? '');
            if ($evtId === '') {
                continue;
            }
            $def = self::catalogItem($catalog, (string) ($ev['catalogo_id'] ?? ''));
            if ($def === null) {
                continue;
            }

            $ids = self::seleccionAutomaticaIds($partida, $ev, $def, $cal, $catalog);
            $minP = (int) ($ev['participantes_min'] ?? ($def['participantes_min'] ?? 3));
            if (count($ids) < $minP) {
                $r = self::cancelarEventoProgramado($partida, $evtId, 'participantes_insuficientes', $logger);
                $detalle[] = ['evento_id' => $evtId, 'accion' => 'cancelado', 'resultado' => $r];
                continue;
            }

            $r = self::confirmarAsistentes($partida, $evtId, $ids, $cal, $catalog, $logger, 'autonomo');
            if (!($r['ok'] ?? false)) {
                if (($r['error'] ?? '') === 'participantes_insuficientes') {
                    $r = self::cancelarEventoProgramado($partida, $evtId, 'participantes_insuficientes', $logger);
                    $detalle[] = ['evento_id' => $evtId, 'accion' => 'cancelado', 'resultado' => $r];
                    continue;
                }
                $detalle[] = ['evento_id' => $evtId, 'accion' => 'error', 'resultado' => $r];
                continue;
            }
            $detalle[] = ['evento_id' => $evtId, 'accion' => 'confirmado_autonomo', 'resultado' => $r];
        }

        return ['resueltos' => count($detalle), 'detalle' => $detalle];
    }

    /**
     * Selección autónoma reutilizando residentesDisponibles + ordenRecomendados (misma lógica canónica).
     *
     * @param array<string, mixed> $ev
     * @param array<string, mixed> $def
     * @return list<string>
     */
    public static function seleccionAutomaticaIds(
        array $partida,
        array $ev,
        array $def,
        array $cal,
        Catalog $catalog
    ): array {
        $durH = max(1, (int) ($ev['duracion_horas'] ?? ($def['duracion_horas'] ?? 2)));
        $dia = (int) ($ev['dia'] ?? 0);
        $hora = (int) ($ev['hora'] ?? 0);
        $disponibles = self::residentesDisponibles($partida, $dia, $hora, $durH);
        if ($disponibles === []) {
            return [];
        }
        $orden = self::ordenRecomendados($partida, $def, $disponibles, $cal);
        $minP = (int) ($ev['participantes_min'] ?? ($def['participantes_min'] ?? 3));
        $maxP = self::aforoEvento($partida, $ev, $def, $catalog);
        if (count($orden) < $minP) {
            return [];
        }
        $want = min($maxP, count($orden));
        $want = max($minP, $want);
        if ($want > count($orden)) {
            return $orden;
        }

        return array_slice($orden, 0, $want);
    }

    /**
     * Cancela un evento programado sin crear encuentro ni cierre narrativo de éxito.
     *
     * @return array<string, mixed>
     */
    public static function cancelarEventoProgramado(
        array &$partida,
        string $eventoId,
        string $motivo,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);
        $idx = self::indiceProgramado($partida, $eventoId);
        if ($idx === null) {
            return ['ok' => false, 'error' => 'evento_no_encontrado'];
        }
        $ev = $partida['eventos_pueblo']['programados'][$idx];
        if (!is_array($ev)) {
            return ['ok' => false, 'error' => 'evento_no_encontrado'];
        }
        if ((string) ($ev['estado'] ?? 'programado') === 'cancelado') {
            return [
                'ok' => true,
                'idempotente' => true,
                'evento_id' => $eventoId,
                'estado' => 'cancelado',
                'motivo' => (string) ($ev['motivo_cancelacion'] ?? $motivo),
            ];
        }
        if (self::seleccionEstado($ev) === 'confirmado') {
            return ['ok' => false, 'error' => 'ya_confirmado'];
        }

        $partida['eventos_pueblo']['programados'][$idx]['estado'] = 'cancelado';
        $partida['eventos_pueblo']['programados'][$idx]['seleccion_estado'] = 'cancelado';
        $partida['eventos_pueblo']['programados'][$idx]['motivo_cancelacion'] = $motivo;
        $partida['eventos_pueblo']['programados'][$idx]['cancelado_en'] = [
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
        ];

        self::fin($partida, 'evento_cancelado', (string) ($ev['catalogo_id'] ?? ''), [
            'ok' => true,
            'evento_id' => $eventoId,
            'motivo' => $motivo,
        ]);
        \aht_log_optional($logger, $partida, 'evento_pueblo_cancelado', [
            'evento_id' => $eventoId,
            'motivo' => $motivo,
        ]);

        return [
            'ok' => true,
            'evento_id' => $eventoId,
            'estado' => 'cancelado',
            'motivo' => $motivo,
        ];
    }

    /**
     * @return int|null Índice en programados[]
     */
    private static function indiceProgramado(array $partida, string $eventoId): ?int
    {
        self::ensure($partida);
        foreach ($partida['eventos_pueblo']['programados'] as $i => $ev) {
            if (is_array($ev) && (string) ($ev['id'] ?? '') === $eventoId) {
                return (int) $i;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $evento
     * @return list<string>
     */
    public static function participantesCanon(array $partida, array $evento): array
    {
        $encId = (string) ($evento['encuentro_id'] ?? '');
        if ($encId !== '') {
            foreach (EncuentroEngine::list($partida) as $enc) {
                if (!is_array($enc) || ($enc['id'] ?? '') !== $encId) {
                    continue;
                }
                $p = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];

                return array_values(array_map('strval', $p));
            }
        }
        $p = is_array($evento['participantes'] ?? null) ? $evento['participantes'] : [];

        return array_values(array_map('strval', $p));
    }

    /**
     * @param array<string, mixed> $evento
     * @return array<string, mixed>
     */
    public static function vistaApuntar(array $partida, array $evento, Catalog $catalog): array
    {
        $def = self::catalogItem($catalog, (string) ($evento['catalogo_id'] ?? ''));
        $parts = self::participantesCanon($partida, $evento);
        $aforo = self::aforoEvento($partida, $evento, is_array($def) ? $def : null, $catalog);
        $plazas = self::plazasDisponibles($partida, $evento, is_array($def) ? $def : null, $catalog);
        $nombre = EventosPuebloAnuncioEngine::nombreNaturalPublico((string) ($evento['nombre'] ?? ''));
        $lugarId = (string) ($evento['lugar'] ?? '');
        $lugarNombre = self::nombreLugarUi($catalog, $lugarId);
        $dia = (int) ($evento['dia'] ?? 0);
        $hora = (int) ($evento['hora'] ?? 0);
        $horaUi = sprintf('%02d:00', max(0, min(23, $hora)));
        $selEstado = self::seleccionEstado($evento);

        return [
            'evento_pueblo_id' => (string) ($evento['id'] ?? ''),
            'catalogo_id' => (string) ($evento['catalogo_id'] ?? ''),
            'encuentro_id' => (string) ($evento['encuentro_id'] ?? ''),
            'nombre' => (string) ($evento['nombre'] ?? ''),
            'nombre_ui' => $nombre,
            'lugar' => $lugarId,
            'lugar_nombre' => $lugarNombre,
            'dia' => $dia,
            'hora' => $hora,
            'hora_ui' => $horaUi,
            'aforo' => $aforo,
            'aforo_total' => $aforo,
            'participantes_actuales' => count($parts),
            'participantes_apuntados' => $parts,
            'plazas_disponibles' => $plazas,
            'seleccion_estado' => $selEstado,
            'pendiente_seleccion' => $selEstado === 'pendiente_asistentes',
            'cta_label' => '¿Quién va?',
            'elegibles' => $elegVecinos,
            'preset_organizar' => [
                'modo' => 'evento_pueblo',
                'evento_pueblo_id' => (string) ($evento['id'] ?? ''),
                'nombre' => (string) ($evento['nombre'] ?? ''),
                'nombre_ui' => $nombre,
                'lugar' => $lugarId,
                'lugar_nombre' => $lugarNombre,
                'dia' => $dia,
                'hora' => $hora,
                'aforo' => $aforo,
                'aforo_total' => $aforo,
                'plazas_disponibles' => $plazas,
                'participantes_apuntados' => $parts,
                'participantes_min' => (int) ($evento['participantes_min'] ?? 3),
                'elegibles' => $elegVecinos,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buscarProgramadoPorEncuentro(array $partida, string $encuentroId): ?array
    {
        self::ensure($partida);
        foreach ($partida['eventos_pueblo']['programados'] as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if ((string) ($ev['encuentro_id'] ?? '') === $encuentroId) {
                return $ev;
            }
        }

        return null;
    }

    /**
     * Próximo evento del pueblo (contrato backend para futura B3).
     *
     * @return array<string, mixed>|null
     */
    public static function proximoEvento(array $partida, ?Catalog $catalog = null): ?array
    {
        self::ensure($partida);
        $now = RelojOperations::ahoraAbsoluto($partida);
        $best = null;
        $bestT = null;
        foreach ($partida['eventos_pueblo']['programados'] as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $estado = self::estadoEvento($partida, $ev);
            if (!in_array($estado, ['programado', 'en_curso'], true)) {
                continue;
            }
            $t = ((int) ($ev['dia'] ?? 0)) * 24 + (int) ($ev['hora'] ?? 0);
            if ($t < $now) {
                continue;
            }
            if ($bestT === null || $t < $bestT) {
                $best = $ev;
                $bestT = $t;
            }
        }
        if ($best === null) {
            return null;
        }
        $def = $catalog !== null ? self::catalogItem($catalog, (string) ($best['catalogo_id'] ?? '')) : null;
        $selEstado = self::seleccionEstado($best);
        $aforo = self::aforoEvento($partida, $best, is_array($def) ? $def : null, $catalog);
        $plazas = self::plazasDisponibles($partida, $best, is_array($def) ? $def : null, $catalog);
        return [
            'id' => (string) ($best['id'] ?? ''),
            'catalogo_id' => (string) ($best['catalogo_id'] ?? ''),
            'nombre' => (string) ($best['nombre'] ?? ($def['nombre'] ?? '')),
            'tipo' => (string) ($best['familia'] ?? ($def['familia'] ?? '')),
            'dia' => (int) ($best['dia'] ?? 0),
            'hora' => (int) ($best['hora'] ?? 0),
            'lugar' => (string) ($best['lugar'] ?? ''),
            'estado' => self::estadoEvento($partida, $best),
            'seleccion_estado' => $selEstado,
            'participantes' => is_array($best['participantes'] ?? null) ? $best['participantes'] : [],
            'participantes_n' => count(is_array($best['participantes'] ?? null) ? $best['participantes'] : []),
            'aforo' => $aforo,
            'plazas_disponibles' => $plazas,
            'encuentro_id' => (string) ($best['encuentro_id'] ?? ''),
            'origen' => (string) ($best['origen'] ?? self::ORIGEN),
        ];
    }

    /**
     * Vista UI del próximo evento del pueblo (contrato B3 / Inicio).
     *
     * @return array<string, mixed>|null
     */
    public static function vistaProximoEvento(array $partida, ?Catalog $catalog = null): ?array
    {
        $raw = self::proximoEvento($partida, $catalog);
        if ($raw === null) {
            return null;
        }
        $lugarId = (string) ($raw['lugar'] ?? '');
        $lugarNombre = self::nombreLugarUi($catalog, $lugarId);
        $dia = (int) ($raw['dia'] ?? 0);
        $hora = (int) ($raw['hora'] ?? 0);
        $reloj = is_array($partida['reloj'] ?? null) ? $partida['reloj'] : [];
        $diaSemana = $dia > 0 ? Reloj::diaSemanaUi($dia, $reloj) : '';
        $horaUi = sprintf('%02d:00', max(0, min(23, $hora)));
        $n = (int) ($raw['participantes_n'] ?? 0);
        $estado = (string) ($raw['estado'] ?? 'programado');
        $selEstado = (string) ($raw['seleccion_estado'] ?? 'pendiente_asistentes');
        $nombre = EventosPuebloAnuncioEngine::nombreNaturalPublico((string) ($raw['nombre'] ?? ''));
        $catalogoId = (string) ($raw['catalogo_id'] ?? '');
        $tipo = (string) ($raw['tipo'] ?? '');
        $def = $catalog !== null && $catalogoId !== '' ? self::catalogItem($catalog, $catalogoId) : null;
        $aforo = (int) ($raw['aforo'] ?? 0);
        if ($aforo <= 0) {
            $aforo = self::aforoEvento($partida, $raw, is_array($def) ? $def : null, $catalog);
        }
        $plazas = (int) ($raw['plazas_disponibles'] ?? 0);
        if ($plazas <= 0 && $selEstado === 'pendiente_asistentes') {
            $plazas = self::plazasDisponibles($partida, $raw, is_array($def) ? $def : null, $catalog);
        }
        $elegVecinos = $selEstado === 'pendiente_asistentes'
            ? (self::vecinosElegibles($partida, (string) ($raw['id'] ?? ''), null, $catalog)['vecinos'] ?? [])
            : [];
        $partsCanon = self::participantesCanon($partida, $raw);

        $metaParts = [];
        if ($estado === 'en_curso') {
            $metaParts[] = 'En curso';
        } else {
            if ($diaSemana !== '') {
                $metaParts[] = $diaSemana;
            }
            $metaParts[] = $horaUi;
        }
        if ($lugarNombre !== '') {
            $metaParts[] = $lugarNombre;
        }

        return array_merge($raw, [
            'nombre_ui' => $nombre,
            'lugar_nombre' => $lugarNombre,
            'dia_semana_ui' => $diaSemana !== '' ? $diaSemana : null,
            'hora_ui' => $horaUi,
            'meta_ui' => implode(' · ', $metaParts),
            'icono' => self::iconoCatalogo($catalog, $catalogoId, $tipo),
            'illustracion' => self::illustracionCatalogo($catalog, $catalogoId, $lugarId),
            'es_evento_pueblo' => true,
            'aforo' => $aforo,
            'plazas_disponibles' => $plazas,
            'participantes_apuntados' => $partsCanon,
            'elegibles' => $elegVecinos,
            'pendiente_seleccion' => $selEstado === 'pendiente_asistentes',
            'cta_label' => '¿Quién va?',
            'preset_organizar' => [
                'modo' => 'evento_pueblo',
                'evento_pueblo_id' => (string) ($raw['id'] ?? ''),
                'nombre' => (string) ($raw['nombre'] ?? ''),
                'nombre_ui' => $nombre,
                'lugar' => $lugarId,
                'lugar_nombre' => $lugarNombre,
                'dia' => $dia,
                'hora' => $hora,
                'aforo' => $aforo,
                'aforo_total' => $aforo,
                'plazas_disponibles' => $plazas,
                'participantes_apuntados' => $partsCanon,
                'participantes_min' => (int) ($raw['participantes_min'] ?? 3),
                'elegibles' => $elegVecinos,
            ],
        ]);
    }

    public static function eventoActivo(array $partida, string $catalogoId): bool
    {
        self::ensure($partida);
        foreach ($partida['eventos_pueblo']['programados'] as $ev) {
            if (!is_array($ev) || (string) ($ev['catalogo_id'] ?? '') !== $catalogoId) {
                continue;
            }
            if (in_array(self::estadoEvento($partida, $ev), ['programado', 'en_curso'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $ev
     */
    public static function estadoEvento(array $partida, array $ev): string
    {
        $encId = (string) ($ev['encuentro_id'] ?? '');
        if ($encId !== '') {
            foreach (EncuentroEngine::list($partida) as $enc) {
                if (($enc['id'] ?? '') === $encId) {
                    return (string) ($enc['estado'] ?? 'programado');
                }
            }
        }
        return (string) ($ev['estado'] ?? 'programado');
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function activa(array $partida, array $cal): bool
    {
        return self::activo($partida, $cal);
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function activo(array $partida, array $cal): bool
    {
        if (FeatureConfig::isEnabled($partida, 'eventos_pueblo_enabled')) {
            return true;
        }
        return (bool) CalibracionConfig::get($cal, 'eventos_pueblo.activo', false);
    }

    private static function contarActivos(array $partida): int
    {
        $n = 0;
        foreach ($partida['eventos_pueblo']['programados'] ?? [] as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if (in_array(self::estadoEvento($partida, $ev), ['programado', 'en_curso'], true)) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * @param array<string, mixed> $def
     */
    private static function elegirLugar(array $partida, array $def, RngService $rng): ?string
    {
        $cands = is_array($def['lugares'] ?? null) ? $def['lugares'] : [];
        $ops = $partida['celeste']['lugares_desbloqueados'] ?? [];
        $valid = [];
        foreach ($cands as $lug) {
            $lug = (string) $lug;
            if ($lug === '') {
                continue;
            }
            if (is_array($ops) && $ops !== [] && !in_array($lug, $ops, true)) {
                continue;
            }
            $valid[] = $lug;
        }
        if ($valid === []) {
            return null;
        }
        return $valid[$rng->nextInt(0, count($valid) - 1)];
    }

    /**
     * @param array<string, mixed> $def
     * @return array{dia:int,hora:int}|null
     */
    private static function buscarFranja(array $partida, array $def, string $lugar, int $durH): ?array
    {
        $dia0 = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $dMin = max(1, (int) ($def['dias_anticipacion_min'] ?? 1));
        $dMax = max($dMin, (int) ($def['dias_anticipacion_max'] ?? 4));
        $hMin = (int) ($def['hora_min'] ?? 18);
        $hMax = (int) ($def['hora_max'] ?? 21);
        $minP = (int) ($def['participantes_min'] ?? 3);

        for ($d = $dia0 + $dMin; $d <= $dia0 + $dMax; $d++) {
            for ($h = $hMin; $h <= $hMax - $durH + 1; $h++) {
                if (!Reloj::esFuturo($partida['reloj'] ?? [], $d, $h)) {
                    continue;
                }
                if (!ComplejoCatalog::estaAbierto($lugar, $h)) {
                    continue;
                }
                if (!AforoEngine::cabe($partida, $lugar, $d, $h, $minP)) {
                    continue;
                }
                if (self::residentesDisponibles($partida, $d, $h, $durH) === []) {
                    continue;
                }
                return ['dia' => $d, 'hora' => $h];
            }
        }
        return null;
    }

    /**
     * @return list<string>
     */
    private static function residentesDisponibles(array $partida, int $dia, int $hora, int $durH): array
    {
        $out = [];
        foreach (array_keys($partida['residentes'] ?? []) as $id) {
            $id = (string) $id;
            if (($partida['residentes'][$id]['presencia'] ?? '') !== 'residente') {
                continue;
            }
            $libre = true;
            for ($k = 0; $k < $durH; $k++) {
                $h = $hora + $k;
                $d = $dia;
                while ($h >= 24) {
                    $h -= 24;
                    $d++;
                }
                $disp = AgendaEngine::estaDisponible($partida, $id, $d, $h);
                if (!($disp['disponible'] ?? false)) {
                    $libre = false;
                    break;
                }
            }
            if ($libre) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $def
     * @param list<string> $disponibles
     * @param array<string, mixed> $cal
     * @return list<string>
     */
    private static function ordenRecomendados(
        array $partida,
        array $def,
        array $disponibles,
        array $cal
    ): array {
        if ($disponibles === []) {
            return [];
        }
        $pesos = [];
        $tieneCal = $cal !== [];
        foreach ($disponibles as $id) {
            $w = 1.0;
            if ($tieneCal) {
                $emo = (string) ($partida['residentes'][$id]['runtime']['estado_emocional']['id'] ?? 'neutro');
                $w += ((int) EstadoEmocional::modificadores($emo, $cal)['iniciativa_social']) / 40.0;
            }
            $pesos[] = ['id' => $id, 'w' => max(0.05, $w)];
        }
        usort($pesos, static function ($a, $b) {
            if ($a['w'] === $b['w']) {
                return strcmp((string) $a['id'], (string) $b['id']);
            }
            return $b['w'] <=> $a['w'];
        });

        return array_map(static fn($p) => (string) $p['id'], $pesos);
    }

    private static function nuevoId(array $partida, RngService $rng): string
    {
        $rngLocal = $rng;
        $hex = bin2hex(substr(pack('N', $rngLocal->next()), 0, 4));
        $rngLocal->persistToPartida($partida);
        return 'evt_' . $hex;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function fin(array &$partida, string $resultado, string $eventoId, array $extra = []): array
    {
        self::ensure($partida);
        $row = array_merge([
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            'catalogo_id' => $eventoId,
            'resultado' => $resultado,
        ], $extra);
        $partida['eventos_pueblo']['log'][] = $row;
        if (count($partida['eventos_pueblo']['log']) > self::LOG_MAX) {
            $partida['eventos_pueblo']['log'] = array_slice($partida['eventos_pueblo']['log'], -self::LOG_MAX);
        }
        if (!isset($row['ok'])) {
            $row['ok'] = str_starts_with($resultado, 'evento_programado');
        }
        return $row;
    }

    private static function nombreLugarUi(?Catalog $catalog, string $lugarId): string
    {
        if ($lugarId === '') {
            return '';
        }
        if ($catalog === null) {
            return $lugarId;
        }
        try {
            return EtiquetaFicha::lugar($lugarId, $catalog->store());
        } catch (\Throwable $ignored) {
            return $lugarId;
        }
    }

    private static function illustracionCatalogo(?Catalog $catalog, string $catalogoId, string $lugarId = ''): string
    {
        if ($catalog !== null && $catalogoId !== '') {
            $def = self::catalogItem($catalog, $catalogoId);
            $img = (string) ($def['illustracion'] ?? '');
            if ($img !== '') {
                return $img;
            }
            if ($catalogoId !== '') {
                return 'assets/play-v3/eventos/' . $catalogoId . '.png';
            }
        }

        return '';
    }

    private static function iconoCatalogo(?Catalog $catalog, string $catalogoId, string $familia): string
    {
        if ($catalog !== null && $catalogoId !== '') {
            $def = self::catalogItem($catalog, $catalogoId);
            $ico = (string) ($def['icono'] ?? '');
            if ($ico !== '') {
                return $ico;
            }
        }
        if ($familia === 'ocio_colectivo') {
            return '🎉';
        }
        if ($familia === 'deporte_benefico') {
            return '⚽';
        }

        return '📅';
    }
}
