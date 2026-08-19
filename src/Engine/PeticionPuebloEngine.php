<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Peticiones de habitantes (B4). Encargos personales realizables.
 * Reloj REAL. Una pendiente por residente. Complementa B3, no lo duplica.
 */
final class PeticionPuebloEngine
{
    public const FLAG = 'peticiones_pueblo_enabled';
    public const EST_ABIERTA = 'abierta';
    public const EST_RESUELTA = 'resuelta';
    public const EST_CADUCADA = 'caducada';
    public const EST_IGNORADA = 'ignorada';

    public static function ensure(array &$partida): void
    {
        $partida['peticiones'] ??= [];
        $partida['peticiones_pueblo'] ??= [
            'validos_dia' => 0,
            'validos_dia_n' => 0,
            'encuentros_usados' => [],
            'historial_plantillas' => [],
            '_provisional' => true,
        ];
        $partida['peticiones_pueblo']['encuentros_usados'] ??= [];
        $partida['peticiones_pueblo']['historial_plantillas'] ??= [];
    }

    public static function activa(array $partida): bool
    {
        return FeatureConfig::isEnabled($partida, self::FLAG);
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function capSimultaneas(int $nRes, array $cal = []): int
    {
        $pct = (float) CalibracionConfig::get($cal, 'peticiones_pueblo.cap_pct', 0.33);
        $max = (int) CalibracionConfig::get($cal, 'peticiones_pueblo.cap_max', 10);
        if ($pct < 0.3) {
            $pct = 0.33;
        }
        if ($max < 1) {
            $max = 10;
        }
        $n = (int) ceil($nRes * $pct);
        if ($n < 1) {
            $n = 1;
        }
        return $n > $max ? $max : $n;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function abiertas(array $partida): array
    {
        $out = [];
        foreach ($partida['peticiones'] ?? [] as $p) {
            if (($p['estado'] ?? '') !== self::EST_ABIERTA) {
                continue;
            }
            if (empty($p['schema_b4'])) {
                continue;
            }
            $out[] = $p;
        }
        return $out;
    }

    public static function pendienteDe(array $partida, string $residenteId): bool
    {
        foreach (self::abiertas($partida) as $p) {
            if ((string) ($p['residente_id'] ?? '') === $residenteId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Caducar (reloj real) + fallo de Vida + 0–N nacimientos naturales.
     *
     * @param array<string, mixed> $cal
     */
    public static function tick(array &$partida, array $cal = [], ?RngService $rng = null, ?GameLogger $logger = null, int $horas = 1): int
    {
        if (!self::activa($partida)) {
            return 0;
        }
        self::ensure($partida);
        PeticionEngine::caducarVencidas($partida, $logger);
        self::aplicarFalloPendiente($partida, $cal, $logger);
        if ($rng === null) {
            $rng = RngService::fromPartida($partida);
        }
        $n = 0;
        $intentos = $horas < 1 ? 1 : $horas;
        if ($intentos > 3) {
            $intentos = 3;
        }
        for ($i = 0; $i < $intentos; $i++) {
            $pet = self::intentarNacer($partida, $cal, $rng, $logger);
            if ($pet !== null) {
                $n++;
            }
        }
        $rng->persistToPartida($partida);
        return $n;
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    public static function intentarNacer(array &$partida, array $cal = [], ?RngService $rng = null, ?GameLogger $logger = null): ?array
    {
        if (!self::activa($partida)) {
            return null;
        }
        self::ensure($partida);
        $nRes = count(self::residentes($partida));
        $cap = self::capSimultaneas($nRes, $cal);
        $abiertas = self::abiertas($partida);
        $huecos = $cap - count($abiertas);
        if ($huecos <= 0) {
            return null;
        }
        if ($rng === null) {
            $rng = RngService::fromPartida($partida);
        }
        $pBase = (float) CalibracionConfig::get($cal, 'peticiones_pueblo.p_nacer_hora_base', 0.045);
        $pHueco = (float) CalibracionConfig::get($cal, 'peticiones_pueblo.p_nacer_hora_hueco', 0.07);
        $p = $pBase + $pHueco * ($huecos / $cap);
        if (empty($partida['_b4_forzar_nacer']) && $rng->nextFloat() > $p) {
            return null;
        }
        $cands = self::candidatosSpawn($partida, $cal);
        if ($cands === []) {
            return null;
        }
        foreach ($cands as $k => $c) {
            $cands[$k]['_score'] = (int) ($c['prioridad'] ?? 0) + $rng->nextInt(0, 8);
        }
        usort($cands, static function ($a, $b) {
            return ((int) ($b['_score'] ?? 0)) <=> ((int) ($a['_score'] ?? 0));
        });
        $pick = $cands[0];
        return self::nacerDesde($partida, $pick, $cal, $rng, $logger);
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function onEncuentroCelestine(array &$partida, array $encuentro, array $cal = [], ?GameLogger $logger = null): int
    {
        if (!self::activa($partida)) {
            return 0;
        }
        if (!MisionDiariaEngine::esEncuentroCelestine($encuentro)) {
            return 0;
        }
        $eid = (string) ($encuentro['id'] ?? '');
        self::ensure($partida);
        $usados = $partida['peticiones_pueblo']['encuentros_usados'] ?? [];
        if ($eid !== '' && in_array($eid, $usados, true)) {
            return 0;
        }
        foreach ($partida['peticiones'] as $i => $p) {
            if (($p['estado'] ?? '') !== self::EST_ABIERTA || empty($p['schema_b4'])) {
                continue;
            }
            if (!self::encaja($p, $encuentro)) {
                continue;
            }
            if ($eid !== '') {
                $partida['peticiones_pueblo']['encuentros_usados'][] = $eid;
            }
            self::cumplirIndice($partida, $i, $cal, $logger);
            return 1;
        }
        return 0;
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function cumplir(array &$partida, string $peticionId, array $cal = [], ?GameLogger $logger = null): array
    {
        self::ensure($partida);
        foreach ($partida['peticiones'] as $i => $p) {
            if ((string) ($p['id'] ?? '') === $peticionId) {
                return self::cumplirIndice($partida, $i, $cal, $logger);
            }
        }
        return ['ok' => false, 'error' => 'peticion_no_encontrada'];
    }

    public static function encaja(array $peticion, array $encuentro): bool
    {
        $pid = (string) ($peticion['plantilla_id'] ?? '');
        $tipo = PropuestaNivel::aliasTipo((string) ($encuentro['tipo'] ?? ''));
        $partes = $encuentro['participantes'] ?? [];
        $lugar = (string) ($encuentro['lugar'] ?? '');
        $params = is_array($peticion['params'] ?? null) ? $peticion['params'] : [];
        $rid = (string) ($peticion['residente_id'] ?? '');
        if ($rid === '' || !in_array($rid, $partes, true)) {
            return false;
        }
        if ($pid === 'salir_de_casa') {
            return true;
        }
        if ($pid === 'ir_al_lugar') {
            return $lugar === (string) ($params['lugar_id'] ?? '');
        }
        if ($pid === 'conocer_a_alguien') {
            return $tipo === PropuestaNivel::PRESENTAR;
        }
        if ($pid === 'volver_a_ver' || $pid === 'quedar_con_x') {
            $otro = (string) ($params['otro'] ?? '');
            return $tipo === PropuestaNivel::QUEDAR && $otro !== '' && in_array($otro, $partes, true);
        }
        if ($pid === 'algo_distinto') {
            return $lugar !== '' && $lugar !== 'lug_cafeteria' && $lugar !== 'lug_casa';
        }
        if ($pid === 'primera_cita_pet') {
            $otro = (string) ($params['otro'] ?? '');
            return $tipo === PropuestaNivel::PRIMERA_CITA && $otro !== '' && in_array($otro, $partes, true);
        }
        return false;
    }

    /**
     * Copy de plazo humano. Sin fechas técnicas ni IDs.
     */
    public static function plazoHumano(array $peticion): string
    {
        $h = self::horasRestantes($peticion);
        if ($h === null) {
            return 'Cuando puedas.';
        }
        if ($h <= 0) {
            return 'Se le ha pasado el arroz.';
        }
        if ($h <= 2) {
            $n = $h === 1 ? '1 h' : $h . ' h';
            return 'Le quedan ' . $n . ' y ya está mirando el reloj.';
        }
        if ($h <= 6) {
            return 'Esto empieza a oler a plantón.';
        }
        if ($h === 1) {
            return 'Te queda 1 h';
        }
        return 'Te quedan ' . $h . ' h';
    }

    public static function horasRestantes(array $peticion): ?int
    {
        $iso = (string) ($peticion['vence_iso'] ?? '');
        if ($iso === '') {
            return null;
        }
        $vence = self::parseIso($iso);
        if ($vence === null) {
            return null;
        }
        $secs = $vence->getTimestamp() - Reloj::ahoraLocal()->getTimestamp();
        if ($secs <= 0) {
            return 0;
        }
        if ($secs < 3600) {
            return 1;
        }
        return (int) floor($secs / 3600);
    }

    /**
     * Vista PLAY: quién, qué, cuánto queda, estado. Sin IDs ni jerga.
     *
     * @return list<array<string, mixed>>
     */
    public static function vistaAbiertas(array $partida): array
    {
        $out = [];
        foreach (self::abiertas($partida) as $p) {
            $rid = (string) ($p['residente_id'] ?? '');
            $out[] = [
                'id' => $p['id'] ?? '',
                'quien' => IdentidadPublica::nombre($partida, $rid),
                'texto' => (string) ($p['texto'] ?? ''),
                'plazo_humano' => self::plazoHumano($p),
                'estado' => 'pendiente',
            ];
        }
        return $out;
    }

    /**
     * Encuentro mínimo que debería completar la petición si Celestine lo organiza.
     *
     * @param array<string, mixed> $peticion
     * @return array<string, mixed>
     */
    public static function encuentroSinteticoPara(array $peticion, array $partida): array
    {
        $pid = (string) ($peticion['plantilla_id'] ?? '');
        $params = is_array($peticion['params'] ?? null) ? $peticion['params'] : [];
        $ids = self::residentes($partida);
        $a = (string) ($peticion['residente_id'] ?? ($ids[0] ?? ''));
        $b = (string) ($params['otro'] ?? '');
        if ($b === '' || $b === $a) {
            foreach ($ids as $id) {
                if ($id !== $a) {
                    $b = $id;
                    break;
                }
            }
        }
        $tipo = PropuestaNivel::QUEDAR;
        if ($pid === 'conocer_a_alguien') {
            $tipo = PropuestaNivel::PRESENTAR;
        } elseif ($pid === 'primera_cita_pet') {
            $tipo = PropuestaNivel::PRIMERA_CITA;
        }
        $lugar = (string) ($params['lugar_id'] ?? 'lug_cafeteria');
        if ($pid === 'algo_distinto' && ($lugar === '' || $lugar === 'lug_cafeteria')) {
            $lugar = 'lug_parque';
        }
        return [
            'id' => 'lab_pet_' . (string) ($peticion['id'] ?? 'x'),
            'tipo' => $tipo,
            'participantes' => array_values(array_filter([$a, $b], static function ($x) {
                return $x !== '';
            })),
            'lugar' => $lugar,
            'intencion' => 'celeste_organizado',
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
        ];
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function onIgnorada(array &$partida, array $peticion, array $cal = [], ?GameLogger $logger = null): void
    {
        if (empty($peticion['schema_b4']) || !self::activa($partida)) {
            return;
        }
        self::aplicarFalloUna($partida, $peticion, $cal, $logger, VidaPuebloEngine::CAUSA_PETICION_IGNORADA);
    }

    /**
     * @param array<string, mixed> $cal
     * @return list<array<string, mixed>>
     */
    public static function candidatosSpawn(array $partida, array $cal = []): array
    {
        $out = [];
        $ocupados = [];
        foreach (self::abiertas($partida) as $p) {
            $ocupados[(string) ($p['residente_id'] ?? '')] = true;
        }
        foreach (self::residentes($partida) as $rid) {
            if (isset($ocupados[$rid])) {
                continue;
            }
            foreach (PeticionPlantillas::catalogo() as $pl) {
                $cands = self::candidatosDe($partida, $rid, $pl, $cal);
                if ($cands === []) {
                    continue;
                }
                $params = $cands[0];
                $out[] = [
                    'residente_id' => $rid,
                    'plantilla' => $pl,
                    'params' => $params,
                    'prioridad' => (int) ($pl['prioridad'] ?? 0),
                ];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $pl
     * @param array<string, mixed> $cal
     * @return list<array<string, mixed>>
     */
    public static function candidatosDe(array $partida, string $rid, array $pl, array $cal = []): array
    {
        $id = (string) ($pl['id'] ?? '');
        $ids = self::residentes($partida);
        if ($id === 'salir_de_casa') {
            $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            $umbral = (int) CalibracionConfig::get($cal, 'peticiones_pueblo.dias_sin_salir', 2);
            if ($dia < $umbral + 1) {
                return [];
            }
            $ult = (int) ($partida['residentes'][$rid]['runtime']['ultimo_contacto_social_dia'] ?? 0);
            $sin = $ult <= 0 ? $dia : ($dia - $ult);
            return $sin >= $umbral ? [[]] : [];
        }
        if ($id === 'ir_al_lugar') {
            $out = [];
            foreach ($partida['celeste']['lugares_desbloqueados'] ?? [] as $lug) {
                $lug = (string) $lug;
                if ($lug === '' || $lug === 'lug_casa') {
                    continue;
                }
                $out[] = ['lugar_id' => $lug];
            }
            return $out;
        }
        if ($id === 'conocer_a_alguien') {
            foreach ($ids as $otro) {
                if ($otro === $rid) {
                    continue;
                }
                if (PropuestaNivel::permite($partida, $rid, $otro, PropuestaNivel::PRESENTAR, $cal)) {
                    return [['otro' => $otro]];
                }
            }
            return [];
        }
        if ($id === 'volver_a_ver' || $id === 'quedar_con_x') {
            $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            $out = [];
            foreach ($ids as $otro) {
                if ($otro === $rid) {
                    continue;
                }
                if (!PropuestaNivel::permite($partida, $rid, $otro, PropuestaNivel::QUEDAR, $cal)) {
                    continue;
                }
                if ($id === 'volver_a_ver') {
                    $ult = (int) ($partida['residentes'][$rid]['runtime']['ultimo_contacto_social_dia'] ?? 0);
                    $sin = $ult <= 0 ? $dia : ($dia - $ult);
                    if ($sin < 2) {
                        continue;
                    }
                }
                $out[] = ['otro' => $otro];
            }
            return $out;
        }
        if ($id === 'algo_distinto') {
            foreach ($partida['celeste']['lugares_desbloqueados'] ?? [] as $lug) {
                $lug = (string) $lug;
                if ($lug !== '' && $lug !== 'lug_cafeteria' && $lug !== 'lug_casa') {
                    return [['lugar_id' => $lug]];
                }
            }
            return [];
        }
        if ($id === 'primera_cita_pet') {
            $out = [];
            foreach ($ids as $otro) {
                if ($otro === $rid) {
                    continue;
                }
                if (PropuestaNivel::permite($partida, $rid, $otro, PropuestaNivel::PRIMERA_CITA, $cal)) {
                    $out[] = ['otro' => $otro];
                }
            }
            return $out;
        }
        return [];
    }

    /**
     * @param array<string, mixed> $pick
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    private static function nacerDesde(array &$partida, array $pick, array $cal, RngService $rng, ?GameLogger $logger): ?array
    {
        $pl = is_array($pick['plantilla'] ?? null) ? $pick['plantilla'] : [];
        $params = is_array($pick['params'] ?? null) ? $pick['params'] : [];
        $rid = (string) ($pick['residente_id'] ?? '');
        if ($rid === '' || $pl === []) {
            return null;
        }
        if (isset($params['lugar_id']) && count(self::candidatosDe($partida, $rid, $pl, $cal)) > 1) {
            $opts = self::candidatosDe($partida, $rid, $pl, $cal);
            $params = $opts[$rng->nextInt(0, count($opts) - 1)];
        } elseif (isset($params['otro']) && count(self::candidatosDe($partida, $rid, $pl, $cal)) > 1) {
            $opts = self::candidatosDe($partida, $rid, $pl, $cal);
            $params = $opts[$rng->nextInt(0, count($opts) - 1)];
        }
        $texto = self::renderCopy((string) ($pl['copy'] ?? ''), $params, $partida);
        $plazo = (int) ($pl['plazo_horas'] ?? 24);
        $r = PeticionEngine::crear($partida, $rid, (string) ($pl['tipo_legado'] ?? 'otro'), [
            'schema_b4' => true,
            'plantilla_id' => (string) ($pl['id'] ?? ''),
            'familia' => (string) ($pl['familia'] ?? ''),
            'params' => $params,
            'texto' => $texto,
            'hecho' => (string) ($pl['hecho'] ?? ''),
            'peso' => (string) ($pl['peso'] ?? PeticionEsquemas::PESO_FACIL),
            'exigencia' => (int) ($pl['exigencia'] ?? 0),
            'plazo_horas' => $plazo,
            'cuenta_latido' => false,
            '_placeholder_copy' => false,
        ], $logger);
        if (empty($r['ok'])) {
            return null;
        }
        $partida['peticiones_pueblo']['historial_plantillas'][] = (string) ($pl['id'] ?? '');
        return $r['peticion'] ?? null;
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function cumplirIndice(array &$partida, int $i, array $cal, ?GameLogger $logger): array
    {
        $p = $partida['peticiones'][$i];
        if (($p['estado'] ?? '') !== self::EST_ABIERTA) {
            return ['ok' => false, 'error' => 'no_abierta', 'peticion' => $p];
        }
        $res = PeticionEngine::resolver($partida, (string) ($p['id'] ?? ''), $logger);
        if (empty($res['ok'])) {
            return $res;
        }
        $p = $res['peticion'];
        foreach ($partida['peticiones'] as $j => $it) {
            if ((string) ($it['id'] ?? '') === (string) ($p['id'] ?? '')) {
                $i = $j;
                $p = $it;
                break;
            }
        }
        $esquema = PeticionEsquemas::activo($cal, $partida);
        $peso = (string) ($p['peso'] ?? PeticionEsquemas::PESO_FACIL);
        $delta = (int) ($esquema['ok'][$peso] ?? 1);
        if ($delta < 1) {
            $delta = 1;
        }
        $quiere = !empty($esquema['valido'][$peso]);
        $maxV = (int) ($esquema['max_validos_dia'] ?? 0);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        self::rotarValidosDia($partida, $dia);
        $valido = false;
        if ($quiere && $maxV > 0 && (int) ($partida['peticiones_pueblo']['validos_dia'] ?? 0) < $maxV) {
            $valido = true;
            $partida['peticiones_pueblo']['validos_dia'] = (int) $partida['peticiones_pueblo']['validos_dia'] + 1;
            $partida['peticiones'][$i]['cuenta_latido'] = true;
        }
        VidaPuebloEngine::aplicar($partida, $delta, [
            'causa' => VidaPuebloEngine::CAUSA_PETICION_CUMPLIDA,
            'origen' => VidaPuebloEngine::ORIGEN_JUGADOR,
            'atribuible_celestine' => true,
            'positivo_valido_latido' => $valido,
            'fuente_id' => $p['id'] ?? null,
        ], $cal, $logger);
        self::marcarBuzon($partida, $p, 'resuelto');
        self::emit($partida, DomainEvents::PETICION_CUMPLIDA, [
            'peticion' => $partida['peticiones'][$i],
            'actores' => [$p['residente_id'] ?? ''],
        ], $logger, 'PeticionPuebloEngine::cumplir');
        return ['ok' => true, 'peticion' => $partida['peticiones'][$i]];
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function aplicarFalloPendiente(array &$partida, array $cal = [], ?GameLogger $logger = null): int
    {
        $n = 0;
        foreach ($partida['peticiones'] ?? [] as $p) {
            $est = (string) ($p['estado'] ?? '');
            if ($est !== self::EST_CADUCADA && $est !== self::EST_IGNORADA) {
                continue;
            }
            if (empty($p['schema_b4']) || !empty($p['vida_fallo_aplicada'])) {
                continue;
            }
            $causa = $est === self::EST_IGNORADA
                ? VidaPuebloEngine::CAUSA_PETICION_IGNORADA
                : VidaPuebloEngine::CAUSA_PETICION_CADUCADA;
            self::aplicarFalloUna($partida, $p, $cal, $logger, $causa);
            $n++;
        }
        return $n;
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function aplicarFalloUna(array &$partida, array $peticion, array $cal, ?GameLogger $logger, string $causa): void
    {
        $id = (string) ($peticion['id'] ?? '');
        foreach ($partida['peticiones'] as $i => $p) {
            if ((string) ($p['id'] ?? '') !== $id) {
                continue;
            }
            if (!empty($p['vida_fallo_aplicada'])) {
                return;
            }
            $esquema = PeticionEsquemas::activo($cal, $partida);
            $peso = (string) ($p['peso'] ?? PeticionEsquemas::PESO_FACIL);
            $dano = (int) ($esquema['fail'][$peso] ?? -1);
            if ($dano > -1) {
                $dano = -1;
            }
            VidaPuebloEngine::aplicar($partida, $dano, [
                'causa' => $causa,
                'origen' => VidaPuebloEngine::ORIGEN_SISTEMA,
                'atribuible_celestine' => true,
                'positivo_valido_latido' => false,
                'fuente_id' => $id,
            ], $cal, $logger);
            $partida['peticiones'][$i]['vida_fallo_aplicada'] = true;
            self::marcarBuzon($partida, $p, 'resuelto');
            return;
        }
    }

    private static function rotarValidosDia(array &$partida, int $dia): void
    {
        self::ensure($partida);
        if ((int) ($partida['peticiones_pueblo']['validos_dia_n'] ?? 0) !== $dia) {
            $partida['peticiones_pueblo']['validos_dia_n'] = $dia;
            $partida['peticiones_pueblo']['validos_dia'] = 0;
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function renderCopy(string $tpl, array $params, array $partida): string
    {
        if (isset($params['lugar_id'])) {
            $tpl = str_replace('{lugar}', MisionPlantillas::nombreLugar((string) $params['lugar_id']), $tpl);
        }
        if (isset($params['otro'])) {
            $tpl = str_replace('{otro}', IdentidadPublica::nombre($partida, (string) $params['otro']), $tpl);
        }
        return $tpl;
    }

    /**
     * @return list<string>
     */
    public static function residentes(array $partida): array
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

    private static function marcarBuzon(array &$partida, array $peticion, string $estado): void
    {
        $bid = (string) ($peticion['buzon_id'] ?? '');
        if ($bid === '') {
            return;
        }
        BuzonEngine::marcarEstado($partida, $bid, $estado);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function emit(array &$partida, string $evento, array $payload, ?GameLogger $logger, string $regla): void
    {
        if (!empty($partida['_lab_peticiones_b4']) || !empty($partida['_lab_misiones_b3'])) {
            return;
        }
        DomainEventDispatcher::emit($partida, $evento, $payload, $logger, $regla);
    }

    private static function parseIso(string $iso): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($iso);
        } catch (\Exception $e) {
            return null;
        }
    }
}
