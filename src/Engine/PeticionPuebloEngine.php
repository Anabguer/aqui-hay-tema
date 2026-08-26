<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadEvaluator;

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

    /** Máximo de personas reales que Celestine evalúa en conocer_a_alguien (UX: pocas opciones). */
    public const MAX_OPCIONES_SELECTOR = 3;
    /** Lugar canónico para la presentación que Celestine organiza tras la elección. */
    public const LUGAR_PRESENTACION = 'lug_cafeteria';

    public static function ensure(array &$partida): void
    {
        $partida['peticiones'] ??= [];
        $partida['peticiones_pueblo'] ??= [
            'validos_dia' => 0,
            'validos_dia_n' => 0,
            'encuentros_usados' => [],
            'historial_plantillas' => [],
            // R08: hora absoluta de juego del último nacimiento autónomo
            // (dia*24+hora). 0 = nunca ha nacido ninguna (gap inactivo).
            'ultima_nace_abs' => 0,
            '_canon_b4' => 'E3',
        ];
        $partida['peticiones_pueblo']['encuentros_usados'] ??= [];
        $partida['peticiones_pueblo']['historial_plantillas'] ??= [];
        $partida['peticiones_pueblo']['ultima_nace_abs'] ??= 0;
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
        // R07: suelo de 2 slots. Evita pueblos pequenos (3-5 vecinos) con un
        // unico slot bloqueado durante demasiado tiempo. Pop >= 8 no cambia.
        $min = (int) CalibracionConfig::get($cal, 'peticiones_pueblo.cap_min', 2);
        if ($pct < 0.3) {
            $pct = 0.33;
        }
        if ($max < 1) {
            $max = 10;
        }
        if ($min < 1) {
            $min = 1;
        }
        if ($min > $max) {
            $min = $max;
        }
        $n = (int) ceil($nRes * $pct);
        if ($n < $min) {
            $n = $min;
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
     * R08: gap mínimo REAL entre nacimientos autónomos. Si nació una petición
     * en dia X hora H, durante las siguientes gap_min_horas de juego no puede
     * nacer otra (aunque el RNG salga favorable). Después vuelve a evaluarse
     * con la probabilidad R07 normal. No toca peticiones manuales ni labs con
     * _b4_forzar_nacer.
     */
    public static function estaEnGap(array $partida, array $cal = []): bool
    {
        $gap = (int) CalibracionConfig::get($cal, 'peticiones_pueblo.gap_min_horas', 6);
        if ($gap <= 0 || !empty($partida['_b4_forzar_nacer'])) {
            return false;
        }
        $ultima = (int) ($partida['peticiones_pueblo']['ultima_nace_abs'] ?? 0);
        if ($ultima <= 0) {
            return false;
        }
        $abs = ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24
            + (int) ($partida['reloj']['hora_actual'] ?? 0);
        return ($abs - $ultima) < $gap;
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
        // R08: separación mínima entre nacimientos autónomos. Antes del RNG:
        // en hora bloqueada ni se consume semilla ni se escanean candidatos.
        if (self::estaEnGap($partida, $cal)) {
            return null;
        }
        if ($rng === null) {
            $rng = RngService::fromPartida($partida);
        }
        $pBase = (float) CalibracionConfig::get($cal, 'peticiones_pueblo.p_nacer_hora_base', 0.045);
        $pHueco = (float) CalibracionConfig::get($cal, 'peticiones_pueblo.p_nacer_hora_hueco', 0.07);
        $p = $pBase + $pHueco * ($huecos / $cap);
        // R07: impulso SOLO para caps pequenos (suelo 2). Pop >= 7 (cap >= 3)
        // mantiene la formula intacta; no se toca p_nacer_hora_hueco global.
        if ($cap <= 2) {
            $impulso = (float) CalibracionConfig::get($cal, 'peticiones_pueblo.impulso_cap_pequeno', 1.25);
            if ($impulso > 1.0) {
                $p *= $impulso;
            }
        }
        if (empty($partida['_b4_forzar_nacer']) && $rng->nextFloat() > $p) {
            return null;
        }
        $cands = self::candidatosSpawn($partida, $cal);
        if ($cands === []) {
            return null;
        }
        $ventana = (int) CalibracionConfig::get($cal, 'peticiones_pueblo.anti_rep_ventana', 3);
        $penal = (int) CalibracionConfig::get($cal, 'peticiones_pueblo.anti_rep_penalizacion', 25);
        $histReciente = $ventana > 0
            ? array_slice($partida['peticiones_pueblo']['historial_plantillas'], -$ventana)
            : [];
        foreach ($cands as $k => $c) {
            $plantillaId = (string) ($c['plantilla']['id'] ?? '');
            $repes = 0;
            foreach ($histReciente as $hp) {
                if ((string) $hp === $plantillaId) {
                    $repes++;
                }
            }
            $cands[$k]['_score'] = (int) ($c['prioridad'] ?? 0) + $rng->nextInt(0, 8) - ($penal > 0 ? $penal * $repes : 0);
            if ($repes > 0) {
                $cands[$k]['_anti_rep'] = $penal * $repes;
            }
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
        return self::nivelEncaje($peticion, $encuentro);
    }

    /**
     * Petición B4 abierta que esta propuesta de Celestine cubriría, y con qué nivel.
     *
     * - 'exacta': el plan ES lo que el peticionario pidió (o cualquier salida válida
     *   para salir_de_casa / cualquier Conocerse para conocer_a_alguien). El
     *   peticionario queda comprometido: no vuelve a tirar RNG por lo ya pedido.
     *   La agenda/cooldown siguen mandando; los TERCEROS conservan voluntad normal.
     * - 'nucleo': Celestine cumple el núcleo (lugar/tipo) pero añadió compañía que
     *   el peticionario NO pidió. Sin garantía: bonus fuerte configurable.
     *
     * @param list<string> $participantes
     * @return array{peticion: array<string, mixed>, nivel: string}|null
     */
    public static function peticionQueCubre(array $partida, array $participantes, string $tipo, string $lugar): ?array
    {
        $tipo = PropuestaNivel::aliasTipo($tipo);
        $exacta = null;
        $nucleo = null;
        foreach (self::abiertas($partida) as $pet) {
            $rid = (string) ($pet['residente_id'] ?? '');
            if ($rid === '' || !in_array($rid, $participantes, true)) {
                continue;
            }
            $params = is_array($pet['params'] ?? null) ? $pet['params'] : [];
            $pid = (string) ($pet['plantilla_id'] ?? '');
            $n = count($participantes);
            if ($pid === 'salir_de_casa') {
                // El núcleo pedido es SALIR; cualquier encuentro celebrado con él vale.
                // Añadir compañía no modifica lo pedido.
                return ['peticion' => $pet, 'nivel' => 'exacta'];
            }
            if ($pid === 'conocer_a_alguien') {
                if ($tipo === PropuestaNivel::PRESENTAR) {
                    return ['peticion' => $pet, 'nivel' => 'exacta'];
                }
                continue;
            }
            if ($pid === 'ir_al_lugar') {
                if ((string) ($params['lugar_id'] ?? '') !== $lugar) {
                    continue;
                }
                $nivel = $n === 1 ? 'exacta' : 'nucleo';
            } elseif ($pid === 'algo_distinto') {
                $lugarValido = $lugar !== '' && $lugar !== 'lug_cafeteria' && $lugar !== 'lug_casa';
                if (!$lugarValido) {
                    continue;
                }
                $nivel = $n === 1 ? 'exacta' : 'nucleo';
            } elseif ($pid === 'volver_a_ver' || $pid === 'quedar_con_x') {
                $otro = (string) ($params['otro'] ?? '');
                if ($tipo !== PropuestaNivel::QUEDAR || $otro === '' || !in_array($otro, $participantes, true)) {
                    continue;
                }
                $nivel = $n === 2 ? 'exacta' : 'nucleo';
            } elseif ($pid === 'primera_cita_pet') {
                $otro = (string) ($params['otro'] ?? '');
                if ($tipo !== PropuestaNivel::PRIMERA_CITA || $otro === '' || !in_array($otro, $participantes, true)) {
                    continue;
                }
                $nivel = $n === 2 ? 'exacta' : 'nucleo';
            } else {
                continue;
            }
            if ($nivel === 'exacta') {
                $exacta = ['peticion' => $pet, 'nivel' => 'exacta'];
                break;
            }
            if ($nucleo === null) {
                $nucleo = ['peticion' => $pet, 'nivel' => 'nucleo'];
            }
        }
        return $exacta ?? $nucleo;
    }

    private static function nivelEncaje(array $peticion, array $encuentro): bool
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
     * Copy de plazo humano, dirigido a Celestine. Sin fechas técnicas ni IDs.
     * Formas canónicas: la UI las muestra como chip y sabe recortarlas del
     * cuerpo del Mensajito (ver cuerpoMensajito en play-v3.js).
     */
    public static function plazoHumano(array $peticion, ?array $partida = null): string
    {
        $h = self::horasRestantes($peticion, $partida);
        if ($h === null) {
            return 'Cuando puedas.';
        }
        if ($h <= 0) {
            return 'El tiempo se acaba.';
        }
        if ($h === 1) {
            return 'Te queda 1 h';
        }
        return 'Te quedan ' . $h . ' h';
    }

    public static function horasRestantes(array $peticion, ?array $partida = null): ?int
    {
        $venceDia = $peticion['vence_dia'] ?? null;
        if ($partida !== null && $venceDia !== null) {
            // Canónico: horas de juego restantes según el reloj de la partida.
            $nowJuego = ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24
                + (int) ($partida['reloj']['hora_actual'] ?? 0);
            $secs = (((int) $venceDia) * 24 + (int) ($peticion['vence_hora'] ?? 0) - $nowJuego) * 3600;
            return self::horasDeSegundos($secs);
        }
        $iso = (string) ($peticion['vence_iso'] ?? '');
        if ($iso === '') {
            return null;
        }
        $vence = self::parseIso($iso);
        if ($vence === null) {
            return null;
        }
        $secs = $vence->getTimestamp() - Reloj::ahoraLocal()->getTimestamp();
        return self::horasDeSegundos($secs);
    }

    private static function horasDeSegundos(int $secs): int
    {
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
            $out[] = self::vistaItem($partida, $p);
        }
        return $out;
    }

    /**
     * Copy de pueblo: quién, qué, plazo, estado. Sin peso, recompensa ni jerga.
     *
     * @param array<string, mixed> $peticion
     * @return array<string, mixed>
     */
    public static function vistaItem(array $partida, array $peticion): array
    {
        $rid = (string) ($peticion['residente_id'] ?? '');
        $est = (string) ($peticion['estado'] ?? self::EST_ABIERTA);
        if ($est === self::EST_ABIERTA) {
            $estadoUi = 'pendiente';
        } elseif ($est === self::EST_RESUELTA) {
            $estadoUi = 'cumplida';
        } elseif ($est === self::EST_CADUCADA || $est === self::EST_IGNORADA) {
            $estadoUi = 'caducada';
        } else {
            $estadoUi = 'pendiente';
        }
        return [
            'id' => $peticion['id'] ?? '',
            'quien' => IdentidadPublica::nombre($partida, $rid),
            'texto' => (string) ($peticion['texto'] ?? ''),
            'plazo_humano' => self::plazoHumano($peticion, $partida),
            'estado' => $estadoUi,
        ];
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
     * Residentes presentes a quienes el peticionario AÚN puede ser presentado
     * según el contrato canónico PRESENTAR (conocimiento, gates, parentesco).
     * Excluye al peticionario. Orden estable (orden del save). Sin duplicados.
     *
     * @param array<string, mixed> $cal
     * @return list<string>
     */
    public static function presentablesParaConocer(array $partida, string $rid, array $cal = []): array
    {
        if (!isset($partida['residentes'][$rid])) {
            return [];
        }
        $out = [];
        foreach (self::residentes($partida) as $otro) {
            if ($otro === $rid) {
                continue;
            }
            if (!PropuestaNivel::permite($partida, $rid, $otro, PropuestaNivel::PRESENTAR, $cal)) {
                continue;
            }
            if (!in_array($otro, $out, true)) {
                $out[] = $otro;
            }
        }
        return $out;
    }

    /**
     * Snapshot del selector de Celestine para conocer_a_alguien: SOLO información
     * que Celestine conoce legítimamente (nombre público + como mucho UNA pista
     * ya revelada por Discovery). Muestreo determinista tipo stride.
     *
     * @param array<string, mixed> $cal
     * @return list<array{personaje_id: string, nombre: string, pista: ?string}>
     */
    public static function opcionesConocer(array $partida, string $rid, array $cal = []): array
    {
        return self::snapshotOpcionesConocer($partida, $rid, self::presentablesParaConocer($partida, $rid, $cal));
    }

    /**
     * @param list<string> $ids
     * @return list<array{personaje_id: string, nombre: string, pista: ?string}>
     */
    private static function snapshotOpcionesConocer(array $partida, string $rid, array $ids): array
    {
        unset($rid);
        $n = count($ids);
        if ($n === 0) {
            return [];
        }
        $k = min(self::MAX_OPCIONES_SELECTOR, $n);
        $sel = [];
        if ($k >= $n) {
            $sel = $ids;
        } else {
            for ($j = 0; $j < $k; $j++) {
                $idx = (int) round($j * ($n - 1) / ($k - 1));
                if (!in_array($ids[$idx], $sel, true)) {
                    $sel[] = $ids[$idx];
                }
            }
        }
        $store = new CatalogStore(dirname(__DIR__, 2));
        $out = [];
        foreach ($sel as $pid) {
            $out[] = self::opcionDto($partida, $pid, $store);
        }
        return $out;
    }

    /**
     * @return array{personaje_id: string, nombre: string, pista: ?string}
     */
    private static function opcionDto(array $partida, string $pid, CatalogStore $store): array
    {
        $o = [
            'personaje_id' => $pid,
            'nombre' => IdentidadPublica::nombre($partida, $pid),
            'pista' => null,
        ];
        $perfil = PerfilPartida::de($partida, $pid);
        $hobbies = is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : [];
        foreach ($hobbies as $h) {
            $h = (string) $h;
            if ($h === '') {
                continue;
            }
            if (DiscoveryReveal::jugadorSabeHobby($partida, $pid, $h)) {
                $o['pista'] = HobbyAccionable::pista($h, $store)
                    ?: ('Le gusta ' . EtiquetaFicha::hobby($h, $store) . '.');
                break;
            }
        }
        return $o;
    }

    /**
     * @param list<array{personaje_id: string, nombre: string, pista: ?string}> $opciones
     */
    private static function adjuntarSelector(array &$partida, string $buzonId, array $opciones): void
    {
        foreach ($partida['buzon'] as &$m) {
            if (!is_array($m) || (string) ($m['id'] ?? '') !== $buzonId) {
                continue;
            }
            $m['acciones'] = [MensajitoAcciones::ELEGIR_PERSONA];
            $m['estado_decision'] = BuzonEngine::DECISION_PENDIENTE;
            $m['selector_opciones'] = $opciones;
            $m['selector_titulo'] = '¿A quién presentas?';
            $m['selector_estado'] = 'pendiente';
            break;
        }
        unset($m);
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function nacerConocer(array &$partida, string $rid, array $cal = [], ?RngService $rng = null, ?GameLogger $logger = null): ?array
    {
        if (!self::activa($partida)) {
            return null;
        }
        self::ensure($partida);
        if (!isset($partida['residentes'][$rid]) || self::pendienteDe($partida, $rid)) {
            return null;
        }
        $pl = PeticionPlantillas::porId('conocer_a_alguien');
        if ($pl === null) {
            return null;
        }
        if ($rng === null) {
            $rng = RngService::fromPartida($partida);
        }
        $pick = [
            'residente_id' => $rid,
            'plantilla' => $pl,
            'params' => [],
            'prioridad' => (int) ($pl['prioridad'] ?? 0),
        ];
        return self::nacerDesde($partida, $pick, $cal, $rng, $logger);
    }

    /**
     * Celestine elige persona y dispara el flujo canónico PRESENTAR.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function elegirCandidato(
        array &$partida,
        string $mensajeId,
        string $personajeId,
        array $payload = [],
        ?VoluntadEvaluator $voluntad = null,
        ?GameLogger $logger = null
    ): array {
        unset($payload);
        $mensaje = null;
        foreach ($partida['buzon'] ?? [] as $m) {
            if (is_array($m) && (string) ($m['id'] ?? '') === $mensajeId) {
                $mensaje = $m;
                break;
            }
        }
        if ($mensaje === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $petIdx = null;
        $pid = (string) ($mensaje['peticion_id'] ?? '');
        foreach ($partida['peticiones'] ?? [] as $i => $p) {
            if ((string) ($p['id'] ?? '') === $pid) {
                $petIdx = $i;
                break;
            }
        }
        if ($petIdx === null) {
            return ['ok' => false, 'error' => 'peticion_no_encontrada'];
        }
        $pet = $partida['peticiones'][$petIdx];
        if (empty($pet['schema_b4']) || (string) ($pet['plantilla_id'] ?? '') !== 'conocer_a_alguien') {
            return ['ok' => false, 'error' => 'plantilla_sin_selector'];
        }
        if ((string) ($pet['estado'] ?? '') !== self::EST_ABIERTA) {
            return ['ok' => false, 'error' => 'peticion_cerrada', 'estado' => (string) $pet['estado']];
        }
        if (!empty($pet['candidato_elegido'])) {
            return [
                'ok' => true,
                'ya_elegido' => true,
                'peticion_id' => $pid,
                'candidato_elegido' => (string) $pet['candidato_elegido'],
                'mensaje_ui' => 'Ya había elegido a alguien para esta presentación.',
            ];
        }
        $opciones = is_array($pet['params']['opciones'] ?? null) ? $pet['params']['opciones'] : [];
        if ($opciones === []) {
            return ['ok' => false, 'error' => 'sin_opciones_legacy'];
        }
        $idsSnapshot = [];
        foreach ($opciones as $o) {
            if (is_array($o) && (string) ($o['personaje_id'] ?? '') !== '') {
                $idsSnapshot[] = (string) $o['personaje_id'];
            }
        }
        if (!in_array($personajeId, $idsSnapshot, true)) {
            return ['ok' => false, 'error' => 'candidato_fuera_snapshot', 'snapshot' => $idsSnapshot];
        }
        $cal = CalibracionConfig::load(dirname(__DIR__, 2));
        $rid = (string) ($pet['residente_id'] ?? '');
        if ($personajeId === $rid
            || !isset($partida['residentes'][$personajeId])
            || ($partida['residentes'][$personajeId]['presencia'] ?? '') !== 'residente'
            || !PropuestaNivel::permite($partida, $rid, $personajeId, PropuestaNivel::PRESENTAR, $cal)
        ) {
            return ['ok' => false, 'error' => 'candidato_no_disponible'];
        }
        $diaAhora = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $horaAhora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $slot = $diaAhora * 24 + $horaAhora + 1;
        $partida['peticiones'][$petIdx]['candidato_elegido'] = $personajeId;
        $partida['peticiones'][$petIdx]['selector'] = [
            'via' => 'celestine',
            'elegido_dia' => $diaAhora,
            'elegido_hora' => $horaAhora,
            'opciones_ids' => $idsSnapshot,
        ];
        \aht_log_optional($logger, $partida, 'peticion_elegir_candidato', [
            'peticion_id' => $pid,
            'peticionario' => $rid,
            'candidato' => $personajeId,
            'opciones_snapshot' => $idsSnapshot,
        ]);
        $r = PropuestaEncuentroEngine::proponer(
            $partida,
            [$rid, $personajeId],
            intdiv($slot, 24),
            $slot % 24,
            PropuestaNivel::PRESENTAR,
            self::LUGAR_PRESENTACION,
            null,
            $voluntad,
            $logger
        );
        return [
            'ok' => true,
            'ya_elegido' => false,
            'peticion_id' => $pid,
            'candidato_elegido' => $personajeId,
            'propuesta_estado' => $r['propuesta']['estado'] ?? null,
            'programado' => !empty($r['programado']),
            'rechazo_clase' => $r['rechazo_clase'] ?? null,
            'mensaje_ui' => $r['mensaje_ui'] ?? null,
        ];
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
        if ((string) ($pl['id'] ?? '') === 'conocer_a_alguien') {
            $presentables = self::presentablesParaConocer($partida, $rid, $cal);
            if ($presentables === []) {
                return null;
            }
            if (count($presentables) === 1) {
                $params = ['otro' => $presentables[0]];
            } else {
                $params = ['opciones' => self::snapshotOpcionesConocer($partida, $rid, $presentables)];
            }
        } elseif (isset($params['lugar_id']) && count(self::candidatosDe($partida, $rid, $pl, $cal)) > 1) {
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
            'generacion' => [
                'via' => 'autonoma',
                'abiertas_al_nacer' => count(self::abiertas($partida)),
            ],
        ], $logger);
        if (empty($r['ok'])) {
            return null;
        }
        $bid = (string) ($r['peticion']['buzon_id'] ?? '');
        if (isset($params['opciones']) && is_array($params['opciones']) && $params['opciones'] !== [] && $bid !== '') {
            self::adjuntarSelector($partida, $bid, $params['opciones']);
        }
        $partida['peticiones_pueblo']['historial_plantillas'][] = (string) ($pl['id'] ?? '');
        if (count($partida['peticiones_pueblo']['historial_plantillas']) > 24) {
            $partida['peticiones_pueblo']['historial_plantillas'] = array_slice(
                $partida['peticiones_pueblo']['historial_plantillas'],
                -24
            );
        }
        // R08: marca el gap desde AHORA. Cruza medianoches sin problema (abs).
        $partida['peticiones_pueblo']['ultima_nace_abs'] = ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24
            + (int) ($partida['reloj']['hora_actual'] ?? 0);
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
        PeticionFeedback::alCumplir($partida, $p, $logger);
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
            // Cierre NEUTRO (leído) del mensajito original: 'resuelto' queda reservado
            // para cumplidas, para que el jugador distinga los desenlaces.
            self::marcarBuzon($partida, $p, 'leido');
            PeticionFeedback::alCaducar($partida, $p, $causa === VidaPuebloEngine::CAUSA_PETICION_IGNORADA, $logger);
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
