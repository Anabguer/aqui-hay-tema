<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Marchas: intención → Mensajito interactivo → decisión Celestine.
 * Nunca offline. Pedir quedarse = se queda siempre (misma causa no reintenta).
 */
final class MarchaEngine
{
    public const TIPO_MSG = 'intencion_marcha';
    public const CAUSA_AISLAMIENTO = 'aislamiento';
    public const CAUSA_EMOCION_NEGATIVA = 'emocion_negativa';
    public const CAUSA_CONFLICTO = 'conflicto';
    public const CAUSA_CRISIS = 'crisis';

    public static function ensure(array &$partida): void
    {
        $partida['marchas'] ??= [];
        $partida['marchas']['intenciones'] ??= [];
        $partida['marchas']['causas_vivas'] ??= [];
        $partida['marchas']['historial'] ??= [];
    }

    /**
     * Evalúa al cerrar día si alguien quiere irse (raro, señales reales).
     *
     * @return array<string, mixed>|null
     */
    public static function evaluarAlCerrarDia(
        array &$partida,
        string $root,
        ?GameLogger $logger = null
    ): ?array {
        self::ensure($partida);
        if (self::tieneIntencionPendiente($partida)) {
            return null;
        }
        $cal = CalibracionConfig::load($root);
        $rng = RngService::fromPartida($partida);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);

        foreach (TutorialIncorporaciones::residentesActivos($partida) as $rid) {
            $rid = (string) $rid;
            $senal = self::senalDeMarcha($partida, $rid, $dia, $cal);
            if ($senal === null) {
                continue;
            }
            $causa = (string) $senal['causa'];
            if (self::causaBloqueada($partida, $rid, $causa)) {
                continue;
            }
            $p = (float) ($senal['probabilidad'] ?? 0);
            if ($rng->nextFloat() > $p) {
                continue;
            }
            $rng->persistToPartida($partida);
            return self::crearIntencion($partida, $rid, $causa, $senal, $logger);
        }
        $rng->persistToPartida($partida);
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function dejarIr(
        array &$partida,
        string $root,
        ?string $mensajeId = null,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);
        $int = self::intencionActiva($partida, $mensajeId);
        if ($int === null) {
            return ['ok' => false, 'error' => 'sin_intencion_marcha'];
        }
        $rid = (string) ($int['residente_id'] ?? '');
        if ($rid === '' || !isset($partida['residentes'][$rid])) {
            return ['ok' => false, 'error' => 'residente_no_encontrado'];
        }

        $nombre = IdentidadPublica::nombre($partida, $rid);
        $viviendaId = (string) ($partida['residentes'][$rid]['vivienda_id'] ?? '');
        CapacidadViviendas::liberarResidente($partida, $rid);
        $partida['residentes'][$rid]['presencia'] = 'antiguo_residente';
        HistorialPersonajesPartida::marcar($partida, $rid);

        $int['estado'] = 'marchado';
        $int['resuelto_dia'] = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida['marchas']['intenciones'][$int['id']] = $int;

        $seed = 'marcha_efectiva|' . $rid;
        $texto = CopyCotilleoFamilias::linea('marcha_efectiva', ['nombre' => $nombre], $seed);
        if ($texto === '') {
            $texto = $nombre . ' se ha ido del pueblo. La vivienda queda libre.';
        }
        BuzonEngine::crear($partida, [
            'clasificacion' => BuzonEngine::COTILLEO,
            'tipo' => 'marcha_publica',
            'texto' => $texto,
            'cotilleo_meta' => CotilleoCategoria::meta(CotilleoCategoria::PUEBLO, true),
            'actores' => [$rid],
            'de_persona' => $rid,
            'origen' => ['tipo_evento' => DomainEvents::MARCHA_EFECTIVA, 'es_narrativo' => true],
        ]);

        DomainEventDispatcher::emit($partida, DomainEvents::MARCHA_EFECTIVA, [
            'residente_id' => $rid,
            'causa' => $int['causa'] ?? null,
            'actores' => [$rid],
        ], $logger, 'MarchaEngine::dejarIr');

        $partida['marchas']['historial'][] = [
            'residente_id' => $rid,
            'resultado' => 'marchado',
            'causa' => $int['causa'] ?? null,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
        ];

        return ['ok' => true, 'residente_id' => $rid, 'vivienda_liberada' => $viviendaId !== ''];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pedirQuedarse(
        array &$partida,
        string $root,
        ?string $mensajeId = null,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);
        $int = self::intencionActiva($partida, $mensajeId);
        if ($int === null) {
            return ['ok' => false, 'error' => 'sin_intencion_marcha'];
        }
        $rid = (string) ($int['residente_id'] ?? '');
        $causa = (string) ($int['causa'] ?? '');
        $nombre = IdentidadPublica::nombre($partida, $rid);

        $int['estado'] = 'se_queda';
        $int['resuelto_dia'] = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida['marchas']['intenciones'][$int['id']] = $int;

        self::registrarCausaViva($partida, $rid, $causa, $int);

        // CONTRATO NARRATIVO: el propio NPC responde a Celestine (1.ª persona).
        $texto = MensajitoVoz::linea(
            $partida,
            'marcha_se_queda',
            [],
            'marcha_se_queda|' . $rid . '|' . $int['resuelto_dia'],
            $rid
        );
        if ($texto !== '') {
            BuzonEngine::crear($partida, [
                'clasificacion' => BuzonEngine::IMPORTANTE,
                'tipo' => 'marcha_se_queda',
                'texto' => $texto,
                'de_persona' => $rid,
                'actores' => [$rid],
                'origen' => ['tipo_evento' => DomainEvents::MARCHA_INTENCION, 'es_narrativo' => true],
            ]);
        }

        return ['ok' => true, 'residente_id' => $rid, 'causa_viva' => $causa];
    }

    /**
     * @param array<string, mixed> $senal
     * @return array<string, mixed>
     */
    private static function crearIntencion(
        array &$partida,
        string $rid,
        string $causa,
        array $senal,
        ?GameLogger $logger
    ): array {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $nombre = IdentidadPublica::nombre($partida, $rid);
        $msgId = 'msg_marcha_' . $rid . '_' . $dia . '_' . bin2hex(random_bytes(2));
        $intId = 'marcha_' . $rid . '_' . $dia;

        // CONTRATO NARRATIVO: la marcha empieza en un Mensajito del propio NPC
        // a Celestine, en primera persona (docs/BUZON_DE_CELESTINE.md).
        $texto = MensajitoVoz::linea(
            $partida,
            'marcha_intencion',
            ['nombre' => $nombre],
            'marcha_intencion|' . $rid . '|' . $causa . '|' . $dia,
            $rid
        );

        $int = [
            'id' => $intId,
            'residente_id' => $rid,
            'causa' => $causa,
            'senal' => $senal,
            'estado' => 'pendiente',
            'dia' => $dia,
            'mensaje_id' => $msgId,
        ];
        $partida['marchas']['intenciones'][$intId] = $int;

        BuzonEngine::crear($partida, [
            'id' => $msgId,
            'clasificacion' => BuzonEngine::IMPORTANTE,
            'tipo' => self::TIPO_MSG,
            'de_persona' => $rid,
            'actores' => [$rid],
            'texto' => $texto,
            'acciones' => [MensajitoAcciones::DEJAR_MARCHAR, MensajitoAcciones::PEDIR_QUEDARSE],
            'estado_decision' => BuzonEngine::DECISION_PENDIENTE,
            'leido' => false,
            'marcha_id' => $intId,
            'origen' => [
                'tipo_evento' => DomainEvents::MARCHA_INTENCION,
                'es_narrativo' => true,
                'informacion_revelada' => ['causa' => $causa],
            ],
        ]);

        DomainEventDispatcher::emit($partida, DomainEvents::MARCHA_INTENCION, [
            'residente_id' => $rid,
            'causa' => $causa,
            'mensaje_id' => $msgId,
            'actores' => [$rid],
        ], $logger, 'MarchaEngine::crearIntencion');

        return $int;
    }

    /**
     * @return array{causa: string, probabilidad: float, detalle: array<string, mixed>}|null
     */
    private static function senalDeMarcha(array $partida, string $rid, int $dia, array $cal): ?array
    {
        $minSenales = (int) CalibracionConfig::get($cal, 'marchas.min_senales', 2);
        $pBase = (float) CalibracionConfig::get($cal, 'marchas.p_base', 0.12);
        $senales = [];

        $emo = (string) ($partida['residentes'][$rid]['runtime']['estado_emocional']['id'] ?? EstadoEmocional::NEUTRO);
        $diasNeg = (int) CalibracionConfig::get($cal, 'marchas.emocion_negativa_dias', 4);
        if (in_array($emo, [EstadoEmocional::TRISTE, EstadoEmocional::ENFADADO], true)) {
            $desde = $partida['residentes'][$rid]['runtime']['estado_emocional']['desde'] ?? null;
            $dEmo = is_array($desde) ? (int) ($desde['dia'] ?? $dia) : $dia;
            if (($dia - $dEmo) >= $diasNeg) {
                $senales[] = self::CAUSA_EMOCION_NEGATIVA;
            }
        }

        $umbralAis = (int) CalibracionConfig::get(
            $cal,
            'marchas.aislamiento_dias',
            (int) CalibracionConfig::get($cal, 'autonomia.anti_aislamiento_umbral_dias', 5)
        );
        $ultCon = (int) ($partida['residentes'][$rid]['runtime']['ultimo_contacto_social_dia'] ?? 0);
        $diasSin = $ultCon === 0 ? $dia : max(0, $dia - $ultCon);
        if ($umbralAis > 0 && $diasSin >= $umbralAis) {
            $senales[] = self::CAUSA_AISLAMIENTO;
        }

        foreach ($partida['bitacora_relaciones'] ?? [] as $h) {
            if (!is_array($h)) {
                continue;
            }
            $tipo = (string) ($h['tipo'] ?? '');
            $partes = is_array($h['participantes'] ?? null) ? $h['participantes'] : [];
            if (!in_array($rid, $partes, true)) {
                continue;
            }
            $hDia = (int) ($h['fecha']['dia'] ?? 0);
            if ($dia - $hDia > 7) {
                continue;
            }
            if ($tipo === RelacionBitacora::DISCUSION_FUERTE) {
                $senales[] = self::CAUSA_CONFLICTO;
            }
            if ($tipo === RelacionBitacora::CRISIS) {
                $senales[] = self::CAUSA_CRISIS;
            }
        }

        $senales = array_values(array_unique($senales));
        if (count($senales) < $minSenales) {
            return null;
        }

        $causa = $senales[0];
        $p = min(0.35, $pBase + 0.04 * (count($senales) - $minSenales));
        return [
            'causa' => $causa,
            'probabilidad' => $p,
            'detalle' => ['senales' => $senales, 'dias_sin_contacto' => $diasSin ?? null],
        ];
    }

    private static function tieneIntencionPendiente(array $partida): bool
    {
        foreach ($partida['marchas']['intenciones'] ?? [] as $int) {
            if (is_array($int) && ($int['estado'] ?? '') === 'pendiente') {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function intencionActiva(array $partida, ?string $mensajeId): ?array
    {
        foreach ($partida['marchas']['intenciones'] ?? [] as $int) {
            if (!is_array($int) || ($int['estado'] ?? '') !== 'pendiente') {
                continue;
            }
            if ($mensajeId !== null && $mensajeId !== '' && ($int['mensaje_id'] ?? '') !== $mensajeId) {
                continue;
            }
            return $int;
        }
        if ($mensajeId !== null && $mensajeId !== '') {
            foreach ($partida['marchas']['intenciones'] ?? [] as $int) {
                if (is_array($int) && ($int['mensaje_id'] ?? '') === $mensajeId) {
                    return ($int['estado'] ?? '') === 'pendiente' ? $int : null;
                }
            }
        }
        return null;
    }

    private static function causaBloqueada(array $partida, string $rid, string $causa): bool
    {
        $vivas = $partida['marchas']['causas_vivas'][$rid] ?? [];
        if (!is_array($vivas)) {
            return false;
        }
        return !empty($vivas[$causa]['bloquea_reintento']);
    }

    /**
     * @param array<string, mixed> $int
     */
    private static function registrarCausaViva(array &$partida, string $rid, string $causa, array $int): void
    {
        self::ensure($partida);
        $partida['marchas']['causas_vivas'][$rid] ??= [];
        $partida['marchas']['causas_vivas'][$rid][$causa] = [
            'desde_dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'senal' => $int['senal'] ?? null,
            'bloquea_reintento' => true,
            'resolucion' => 'se_queda',
        ];
    }

    /** Dev: forzar intención de marcha para pruebas. */
    public static function forzarIntencionDev(array &$partida, string $rid, string $causa = self::CAUSA_AISLAMIENTO): array
    {
        self::ensure($partida);
        return self::crearIntencion($partida, $rid, $causa, [
            'causa' => $causa,
            'probabilidad' => 1.0,
            'detalle' => ['dev' => true],
        ], null);
    }
}
