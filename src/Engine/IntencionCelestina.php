<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Catálogo canónico de INTENCIONES jugables de relación (PIEZA 1).
 *
 * Las 5 intenciones son el contrato visible para Celestine/Jugador:
 *   - Romper el hielo (presentar)
 *   - Pasar el rato    (quedar + flag)
 *   - Hacer piña       (quedar + flag)
 *   - Ver si hay chispa (quedar + flag)
 *   - Hacer las paces  (quedar + flag)
 *
 * NO introduce tipos de Encuentro nuevos. Reutiliza:
 *   - PropuestaNivel::PRESENTAR y PropuestaNivel::QUEDAR como `tipo_encuentro`
 *   - RelacionEngine, RelacionBandas, SenalRomantica, RomanceElegibilidad,
 *     ParejaEngine, RelacionBitacora, RechazoMemoria, PropuestaCooldown
 *     para resolver el estado del par y la disponibilidad de cada intención.
 *
 * El resultado ≠ la intención (canon): la matriz sólo expone qué
 * intenciones pueden intentarse. NO crea amistad, romance, pareja ni
 * reconciliación garantizados.
 *
 * Estado de una intención:
 *   - visible:   apta para proponer ahora mismo
 *   - bloqueada: existe el contexto pero la intención no es viable ahora
 *                (saturación, cooldown o tensión demasiado alta)
 *   - oculta:    no aplica al estado del par (no se muestra al jugador)
 *
 * Las cifras concretas (umbrales de saturación, intensidad máxima de
 * conflicto para "Hacer las paces") son configurables vía
 * CalibracionConfig::get($cal, 'intencion_celestina.*', default).
 */
final class IntencionCelestina
{
    public const PRESENTAR = 'presentar';
    public const PASAR_RATO = 'pasar_rato';
    public const HACER_PINA = 'hacer_pina';
    public const VER_CHISPA = 'ver_chispa';
    public const HACER_PACES = 'hacer_paces';

    public const ESTADO_VISIBLE = 'visible';
    public const ESTADO_BLOQUEADA = 'bloqueada';
    public const ESTADO_OCULTA = 'oculta';

    /**
     * Orden canónico de presentación cuando varias intenciones son visibles.
     * De más específica (conflicto) a más general (presentar).
     */
    public const ORDEN_VISUAL = [
        self::HACER_PACES,
        self::VER_CHISPA,
        self::HACER_PINA,
        self::PASAR_RATO,
        self::PRESENTAR,
    ];

    /**
     * Mapeo intención → tipo_encuentro canónico existente.
     * Reutiliza `presentar` y `quedar`; no amplía EncuentroEngine::TIPOS.
     */
    private const TIPO_ENCUENTRO = [
        self::PRESENTAR => PropuestaNivel::PRESENTAR,
        self::PASAR_RATO => PropuestaNivel::QUEDAR,
        self::HACER_PINA => PropuestaNivel::QUEDAR,
        self::VER_CHISPA => PropuestaNivel::QUEDAR,
        self::HACER_PACES => PropuestaNivel::QUEDAR,
    ];

    private const LABELS = [
        self::PRESENTAR => 'Romper el hielo',
        self::PASAR_RATO => 'Pasar el rato',
        self::HACER_PINA => 'Hacer piña',
        self::VER_CHISPA => 'Ver si hay chispa',
        self::HACER_PACES => 'Hacer las paces',
    ];

    private const EMOJIS = [
        self::PRESENTAR => '🤝',
        self::PASAR_RATO => '☕',
        self::HACER_PINA => '💛',
        self::VER_CHISPA => '💘',
        self::HACER_PACES => '🕊️',
    ];

    public static function todas(): array
    {
        return self::ORDEN_VISUAL;
    }

    public static function label(string $id): string
    {
        return self::LABELS[$id] ?? (string) $id;
    }

    public static function emoji(string $id): string
    {
        return self::EMOJIS[$id] ?? '';
    }

    public static function tipoEncuentro(string $id): string
    {
        return self::TIPO_ENCUENTRO[$id] ?? PropuestaNivel::QUEDAR;
    }

    /**
     * Catálogo estático: identificadores internos + etiquetas visibles + emoji + tipo_encuentro.
     * Pensado para PIEZA 2 (UI Organizar) sin tener que duplicar la matriz.
     *
     * @return list<array{id: string, label: string, emoji: string, tipo_encuentro: string, orden: int}>
     */
    public static function map(): array
    {
        $out = [];
        foreach (self::ORDEN_VISUAL as $idx => $id) {
            $out[] = [
                'id' => $id,
                'label' => self::label($id),
                'emoji' => self::emoji($id),
                'tipo_encuentro' => self::tipoEncuentro($id),
                'orden' => $idx,
            ];
        }
        return $out;
    }

    /**
     * Contrato JSON-ready para PIEZA 2 (UI Organizar).
     * Define el shape de la respuesta de `disponiblesPara()` para que
     * frontend y backend compartan schema sin re-hardcodear la matriz.
     *
     * @return array<string, mixed>
     */
    public static function contratoOrganizar(): array
    {
        return [
            'version' => 'p1',
            'intenciones' => self::map(),
            'estados' => [
                self::ESTADO_VISIBLE,
                self::ESTADO_BLOQUEADA,
                self::ESTADO_OCULTA,
            ],
            'motivos_bloqueo' => [
                'saturacion' => 'Antes quizá podías volver a intentarlo pronto. Ahora está saturado. Déjale espacio.',
                'cooldown' => 'Le propusiste algo parecido hace poco, mejor en otro momento.',
                'tension_alta' => 'La tensión es demasiado alta ahora mismo. Mejor en otro momento.',
                'incompatibilidad_romantica' => 'No es posible una intención romántica en este contexto.',
            ],
        ];
    }

    /**
     * Matriz semántica de disponibilidad para el par (a, b).
     *
     * Devuelve TODAS las intenciones con su estado y motivo si está bloqueada.
     * El orden de salida sigue `ORDEN_VISUAL`. El consumidor decide
     * qué enseñar y cómo (botón visible, gris con tooltip, oculto).
     *
     * Reglas (semánticas, NO fórmulas):
     *
     *   PRESENTAR    → visible sólo si NO se conocen.
     *
     *   HACER_PACES  → visible sólo si hay conflicto/registro/crisis/ex.
     *                  bloqueada si el conflicto.intensidad supera umbral
     *                  o si hay saturación por rechazos repetidos.
     *
     *   VER_CHISPA   → visible si se conocen, sin conflicto activo,
     *                  estado_pareja ∈ {ninguna, ex} y el par es
     *                  románticamente elegible (parentesco/edad no vetan).
     *                  NO requiere señal romántica previa.
     *
     *   HACER_PINA   → visible si se conocen, sin conflicto activo,
     *                  estado_pareja ∈ {ninguna, ex},
     *                  y la amistad mutua NO ha llegado ya a `mejor_amigo`.
     *                  Mantenimiento: sigue disponible en buen_amigo.
     *
     *   PASAR_RATO   → visible si se conocen, sin conflicto activo,
     *                  y estado_pareja !== crisis.
     *                  Para pareja estable y ex sin conflicto reciente.
     *
     *   Conflicto activo (intensidad ≥ 1 OR discusión fuerte reciente
     *   OR estado_pareja === crisis) → bloquea PR/HP/VC, deja sólo HPZ.
     *
     *   Enemigo (banda social `enemigo` en cualquier dirección)
     *   sin conflicto activo → bloquea romance/socialización directa
     *   (VC, HP, PR). Sólo HPZ sería admisible si hubiera conflicto.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return list<array{id: string, label: string, emoji: string, tipo_encuentro: string, estado: string, motivo_bloqueo: ?string}>
     */
    public static function disponiblesPara(array &$partida, string $a, string $b, array $cal = []): array
    {
        if ($a === '' || $b === '' || $a === $b) {
            return [];
        }

        $estado = self::estadoPareja($partida, $a, $b);
        $ctx = self::contexto($partida, $a, $b, $cal);

        $out = [];
        foreach (self::ORDEN_VISUAL as $id) {
            $res = self::resolverIntencion($id, $ctx, $estado, $cal);
            $out[] = [
                'id' => $id,
                'label' => self::label($id),
                'emoji' => self::emoji($id),
                'tipo_encuentro' => self::tipoEncuentro($id),
                'estado' => $res['estado'],
                'motivo_bloqueo' => $res['motivo_bloqueo'],
            ];
        }
        return $out;
    }

    /**
     * Helpers públicos para tests / consumidores que necesiten lógica puntual.
     * Mantienen contrato: el estado del par es la única fuente.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function esMejorAmigoMutuo(array $partida, string $a, string $b, array $cal = []): bool
    {
        if (!RelacionEngine::seConocen($partida, $a, $b)) {
            return false;
        }
        $vab = RelacionEngine::valorSocialHacia($partida, $a, $b);
        $vba = RelacionEngine::valorSocialHacia($partida, $b, $a);
        if ($vab < 82 || $vba < 82) {
            return false;
        }
        $bandaAb = self::leerBandaSocial($partida, $a, $b);
        $bandaBa = self::leerBandaSocial($partida, $b, $a);
        return $bandaAb === 'mejor_amigo' && $bandaBa === 'mejor_amigo';
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function hayEnemistadDirecta(array $partida, string $a, string $b): bool
    {
        if (!RelacionEngine::seConocen($partida, $a, $b)) {
            return false;
        }
        $ba = self::leerBandaSocial($partida, $a, $b);
        $bb = self::leerBandaSocial($partida, $b, $a);
        return str_starts_with($ba, 'enemigo') || str_starts_with($bb, 'enemigo');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Internos
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function contexto(array $partida, string $a, string $b, array $cal): array
    {
        $conocidos = RelacionEngine::seConocen($partida, $a, $b);
        $conflicto = RelacionEngine::obtenerEntre($partida, $a, $b)['conflicto'] ?? null;
        $conflictoIntensidad = is_array($conflicto) ? (int) ($conflicto['intensidad'] ?? 0) : 0;

        $discusionReciente = self::discusionFuerteReciente($partida, $a, $b);

        $romanceElegible = self::romanceElegible($partida, $a, $b, $cal);
        $senalAb = self::senalRomantica($partida, $a, $b, $cal);
        $senalBa = self::senalRomantica($partida, $b, $a, $cal);

        $rechazosAb = RechazoMemoria::countHacia($partida, $a, $b);
        $rechazosBa = RechazoMemoria::countHacia($partida, $b, $a);
        $cooldownQuedarAb = PropuestaCooldown::activo($partida, $a, $b, PropuestaNivel::QUEDAR, $cal);
        $cooldownQuedarBa = PropuestaCooldown::activo($partida, $b, $a, PropuestaNivel::QUEDAR, $cal);

        $mejorAmigoMutuo = self::esMejorAmigoMutuo($partida, $a, $b, $cal);
        $hayEnemistad = self::hayEnemistadDirecta($partida, $a, $b);

        return [
            'conocidos' => $conocidos,
            'conflicto_intensidad' => $conflictoIntensidad,
            'discusion_reciente' => $discusionReciente,
            'conflicto_activo' => $conflictoIntensidad >= 1 || $discusionReciente,
            'romance_elegible' => $romanceElegible,
            'senal_ab' => $senalAb,
            'senal_ba' => $senalBa,
            'rechazos_ab' => $rechazosAb,
            'rechazos_ba' => $rechazosBa,
            'cooldown_quedar_ab' => $cooldownQuedarAb,
            'cooldown_quedar_ba' => $cooldownQuedarBa,
            'mejor_amigo_mutuo' => $mejorAmigoMutuo,
            'hay_enemistad' => $hayEnemistad,
        ];
    }

    /**
     * @param array<string, mixed> $ctx
     * @param array<string, mixed> $cal
     * @return array{estado: string, motivo_bloqueo: ?string}
     */
    private static function resolverIntencion(string $id, array $ctx, string $estadoPareja, array $cal): array
    {
        switch ($id) {
            case self::PRESENTAR:
                if (!$ctx['conocidos']) {
                    return self::resultadoVisible();
                }
                return self::resultadoOculta();

            case self::HACER_PACES:
                if (!self::conflictoOReparacionAplicable($ctx, $estadoPareja, $estadoPareja)) {
                    return self::resultadoOculta();
                }
                $umbralTension = (int) CalibracionConfig::get($cal, 'intencion_celestina.conflicto_max_para_paces', 70);
                if ($ctx['conflicto_intensidad'] >= $umbralTension) {
                    return self::resultadoBloqueada('tension_alta');
                }
                $umbralSaturacion = (int) CalibracionConfig::get($cal, 'intencion_celestina.saturacion_rechazos', 3);
                if ($ctx['rechazos_ab'] >= $umbralSaturacion || $ctx['rechazos_ba'] >= $umbralSaturacion) {
                    return self::resultadoBloqueada('saturacion');
                }
                return self::resultadoVisible();

            case self::VER_CHISPA:
                if (!$ctx['conocidos']) {
                    return self::resultadoOculta();
                }
                if ($ctx['conflicto_activo']) {
                    return self::resultadoOculta();
                }
                if ($estadoPareja !== ParejaEngine::NINGUNA && $estadoPareja !== ParejaEngine::EX) {
                    return self::resultadoOculta();
                }
                if (!$ctx['romance_elegible']) {
                    return self::resultadoBloqueada('incompatibilidad_romantica');
                }
                if ($ctx['hay_enemistad']) {
                    return self::resultadoOculta();
                }
                if ($ctx['cooldown_quedar_ab'] || $ctx['cooldown_quedar_ba']) {
                    return self::resultadoBloqueada('cooldown');
                }
                return self::resultadoVisible();

            case self::HACER_PINA:
                if (!$ctx['conocidos']) {
                    return self::resultadoOculta();
                }
                if ($ctx['conflicto_activo']) {
                    return self::resultadoOculta();
                }
                if ($estadoPareja === ParejaEngine::PAREJA || $estadoPareja === ParejaEngine::CRISIS) {
                    return self::resultadoOculta();
                }
                if ($ctx['mejor_amigo_mutuo']) {
                    return self::resultadoOculta();
                }
                if ($ctx['hay_enemistad']) {
                    return self::resultadoOculta();
                }
                if ($ctx['cooldown_quedar_ab'] || $ctx['cooldown_quedar_ba']) {
                    return self::resultadoBloqueada('cooldown');
                }
                return self::resultadoVisible();

            case self::PASAR_RATO:
                if (!$ctx['conocidos']) {
                    return self::resultadoOculta();
                }
                if ($ctx['conflicto_activo']) {
                    return self::resultadoOculta();
                }
                if ($estadoPareja === ParejaEngine::CRISIS) {
                    return self::resultadoOculta();
                }
                if ($ctx['hay_enemistad']) {
                    return self::resultadoOculta();
                }
                if ($ctx['cooldown_quedar_ab'] || $ctx['cooldown_quedar_ba']) {
                    return self::resultadoBloqueada('cooldown');
                }
                return self::resultadoVisible();

            default:
                return self::resultadoOculta();
        }
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private static function conflictoOReparacionAplicable(array $ctx, string $estadoPareja, string $_unused): bool
    {
        if ($ctx['conflicto_intensidad'] >= 1) {
            return true;
        }
        if ($ctx['discusion_reciente']) {
            return true;
        }
        if ($estadoPareja === ParejaEngine::CRISIS) {
            return true;
        }
        if ($estadoPareja === ParejaEngine::EX) {
            return true;
        }
        return false;
    }

    /**
     * @return array{estado: string, motivo_bloqueo: ?string}
     */
    private static function resultadoVisible(): array
    {
        return ['estado' => self::ESTADO_VISIBLE, 'motivo_bloqueo' => null];
    }

    /**
     * @return array{estado: string, motivo_bloqueo: ?string}
     */
    private static function resultadoOculta(): array
    {
        return ['estado' => self::ESTADO_OCULTA, 'motivo_bloqueo' => null];
    }

    /**
     * @return array{estado: string, motivo_bloqueo: ?string}
     */
    private static function resultadoBloqueada(string $motivo): array
    {
        return ['estado' => self::ESTADO_BLOQUEADA, 'motivo_bloqueo' => $motivo];
    }

    /**
     * @param array<string, mixed> $partida
     */
    private static function estadoPareja(array $partida, string $a, string $b): string
    {
        return ParejaEngine::estado($partida, $a, $b);
    }

    /**
     * Lee la banda social almacenada en la dirección (desde → hacia).
     * Reutiliza RelacionEngine::socialHacia que ya resuelve la banda
     * desde el registro persistido sin recalcular.
     *
     * @param array<string, mixed> $partida
     */
    private static function leerBandaSocial(array $partida, string $desde, string $hacia): string
    {
        $dir = RelacionEngine::socialHacia($partida, $desde, $hacia);
        if ($dir === null) {
            return 'desconocido';
        }
        return (string) ($dir['banda'] ?? 'desconocido');
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     */
    private static function senalRomantica(array $partida, string $desde, string $hacia, array $cal): bool
    {
        $s = SenalRomantica::desdeHacia($partida, $desde, $hacia, $cal);
        return !empty($s['ok']);
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     */
    private static function romanceElegible(array $partida, string $a, string $b, array $cal): bool
    {
        $r = RomanceElegibilidad::par($partida, $a, $b, $cal);
        return !empty($r['ok']);
    }

    /**
     * Discusión fuerte reciente: ventana calibrable (default 14 días).
     *
     * @param array<string, mixed> $partida
     */
    private static function discusionFuerteReciente(array $partida, string $a, string $b): bool
    {
        $hitos = RelacionBitacora::entre($partida, $a, $b, RelacionBitacora::DISCUSION_FUERTE);
        if ($hitos === []) {
            return false;
        }
        $cal = CalibracionConfig::load(dirname(__DIR__, 2));
        $ventana = (int) CalibracionConfig::get($cal, 'intencion_celestina.discusion_fuerte_ventana_dias', 14);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 0);
        foreach ($hitos as $h) {
            $f = is_array($h['fecha'] ?? null) ? $h['fecha'] : [];
            $hd = (int) ($f['dia'] ?? 0);
            if ($hd > 0 && ($dia - $hd) <= $ventana) {
                return true;
            }
        }
        return false;
    }
}
