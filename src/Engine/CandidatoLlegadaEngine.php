<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Llegadas post-tutorial: un candidato activo → buzón → aceptar/rechazar/expirar
 * → espera 1–10 min → incorporar + vivienda.
 *
 * Parámetros de probabilidad: lab SimuladorEconomia (p = min(0.45, 0.08+0.04*huecos)/día)
 * + brief post-gate (espera 1–10 min, un candidato).
 * Plazo oferta: 2 días (configurable; no había cifra canónica cerrada → documentado en cal).
 */
final class CandidatoLlegadaEngine
{
    public const TIPO_MSG = 'candidato_llegada';
    public const TIPO_ESPERA = 'candidato_en_camino';
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_ACEPTADO_EN_CAMINO = 'en_camino';
    public const ESTADO_LLEGADO = 'llegado';
    public const ESTADO_RECHAZADO = 'rechazado';
    public const ESTADO_EXPIRADO = 'expirado';

    /**
     * @param array<string, mixed> $partida
     */
    public static function ensure(array &$partida): void
    {
        CapacidadViviendas::ensure($partida);
        $partida['llegadas'] ??= [];
        $l = &$partida['llegadas'];
        $l['candidato_activo'] ??= null;
        $l['en_camino'] ??= null;
        $l['cooldown_hasta_dia'] ??= 0;
        $l['excluidos'] ??= []; // rechazados/expirados/archivados esta partida
        $l['historial'] ??= [];
        $l['modo'] ??= 'off'; // off | tutorial | normal
        if (!isset($l['normal_desde_dia'])) {
            $l['normal_desde_dia'] = null;
        }
    }

    public static function modoNormalActivo(array $partida): bool
    {
        self::ensure($partida);
        if (($partida['llegadas']['modo'] ?? '') !== 'normal') {
            return false;
        }
        $tut = TutorialBucle::vista($partida);
        if (!empty($tut['activo'])) {
            return false;
        }
        return true;
    }

    public static function activarModoNormal(array &$partida): void
    {
        self::ensure($partida);
        $partida['llegadas']['modo'] = 'normal';
        $partida['llegadas']['normal_desde_dia'] = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
    }

    /**
     * Tick en cada avance de reloj.
     *
     * @return array<string, mixed>
     */
    public static function tick(
        array &$partida,
        string $root,
        ?GameLogger $logger = null,
        int $horasAvanzadas = 1
    ): array {
        self::ensure($partida);
        $out = ['llegadas_completadas' => [], 'expirados' => [], 'ofrecidos' => null];

        $done = self::resolverEnCamino($partida, $root, $logger);
        if ($done !== null) {
            $out['llegadas_completadas'][] = $done;
        }

        $exp = self::expirarSiToca($partida, $root);
        if ($exp !== null) {
            $out['expirados'][] = $exp;
        }

        if (self::modoNormalActivo($partida)
            && ($partida['llegadas']['candidato_activo'] ?? null) === null
            && ($partida['llegadas']['en_camino'] ?? null) === null
        ) {
            $of = self::intentarOfrecer($partida, $root, $logger, max(1, $horasAvanzadas));
            if ($of !== null) {
                $out['ofrecidos'] = $of;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function intentarOfrecer(
        array &$partida,
        string $root,
        ?GameLogger $logger = null,
        int $horasAvanzadas = 24
    ): ?array {
        self::ensure($partida);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        if ($dia < (int) ($partida['llegadas']['cooldown_hasta_dia'] ?? 0)) {
            return null;
        }
        $huecos = CapacidadViviendas::huecos($partida);
        if ($huecos <= 0) {
            return null;
        }
        $n = count(TutorialIncorporaciones::residentesActivos($partida));
        if ($n >= CapacidadViviendas::CAP_PRODUCTO) {
            return null;
        }
        if (($partida['llegadas']['candidato_activo'] ?? null) !== null) {
            return null;
        }
        if (($partida['llegadas']['en_camino'] ?? null) !== null) {
            return null;
        }

        $cal = CalibracionConfig::load($root);
        if (self::modoNormalActivo($partida)) {
            $pDia = self::pDiaV3($n);
        } else {
            $pBase = (float) CalibracionConfig::get($cal, 'llegadas.p_base', 0.08);
            $pPorHueco = (float) CalibracionConfig::get($cal, 'llegadas.p_por_hueco', 0.04);
            $pMax = (float) CalibracionConfig::get($cal, 'llegadas.p_max_dia', 0.45);
            $pDia = $huecos <= 0 ? 0.0 : min($pMax, $pBase + $pPorHueco * $huecos);
        }
        // p diaria canónica. Si el reloj salta N horas, componer: 1-(1-p)^(N/24).
        // Evita el bug de avanzar(+24h) con una sola tirada a p/24.
        $fracDias = max(1, $horasAvanzadas) / 24.0;
        $p = 1.0 - pow(1.0 - $pDia, $fracDias);

        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        if ($tirada >= $p) {
            $rng->persistToPartida($partida);
            return null;
        }

        $pool = self::poolDisponible($partida, $root);
        if ($pool === []) {
            $rng->persistToPartida($partida);
            return null;
        }
        $pick = $rng->pickUnique($pool, 1);
        $rng->persistToPartida($partida);
        $catalogId = (string) ($pick[0] ?? '');
        if ($catalogId === '') {
            return null;
        }
        HistorialPersonajesPartida::marcar($partida, $catalogId);

        $plazoDias = (int) CalibracionConfig::get($cal, 'llegadas.plazo_oferta_dias', 2);
        $nombre = self::nombreCatalogo($root, $catalogId);
        $msgId = 'msg_cand_' . $catalogId . '_' . $dia . '_' . bin2hex(random_bytes(2));
        $cand = [
            'catalog_id' => $catalogId,
            'nombre' => $nombre,
            'estado' => self::ESTADO_PENDIENTE,
            'dia_oferta' => $dia,
            'hora_oferta' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            'vence_dia' => $dia + max(1, $plazoDias),
            'mensaje_id' => $msgId,
            'p_dia' => $pDia,
            'huecos' => $huecos,
        ];
        $partida['llegadas']['candidato_activo'] = $cand;

        BuzonEngine::crear($partida, [
            'id' => $msgId,
            'clasificacion' => BuzonEngine::OPORTUNIDAD,
            'tipo' => self::TIPO_MSG,
            'estado' => 'pendiente',
            'de_persona' => null,
            'actores' => [],
            'texto' => $nombre . ' quiere mudarse al pueblo. ¿Le dejamos hueco? '
                . 'Tiene hasta el día ' . $cand['vence_dia'] . ' para una respuesta.',
            'candidato_catalog_id' => $catalogId,
            'acciones' => ['aceptar_candidato', 'rechazar_candidato'],
            'estado_decision' => BuzonEngine::DECISION_PENDIENTE,
            'leido' => false,
            'origen' => [
                'evento_id' => null,
                'tipo_evento' => 'candidato_llegada',
                'es_narrativo' => false,
            ],
        ]);

        return $cand;
    }

    /**
     * @return array<string, mixed>
     */
    public static function aceptar(
        array &$partida,
        string $root,
        ?string $mensajeId = null,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);
        $cand = $partida['llegadas']['candidato_activo'] ?? null;
        if (!is_array($cand)) {
            return ['ok' => false, 'error' => 'sin_candidato'];
        }
        if ($mensajeId !== null && $mensajeId !== '' && ($cand['mensaje_id'] ?? '') !== $mensajeId) {
            return ['ok' => false, 'error' => 'mensaje_no_coincide'];
        }
        if (CapacidadViviendas::huecos($partida) <= 0) {
            return ['ok' => false, 'error' => 'sin_hueco'];
        }

        $rng = RngService::fromPartida($partida);
        $esperaMin = $rng->nextInt(1, 10);
        $rng->persistToPartida($partida);

        $abs = self::minutosAbs($partida) + $esperaMin;
        $enCamino = [
            'catalog_id' => (string) $cand['catalog_id'],
            'nombre' => (string) ($cand['nombre'] ?? $cand['catalog_id']),
            'estado' => self::ESTADO_ACEPTADO_EN_CAMINO,
            'espera_minutos' => $esperaMin,
            'llega_en_minutos_abs' => $abs,
            'mensaje_id' => $cand['mensaje_id'] ?? null,
            'aceptado_dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'aceptado_hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            'aceptado_minuto' => (int) ($partida['reloj']['minuto_actual'] ?? 0),
        ];
        $partida['llegadas']['en_camino'] = $enCamino;
        $partida['llegadas']['candidato_activo'] = null;

        if (!empty($cand['mensaje_id'])) {
            BuzonEngine::resolverDecision($partida, (string) $cand['mensaje_id']);
        }
        BuzonEngine::crear($partida, [
            'id' => 'msg_espera_' . $enCamino['catalog_id'] . '_' . bin2hex(random_bytes(2)),
            'clasificacion' => BuzonEngine::IMPORTANTE,
            'tipo' => self::TIPO_ESPERA,
            'estado' => 'en_espera',
            'texto' => 'Estamos esperando a ' . $enCamino['nombre'] . '. '
                . 'Debería aparecer en unos ' . $esperaMin . ' minuto' . ($esperaMin === 1 ? '' : 's') . '.',
            'candidato_catalog_id' => $enCamino['catalog_id'],
            'origen' => ['tipo_evento' => 'candidato_en_camino', 'es_narrativo' => false],
        ]);

        return ['ok' => true, 'en_camino' => $enCamino];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rechazar(
        array &$partida,
        string $root,
        ?string $mensajeId = null
    ): array {
        self::ensure($partida);
        $cand = $partida['llegadas']['candidato_activo'] ?? null;
        if (!is_array($cand)) {
            return ['ok' => false, 'error' => 'sin_candidato'];
        }
        if ($mensajeId !== null && $mensajeId !== '' && ($cand['mensaje_id'] ?? '') !== $mensajeId) {
            return ['ok' => false, 'error' => 'mensaje_no_coincide'];
        }
        $id = (string) $cand['catalog_id'];
        HistorialPersonajesPartida::marcar($partida, $id);
        if (!empty($cand['mensaje_id'])) {
            BuzonEngine::resolverDecision($partida, (string) $cand['mensaje_id']);
        }
        $partida['llegadas']['candidato_activo'] = null;
        self::aplicarCooldown($partida, $root, 'rechazo');
        $partida['llegadas']['historial'][] = [
            'catalog_id' => $id,
            'resultado' => self::ESTADO_RECHAZADO,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
        ];
        return ['ok' => true, 'rechazado' => $id];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function expirarSiToca(array &$partida, string $root): ?array
    {
        $cand = $partida['llegadas']['candidato_activo'] ?? null;
        if (!is_array($cand)) {
            return null;
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        if ($dia < (int) ($cand['vence_dia'] ?? PHP_INT_MAX)) {
            return null;
        }
        $id = (string) $cand['catalog_id'];
        HistorialPersonajesPartida::marcar($partida, $id);
        if (!empty($cand['mensaje_id'])) {
            BuzonEngine::resolverDecision($partida, (string) $cand['mensaje_id']);
        }
        $partida['llegadas']['candidato_activo'] = null;
        self::aplicarCooldown($partida, $root, 'expirado');
        $row = [
            'catalog_id' => $id,
            'resultado' => self::ESTADO_EXPIRADO,
            'dia' => $dia,
        ];
        $partida['llegadas']['historial'][] = $row;
        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resolverEnCamino(
        array &$partida,
        string $root,
        ?GameLogger $logger
    ): ?array {
        $ec = $partida['llegadas']['en_camino'] ?? null;
        if (!is_array($ec)) {
            return null;
        }
        if (self::minutosAbs($partida) < (int) ($ec['llega_en_minutos_abs'] ?? PHP_INT_MAX)) {
            return null;
        }
        $catalogId = (string) ($ec['catalog_id'] ?? '');
        $ops = new ResidenteOperations(new Catalog($root), $logger);
        if (!PoolJugableCanon::esIdCanonico($catalogId)) {
            $partida['llegadas']['en_camino'] = null;
            return ['ok' => false, 'error' => 'candidato_no_canonico', 'catalog_id' => $catalogId];
        }
        $r = $ops->incorporarCatalogo($partida, $catalogId, 'residente');
        $partida['llegadas']['en_camino'] = null;
        if (!($r['ok'] ?? false)) {
            return ['ok' => false, 'error' => $r['error'] ?? 'incorporar_fallo', 'catalog_id' => $catalogId];
        }
        $partida['llegadas']['historial'][] = [
            'catalog_id' => $catalogId,
            'resultado' => self::ESTADO_LLEGADO,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'vivienda_id' => $r['vivienda_id'] ?? null,
        ];
        self::aplicarCooldown($partida, $root, 'llegada');
        $nombre = IdentidadPublica::nombre($partida, $catalogId);
        BuzonEngine::crear($partida, [
            'id' => 'msg_llegada_' . $catalogId . '_' . bin2hex(random_bytes(2)),
            'clasificacion' => BuzonEngine::IMPORTANTE,
            'tipo' => 'llegada_efectiva',
            'texto' => $nombre . ' ya está en el pueblo. Tiene vivienda y puede salir.',
            'de_persona' => $catalogId,
            'actores' => [$catalogId],
            'origen' => ['tipo_evento' => DomainEvents::RESIDENTE_INCORPORADO, 'es_narrativo' => false],
        ]);
        return ['ok' => true, 'catalog_id' => $catalogId, 'vivienda_id' => $r['vivienda_id'] ?? null];
    }

    private static function aplicarCooldown(array &$partida, string $root, string $motivo): void
    {
        if (self::modoNormalActivo($partida)) {
            self::aplicarCooldownV3($partida);
            return;
        }
        $cal = CalibracionConfig::load($root);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $pCd = (float) CalibracionConfig::get($cal, 'llegadas.p_cooldown_1d', 0.40);
        $rng = RngService::fromPartida($partida);
        $cd = $rng->nextFloat() < $pCd ? 1 : 0;
        if ($motivo === 'llegada') {
            $cd = max($cd, 1);
        }
        $rng->persistToPartida($partida);
        $partida['llegadas']['cooldown_hasta_dia'] = $dia + $cd;
    }

    public static function gapMin(int $n): int
    {
        $n = max(8, min(CapacidadViviendas::CAP_PRODUCTO - 1, $n));
        return 2 + (int) floor(($n - 8) * 1.25);
    }

    public static function pDiaV3(int $n): float
    {
        $h = max(0, CapacidadViviendas::CAP_PRODUCTO - $n);
        return min(0.30, 0.04 + 0.015 * $h);
    }

    private static function aplicarCooldownV3(array &$partida): void
    {
        $n = count(TutorialIncorporaciones::residentesActivos($partida));
        $gap = self::gapMin($n);
        $rng = RngService::fromPartida($partida);
        $jitter = $rng->nextInt(0, 2);
        $rng->persistToPartida($partida);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida['llegadas']['cooldown_hasta_dia'] = $dia + $gap + $jitter;
    }

    /** @return list<string> */
    public static function poolDisponible(array $partida, string $root): array
    {
        HistorialPersonajesPartida::ensure($partida);
        $catalog = new Catalog($root);
        $ids = $catalog->listPersonajeIdsJugables();
        // Regla global de partida: sin nombres duplicados entre personajes.
        // El histórico excluye por ID, pero el catálogo puede tener dos fichas
        // distintas con el mismo nombre (p. ej. per_p014 y per_p104 = "Alba").
        $usados = NombresReservadosPartida::usados($partida, $root);
        $out = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            if (HistorialPersonajesPartida::yaAparecio($partida, $id)) {
                continue;
            }
            if (NombresReservadosPartida::idBloqueado($usados, $root, $id)) {
                continue;
            }
            $out[] = $id;
        }
        return $out;
    }

    private static function nombreCatalogo(string $root, string $catalogId): string
    {
        return NombresReservadosPartida::nombreCatalogo($root, $catalogId);
    }

    public static function minutosAbs(array $partida): int
    {
        $d = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $h = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $m = (int) ($partida['reloj']['minuto_actual'] ?? 0);
        return (($d - 1) * 24 * 60) + ($h * 60) + $m;
    }

    /**
     * Avanza minutos de reloj (para espera 1–10).
     */
    public static function avanzarMinutosReloj(array &$partida, int $minutos): void
    {
        if ($minutos <= 0) {
            return;
        }
        Reloj::ensure($partida);
        $total = (int) ($partida['reloj']['minuto_actual'] ?? 0) + $minutos;
        $addH = intdiv($total, 60);
        $partida['reloj']['minuto_actual'] = $total % 60;
        if ($addH > 0) {
            Reloj::avanzarHoras($partida, $addH);
        }
    }
}
