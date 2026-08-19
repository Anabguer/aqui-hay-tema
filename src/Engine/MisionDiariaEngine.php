<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Misiones diarias de pueblo. Máximo 3 por día de reloj de juego.
 * Solo hechos controlables por Celestine. Vida vía VidaPuebloEngine.
 */
final class MisionDiariaEngine
{
    public const FLAG = 'misiones_diarias_enabled';
    public const EST_PENDIENTE = 'pendiente';
    public const EST_CUMPLIDA = 'cumplida';
    public const EST_CADUCADA = 'caducada';

    public static function ensure(array &$partida): void
    {
        $partida['misiones_diarias'] ??= [
            'dia' => 0,
            'items' => [],
            'historial_plantillas' => [],
            'encuentros_usados' => [],
            '_provisional' => true,
        ];
        $partida['misiones_diarias']['items'] ??= [];
        $partida['misiones_diarias']['historial_plantillas'] ??= [];
        $partida['misiones_diarias']['encuentros_usados'] ??= [];
    }

    public static function activa(array $partida): bool
    {
        return FeatureConfig::isEnabled($partida, self::FLAG);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function delDia(array $partida, ?int $dia = null): array
    {
        $dia = $dia ?? (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $out = [];
        foreach ($partida['misiones_diarias']['items'] ?? [] as $m) {
            if ((int) ($m['dia'] ?? 0) === $dia) {
                $out[] = $m;
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function alCerrarDia(array &$partida, int $diaCerrado, array $cal = [], ?GameLogger $logger = null): int
    {
        if (!self::activa($partida)) {
            return 0;
        }
        self::ensure($partida);
        $n = 0;
        foreach ($partida['misiones_diarias']['items'] as $i => $m) {
            if ((int) ($m['dia'] ?? 0) !== $diaCerrado) {
                continue;
            }
            if (($m['estado'] ?? '') !== self::EST_PENDIENTE) {
                continue;
            }
            $partida['misiones_diarias']['items'][$i]['estado'] = self::EST_CADUCADA;
            $n++;
            $dano = (int) CalibracionConfig::get($cal, 'misiones_diarias.vida_caducada', -2);
            if ($dano > -1) {
                $dano = -2;
            }
            VidaPuebloEngine::aplicar($partida, $dano, [
                'causa' => VidaPuebloEngine::CAUSA_MISION_FALLIDA,
                'origen' => VidaPuebloEngine::ORIGEN_SISTEMA,
                'atribuible_celestine' => true,
                'positivo_valido_latido' => false,
                'fuente_id' => $m['id'] ?? null,
            ], $cal, $logger);
            self::emit($partida, DomainEvents::MISION_CADUCADA, [
                'mision' => $partida['misiones_diarias']['items'][$i],
                'actores' => [],
            ], $logger, 'MisionDiariaEngine::caducar');
        }
        return $n;
    }

    /**
     * @param array<string, mixed> $cal
     * @return list<array<string, mixed>>
     */
    public static function alComenzarDia(array &$partida, array $cal = [], ?RngService $rng = null, ?GameLogger $logger = null): array
    {
        if (!self::activa($partida)) {
            return [];
        }
        self::ensure($partida);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        if ((int) ($partida['misiones_diarias']['dia'] ?? 0) === $dia) {
            return self::delDia($partida, $dia);
        }
        $partida['misiones_diarias']['encuentros_usados'] = [];
        if ($rng === null) {
            $rng = RngService::fromPartida($partida);
        }
        $ayerIds = [];
        $ayerFam = [];
        foreach (self::delDia($partida, $dia - 1) as $prev) {
            $ayerIds[] = (string) ($prev['plantilla_id'] ?? '');
            $ayerFam[] = (string) ($prev['familia'] ?? '');
        }
        $elegibles = self::plantillasElegibles($partida, $cal);
        foreach ($elegibles as $k => $pl) {
            $score = (int) ($pl['prioridad'] ?? 0);
            $score += $rng->nextInt(0, 4);
            if (in_array((string) ($pl['id'] ?? ''), $ayerIds, true)) {
                $score -= 30;
            }
            if (in_array((string) ($pl['familia'] ?? ''), $ayerFam, true)) {
                $score -= 15;
            }
            $elegibles[$k]['_score'] = $score;
        }
        usort($elegibles, static function ($a, $b) {
            return ((int) ($b['_score'] ?? 0)) <=> ((int) ($a['_score'] ?? 0));
        });

        $elegidas = [];
        $famUsadas = [];
        foreach ($elegibles as $pl) {
            if (count($elegidas) >= 3) {
                break;
            }
            $fam = (string) $pl['familia'];
            if (isset($famUsadas[$fam])) {
                continue;
            }
            $m = self::instanciar($partida, $pl, $cal, $rng, $ayerIds, self::delDia($partida, $dia - 1));
            if ($m === null) {
                continue;
            }
            $famUsadas[$fam] = true;
            $elegidas[] = $m;
        }
        self::marcarSlotLatido($elegidas);

        $partida['misiones_diarias']['dia'] = $dia;
        foreach ($elegidas as $m) {
            $partida['misiones_diarias']['items'][] = $m;
            $partida['misiones_diarias']['historial_plantillas'][] = $m['plantilla_id'];
            self::emit($partida, DomainEvents::MISION_GENERADA, [
                'mision' => $m,
                'actores' => [],
            ], $logger, 'MisionDiariaEngine::generar');
        }
        $rng->persistToPartida($partida);
        return $elegidas;
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function onEncuentroCelestine(array &$partida, array $encuentro, array $cal = [], ?GameLogger $logger = null): int
    {
        if (!self::activa($partida)) {
            return 0;
        }
        if (!self::esEncuentroCelestine($encuentro)) {
            return 0;
        }
        $eid = (string) ($encuentro['id'] ?? '');
        self::ensure($partida);
        $usados = $partida['misiones_diarias']['encuentros_usados'] ?? [];
        if ($eid !== '' && in_array($eid, $usados, true)) {
            return 0;
        }
        if ($eid !== '') {
            $partida['misiones_diarias']['encuentros_usados'][] = $eid;
        }
        $dia = (int) ($encuentro['dia'] ?? $partida['reloj']['dia_pueblo'] ?? 1);
        $hechos = 0;
        foreach ([false, true] as $incluirTema) {
            foreach ($partida['misiones_diarias']['items'] as $i => $m) {
                if ((int) ($m['dia'] ?? 0) !== $dia) {
                    continue;
                }
                if (($m['estado'] ?? '') !== self::EST_PENDIENTE) {
                    continue;
                }
                $esTema = (string) ($m['plantilla_id'] ?? '') === 'tema_del_dia';
                if ($esTema !== $incluirTema) {
                    continue;
                }
                if (!self::encaja($m, $encuentro)) {
                    continue;
                }
                self::cumplirIndice($partida, $i, $cal, $logger);
                $hechos++;
                break 2;
            }
        }
        return $hechos;
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function cumplir(array &$partida, string $misionId, array $cal = [], ?GameLogger $logger = null): array
    {
        self::ensure($partida);
        foreach ($partida['misiones_diarias']['items'] as $i => $m) {
            if (($m['id'] ?? '') === $misionId) {
                return self::cumplirIndice($partida, $i, $cal, $logger);
            }
        }
        return ['ok' => false, 'error' => 'mision_no_encontrada'];
    }

    /**
     * Vista PLAY: sin números de Vida.
     *
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function vistaHoy(array $partida, array $cal = []): array
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $quedan = max(0, 24 - $hora);
        if ($quedan <= 0) {
            $plazo = 'El día se acaba';
        } elseif ($quedan >= 18) {
            $plazo = 'Todavía hay día por delante';
        } elseif ($quedan === 1) {
            $plazo = 'Te queda 1 h de hoy';
        } else {
            $plazo = 'Te quedan ' . $quedan . ' h de hoy';
        }
        $items = [];
        foreach (self::delDia($partida, $dia) as $m) {
            $items[] = [
                'id' => $m['id'] ?? '',
                'texto' => $m['texto'] ?? '',
                'estado' => $m['estado'] ?? self::EST_PENDIENTE,
                'familia' => $m['familia'] ?? '',
            ];
        }
        return [
            'dia' => $dia,
            'plazo_humano' => $plazo,
            'misiones' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $cal
     * @return list<array<string, mixed>>
     */
    public static function plantillasElegibles(array $partida, array $cal = []): array
    {
        $out = [];
        foreach (MisionPlantillas::catalogo() as $pl) {
            $cands = self::candidatos($partida, $pl, $cal);
            if ($cands === []) {
                continue;
            }
            $pl['_candidatos'] = $cands;
            $out[] = $pl;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $pl
     * @param array<string, mixed> $cal
     * @return list<array<string, mixed>>
     */
    public static function candidatos(array $partida, array $pl, array $cal = []): array
    {
        $id = (string) ($pl['id'] ?? '');
        $ids = self::residentes($partida);
        if (count($ids) < 2 && $id !== 'vida_de_mueble' && $id !== 'por_descubrir') {
            return [];
        }
        if ($id === 'conocerse_dos') {
            return self::paresTipo($partida, $ids, PropuestaNivel::PRESENTAR, $cal);
        }
        if ($id === 'quedar_dos') {
            return self::paresTipo($partida, $ids, PropuestaNivel::QUEDAR, $cal);
        }
        if ($id === 'primera_cita_hoy') {
            return self::paresTipo($partida, $ids, PropuestaNivel::PRIMERA_CITA, $cal);
        }
        if ($id === 'cita_desbloqueada') {
            return self::paresTipo($partida, $ids, PropuestaNivel::CITA, $cal);
        }
        if ($id === 'sitio_del_dia') {
            $lugs = $partida['celeste']['lugares_desbloqueados'] ?? [];
            $out = [];
            foreach ($lugs as $lug) {
                $lug = (string) $lug;
                if ($lug === '' || $lug === 'lug_casa') {
                    continue;
                }
                $out[] = ['lugar_id' => $lug];
            }
            return $out;
        }
        if ($id === 'vida_de_mueble') {
            $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            $umbral = (int) CalibracionConfig::get($cal, 'misiones_diarias.dias_sin_salir', 2);
            if ($dia < $umbral + 1) {
                return [];
            }
            $out = [];
            foreach ($ids as $rid) {
                $ult = (int) ($partida['residentes'][$rid]['runtime']['ultimo_contacto_social_dia'] ?? 0);
                $sin = $ult <= 0 ? $dia : ($dia - $ult);
                if ($sin >= $umbral) {
                    $out[] = ['residente_id' => $rid];
                }
            }
            return $out;
        }
        if ($id === 'por_descubrir') {
            $out = [];
            foreach ($ids as $rid) {
                if (self::tieneDiscoveryPendiente($partida, $rid)) {
                    $out[] = ['residente_id' => $rid];
                }
            }
            return $out;
        }
        if ($id === 'relacion_floja') {
            $tope = (int) CalibracionConfig::get($cal, 'misiones_diarias.social_floja_max', 12);
            $out = [];
            $n = count($ids);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $ids[$i];
                    $b = $ids[$j];
                    if (!RelacionEngine::seConocen($partida, $a, $b)) {
                        continue;
                    }
                    $va = RelacionEngine::valorSocialHacia($partida, $a, $b);
                    $vb = RelacionEngine::valorSocialHacia($partida, $b, $a);
                    $mx = $va > $vb ? $va : $vb;
                    if ($mx <= $tope) {
                        $out[] = ['a' => $a, 'b' => $b];
                    }
                }
            }
            return $out;
        }
        if ($id === 'cambio_de_sitio') {
            $lugs = $partida['celeste']['lugares_desbloqueados'] ?? [];
            foreach ($lugs as $lug) {
                if ((string) $lug !== 'lug_cafeteria' && (string) $lug !== 'lug_casa' && $lug !== '') {
                    return [['ok' => true]];
                }
            }
            return [];
        }
        if ($id === 'tema_del_dia') {
            return count($ids) >= 2 ? [['ok' => true]] : [];
        }
        return [];
    }

    /**
     * @param array<string, mixed> $mision
     */
    public static function encaja(array $mision, array $encuentro): bool
    {
        $pid = (string) ($mision['plantilla_id'] ?? '');
        $tipo = PropuestaNivel::aliasTipo((string) ($encuentro['tipo'] ?? ''));
        $partes = $encuentro['participantes'] ?? [];
        $lugar = (string) ($encuentro['lugar'] ?? '');
        $params = is_array($mision['params'] ?? null) ? $mision['params'] : [];

        if ($pid === 'conocerse_dos') {
            return $tipo === PropuestaNivel::PRESENTAR;
        }
        if ($pid === 'quedar_dos' || $pid === 'relacion_floja') {
            if ($tipo !== PropuestaNivel::QUEDAR) {
                return false;
            }
            if ($pid === 'relacion_floja') {
                return in_array($params['a'] ?? '', $partes, true) && in_array($params['b'] ?? '', $partes, true);
            }
            return true;
        }
        if ($pid === 'primera_cita_hoy') {
            return $tipo === PropuestaNivel::PRIMERA_CITA;
        }
        if ($pid === 'cita_desbloqueada') {
            return $tipo === PropuestaNivel::CITA;
        }
        if ($pid === 'sitio_del_dia') {
            return $lugar === (string) ($params['lugar_id'] ?? '');
        }
        if ($pid === 'vida_de_mueble' || $pid === 'por_descubrir') {
            return in_array($params['residente_id'] ?? '', $partes, true);
        }
        if ($pid === 'cambio_de_sitio') {
            return $lugar !== '' && $lugar !== 'lug_cafeteria';
        }
        if ($pid === 'tema_del_dia') {
            return true;
        }
        return false;
    }

    public static function esEncuentroCelestine(array $encuentro): bool
    {
        $int = (string) ($encuentro['intencion'] ?? '');
        if (in_array($int, ['autonomo', 'autonomo_relacion', 'casual_quedada'], true)) {
            return false;
        }
        return $int === 'celeste_organizado' || $int === 'jugador_propone' || $int === '';
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function cumplirIndice(array &$partida, int $i, array $cal, ?GameLogger $logger): array
    {
        $m = $partida['misiones_diarias']['items'][$i];
        if (($m['estado'] ?? '') !== self::EST_PENDIENTE) {
            return ['ok' => false, 'error' => 'no_pendiente', 'mision' => $m];
        }
        $partida['misiones_diarias']['items'][$i]['estado'] = self::EST_CUMPLIDA;
        $m = $partida['misiones_diarias']['items'][$i];
        $delta = (int) CalibracionConfig::get($cal, 'misiones_diarias.vida_cumplida', 1);
        if ($delta < 1) {
            $delta = 1;
        }
        $valido = !empty($m['cuenta_latido']) && !self::yaHuboValidoHoy($partida, (int) ($m['dia'] ?? 0), (string) ($m['id'] ?? ''));
        VidaPuebloEngine::aplicar($partida, $delta, [
            'causa' => VidaPuebloEngine::CAUSA_MISION_CUMPLIDA,
            'origen' => VidaPuebloEngine::ORIGEN_JUGADOR,
            'atribuible_celestine' => true,
            'positivo_valido_latido' => $valido,
            'fuente_id' => $m['id'] ?? null,
        ], $cal, $logger);
        self::emit($partida, DomainEvents::MISION_CUMPLIDA, [
            'mision' => $m,
            'actores' => [],
        ], $logger, 'MisionDiariaEngine::cumplir');
        return ['ok' => true, 'mision' => $m];
    }

    /**
     * @param array<string, mixed> $pl
     * @param array<string, mixed> $cal
     * @param list<string> $ayerIds
     * @param list<array<string, mixed>> $ayerMisiones
     * @return array<string, mixed>|null
     */
    private static function instanciar(array $partida, array $pl, array $cal, RngService $rng, array $ayerIds, array $ayerMisiones = []): ?array
    {
        $cands = $pl['_candidatos'] ?? self::candidatos($partida, $pl, $cal);
        if ($cands === []) {
            return null;
        }
        $prevParams = null;
        foreach ($ayerMisiones as $am) {
            if ((string) ($am['plantilla_id'] ?? '') === (string) ($pl['id'] ?? '')) {
                $prevParams = is_array($am['params'] ?? null) ? $am['params'] : null;
                break;
            }
        }
        if (is_array($prevParams) && count($cands) > 1) {
            $alt = [];
            foreach ($cands as $c) {
                if (!is_array($c) || $c != $prevParams) {
                    $alt[] = $c;
                }
            }
            if ($alt !== []) {
                $cands = array_values($alt);
            }
        }
        $pick = $cands[$rng->nextInt(0, count($cands) - 1)];
        $params = is_array($pick) ? $pick : [];
        $texto = self::renderCopy((string) ($pl['copy'] ?? ''), $params, $partida);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $n = count($partida['misiones_diarias']['items'] ?? []);
        return [
            'id' => 'mis_' . $dia . '_' . $n . '_' . substr(bin2hex(pack('N', $rng->next())), 0, 6),
            'plantilla_id' => (string) $pl['id'],
            'familia' => (string) $pl['familia'],
            'dia' => $dia,
            'estado' => self::EST_PENDIENTE,
            'texto' => $texto,
            'hecho' => (string) ($pl['hecho'] ?? ''),
            'params' => $params,
            'exigencia' => (int) ($pl['exigencia'] ?? ($pl['prioridad'] ?? 0)),
            'cuenta_latido' => false,
            'ayer_repetida' => in_array((string) $pl['id'], $ayerIds, true),
        ];
    }

    /**
     * Solo una misión del paquete diario alimenta positivos válidos de Latido:
     * la de mayor exigencia (más específica / más gated).
     *
     * @param list<array<string, mixed>> $elegidas
     */
    private static function marcarSlotLatido(array &$elegidas): void
    {
        if ($elegidas === []) {
            return;
        }
        $best = 0;
        $bestEx = -1;
        foreach ($elegidas as $i => $m) {
            $ex = (int) ($m['exigencia'] ?? 0);
            if ($ex > $bestEx) {
                $bestEx = $ex;
                $best = $i;
            }
        }
        foreach ($elegidas as $i => $_) {
            $elegidas[$i]['cuenta_latido'] = ($i === $best);
        }
    }

    private static function yaHuboValidoHoy(array $partida, int $dia, string $exceptoId): bool
    {
        foreach (self::delDia($partida, $dia) as $m) {
            if ((string) ($m['id'] ?? '') === $exceptoId) {
                continue;
            }
            if (($m['estado'] ?? '') !== self::EST_CUMPLIDA) {
                continue;
            }
            if (!empty($m['cuenta_latido'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function renderCopy(string $tpl, array $params, array $partida): string
    {
        if (isset($params['lugar_id'])) {
            $tpl = str_replace('{lugar}', MisionPlantillas::nombreLugar((string) $params['lugar_id']), $tpl);
        }
        if (isset($params['residente_id'])) {
            $tpl = str_replace('{nombre}', IdentidadPublica::nombre($partida, (string) $params['residente_id']), $tpl);
        }
        if (isset($params['a'], $params['b'])) {
            $tpl = str_replace('{a}', IdentidadPublica::nombre($partida, (string) $params['a']), $tpl);
            $tpl = str_replace('{b}', IdentidadPublica::nombre($partida, (string) $params['b']), $tpl);
        }
        return $tpl;
    }

    /**
     * @return list<string>
     */
    private static function residentes(array $partida): array
    {
        $out = [];
        foreach ($partida['residentes'] ?? [] as $id => $r) {
            if (($r['presencia'] ?? '') !== 'residente') {
                continue;
            }
            $out[] = (string) $id;
        }
        return $out;
    }

    /**
     * @param list<string> $ids
     * @param array<string, mixed> $cal
     * @return list<array<string, mixed>>
     */
    private static function paresTipo(array $partida, array $ids, string $tipo, array $cal): array
    {
        $out = [];
        $n = count($ids);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if (PropuestaNivel::permite($partida, $ids[$i], $ids[$j], $tipo, $cal)) {
                    $out[] = ['a' => $ids[$i], 'b' => $ids[$j]];
                }
            }
        }
        return $out;
    }

    private static function tieneDiscoveryPendiente(array $partida, string $rid): bool
    {
        $perfil = PerfilPartida::de($partida, $rid);
        if (!is_array($perfil)) {
            return false;
        }
        $hobbies = is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : [];
        $rasgos = is_array($perfil['rasgos'] ?? null) ? $perfil['rasgos'] : [];
        foreach ($hobbies as $h) {
            if (DiscoveryEngine::estado($partida, $rid, ConocimientoNpc::campoHobby((string) $h)) !== DiscoveryEngine::DESCUBIERTO) {
                return true;
            }
        }
        foreach ($rasgos as $r) {
            if (DiscoveryEngine::estado($partida, $rid, ConocimientoNpc::campoRasgo((string) $r)) !== DiscoveryEngine::DESCUBIERTO) {
                return true;
            }
        }
        return false;
    }

    /**
     * Encuentro mínimo que debería completar la misión si Celestine lo organiza.
     *
     * @param array<string, mixed> $mision
     * @return array<string, mixed>
     */
    public static function encuentroSinteticoPara(array $mision, array $partida): array
    {
        $pid = (string) ($mision['plantilla_id'] ?? '');
        $params = is_array($mision['params'] ?? null) ? $mision['params'] : [];
        $ids = self::residentes($partida);
        $a = (string) ($params['a'] ?? $params['residente_id'] ?? ($ids[0] ?? ''));
        $b = isset($params['b']) ? (string) $params['b'] : '';
        if ($b === '' || $b === $a) {
            foreach ($ids as $id) {
                if ($id !== $a) {
                    $b = $id;
                    break;
                }
            }
        }
        $tipo = PropuestaNivel::QUEDAR;
        if ($pid === 'conocerse_dos') {
            $tipo = PropuestaNivel::PRESENTAR;
        } elseif ($pid === 'primera_cita_hoy') {
            $tipo = PropuestaNivel::PRIMERA_CITA;
        } elseif ($pid === 'cita_desbloqueada') {
            $tipo = PropuestaNivel::CITA;
        }
        $lugar = (string) ($params['lugar_id'] ?? 'lug_cafeteria');
        if ($pid === 'cambio_de_sitio' && ($lugar === '' || $lugar === 'lug_cafeteria')) {
            $lugar = 'lug_parque';
        }
        return [
            'id' => 'lab_' . (string) ($mision['id'] ?? 'x'),
            'tipo' => $tipo,
            'participantes' => array_values(array_filter([$a, $b], static function ($x) {
                return $x !== '';
            })),
            'lugar' => $lugar,
            'intencion' => 'celeste_organizado',
            'dia' => (int) ($mision['dia'] ?? ($partida['reloj']['dia_pueblo'] ?? 1)),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function emit(array &$partida, string $evento, array $payload, ?GameLogger $logger, string $regla): void
    {
        if (!empty($partida['_lab_misiones_b3'])) {
            return;
        }
        DomainEventDispatcher::emit($partida, $evento, $payload, $logger, $regla);
    }
}
