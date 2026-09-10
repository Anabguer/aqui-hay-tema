<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Única API que puede modificar Vida del Pueblo (0–100).
 * B3/B4/B7/B11 deben llamar aquí. Nada más escribe el entero.
 */
final class VidaPuebloEngine
{
    public const FLAG = 'vida_pueblo_enabled';

    public const CAUSA_MISION_CUMPLIDA = 'mision_cumplida';
    /** Legacy R3 (23/08/2026): la caducada individual ya no mueve Vida en play.
     * Constante viva solo para escrituras sintéticas de lab/tests. */
    public const CAUSA_MISION_FALLIDA = 'mision_fallida';
    public const CAUSA_DIA_MISIONES_IGNORADO = 'dia_misiones_ignorado';
    public const CAUSA_PETICION_CUMPLIDA = 'peticion_cumplida';
    public const CAUSA_PETICION_CADUCADA = 'peticion_caducada';
    public const CAUSA_PETICION_IGNORADA = 'peticion_ignorada';
    public const CAUSA_HITO = 'hito';
    public const CAUSA_ACTIVIDAD_POSITIVA = 'actividad_positiva';
    public const CAUSA_ACTIVIDAD_NEGATIVA = 'actividad_negativa';
    public const CAUSA_LATIDO_RESACA = 'latido_resaca';
    public const CAUSA_OFFLINE = 'offline_acumulado';
    public const CAUSA_LAB = 'lab_sintetico';
    public const CAUSA_LAB_SETUP = 'lab_setup';
    public const CAUSA_ENCUENTRO_JUGADOR = 'encuentro_jugador';
    public const CAUSA_ACONTECIMIENTO = 'acontecimiento_vida';
    public const CAUSA_ESTANCAMIENTO = 'estancamiento_pueblo';

    public const DELTA_MISION_CUMPLIDA = 2;
    /** Legacy R3 (23/08/2026): sin uso en play. El castigo diario vive en
     * calibracion misiones_diarias.vida_dia_ignorado (MisionDiariaEngine::alCerrarDia). */
    public const DELTA_MISION_FALLIDA = -3;

    public const ORIGEN_JUGADOR = 'jugador';
    public const ORIGEN_SISTEMA = 'sistema';
    public const ORIGEN_LAB = 'lab';
    public const ORIGEN_OFFLINE = 'offline';
    public const ORIGEN_NPC_RNG = 'npc_rng';

    public const BANDA_CRITICO = 'critico';
    public const BANDA_ALERTA = 'alerta';
    public const BANDA_TIRANDO = 'tirando';
    public const BANDA_TEMITA = 'temita';
    public const BANDA_CONTROLADO = 'controlado';
    public const BANDA_LATIDO = 'latido';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            '_provisional' => true,
            'inicial' => 65,
            'min' => 0,
            'max' => 100,
            'post_latido' => 75,
            'umbral_positivos_latido' => 25,
            'offline_dano_max' => 15,
            'offline_suelo' => 5,
            'game_over_en_play' => true,
            'ledger_cap' => 400,
            'sobreextension_factor' => 0.45,
            'sobreextension_pesos_necesidades' => 0.50,
            'sobreextension_pesos_emociones' => 0.30,
            'sobreextension_pesos_relaciones' => 0.20,
            'sobreextension_estado_min' => 10,
            'sobreextension_estado_max' => 90,
            'estancamiento' => [
                'activo' => true,
                'umbral_state_heart' => 60,
                'ventana_dias' => 5,
                'umbral_mejora' => 2,
                'grace_dias' => 5,
                'presion_por_dia' => 1,
            ],
            'bandas' => [
                ['id' => self::BANDA_CRITICO, 'min' => 0, 'max' => 19, 'etiqueta' => 'Se nos va de las manos'],
                ['id' => self::BANDA_ALERTA, 'min' => 20, 'max' => 39, 'etiqueta' => 'Aquí pasa algo'],
                ['id' => self::BANDA_TIRANDO, 'min' => 40, 'max' => 59, 'etiqueta' => 'Tirando, que no es poco'],
                ['id' => self::BANDA_TEMITA, 'min' => 60, 'max' => 79, 'etiqueta' => 'Hay temita'],
                ['id' => self::BANDA_CONTROLADO, 'min' => 80, 'max' => 99, 'etiqueta' => 'Todo controlado'],
                ['id' => self::BANDA_LATIDO, 'min' => 100, 'max' => 100, 'etiqueta' => 'Latido'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function cfg(array $cal = []): array
    {
        $d = self::defaults();
        if ($cal === []) {
            return $d;
        }
        $d['inicial'] = (int) CalibracionConfig::get($cal, 'vida_pueblo.inicial', $d['inicial']);
        $d['post_latido'] = (int) CalibracionConfig::get($cal, 'vida_pueblo.post_latido', $d['post_latido']);
        $d['umbral_positivos_latido'] = (int) CalibracionConfig::get($cal, 'vida_pueblo.umbral_positivos_latido', $d['umbral_positivos_latido']);
        $d['offline_dano_max'] = (int) CalibracionConfig::get($cal, 'vida_pueblo.offline_dano_max', $d['offline_dano_max']);
        $d['offline_suelo'] = (int) CalibracionConfig::get($cal, 'vida_pueblo.offline_suelo', $d['offline_suelo']);
        $d['game_over_en_play'] = (bool) CalibracionConfig::get($cal, 'vida_pueblo.game_over_en_play', $d['game_over_en_play']);
        $d['ledger_cap'] = (int) CalibracionConfig::get($cal, 'vida_pueblo.ledger_cap', $d['ledger_cap']);
        $d['sobreextension_factor'] = (float) CalibracionConfig::get($cal, 'vida_pueblo.sobreextension_factor', $d['sobreextension_factor']);
        $d['sobreextension_pesos_necesidades'] = (float) CalibracionConfig::get($cal, 'vida_pueblo.sobreextension_pesos_necesidades', $d['sobreextension_pesos_necesidades']);
        $d['sobreextension_pesos_emociones'] = (float) CalibracionConfig::get($cal, 'vida_pueblo.sobreextension_pesos_emociones', $d['sobreextension_pesos_emociones']);
        $d['sobreextension_pesos_relaciones'] = (float) CalibracionConfig::get($cal, 'vida_pueblo.sobreextension_pesos_relaciones', $d['sobreextension_pesos_relaciones']);
        $d['sobreextension_estado_min'] = (float) CalibracionConfig::get($cal, 'vida_pueblo.sobreextension_estado_min', $d['sobreextension_estado_min']);
        $d['sobreextension_estado_max'] = (float) CalibracionConfig::get($cal, 'vida_pueblo.sobreextension_estado_max', $d['sobreextension_estado_max']);
        $estCfg = CalibracionConfig::get($cal, 'vida_pueblo.estancamiento', null);
        if (is_array($estCfg)) {
            $d['estancamiento']['activo'] = (bool) ($estCfg['activo'] ?? $d['estancamiento']['activo']);
            $d['estancamiento']['umbral_state_heart'] = (float) ($estCfg['umbral_state_heart'] ?? $d['estancamiento']['umbral_state_heart']);
            $d['estancamiento']['ventana_dias'] = (int) ($estCfg['ventana_dias'] ?? $d['estancamiento']['ventana_dias']);
            $d['estancamiento']['umbral_mejora'] = (float) ($estCfg['umbral_mejora'] ?? $d['estancamiento']['umbral_mejora']);
            $d['estancamiento']['grace_dias'] = (int) ($estCfg['grace_dias'] ?? $d['estancamiento']['grace_dias']);
            $d['estancamiento']['presion_por_dia'] = (int) ($estCfg['presion_por_dia'] ?? $d['estancamiento']['presion_por_dia']);
        }
        $bandas = CalibracionConfig::get($cal, 'vida_pueblo.bandas', null);
        if (is_array($bandas) && $bandas !== []) {
            $d['bandas'] = $bandas;
        }
        return $d;
    }

    /**
     * Save aditivo. No pisa un valor ya persistido.
     *
     * @param array<string, mixed> $cal
     */
    public static function ensure(array &$partida, array $cal = []): void
    {
        $cfg = self::cfg($cal);
        $inicial = (int) $cfg['inicial'];
        if (!isset($partida['vida_pueblo']) || !is_array($partida['vida_pueblo'])) {
            $partida['vida_pueblo'] = self::estadoNuevo($inicial, $cfg);
            return;
        }
        $v = &$partida['vida_pueblo'];
        $base = self::estadoNuevo($inicial, $cfg);
        foreach ($base as $k => $val) {
            if (!array_key_exists($k, $v)) {
                $v[$k] = $val;
            }
        }
        if (!isset($v['ledger']) || !is_array($v['ledger'])) {
            $v['ledger'] = [];
        }
        if (!isset($v['ledger_archivo']) || !is_array($v['ledger_archivo'])) {
            $v['ledger_archivo'] = [];
        }
        $v['valor'] = self::clamp((int) ($v['valor'] ?? $inicial), $cfg);
    }

    /**
     * @param array<string, mixed> $cfg
     * @return array<string, mixed>
     */
    private static function estadoNuevo(int $inicial, array $cfg): array
    {
        return [
            'valor' => $inicial,
            'valor_inicial' => $inicial,
            'latidos' => 0,
            'positivos_desde_latido' => 0,
            'positivos_validos_total' => 0,
            'negativos_total' => 0,
            'umbral_positivos_latido' => (int) $cfg['umbral_positivos_latido'],
            'valor_post_latido' => (int) $cfg['post_latido'],
            'primer_latido_dia' => null,
            'ultimo_latido_dia' => null,
            'ultimo_latido_hora' => null,
            'game_over_pendiente' => false,
            'game_over_activo' => false,
            'llego_a_cero' => false,
            'origen_ultimo_cero' => null,
            'dias_en_critico' => 0,
            'offline_dano_ultima_ausencia' => 0,
            'estancamiento' => [
                'historial_sh' => [],
                'dias_bajo_umbral' => 0,
                'activo' => false,
                'dias_activo' => 0,
                'dia_activacion' => null,
                'sh_en_activacion' => null,
                'tendencia_en_activacion' => null,
                'ultimo_sh' => null,
                'notificado_ui' => false,
            ],
            'ledger' => [],
            'ledger_archivo' => [],
            '_provisional' => true,
        ];
    }

    /**
     * @param array<string, mixed> $cfg
     */
    public static function clamp(int $valor, array $cfg): int
    {
        $min = (int) ($cfg['min'] ?? 0);
        $max = (int) ($cfg['max'] ?? 100);
        if ($valor < $min) {
            return $min;
        }
        if ($valor > $max) {
            return $max;
        }
        return $valor;
    }

    public static function valor(array $partida): int
    {
        return (int) ($partida['vida_pueblo']['valor'] ?? self::defaults()['inicial']);
    }

    /**
     * @param array<string, mixed> $cal
     * @return array{id:string,etiqueta:string,min:int,max:int}
     */
    public static function banda(int $valor, array $cal = []): array
    {
        $cfg = self::cfg($cal);
        $valor = self::clamp($valor, $cfg);
        foreach ($cfg['bandas'] as $b) {
            if (!is_array($b)) {
                continue;
            }
            $min = (int) ($b['min'] ?? 0);
            $max = (int) ($b['max'] ?? 0);
            if ($valor >= $min && $valor <= $max) {
                return [
                    'id' => (string) ($b['id'] ?? ''),
                    'etiqueta' => (string) ($b['etiqueta'] ?? ''),
                    'min' => $min,
                    'max' => $max,
                ];
            }
        }
        return ['id' => self::BANDA_TEMITA, 'etiqueta' => 'Hay temita', 'min' => 60, 'max' => 79];
    }

    /**
     * Vista jugable: sin el número exacto.
     *
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function vista(array $partida, array $cal = []): array
    {
        self::ensure($partida, $cal);
        $valor = self::valor($partida);
        $b = self::banda($valor, $cal);
        $pct = $valor;
        if ($pct < 0) {
            $pct = 0;
        }
        if ($pct > 100) {
            $pct = 100;
        }
        $latidoAnim = (bool) ($partida['vida_pueblo']['latido_ui_pendiente'] ?? false);
        if ($latidoAnim) {
            $partida['vida_pueblo']['latido_ui_pendiente'] = false;
        }
        $est = $partida['vida_pueblo']['estancamiento'] ?? null;
        $estVista = null;
        if ($est) {
            $estVista = [
                'activo' => (bool) $est['activo'],
                'dias_bajo_umbral' => (int) $est['dias_bajo_umbral'],
                'dias_activo' => (int) $est['dias_activo'],
                'dia_activacion' => (int) ($est['dia_activacion'] ?? 0),
            ];
            // notificado_ui: solo se envía en la primera respuesta tras activación
            if (!(bool) $est['notificado_ui'] && (bool) $est['activo']) {
                $estVista['notificar'] = true;
                $partida['vida_pueblo']['estancamiento']['notificado_ui'] = true;
            }
        }
        return [
            'banda' => $b['id'],
            'etiqueta' => $b['etiqueta'],
            'corazon_pct' => $pct,
            'latidos' => (int) ($partida['vida_pueblo']['latidos'] ?? 0),
            'latido_anim' => $latidoAnim,
            'critico' => $valor <= 19,
            'game_over_pendiente' => (bool) ($partida['vida_pueblo']['game_over_pendiente'] ?? false),
            'game_over_activo' => self::derrotaVisibleEnPlay($partida, $cal),
            'estancamiento' => $estVista,
        ];
    }

    /**
     * Derrota visible en PLAY: apagada en B1/B2.
     *
     * @param array<string, mixed> $cal
     */
    public static function derrotaVisibleEnPlay(array $partida, array $cal = []): bool
    {
        $cfg = self::cfg($cal);
        if (!$cfg['game_over_en_play']) {
            return false;
        }
        if (!FeatureConfig::isEnabled($partida, self::FLAG)) {
            return false;
        }
        return (bool) ($partida['vida_pueblo']['game_over_activo'] ?? false);
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function partidaPerdida(array $partida, array $cal = []): bool
    {
        if (!FeatureConfig::isEnabled($partida, self::FLAG)) {
            return false;
        }
        $vp = $partida['vida_pueblo'] ?? [];
        if (self::derrotaVisibleEnPlay($partida, $cal)) {
            return true;
        }
        return !empty($vp['game_over_pendiente']) && !empty($vp['llego_a_cero']);
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    public static function rechazoSiPerdida(array $partida, array $cal = []): ?array
    {
        if (!self::partidaPerdida($partida, $cal)) {
            return null;
        }
        return [
            'ok' => false,
            'error' => 'partida_perdida',
            'partida_perdida' => true,
            'mensaje_ui' => 'La partida ha terminado. El pueblo no aguantó más.',
        ];
    }

    /**
     * Único escritor de Vida. Rechaza orígenes no atribuibles a Celestine.
     *
     * @param array<string, mixed> $meta causa, origen, atribuible_celestine, positivo_valido_latido, fuente_id, lab
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function aplicar(
        array &$partida,
        int $delta,
        array $meta,
        array $cal = [],
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida, $cal);
        $cfg = self::cfg($cal);

        $atribuible = (bool) ($meta['atribuible_celestine'] ?? false);
        $origen = (string) ($meta['origen'] ?? '');
        if ($origen === self::ORIGEN_NPC_RNG || $origen === 'autonomia' || $origen === 'emocion_rng') {
            $atribuible = false;
        }
        if (!$atribuible) {
            return [
                'ok' => false,
                'error' => 'no_atribuible_celestine',
                'valor' => self::valor($partida),
                'delta_aplicado' => 0,
            ];
        }

        $causa = (string) ($meta['causa'] ?? self::CAUSA_LAB);
        $positivoValido = (bool) ($meta['positivo_valido_latido'] ?? false);
        if ($delta <= 0) {
            $positivoValido = false;
        }
        $esLab = (bool) ($meta['lab'] ?? ($origen === self::ORIGEN_LAB));
        $esOffline = ($causa === self::CAUSA_OFFLINE || $origen === self::ORIGEN_OFFLINE);

        $v = &$partida['vida_pueblo'];
        $antes = (int) $v['valor'];
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);

        if ($positivoValido && $delta > 0) {
            $v['positivos_desde_latido'] = (int) $v['positivos_desde_latido'] + 1;
            $v['positivos_validos_total'] = (int) $v['positivos_validos_total'] + 1;
        }
        if ($delta < 0 && $causa !== self::CAUSA_LATIDO_RESACA) {
            $v['negativos_total'] = (int) $v['negativos_total'] + (-$delta);
        }

        $umbral = (int) ($v['umbral_positivos_latido'] ?? $cfg['umbral_positivos_latido']);
        $post = (int) ($v['valor_post_latido'] ?? $cfg['post_latido']);
        $despues = self::clamp($antes + $delta, $cfg);
        $latido = false;
        $resacaDe = null;

        if ($despues >= 100 && (int) $v['positivos_desde_latido'] >= $umbral) {
            $latido = true;
            $entradaDelta = self::entradaLedger(
                $partida,
                $delta,
                $antes,
                100,
                $causa,
                $origen,
                true,
                $positivoValido,
                $esLab,
                $meta
            );
            $v['ledger'][] = $entradaDelta;

            $resacaDe = 100;
            $despues = self::clamp($post, $cfg);
            $v['valor'] = $despues;
            $v['latidos'] = (int) $v['latidos'] + 1;
            $v['latido_ui_pendiente'] = true;
            $v['positivos_desde_latido'] = 0;
            $v['ultimo_latido_dia'] = $dia;
            $v['ultimo_latido_hora'] = $hora;
            if ($v['primer_latido_dia'] === null) {
                $v['primer_latido_dia'] = $dia;
            }
            $entradaResaca = self::entradaLedger(
                $partida,
                $despues - 100,
                100,
                $despues,
                self::CAUSA_LATIDO_RESACA,
                self::ORIGEN_SISTEMA,
                true,
                false,
                $esLab,
                ['fuente_id' => $entradaDelta['id'], 'lab' => $esLab]
            );
            $v['ledger'][] = $entradaResaca;
        } else {
            if ($despues >= 100) {
                $despues = 99;
            }
            $v['valor'] = $despues;
            $v['ledger'][] = self::entradaLedger(
                $partida,
                $despues - $antes,
                $antes,
                $despues,
                $causa,
                $origen,
                true,
                $positivoValido,
                $esLab,
                $meta
            );
        }

        if (!$esOffline && $despues === 0 && $antes > 0) {
            $v['game_over_pendiente'] = true;
            $v['llego_a_cero'] = true;
            $v['origen_ultimo_cero'] = $origen;
            if ($cfg['game_over_en_play'] && FeatureConfig::isEnabled($partida, self::FLAG)) {
                $v['game_over_activo'] = true;
            }
        }

        self::recortarLedger($partida, $cfg);

        if (empty($partida['_lab_misiones_b3'])) {
            DomainEventDispatcher::emit($partida, DomainEvents::VIDA_PUEBLO_CAMBIADA, [
                'delta' => $delta,
                'antes' => $antes,
                'despues' => $despues,
                'causa' => $causa,
                'origen' => $origen,
                'latido' => $latido,
                'lab' => $esLab,
                'actores' => [],
            ], $logger, 'VidaPuebloEngine::aplicar');
            if ($latido) {
                DomainEventDispatcher::emit($partida, DomainEvents::VIDA_PUEBLO_LATIDO, [
                    'latidos' => (int) $v['latidos'],
                    'resaca_a' => $despues,
                    'desde' => $resacaDe,
                    'lab' => $esLab,
                    'actores' => [],
                ], $logger, 'VidaPuebloEngine::latido');
            }
        }

        \aht_log_optional($logger, $partida, 'vida_pueblo', [
            'delta' => $delta,
            'despues' => $despues,
            'causa' => $causa,
            'latido' => $latido,
        ]);

        LabAudit::eventoVidaCambio($partida, $antes, $despues, $despues - $antes, $causa, $origen, $meta);

        return [
            'ok' => true,
            'delta_pedido' => $delta,
            'delta_aplicado' => $despues - $antes,
            'valor_antes' => $antes,
            'valor' => $despues,
            'latido' => $latido,
            'game_over_pendiente' => (bool) $v['game_over_pendiente'],
            'game_over_activo' => (bool) $v['game_over_activo'],
            'positivos_desde_latido' => (int) $v['positivos_desde_latido'],
            'latidos' => (int) $v['latidos'],
        ];
    }

    /**
     * Daño de ausencia: cap provisional −15, suelo ≠ 0, nunca GO.
     *
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function aplicarAusencia(
        array &$partida,
        int $deltaPropuesto,
        array $meta = [],
        array $cal = [],
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida, $cal);
        $cfg = self::cfg($cal);
        $maxDano = (int) $cfg['offline_dano_max'];
        $suelo = (int) $cfg['offline_suelo'];
        if ($suelo < 1) {
            $suelo = 1;
        }

        $pedido = $deltaPropuesto;
        if ($pedido > 0) {
            $pedido = 0;
        }
        if ($pedido < -$maxDano) {
            $pedido = -$maxDano;
        }

        $antes = self::valor($partida);
        $minPermitido = $suelo - $antes;
        if ($pedido < $minPermitido) {
            $pedido = $minPermitido;
        }
        $metaOff = array_merge($meta, [
            'causa' => self::CAUSA_OFFLINE,
            'origen' => self::ORIGEN_OFFLINE,
            'atribuible_celestine' => true,
            'positivo_valido_latido' => false,
            'lab' => (bool) ($meta['lab'] ?? false),
        ]);
        if ($pedido === 0) {
            $partida['vida_pueblo']['offline_dano_ultima_ausencia'] = 0;
            $partida['vida_pueblo']['game_over_activo'] = false;
            return [
                'ok' => true,
                'delta_pedido' => 0,
                'delta_aplicado' => 0,
                'delta_propuesto' => $deltaPropuesto,
                'delta_capeado' => 0,
                'valor_antes' => $antes,
                'valor' => $antes,
                'latido' => false,
                'game_over_pendiente' => false,
                'game_over_activo' => false,
                'suelo_aplicado' => $antes <= $suelo,
                'offline' => true,
            ];
        }
        $r = self::aplicar($partida, $pedido, $metaOff, $cal, $logger);

        $v = &$partida['vida_pueblo'];
        $v['offline_dano_ultima_ausencia'] = $pedido;
        $v['game_over_activo'] = false;
        if (self::valor($partida) > 0) {
            $v['game_over_pendiente'] = false;
        }

        $r['valor'] = self::valor($partida);
        $r['delta_propuesto'] = $deltaPropuesto;
        $r['delta_capeado'] = $pedido;
        $r['suelo_aplicado'] = self::valor($partida) === $suelo && ($antes + $pedido) < $suelo;
        $r['game_over_pendiente'] = false;
        $r['game_over_activo'] = false;
        $r['offline'] = true;
        return $r;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private static function entradaLedger(
        array $partida,
        int $delta,
        int $antes,
        int $despues,
        string $causa,
        string $origen,
        bool $atribuible,
        bool $positivoValido,
        bool $lab,
        array $meta
    ): array {
        $n = count($partida['vida_pueblo']['ledger'] ?? []);
        return [
            'id' => 'vp_' . $n . '_' . ((int) ($partida['reloj']['dia_pueblo'] ?? 0)),
            'delta' => $delta,
            'valor_antes' => $antes,
            'valor_despues' => $despues,
            'causa' => $causa,
            'origen' => $origen,
            'atribuible_celestine' => $atribuible,
            'positivo_valido_latido' => $positivoValido,
            'lab' => $lab,
            'fuente_id' => $meta['fuente_id'] ?? null,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            'iso' => $partida['reloj']['ultima_sesion_iso'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function recortarLedger(array &$partida, array $cfg): void
    {
        $cap = (int) ($cfg['ledger_cap'] ?? 400);
        $lista = $partida['vida_pueblo']['ledger'] ?? [];
        if (count($lista) <= $cap) {
            return;
        }
        $caidos = count($lista) - $cap;
        $partida['vida_pueblo']['ledger_archivo'][] = [
            'count' => $caidos,
            'dia' => $partida['reloj']['dia_pueblo'] ?? null,
        ];
        $partida['vida_pueblo']['ledger'] = array_values(array_slice($lista, -$cap));
    }

    public static function deltaResultadoEncuentro(string $resultado): int
    {
        $map = [
            'muy_bien' => 2,
            'bien' => 1,
            'normal' => 0,
            'mal' => -1,
            'muy_mal' => -2,
        ];
        return $map[$resultado] ?? 0;
    }

    /**
     * @param array<string, mixed> $resultado
     */
    public static function resultadoGlobalEncuentro(array $resultado): ?string
    {
        $global = $resultado['resultado'] ?? $resultado['experiencia']['resultado'] ?? null;
        if (is_string($global) && $global !== '') {
            return $global;
        }
        $por = $resultado['por_participante'] ?? [];
        if (!is_array($por) || $por === []) {
            return null;
        }
        $vals = [];
        foreach ($por as $row) {
            if (!is_array($row)) {
                continue;
            }
            $r = (string) ($row['resultado'] ?? '');
            if ($r !== '') {
                $vals[] = self::deltaResultadoEncuentro($r);
            }
        }
        if ($vals === []) {
            return null;
        }
        $avg = (int) round(array_sum($vals) / count($vals));
        foreach (['muy_mal' => -2, 'mal' => -1, 'normal' => 0, 'bien' => 1, 'muy_bien' => 2] as $k => $v) {
            if ($avg === $v) {
                return $k;
            }
        }
        if ($avg > 0) {
            return 'bien';
        }
        if ($avg < 0) {
            return 'mal';
        }
        return 'normal';
    }

    /**
     * Una sola variación por encuentro organizado por Celestine al resolverse.
     *
     * @param array<string, mixed> $encuentro
     * @param array<string, mixed> $resultado
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    public static function aplicarEncuentroOrganizado(
        array &$partida,
        array $encuentro,
        array $resultado,
        array $cal = [],
        ?GameLogger $logger = null
    ): ?array {
        if (!FeatureConfig::isEnabled($partida, self::FLAG)) {
            return null;
        }
        if (!MisionDiariaEngine::esEncuentroCelestine($encuentro)) {
            return null;
        }
        if (!empty($encuentro['vida_pueblo_aplicada'])) {
            return null;
        }
        $res = self::resultadoGlobalEncuentro($resultado);
        if ($res === null) {
            return null;
        }
        $delta = self::deltaResultadoEncuentro($res);
        if ($delta === 0) {
            return ['ok' => true, 'delta_aplicado' => 0, 'resultado' => $res];
        }
        $r = self::aplicar($partida, $delta, [
            'causa' => self::CAUSA_ENCUENTRO_JUGADOR,
            'origen' => self::ORIGEN_JUGADOR,
            'atribuible_celestine' => true,
            'positivo_valido_latido' => false,
            'fuente_id' => $encuentro['id'] ?? null,
            'detalle' => $res,
        ], $cal, $logger);
        return array_merge($r, ['resultado' => $res]);
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $cal
     */
    public static function aplicarAcontecimientoVida(
        array &$partida,
        string $eventoId,
        array $item,
        array $cal = [],
        ?GameLogger $logger = null
    ): ?array {
        if (!FeatureConfig::isEnabled($partida, self::FLAG)) {
            return null;
        }
        $delta = self::deltaAcontecimientoCatalogo($eventoId, $item);
        if ($delta === null || $delta === 0) {
            return null;
        }
        return self::aplicar($partida, $delta, [
            'causa' => self::CAUSA_ACONTECIMIENTO,
            'origen' => self::ORIGEN_SISTEMA,
            'atribuible_celestine' => false,
            'positivo_valido_latido' => false,
            'fuente_id' => $eventoId,
        ], $cal, $logger);
    }

    /**
     * Aplica penalty de sobreextensión al final del día.
     * Si el corazón está por encima de lo que el estado del pueblo justifica,
     * recibe un golpe proporcional. Impide farmear misiones con pueblo deteriorado.
     *
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function aplicarSobreextension(
        array &$partida,
        array $cal = [],
        ?GameLogger $logger = null
    ): array {
        if (!FeatureConfig::isEnabled($partida, self::FLAG)) {
            return ['ok' => false, 'error' => 'feature_disabled', 'delta_aplicado' => 0];
        }

        $cfg = self::cfg($cal);
        $factor = (float) $cfg['sobreextension_factor'];
        if ($factor <= 0.0) {
            return ['ok' => false, 'error' => 'factor_cero', 'delta_aplicado' => 0];
        }

        $heart = self::valor($partida);
        $estado = self::calcularEstadoPueblo($partida, $cal);

        // Sin residentes activos no hay estado que medir — no se aplica penalty
        $residentes = $partida['residentes'] ?? [];
        $activos = array_filter($residentes, function ($r) {
            return ($r['presencia'] ?? '') === 'residente';
        });
        if ($activos === []) {
            return ['ok' => true, 'delta_aplicado' => 0, 'heart' => $heart, 'state_heart' => 0.0, 'overextension' => 0.0, 'skip' => 'sin_residentes'];
        }

        $stateHeart = self::stateHeart($estado, $cfg);

        $overextension = max(0.0, (float) $heart - $stateHeart);
        $penalty = (int) round(-$overextension * $factor);

        if ($penalty === 0) {
            return ['ok' => true, 'delta_aplicado' => 0, 'heart' => $heart, 'state_heart' => $stateHeart, 'overextension' => $overextension];
        }

        $r = self::aplicar($partida, $penalty, [
            'causa' => 'sobreextension_estado',
            'origen' => self::ORIGEN_SISTEMA,
            'atribuible_celestine' => true,
            'positivo_valido_latido' => false,
        ], $cal, $logger);

        return array_merge($r, [
            'heart_antes' => $heart,
            'state_heart' => $stateHeart,
            'overextension' => $overextension,
            'factor' => $factor,
        ]);
    }

    /**
     * Detecta y aplica presión por estancamiento del pueblo.
     *
     * Condición: stateHeart ≤ umbral durante ventana_dias consecutivos,
     *            sin mejora ≥ umbral_mejora comparado contra hace ventana_dias.
     * Si se cumple tras grace_dias, aplica -presion_por_dia al heart.
     * La presión se desactiva cuando stateHeart mejora ≥ umbral_mejora.
     *
     * Fórmula de tendencia:
     *   tendencia = stateHeart_hoy - historial_sh[count - ventana_dias]
     *   mejora = tendencia ≥ umbral_mejora
     *
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function aplicarEstancamiento(
        array &$partida,
        array $cal = [],
        ?GameLogger $logger = null
    ): array {
        if (!FeatureConfig::isEnabled($partida, self::FLAG)) {
            return ['ok' => false, 'error' => 'feature_disabled', 'delta_aplicado' => 0];
        }

        $cfg = self::cfg($cal);
        $estCfg = $cfg['estancamiento'];
        if (!$estCfg['activo']) {
            return ['ok' => true, 'activo' => false, 'delta_aplicado' => 0, 'skip' => 'inactivo'];
        }

        $heart = self::valor($partida);
        $v = &$partida['vida_pueblo'];
        $est = &$v['estancamiento'];

        // Calcular stateHeart actual
        $estado = self::calcularEstadoPueblo($partida, $cal);
        $stateHeart = self::stateHeart($estado, $cfg);

        // Mantener historial (últimos 10 días, suficiente para ventana de 5)
        $est['historial_sh'][] = $stateHeart;
        if (count($est['historial_sh']) > 10) {
            array_shift($est['historial_sh']);
        }

        $ventana = $estCfg['ventana_dias'];
        $umbralSH = $estCfg['umbral_state_heart'];
        $umbralMejora = $estCfg['umbral_mejora'];
        $graceDias = $estCfg['grace_dias'];
        $presionDia = $estCfg['presion_por_dia'];

        // Condición: stateHeart bajo umbral
        $bajoUmbral = $stateHeart <= $umbralSH;

        // Detectar tendencia contra hace ventana_dias (siempre que haya historial suficiente)
        $mejora = false;
        $tendencia = 0.0;
        if (count($est['historial_sh']) > $ventana) {
            $shAntiguo = $est['historial_sh'][count($est['historial_sh']) - 1 - $ventana];
            $tendencia = $stateHeart - $shAntiguo;
            $mejora = $tendencia >= $umbralMejora;
        }

        // Actualizar contador de días bajo umbral
        if ($bajoUmbral && !$mejora) {
            $est['dias_bajo_umbral'] = (int) $est['dias_bajo_umbral'] + 1;
        } else {
            $est['dias_bajo_umbral'] = 0;
        }

        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $deltaEstancamiento = 0;
        $motivo = null;

        // Si ya estaba activo, verificar si se desactiva
        if ((bool) $est['activo']) {
            if ($mejora) {
                // Se desactiva: pueblo mejoró
                $est['activo'] = false;
                $est['dias_activo'] = 0;
                $motivo = 'desactivado_mejora';
            } else {
                // Sigue activo: aplicar presión
                $deltaEstancamiento = -$presionDia;
                $est['dias_activo'] = (int) $est['dias_activo'] + 1;
                $motivo = 'presion_activa';
            }
        } else {
            // No está activo: ¿debería activarse?
            if ($bajoUmbral && !$mejora && $est['dias_bajo_umbral'] > $graceDias) {
                $est['activo'] = true;
                $est['dia_activacion'] = $dia;
                $est['sh_en_activacion'] = $stateHeart;
                $est['tendencia_en_activacion'] = $tendencia;
                $est['dias_activo'] = 1;
                $est['notificado_ui'] = false;
                $deltaEstancamiento = -$presionDia;
                $motivo = 'activacion';
            }
        }

        $est['ultimo_sh'] = $stateHeart;
        $antes = $heart;

        // Aplicar presión si hay delta
        $r = null;
        if ($deltaEstancamiento !== 0) {
            $r = self::aplicar($partida, $deltaEstancamiento, [
                'causa' => self::CAUSA_ESTANCAMIENTO,
                'origen' => self::ORIGEN_SISTEMA,
                'atribuible_celestine' => true,
                'positivo_valido_latido' => false,
            ], $cal, $logger);
        }

        \aht_log_optional($logger, $partida, 'estancamiento', [
            'dia' => $dia,
            'state_heart' => round($stateHeart, 1),
            'tendencia' => round($tendencia, 1),
            'dias_bajo_umbral' => (int) $est['dias_bajo_umbral'],
            'activo' => (bool) $est['activo'],
            'dias_activo' => (int) $est['dias_activo'],
            'delta' => $deltaEstancamiento,
            'motivo' => $motivo,
        ]);

        return [
            'ok' => true,
            'activo' => (bool) $est['activo'],
            'delta_aplicado' => $deltaEstancamiento,
            'state_heart' => round($stateHeart, 1),
            'tendencia' => round($tendencia, 1),
            'dias_bajo_umbral' => (int) $est['dias_bajo_umbral'],
            'dias_activo' => (int) $est['dias_activo'],
            'motivo' => $motivo,
            'heart' => self::valor($partida),
        ];
    }

    /**
     * Calcula el estado del pueblo (-1 a +1) a partir de necesidades, emociones y relaciones.
     *
     * @return array{necesidades: float, emociones: float, relaciones: float, score: float}
     */
    public static function calcularEstadoPueblo(array $partida, array $cal = []): array
    {
        $cfg = self::cfg($cal);
        $residentes = $partida['residentes'] ?? [];
        $activos = array_filter($residentes, function ($r) {
            return ($r['presencia'] ?? '') === 'residente';
        });

        if ($activos === []) {
            return ['necesidades' => 0.0, 'emociones' => 0.0, 'relaciones' => 0.0, 'score' => 0.0];
        }

        $pesoN = (float) $cfg['sobreextension_pesos_necesidades'];
        $pesoE = (float) $cfg['sobreextension_pesos_emociones'];
        $pesoR = (float) $cfg['sobreextension_pesos_relaciones'];

        $sumNecesidades = 0.0;
        $sumEmociones = 0.0;
        $sumRelaciones = 0.0;
        $count = 0;

        foreach ($activos as $id => $residente) {
            $sumNecesidades += self::scoreNecesidadesResidente($residente);
            $sumEmociones += self::scoreEmocionesResidente($residente, $partida);
            $sumRelaciones += self::scoreRelacionesResidente($partida, $id, $activos);
            $count++;
        }

        if ($count === 0) {
            return ['necesidades' => 0.0, 'emociones' => 0.0, 'relaciones' => 0.0, 'score' => 0.0];
        }

        $necesidades = $sumNecesidades / $count;
        $emociones = $sumEmociones / $count;
        $relaciones = $sumRelaciones / $count;

        $score = $necesidades * $pesoN + $emociones * $pesoE + $relaciones * $pesoR;

        return [
            'necesidades' => $necesidades,
            'emociones' => $emociones,
            'relaciones' => $relaciones,
            'score' => $score,
        ];
    }

    /**
     * Score de necesidades de un residente (-1 a +1).
     * 0-24 = en_rojo (-1), 25-49 = lo_necesita (-0.5), 50-74 = le_vendria_bien (+0.3), 75-100 = bien (+1)
     */
    private static function scoreNecesidadesResidente(array $residente): float
    {
        $necesidades = NecesidadEstado::obtener($residente);
        if ($necesidades === []) {
            return 0.0;
        }
        $suma = 0.0;
        $n = 0;
        foreach ($necesidades as $nec) {
            if (!is_array($nec) || !isset($nec['valor'])) {
                continue;
            }
            $v = (int) $nec['valor'];
            if ($v >= 75) {
                $suma += 1.0;
            } elseif ($v >= 50) {
                $suma += 0.3;
            } elseif ($v >= 25) {
                $suma += -0.5;
            } else {
                $suma += -1.0;
            }
            $n++;
        }
        return $n > 0 ? $suma / $n : 0.0;
    }

    /**
     * Score emocional de un residente (-1 a +1).
     * alegre=+1, neutro=+0.2, triste=-0.6, enfadado=-1
     */
    private static function scoreEmocionesResidente(array $residente, array $partida): float
    {
        $emocion = $residente['runtime']['estado_emocional']['id'] ?? 'neutro';
        $map = [
            'alegre' => 1.0,
            'neutro' => 0.2,
            'triste' => -0.6,
            'enfadado' => -1.0,
        ];
        return $map[$emocion] ?? 0.2;
    }

    /**
     * Score promedio de relaciones sociales de un residente con los demás (-1 a +1).
     * social en -100..+100 → normalizado a -1..+1
     */
    private static function scoreRelacionesResidente(array $partida, string $residenteId, array $activos): float
    {
        $otros = [];
        foreach ($activos as $id => $_) {
            if ($id !== $residenteId) {
                $otros[] = $id;
            }
        }
        if ($otros === []) {
            return 0.0;
        }
        $suma = 0.0;
        foreach ($otros as $otroId) {
            $social = RelacionEngine::valorSocialHacia($partida, $residenteId, $otroId);
            $suma += $social / 100.0;
        }
        return $suma / count($otros);
    }

    /**
     * Calcula el "corazón que el estado justifica" (stateHeart).
     * Mapea score (-1 a +1) a un rango de corazones.
     */
    public static function stateHeart(array $estado, array $cfg): float
    {
        $min = (float) ($cfg['sobreextension_estado_min'] ?? 10.0);
        $max = (float) ($cfg['sobreextension_estado_max'] ?? 90.0);
        $mid = ($min + $max) / 2.0;
        $range = ($max - $min) / 2.0;
        return $mid + $estado['score'] * $range;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function deltaAcontecimientoCatalogo(string $eventoId, array $item): ?int
    {
        $map = [
            'perder_trabajo' => -1,
            'encontrar_trabajo' => 1,
            'crisis_pareja' => -2,
            'ruptura' => -2,
            'reconciliacion' => 2,
        ];
        if (isset($map[$eventoId])) {
            return $map[$eventoId];
        }
        $imp = (string) ($item['importancia'] ?? '');
        $vis = (string) ($item['visibilidad_jugador'] ?? 'ninguna');
        if ($imp === 'hito' && ($vis === 'importante' || $vis === 'aviso')) {
            return null;
        }
        return null;
    }
}
