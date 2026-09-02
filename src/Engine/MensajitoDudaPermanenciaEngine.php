<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * F8 — Duda de permanencia (puente §19.4 → MarchaEngine).
 *
 * El vecino le confiesa a Celestine que siente desarraigo por falta de trato,
 * antes de la intención formal de marcha. Si se ignora, facilita la escalada.
 */
final class MensajitoDudaPermanenciaEngine
{
    public const FAMILIA = 'f_duda_permanencia';

    public static function ensure(array &$partida): void
    {
        $partida['mensajitos_duda_permanencia'] ??= [];
    }

    /**
     * Evalúa al cerrar día: como mucho un F8 si hay señal de arraigo débil.
     *
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    public static function evaluarAlCerrarDia(array &$partida, array $cal, ?GameLogger $logger = null): ?array
    {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return null;
        }
        self::ensure($partida);
        if (self::tieneF8Pendiente($partida)) {
            return null;
        }
        if (self::tieneIntencionPendientePublico($partida)) {
            return null;
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        foreach (TutorialIncorporaciones::residentesActivos($partida) as $rid) {
            $rid = (string) $rid;
            $senal = self::senalArraigoDebil($partida, $rid, $dia, $cal);
            if ($senal === null) {
                continue;
            }
            if (MensajitoConsejoEngine::yaExisteHiloReciente(
                $partida,
                $rid,
                self::FAMILIA,
                (string) ($senal['clave'] ?? '')
            )) {
                continue;
            }
            return self::generar($partida, $rid, $senal, $logger);
        }
        return null;
    }

    /**
     * @return array{familia: string, peso: int, datos: array<string, mixed>}|null
     */
    public static function candidatoEspontaneo(array $partida, string $rid, array $cal): ?array
    {
        if (self::tieneF8Pendiente($partida)) {
            return null;
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $senal = self::senalArraigoDebil($partida, $rid, $dia, $cal);
        if ($senal === null) {
            return null;
        }
        $clave = (string) ($senal['clave'] ?? '');
        if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, self::FAMILIA, $clave)) {
            return null;
        }
        return ['familia' => self::FAMILIA, 'peso' => 3, 'datos' => $senal];
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>|null
     */
    public static function generarDesdeCandidato(
        array &$partida,
        string $rid,
        array $datos,
        ?GameLogger $logger = null
    ): ?array {
        return self::generar($partida, $rid, $datos, $logger);
    }

    /**
     * @return array<string, mixed>
     */
    public static function organizarContacto(array &$partida, string $mensajeId): array
    {
        $mensaje = BuzonEngine::buscar($partida, $mensajeId);
        if ($mensaje === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $rid = (string) ($mensaje['de_persona'] ?? '');
        self::marcarAtendida($partida, $rid, 'organizar');
        MensajitoConsejoEngine::cerrarHiloPublico($partida, $mensajeId, ['accion' => 'organizar_contacto']);
        return [
            'ok' => true,
            'preset_organizar' => [
                'modo' => 'solo',
                'a' => $rid,
                'tipo' => PropuestaNivel::QUEDAR,
                'intencion' => 'animo',
                'participantes' => [$rid],
            ],
            'mensaje_ui' => 'Le montamos algo para que no se sienta tan solo' . GeneroConcordancia::oa($partida, $rid) . '.',
        ];
    }

    public static function marcarAtendidaPublico(array &$partida, string $rid, string $via): void
    {
        self::marcarAtendida($partida, $rid, $via);
    }

    public static function marcarEscaladaPublico(array &$partida, string $rid, string $mensajeId): void
    {
        self::marcarEscalada($partida, $rid, $mensajeId);
    }

    public static function registrarPendientePublico(array &$partida, string $rid, string $mensajeId): void
    {
        self::ensure($partida);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida['mensajitos_duda_permanencia'][$rid] = [
            'mensaje_id' => $mensajeId,
            'dia' => $dia,
            'estado' => 'pendiente',
            'escalada' => false,
        ];
    }

    public static function tieneIntencionPendientePublico(array $partida): bool
    {
        foreach ($partida['marchas']['intenciones'] ?? [] as $int) {
            if (is_array($int) && ($int['estado'] ?? '') === 'pendiente') {
                return true;
            }
        }
        return false;
    }

    public static function factorEscaladaMarcha(array $partida, string $rid): float
    {
        self::ensure($partida);
        $st = $partida['mensajitos_duda_permanencia'][$rid] ?? null;
        if (!is_array($st) || empty($st['escalada'])) {
            return 1.0;
        }
        return 1.35;
    }

    public static function minSenalesReducido(array $partida, string $rid, int $minSenales): int
    {
        self::ensure($partida);
        $st = $partida['mensajitos_duda_permanencia'][$rid] ?? null;
        if (is_array($st) && !empty($st['escalada']) && $minSenales > 1) {
            return $minSenales - 1;
        }
        return $minSenales;
    }

    /**
     * @param array<string, mixed> $senal
     * @return array<string, mixed>
     */
    private static function generar(array &$partida, string $rid, array $senal, ?GameLogger $logger): array
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $msgId = 'msg_f8_' . $rid . '_' . $dia . '_' . bin2hex(random_bytes(2));
        $texto = MensajitoVoz::linea(
            $partida,
            self::FAMILIA,
            ['texto' => (string) ($senal['motivo'] ?? 'un poco invisible')],
            self::FAMILIA . '|' . $rid . '|' . $dia,
            $rid
        );
        $datos = [
            'motivo' => (string) ($senal['motivo'] ?? 'aislamiento'),
            'dias_sin_contacto' => (int) ($senal['dias_sin_contacto'] ?? 0),
            'clave' => (string) ($senal['clave'] ?? ''),
        ];
        $r = CanalDeduplicador::crearSiAplica($partida, [
            'id' => $msgId,
            'clasificacion' => BuzonEngine::IMPORTANTE,
            'tipo' => 'espontaneo_' . self::FAMILIA,
            'de_persona' => $rid,
            'actores' => [$rid],
            'texto' => $texto,
            'acciones' => [
                MensajitoAcciones::ORGANIZAR_ALGO,
                MensajitoAcciones::RESPONDER_ESCUCHAR,
                MensajitoAcciones::NO_METERSE,
            ],
            'familia_mensajito' => self::FAMILIA,
            'datos_familia' => $datos,
            'hilo_id' => $msgId,
            'hilo_estado' => 'abierto',
            'origen' => [
                'evento_id' => $msgId,
                'tipo_evento' => 'espontaneo_' . self::FAMILIA,
                'es_narrativo' => true,
                'informacion_revelada' => [],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);
        if ($r === null || !($r['ok'] ?? false)) {
            return ['ok' => false];
        }
        self::ensure($partida);
        $partida['mensajitos_duda_permanencia'][$rid] = [
            'mensaje_id' => $msgId,
            'dia' => $dia,
            'estado' => 'pendiente',
            'escalada' => false,
        ];
        MensajitosCadenciaEngine::registrar($partida, $rid, self::FAMILIA, 'espontaneo', (string) $datos['clave']);
        DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
            'mensaje' => $r['mensaje'] ?? null,
            'origen_evento' => 'espontaneo_' . self::FAMILIA,
        ], $logger, 'MensajitoDudaPermanenciaEngine');
        return ['ok' => true, 'mensaje' => $r['mensaje'] ?? null];
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    private static function senalArraigoDebil(array $partida, string $rid, int $dia, array $cal): ?array
    {
        $umbralAis = (int) CalibracionConfig::get(
            $cal,
            'marchas.aislamiento_dias',
            (int) CalibracionConfig::get($cal, 'autonomia.anti_aislamiento_umbral_dias', 5)
        );
        if ($umbralAis < 3) {
            $umbralAis = 5;
        }
        $ultCon = (int) ($partida['residentes'][$rid]['runtime']['ultimo_contacto_social_dia'] ?? 0);
        $diasSin = $ultCon === 0 ? $dia : max(0, $dia - $ultCon);
        $minD = (int) max(2, floor($umbralAis * 0.55));
        $maxD = $umbralAis - 1;
        if ($diasSin < $minD || $diasSin > $maxD) {
            return null;
        }
        $st = $partida['mensajitos_duda_permanencia'][$rid] ?? null;
        if (is_array($st) && ($st['estado'] ?? '') === 'pendiente') {
            return null;
        }
        if (is_array($st) && !empty($st['atendida_dia']) && $dia - (int) $st['atendida_dia'] < 4) {
            return null;
        }
        return [
            'motivo' => 'poco contacto',
            'dias_sin_contacto' => $diasSin,
            'clave' => self::FAMILIA . '|' . $rid . '|' . $diasSin,
        ];
    }

    private static function tieneF8Pendiente(array $partida): bool
    {
        self::ensure($partida);
        foreach ($partida['mensajitos_duda_permanencia'] ?? [] as $st) {
            if (is_array($st) && ($st['estado'] ?? '') === 'pendiente') {
                return true;
            }
        }
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            if (($m['familia_mensajito'] ?? '') === self::FAMILIA
                && BuzonEngine::tieneDecisionPendiente($m)) {
                return true;
            }
        }
        return false;
    }

    private static function marcarAtendida(array &$partida, string $rid, string $via): void
    {
        self::ensure($partida);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida['mensajitos_duda_permanencia'][$rid] = [
            'estado' => 'atendida',
            'atendida_dia' => $dia,
            'via' => $via,
            'escalada' => false,
        ];
    }

    private static function marcarEscalada(array &$partida, string $rid, string $mensajeId): void
    {
        self::ensure($partida);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida['mensajitos_duda_permanencia'][$rid] = [
            'estado' => 'escalada',
            'mensaje_id' => $mensajeId,
            'escalada' => true,
            'escalada_dia' => $dia,
        ];
    }
}
