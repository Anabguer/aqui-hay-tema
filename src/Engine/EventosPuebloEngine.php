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
        if ($items === []) {
            return null;
        }
        $idx = $rng->nextInt(0, count($items) - 1);
        $def = $items[$idx];
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

        $disponibles = self::residentesDisponibles($partida, (int) $franja['dia'], (int) $franja['hora'], $durH);
        $participantes = self::seleccionarParticipantes($partida, $def, $disponibles, $rng, $cal);
        $minP = (int) ($def['participantes_min'] ?? 3);
        if (count($participantes) < $minP) {
            return self::fin($partida, 'participantes_insuficientes', $eventoId, [
                'disponibles' => count($disponibles),
                'min' => $minP,
            ]);
        }
        if (!AforoEngine::cabe($partida, $lugar, (int) $franja['dia'], (int) $franja['hora'], count($participantes))) {
            return self::fin($partida, 'aforo_insuficiente', $eventoId, ['lugar' => $lugar]);
        }

        $usadasAntes = (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
        $r = EncuentroEngine::programar(
            $partida,
            $participantes,
            (int) $franja['dia'],
            (int) $franja['hora'],
            self::TIPO_ENCUENTRO,
            $lugar,
            $eventoId,
            $logger,
            false
        );
        if (!($r['ok'] ?? false)) {
            return self::fin($partida, 'error_programar_' . (string) ($r['error'] ?? '?'), $eventoId, [
                'error' => $r['error'] ?? null,
            ]);
        }

        $encId = (string) ($r['encuentro']['id'] ?? '');
        $evtId = self::nuevoId($partida, $rng);
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
            $partida['encuentros'][$i]['evento_pueblo_id'] = $evtId;
            $partida['encuentros'][$i]['evento_pueblo_catalogo_id'] = $eventoId;
        }

        $row = [
            'id' => $evtId,
            'catalogo_id' => $eventoId,
            'nombre' => (string) ($def['nombre'] ?? $eventoId),
            'familia' => (string) ($def['familia'] ?? 'ocio_colectivo'),
            'encuentro_id' => $encId,
            'dia' => (int) $franja['dia'],
            'hora' => (int) $franja['hora'],
            'lugar' => $lugar,
            'estado' => 'programado',
            'participantes' => $participantes,
            'origen' => self::ORIGEN,
        ];
        $partida['eventos_pueblo']['programados'][] = $row;

        EventosPuebloAnuncioEngine::anunciarTrasProgramar($partida, $row, $catalog, $cal, $rng, $logger);

        return self::fin($partida, 'evento_programado', $eventoId, [
            'ok' => true,
            'evento' => $row,
            'encuentro_id' => $encId,
            'intervenciones_celeste_antes' => $usadasAntes,
            'intervenciones_celeste_despues' => (int) ($partida['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0),
        ]);
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
        return [
            'id' => (string) ($best['id'] ?? ''),
            'catalogo_id' => (string) ($best['catalogo_id'] ?? ''),
            'nombre' => (string) ($best['nombre'] ?? ($def['nombre'] ?? '')),
            'tipo' => (string) ($best['familia'] ?? ($def['familia'] ?? '')),
            'dia' => (int) ($best['dia'] ?? 0),
            'hora' => (int) ($best['hora'] ?? 0),
            'lugar' => (string) ($best['lugar'] ?? ''),
            'estado' => self::estadoEvento($partida, $best),
            'participantes' => is_array($best['participantes'] ?? null) ? $best['participantes'] : [],
            'participantes_n' => count(is_array($best['participantes'] ?? null) ? $best['participantes'] : []),
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
        $nombre = EventosPuebloAnuncioEngine::nombreNaturalPublico((string) ($raw['nombre'] ?? ''));
        $catalogoId = (string) ($raw['catalogo_id'] ?? '');
        $tipo = (string) ($raw['tipo'] ?? '');

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
        if ($n > 0) {
            $metaParts[] = $n === 1 ? '1 vecino apuntado' : ($n . ' vecinos apuntados');
        }

        return array_merge($raw, [
            'nombre_ui' => $nombre,
            'lugar_nombre' => $lugarNombre,
            'dia_semana_ui' => $diaSemana !== '' ? $diaSemana : null,
            'hora_ui' => $horaUi,
            'meta_ui' => implode(' · ', $metaParts),
            'icono' => self::iconoCatalogo($catalogoId, $tipo),
            'es_evento_pueblo' => true,
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
    private static function seleccionarParticipantes(
        array $partida,
        array $def,
        array $disponibles,
        RngService $rng,
        array $cal
    ): array {
        $minP = (int) ($def['participantes_min'] ?? 3);
        $maxP = (int) ($def['participantes_max'] ?? 8);
        if ($disponibles === []) {
            return [];
        }
        $pesos = [];
        foreach ($disponibles as $id) {
            $w = 1.0;
            $emo = (string) ($partida['residentes'][$id]['runtime']['estado_emocional']['id'] ?? 'neutro');
            $w += ((int) EstadoEmocional::modificadores($emo, $cal)['iniciativa_social']) / 40.0;
            $pesos[] = ['id' => $id, 'w' => max(0.05, $w)];
        }
        usort($pesos, static function ($a, $b) use ($rng) {
            if ($a['w'] === $b['w']) {
                return $rng->nextInt(0, 1) === 0 ? -1 : 1;
            }
            return $b['w'] <=> $a['w'];
        });
        $orden = array_map(static fn($p) => (string) $p['id'], $pesos);
        $want = min($maxP, count($orden));
        $want = max($minP, $want);
        if ($want > count($orden)) {
            return $orden;
        }
        return array_slice($orden, 0, $want);
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

    private static function iconoCatalogo(string $catalogoId, string $familia): string
    {
        if ($catalogoId === 'noche_bingo') {
            return '🎱';
        }
        if ($familia === 'ocio_colectivo') {
            return '🎉';
        }

        return '📅';
    }
}
