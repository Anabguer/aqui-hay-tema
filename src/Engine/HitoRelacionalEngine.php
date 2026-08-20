<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Hitos relacionales/románticos con probabilidad condicionada.
 * Nunca: romance>=X ⇒ pareja. Crisis/ruptura: hito + tirada (nunca umbral solo).
 */
final class HitoRelacionalEngine
{
    private const GRANDES = [
        RelacionBitacora::CONFESION,
        RelacionBitacora::BESO,
        RelacionBitacora::INICIO_PAREJA,
        RelacionBitacora::CRISIS,
        RelacionBitacora::RUPTURA,
        RelacionBitacora::RECONCILIACION,
        RelacionBitacora::INFIDELIDAD,
    ];

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function alCerrarDia(array &$partida, array $cal, RngService $rng): array
    {
        if (!(bool) CalibracionConfig::get($cal, 'hitos_relacionales.activo', false)) {
            return ['ok' => true, 'activo' => false, 'hitos' => []];
        }
        RelacionBitacora::ensure($partida);
        $ocurridos = [];
        $paresDia = [];
        $intentosRes = [];
        $maxGrandes = (int) CalibracionConfig::get($cal, 'hitos_relacionales.max_hitos_grandes_por_par_dia', 1);
        $maxRes = (int) CalibracionConfig::get($cal, 'hitos_relacionales.max_intentos_por_residente_dia', 2);
        $maxPares = (int) CalibracionConfig::get($cal, 'hitos_relacionales.max_pares_evaluados_por_dia', 24);

        self::crushTerceros($partida, $cal, $rng, $ocurridos);

        $pares = self::paresCandidatos($partida);
        self::shuffleInPlace($pares, $rng);
        if ($maxPares > 0 && count($pares) > $maxPares) {
            $pares = array_slice($pares, 0, $maxPares);
        }
        foreach ($pares as $par) {
            $a = $par[0];
            $b = $par[1];
            $key = $a < $b ? $a . '|' . $b : $b . '|' . $a;
            if (ParentescoVeto::bloqueaRomance($partida, $a, $b, $cal)) {
                continue;
            }
            $elig = RomanceElegibilidad::par($partida, $a, $b, $cal);
            if (empty($elig['ok'])) {
                continue;
            }
            $snap = HitoRelacionalContexto::snapshotPar($partida, $a, $b, $cal);
            foreach (self::ordenHitos($snap) as $tipo) {
                if (($paresDia[$key] ?? 0) >= $maxGrandes && in_array($tipo, self::GRANDES, true)) {
                    continue;
                }
                if (($intentosRes[$a] ?? 0) >= $maxRes || ($intentosRes[$b] ?? 0) >= $maxRes) {
                    continue;
                }
                $res = self::intentar($partida, $a, $b, $tipo, $cal, $rng, $snap);
                if ($res === null) {
                    continue;
                }
                $ocurridos[] = $res;
                $intentosRes[$a] = ($intentosRes[$a] ?? 0) + 1;
                $intentosRes[$b] = ($intentosRes[$b] ?? 0) + 1;
                if (in_array($tipo, self::GRANDES, true)) {
                    $paresDia[$key] = ($paresDia[$key] ?? 0) + 1;
                }
                break;
            }
        }

        return ['ok' => true, 'activo' => true, 'hitos' => $ocurridos, 'n' => count($ocurridos)];
    }

    /**
     * Escenarios de laboratorio (no play).
     *
     * @param array<string, mixed> $cal
     * @param array<string, mixed> $setup
     * @return array<string, mixed>
     */
    public static function escenarioDirigido(array &$partida, string $escenario, array $setup, array $cal, RngService $rng): array
    {
        $a = (string) ($setup['a'] ?? '');
        $b = (string) ($setup['b'] ?? '');
        $c = (string) ($setup['c'] ?? '');
        RelacionEngine::registrarContacto($partida, $a, $b, ContactoCalidad::SIGNIFICATIVO, $cal);

        switch ($escenario) {
            case 'amistad_sin_romance':
                RelacionEngine::ajustarSocialHacia($partida, $a, $b, 55, $cal);
                RelacionEngine::ajustarSocialHacia($partida, $b, $a, 55, $cal);
                RelacionEngine::setRomanceHacia($partida, $a, $b, 0);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 0);
                break;
            case 'romance_unilateral':
                RelacionEngine::setRomanceHacia($partida, $a, $b, 70);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 8);
                break;
            case 'romance_mutuo_alto':
                RelacionEngine::setRomanceHacia($partida, $a, $b, 75);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 72);
                RelacionEngine::ajustarSocialHacia($partida, $a, $b, 35, $cal);
                break;
            case 'beso_sin_pareja':
                RelacionEngine::setRomanceHacia($partida, $a, $b, 55);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 50);
                RelacionBitacora::registrar($partida, RelacionBitacora::COQUETEO, [$a, $b], $a . '>' . $b, 'ok');
                self::aplicarBeso($partida, $a, $b, $cal, $rng, true, 1.0);
                break;
            case 'pareja_rapida':
                RelacionEngine::setRomanceHacia($partida, $a, $b, 60);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 58);
                RelacionBitacora::registrar($partida, RelacionBitacora::BESO, [$a, $b], $a . '>' . $b, 'ok');
                ParejaEngine::formar($partida, $a, $b, true, true, RelacionBitacora::INICIO_PAREJA, $cal);
                break;
            case 'pareja_lenta':
                RelacionEngine::setRomanceHacia($partida, $a, $b, 45);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 44);
                RelacionBitacora::registrar($partida, RelacionBitacora::TENSION_ROMANTICA, [$a, $b]);
                RelacionBitacora::registrar($partida, RelacionBitacora::COQUETEO, [$a, $b], $a . '>' . $b, 'ok');
                break;
            case 'pareja_estable':
                RelacionEngine::setRomanceHacia($partida, $a, $b, 70);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 68);
                ParejaEngine::formar($partida, $a, $b, true, true, RelacionBitacora::INICIO_PAREJA, $cal);
                $rel = ParejaEngine::ensureRomance($partida, $a, $b);
                $rel['estabilidad_pareja']['valor'] = 75;
                RelacionEngine::persistirRomance($partida, $rel);
                break;
            case 'pareja_deteriorada':
                RelacionEngine::setRomanceHacia($partida, $a, $b, 55);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 28);
                ParejaEngine::formar($partida, $a, $b, true, true, RelacionBitacora::INICIO_PAREJA, $cal);
                $rel = ParejaEngine::ensureRomance($partida, $a, $b);
                $rel['estabilidad_pareja']['valor'] = 22;
                RelacionEngine::persistirRomance($partida, $rel);
                break;
            case 'crisis_recuperada':
                RelacionEngine::setRomanceHacia($partida, $a, $b, 50);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 48);
                ParejaEngine::formar($partida, $a, $b, true, true, RelacionBitacora::INICIO_PAREJA, $cal);
                ParejaEngine::crisis($partida, $a, $b);
                $rel = ParejaEngine::ensureRomance($partida, $a, $b);
                $rel['estado_pareja'] = ParejaEngine::PAREJA;
                $rel['estabilidad_pareja']['valor'] = 48;
                RelacionEngine::persistirRomance($partida, $rel);
                RelacionBitacora::registrar($partida, RelacionBitacora::APOYO_IMPORTANTE, [$a, $b]);
                break;
            case 'crisis_a_ruptura':
                RelacionEngine::setRomanceHacia($partida, $a, $b, 30);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 20);
                ParejaEngine::formar($partida, $a, $b, true, true, RelacionBitacora::INICIO_PAREJA, $cal);
                ParejaEngine::crisis($partida, $a, $b);
                ParejaEngine::romper($partida, $a, $b, 'escenario');
                break;
            case 'tercero_compatible':
            case 'triangulo':
            case 'crush_no_actuado':
            case 'infidelidad_rara':
                RelacionEngine::setRomanceHacia($partida, $a, $b, 60);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 55);
                ParejaEngine::formar($partida, $a, $b, true, true, RelacionBitacora::INICIO_PAREJA, $cal);
                if ($c !== '') {
                    RelacionEngine::registrarContacto($partida, $a, $c, ContactoCalidad::NORMAL, $cal);
                    RelacionEngine::setRomanceHacia($partida, $a, $c, 48);
                    RelacionEngine::setRomanceHacia($partida, $c, $a, $escenario === 'crush_no_actuado' ? 12 : 40);
                    if ($escenario === 'infidelidad_rara') {
                        $rel = ParejaEngine::ensureRomance($partida, $a, $b);
                        $rel['estabilidad_pareja']['valor'] = 18;
                        RelacionEngine::persistirRomance($partida, $rel);
                        self::aplicarInfidelidad($partida, $a, $c, $b, $cal, $rng, true, 1.0);
                    }
                }
                break;
            case 'ruptura_antes_otra':
                RelacionEngine::setRomanceHacia($partida, $a, $b, 40);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 35);
                ParejaEngine::formar($partida, $a, $b, true, true, RelacionBitacora::INICIO_PAREJA, $cal);
                ParejaEngine::crisis($partida, $a, $b);
                ParejaEngine::romper($partida, $a, $b, 'antes_de_otra');
                if ($c !== '') {
                    RelacionEngine::registrarContacto($partida, $a, $c, ContactoCalidad::NORMAL, $cal);
                    RelacionEngine::setRomanceHacia($partida, $a, $c, 50);
                    RelacionEngine::setRomanceHacia($partida, $c, $a, 48);
                }
                break;
            case 'reconciliacion':
                RelacionEngine::setRomanceHacia($partida, $a, $b, 45);
                RelacionEngine::setRomanceHacia($partida, $b, $a, 42);
                ParejaEngine::formar($partida, $a, $b, true, true, RelacionBitacora::INICIO_PAREJA, $cal);
                ParejaEngine::romper($partida, $a, $b, 'temp');
                ParejaEngine::reconciliar($partida, $a, $b, true, true, $cal);
                break;
            default:
                return ['ok' => false, 'error' => 'escenario_desconocido', 'escenario' => $escenario];
        }

        return [
            'ok' => true,
            'escenario' => $escenario,
            'estado_ab' => ParejaEngine::estado($partida, $a, $b),
            'romance_ab' => RelacionEngine::romanceHacia($partida, $a, $b),
            'romance_ba' => RelacionEngine::romanceHacia($partida, $b, $a),
        ];
    }

    /**
     * @param array<string, mixed> $cal
     * @param array<string, mixed> $snap
     * @return array<string, mixed>|null
     */
    private static function intentar(
        array &$partida,
        string $a,
        string $b,
        string $tipo,
        array $cal,
        RngService $rng,
        array $snap
    ): ?array {
        if (HitoRelacionalContexto::enCooldown($partida, $a, $b, $tipo, $cal)) {
            return null;
        }
        switch ($tipo) {
            case RelacionBitacora::TENSION_ROMANTICA:
                return self::tryTension($partida, $a, $b, $cal, $rng, $snap);
            case RelacionBitacora::COQUETEO:
                return self::tryCoqueteo($partida, $a, $b, $cal, $rng, $snap);
            case RelacionBitacora::CONFESION:
                return self::tryConfesion($partida, $a, $b, $cal, $rng, $snap);
            case RelacionBitacora::BESO:
                return self::tryBeso($partida, $a, $b, $cal, $rng, $snap);
            case RelacionBitacora::INICIO_PAREJA:
                return self::tryInicioPareja($partida, $a, $b, $cal, $rng, $snap);
            case RelacionBitacora::CRISIS:
                return self::tryCrisis($partida, $a, $b, $cal, $rng, $snap);
            case RelacionBitacora::RUPTURA:
                return self::tryRuptura($partida, $a, $b, $cal, $rng, $snap);
            case RelacionBitacora::RECONCILIACION:
                return self::tryReconciliacion($partida, $a, $b, $cal, $rng, $snap);
            case RelacionBitacora::INFIDELIDAD:
                return self::tryInfidelidad($partida, $a, $b, $cal, $rng, $snap);
            case RelacionBitacora::CELOS:
                return self::tryCelos($partida, $a, $b, $cal, $rng, $snap);
            default:
                return null;
        }
    }

    /**
     * @param array<string, mixed> $cal
     * @param array<string, mixed> $snap
     */
    private static function pCompuesta(
        string $tipoCfg,
        array $cal,
        array $snap,
        string $emisor,
        string $receptor,
        array $partida,
        bool $exigeOportunidad
    ): float {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.' . $tipoCfg, []);
        if (!is_array($cfg)) {
            return 0.0;
        }
        $p = (float) ($cfg['p_base'] ?? 0);
        $fac = CalibracionConfig::get($cal, 'hitos_relacionales.factores', []);
        if (!is_array($fac)) {
            $fac = [];
        }
        $romE = $emisor === $snap['a'] ? (int) $snap['romance_ab'] : (int) $snap['romance_ba'];
        $romR = $emisor === $snap['a'] ? (int) $snap['romance_ba'] : (int) $snap['romance_ab'];
        $p *= HitoRelacionalContexto::multTabla(is_array($fac['interes_emisor'] ?? null) ? $fac['interes_emisor'] : [], $romE);
        $p *= HitoRelacionalContexto::multTabla(is_array($fac['interes_receptor'] ?? null) ? $fac['interes_receptor'] : [], $romR);
        $p *= HitoRelacionalContexto::multTabla(is_array($fac['social_media'] ?? null) ? $fac['social_media'] : [], (int) $snap['social_media']);
        if ($exigeOportunidad && empty($snap['oportunidad'])) {
            $p *= (float) ($fac['sin_oportunidad_mult'] ?? 0.08);
        }
        $rech = RelacionBitacora::entre($partida, $snap['a'], $snap['b'], RelacionBitacora::RECHAZO_ROMANTICO);
        if ($rech !== []) {
            $diaR = (int) ($rech[count($rech) - 1]['fecha']['dia'] ?? 0);
            $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            if (($dia - $diaR) < 20) {
                $p *= (float) ($fac['historia_rechazo_reciente_mult'] ?? 0.35);
            }
        }
        $pos = (array) CalibracionConfig::get($cal, 'hitos_relacionales.inicio_pareja.hitos_positivos', [
            RelacionBitacora::COQUETEO,
            RelacionBitacora::CONFESION,
            RelacionBitacora::BESO,
        ]);
        $nPos = HitoRelacionalContexto::cuentaHitos($partida, $snap['a'], $snap['b'], $pos);
        $bonus = min(
            (float) ($fac['historia_hitos_positivos_bonus_cap'] ?? 0.3),
            $nPos * (float) ($fac['historia_hitos_positivos_bonus_por_hito'] ?? 0.06)
        );
        $p *= (1.0 + $bonus);
        $p *= HitoRelacionalContexto::emocionModIniciativa($partida, $emisor, $cal);
        $mapa = CalibracionConfig::get($cal, 'hitos_relacionales.rasgos_modulan.' . $tipoCfg, []);
        if (is_array($mapa)) {
            $p *= HitoRelacionalContexto::multRasgos($partida, $emisor, $mapa);
        }
        return HitoRelacionalContexto::clampP($p, $cal, isset($cfg['p_cap']) ? (float) $cfg['p_cap'] : null);
    }

    /** @return array<string, mixed>|null */
    private static function tryTension(array &$partida, string $a, string $b, array $cal, RngService $rng, array $snap): ?array
    {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.tension_romantica', []);
        if (!is_array($cfg) || empty($snap['conocidos'])) {
            return null;
        }
        if ($snap['estado_pareja'] === ParejaEngine::PAREJA || $snap['estado_pareja'] === ParejaEngine::CRISIS) {
            return null;
        }
        $emisor = (int) $snap['romance_ab'] >= (int) $snap['romance_ba'] ? $a : $b;
        $receptor = $emisor === $a ? $b : $a;
        $romE = $emisor === $a ? (int) $snap['romance_ab'] : (int) $snap['romance_ba'];
        if ($romE < (int) ($cfg['romance_min_emisor'] ?? 12)) {
            return null;
        }
        $p = self::pCompuesta('tension_romantica', $cal, $snap, $emisor, $receptor, $partida, false);
        if ($rng->nextFloat() > $p) {
            return null;
        }
        $d = HitoRelacionalContexto::randRango($rng, is_array($cfg['delta_romance_emisor'] ?? null) ? $cfg['delta_romance_emisor'] : [1, 3]);
        HitoRelacionalContexto::bumpRomance($partida, $emisor, $receptor, $d);
        $h = RelacionBitacora::registrar(
            $partida,
            RelacionBitacora::TENSION_ROMANTICA,
            [$emisor, $receptor],
            $emisor . '>' . $receptor,
            'ok',
            null,
            ['p' => round($p, 4), 'familia_copy' => $cfg['familia_copy'] ?? 'tension']
        );
        return ['tipo' => RelacionBitacora::TENSION_ROMANTICA, 'entry' => $h, 'p' => $p];
    }

    /** @return array<string, mixed>|null */
    private static function tryCoqueteo(array &$partida, string $a, string $b, array $cal, RngService $rng, array $snap): ?array
    {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.coqueteo', []);
        if (!is_array($cfg)) {
            return null;
        }
        if ($snap['estado_pareja'] === ParejaEngine::PAREJA || $snap['estado_pareja'] === ParejaEngine::CRISIS) {
            return null;
        }
        $emisor = (int) $snap['romance_ab'] >= (int) $snap['romance_ba'] ? $a : $b;
        $receptor = $emisor === $a ? $b : $a;
        $romE = $emisor === $a ? (int) $snap['romance_ab'] : (int) $snap['romance_ba'];
        $romR = $emisor === $a ? (int) $snap['romance_ba'] : (int) $snap['romance_ab'];
        if ($romE < (int) ($cfg['romance_min_emisor'] ?? 18)) {
            return null;
        }
        $p = self::pCompuesta('coqueteo', $cal, $snap, $emisor, $receptor, $partida, !empty($cfg['requiere_oportunidad']));
        $p *= TerceroRomantico::multiplicador($partida, $emisor, $receptor, $cal);
        $p = HitoRelacionalContexto::clampP($p, $cal);
        if ($rng->nextFloat() > $p) {
            return null;
        }
        $pAcc = (float) ($cfg['p_aceptacion_base'] ?? 0.55);
        $pAcc *= HitoRelacionalContexto::multTabla(
            (array) CalibracionConfig::get($cal, 'hitos_relacionales.factores.interes_receptor', []),
            $romR
        );
        $acepta = $romR >= (int) ($cfg['romance_min_receptor_acepta'] ?? 10)
            && $rng->nextFloat() <= HitoRelacionalContexto::clampP($pAcc, $cal);
        if ($acepta) {
            $d = HitoRelacionalContexto::randRango($rng, is_array($cfg['delta_romance_si_ok'] ?? null) ? $cfg['delta_romance_si_ok'] : [2, 5]);
            HitoRelacionalContexto::bumpRomance($partida, $emisor, $receptor, $d);
            HitoRelacionalContexto::bumpRomance($partida, $receptor, $emisor, max(1, (int) floor($d * 0.7)));
            $res = 'aceptado';
        } else {
            $d = HitoRelacionalContexto::randRango($rng, is_array($cfg['delta_romance_si_freno'] ?? null) ? $cfg['delta_romance_si_freno'] : [0, 1]);
            HitoRelacionalContexto::bumpRomance($partida, $emisor, $receptor, $d);
            $res = 'freno';
        }
        $h = RelacionBitacora::registrar(
            $partida,
            RelacionBitacora::COQUETEO,
            [$emisor, $receptor],
            $emisor . '>' . $receptor,
            $res,
            null,
            ['p' => round($p, 4), 'familia_copy' => $cfg['familia_copy'] ?? 'coqueteo']
        );
        return ['tipo' => RelacionBitacora::COQUETEO, 'entry' => $h, 'p' => $p, 'resultado' => $res];
    }

    /** @return array<string, mixed>|null */
    private static function tryConfesion(array &$partida, string $a, string $b, array $cal, RngService $rng, array $snap): ?array
    {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.confesion', []);
        if (!is_array($cfg)) {
            return null;
        }
        if (!(bool) ($cfg['repetible'] ?? false) && RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::CONFESION)) {
            return null;
        }
        if ($snap['estado_pareja'] === ParejaEngine::PAREJA || $snap['estado_pareja'] === ParejaEngine::CRISIS) {
            return null;
        }
        $emisor = (int) $snap['romance_ab'] >= (int) $snap['romance_ba'] ? $a : $b;
        $receptor = $emisor === $a ? $b : $a;
        $romE = $emisor === $a ? (int) $snap['romance_ab'] : (int) $snap['romance_ba'];
        $romR = $emisor === $a ? (int) $snap['romance_ba'] : (int) $snap['romance_ab'];
        if ($romE < (int) ($cfg['romance_min_emisor'] ?? 40)) {
            return null;
        }
        $p = self::pCompuesta('confesion', $cal, $snap, $emisor, $receptor, $partida, !empty($cfg['requiere_oportunidad']));
        if ($rng->nextFloat() > $p) {
            return null;
        }
        $pAcc = (float) ($cfg['p_aceptacion_base'] ?? 0.45);
        $pAcc *= HitoRelacionalContexto::multTabla(
            (array) CalibracionConfig::get($cal, 'hitos_relacionales.factores.interes_receptor', []),
            $romR
        );
        $acepta = $romR >= (int) ($cfg['romance_min_receptor_acepta'] ?? 22)
            && $rng->nextFloat() <= HitoRelacionalContexto::clampP($pAcc, $cal);
        if ($acepta) {
            HitoRelacionalContexto::bumpRomance(
                $partida,
                $emisor,
                $receptor,
                HitoRelacionalContexto::randRango($rng, is_array($cfg['delta_romance_aceptada_emisor'] ?? null) ? $cfg['delta_romance_aceptada_emisor'] : [4, 8])
            );
            HitoRelacionalContexto::bumpRomance(
                $partida,
                $receptor,
                $emisor,
                HitoRelacionalContexto::randRango($rng, is_array($cfg['delta_romance_aceptada_receptor'] ?? null) ? $cfg['delta_romance_aceptada_receptor'] : [3, 7])
            );
            RelacionBitacora::registrar($partida, RelacionBitacora::DECLARACION, [$emisor, $receptor], $emisor . '>' . $receptor, 'aceptada');
            $res = 'aceptada';
        } else {
            HitoRelacionalContexto::bumpRomance(
                $partida,
                $emisor,
                $receptor,
                HitoRelacionalContexto::randRango($rng, is_array($cfg['delta_romance_rechazo_emisor'] ?? null) ? $cfg['delta_romance_rechazo_emisor'] : [-8, -3])
            );
            RelacionBitacora::registrar(
                $partida,
                RelacionBitacora::RECHAZO_ROMANTICO,
                [$emisor, $receptor],
                $receptor . '>' . $emisor,
                'rechazo',
                null,
                ['origen' => 'confesion']
            );
            $res = 'rechazada';
        }
        $h = RelacionBitacora::registrar(
            $partida,
            RelacionBitacora::CONFESION,
            [$emisor, $receptor],
            $emisor . '>' . $receptor,
            $res,
            null,
            ['p' => round($p, 4), 'familia_copy' => $cfg['familia_copy'] ?? 'confesion']
        );
        return ['tipo' => RelacionBitacora::CONFESION, 'entry' => $h, 'p' => $p, 'resultado' => $res];
    }

    /** @return array<string, mixed>|null */
    private static function tryBeso(array &$partida, string $a, string $b, array $cal, RngService $rng, array $snap): ?array
    {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.beso', []);
        if (!is_array($cfg) || $snap['estado_pareja'] === ParejaEngine::CRISIS) {
            return null;
        }
        $emisor = (int) $snap['romance_ab'] >= (int) $snap['romance_ba'] ? $a : $b;
        $receptor = $emisor === $a ? $b : $a;
        $romE = $emisor === $a ? (int) $snap['romance_ab'] : (int) $snap['romance_ba'];
        $romR = $emisor === $a ? (int) $snap['romance_ba'] : (int) $snap['romance_ab'];
        if ($romE < (int) ($cfg['romance_min_emisor'] ?? 35) || $romR < (int) ($cfg['romance_min_receptor'] ?? 28)) {
            return null;
        }
        $prev = (array) ($cfg['requiere_hito_previo'] ?? []);
        $mutuo = (int) ($cfg['o_romance_mutuo_min'] ?? 50);
        $okPrev = HitoRelacionalContexto::cuentaHitos($partida, $a, $b, $prev) > 0
            || ($romE >= $mutuo && $romR >= $mutuo);
        if (!$okPrev) {
            return null;
        }
        $p = self::pCompuesta('beso', $cal, $snap, $emisor, $receptor, $partida, !empty($cfg['requiere_oportunidad']));
        if ($rng->nextFloat() > $p) {
            return null;
        }
        return self::aplicarBeso($partida, $emisor, $receptor, $cal, $rng, false, $p);
    }

    /** @return array<string, mixed> */
    private static function aplicarBeso(
        array &$partida,
        string $emisor,
        string $receptor,
        array $cal,
        RngService $rng,
        bool $forzar,
        float $p
    ): array {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.beso', []);
        $d = HitoRelacionalContexto::randRango($rng, is_array($cfg['delta_romance'] ?? null) ? $cfg['delta_romance'] : [3, 6]);
        HitoRelacionalContexto::bumpRomance($partida, $emisor, $receptor, $d);
        HitoRelacionalContexto::bumpRomance($partida, $receptor, $emisor, $d);
        $h = RelacionBitacora::registrar(
            $partida,
            RelacionBitacora::BESO,
            [$emisor, $receptor],
            $emisor . '>' . $receptor,
            'ok',
            null,
            ['p' => round($p, 4), 'forzado' => $forzar, 'familia_copy' => is_array($cfg) ? ($cfg['familia_copy'] ?? 'beso') : 'beso']
        );
        return ['tipo' => RelacionBitacora::BESO, 'entry' => $h, 'p' => $p];
    }

    /** @return array<string, mixed>|null */
    private static function tryInicioPareja(array &$partida, string $a, string $b, array $cal, RngService $rng, array $snap): ?array
    {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.inicio_pareja', []);
        if (!is_array($cfg)) {
            return null;
        }
        if ($snap['estado_pareja'] === ParejaEngine::PAREJA || $snap['estado_pareja'] === ParejaEngine::CRISIS) {
            return null;
        }
        $minR = (int) ($cfg['romance_min_ambos'] ?? 42);
        if ((int) $snap['romance_ab'] < $minR || (int) $snap['romance_ba'] < $minR) {
            return null;
        }
        if ((int) $snap['social_media'] < (int) ($cfg['social_min_media'] ?? 15)) {
            return null;
        }
        if (!empty($cfg['requiere_trayectoria'])) {
            $pos = (array) ($cfg['hitos_positivos'] ?? [RelacionBitacora::COQUETEO, RelacionBitacora::CONFESION, RelacionBitacora::BESO]);
            if (HitoRelacionalContexto::cuentaHitos($partida, $a, $b, $pos) < (int) ($cfg['hitos_positivos_min'] ?? 1)) {
                return null;
            }
        }
        $p = self::pCompuesta('inicio_pareja', $cal, $snap, $a, $b, $partida, !empty($cfg['requiere_oportunidad']));
        if ($rng->nextFloat() > $p) {
            return null;
        }
        $pAcc = (float) ($cfg['p_aceptacion_base'] ?? 0.7);
        $aceptaA = $rng->nextFloat() <= HitoRelacionalContexto::clampP($pAcc * ((int) $snap['romance_ab'] / 100.0 + 0.3), $cal);
        $aceptaB = $rng->nextFloat() <= HitoRelacionalContexto::clampP($pAcc * ((int) $snap['romance_ba'] / 100.0 + 0.3), $cal);
        $form = ParejaEngine::formar($partida, $a, $b, $aceptaA, $aceptaB, RelacionBitacora::INICIO_PAREJA, $cal);
        if (empty($form['ok'])) {
            return ['tipo' => 'inicio_pareja_fallida', 'p' => $p, 'form' => $form];
        }
        return [
            'tipo' => RelacionBitacora::INICIO_PAREJA,
            'p' => $p,
            'vuelta' => !empty($form['vuelta']),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function tryCrisis(array &$partida, string $a, string $b, array $cal, RngService $rng, array $snap): ?array
    {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.crisis', []);
        if (!is_array($cfg) || $snap['estado_pareja'] !== ParejaEngine::PAREJA) {
            return null;
        }
        $estabMax = (int) ($cfg['estabilidad_max_para_habilitar'] ?? 40);
        $romMin = (int) ($cfg['o_romance_min_caido'] ?? 25);
        $habilita = ($snap['estabilidad'] !== null && (int) $snap['estabilidad'] <= $estabMax)
            || min((int) $snap['romance_ab'], (int) $snap['romance_ba']) <= $romMin;
        if (!$habilita) {
            return null;
        }
        $p = (float) ($cfg['p_base'] ?? 0.008);
        if ($snap['estabilidad'] !== null && (int) $snap['estabilidad'] <= $estabMax) {
            $p += (float) ($cfg['p_bonus_estabilidad_baja'] ?? 0.025)
                * (1.0 - (int) $snap['estabilidad'] / max(1, $estabMax));
        }
        $p *= HitoRelacionalContexto::multRasgos(
            $partida,
            $a,
            (array) CalibracionConfig::get($cal, 'hitos_relacionales.rasgos_modulan.crisis', [])
        );
        $p = HitoRelacionalContexto::clampP($p, $cal);
        if ($rng->nextFloat() > $p) {
            return null;
        }
        $r = ParejaEngine::crisis($partida, $a, $b);
        return ['tipo' => RelacionBitacora::CRISIS, 'p' => $p, 'ok' => !empty($r['ok'])];
    }

    /** @return array<string, mixed>|null */
    private static function tryRuptura(array &$partida, string $a, string $b, array $cal, RngService $rng, array $snap): ?array
    {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.ruptura', []);
        if (!is_array($cfg)) {
            return null;
        }
        if ($snap['estado_pareja'] === ParejaEngine::CRISIS) {
            $p = (float) ($cfg['p_base'] ?? 0.015);
        } elseif ($snap['estado_pareja'] === ParejaEngine::PAREJA
            && $snap['estabilidad'] !== null
            && (int) $snap['estabilidad'] <= (int) ($cfg['o_desde_pareja_si_estabilidad_max'] ?? 12)
        ) {
            $p = (float) ($cfg['p_desde_pareja_muy_baja'] ?? 0.004);
        } else {
            return null;
        }
        $p = HitoRelacionalContexto::clampP($p, $cal);
        if ($rng->nextFloat() > $p) {
            return null;
        }
        $r = ParejaEngine::romper($partida, $a, $b, 'hito_probabilistico');
        return ['tipo' => RelacionBitacora::RUPTURA, 'p' => $p, 'ok' => !empty($r['ok'])];
    }

    /** @return array<string, mixed>|null */
    private static function tryReconciliacion(array &$partida, string $a, string $b, array $cal, RngService $rng, array $snap): ?array
    {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.reconciliacion', []);
        if (!is_array($cfg) || $snap['estado_pareja'] !== ParejaEngine::EX) {
            return null;
        }
        $min = (int) ($cfg['romance_min_ambos'] ?? 30);
        if ((int) $snap['romance_ab'] < $min || (int) $snap['romance_ba'] < $min) {
            return null;
        }
        $cd = (int) ($cfg['cooldown_tras_ruptura_dias'] ?? 7);
        $rup = RelacionBitacora::entre($partida, $a, $b, RelacionBitacora::RUPTURA);
        if ($rup !== []) {
            $diaR = (int) ($rup[count($rup) - 1]['fecha']['dia'] ?? 0);
            $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            if (($dia - $diaR) < $cd) {
                return null;
            }
        }
        $p = self::pCompuesta('reconciliacion', $cal, $snap, $a, $b, $partida, false);
        if ($rng->nextFloat() > $p) {
            return null;
        }
        $pAcc = (float) ($cfg['p_aceptacion_base'] ?? 0.55);
        $r = ParejaEngine::reconciliar(
            $partida,
            $a,
            $b,
            $rng->nextFloat() <= $pAcc,
            $rng->nextFloat() <= $pAcc,
            $cal
        );
        return ['tipo' => RelacionBitacora::RECONCILIACION, 'p' => $p, 'ok' => !empty($r['ok'])];
    }

    /** @return array<string, mixed>|null */
    private static function tryInfidelidad(array &$partida, string $a, string $b, array $cal, RngService $rng, array $snap): ?array
    {
        if ($snap['pareja_de_a'] === $b || $snap['pareja_de_b'] === $a) {
            return null;
        }
        if ($snap['pareja_de_a'] !== null) {
            return self::evaluarInfidelidadActor($partida, $a, $b, (string) $snap['pareja_de_a'], $cal, $rng);
        }
        if ($snap['pareja_de_b'] !== null) {
            return self::evaluarInfidelidadActor($partida, $b, $a, (string) $snap['pareja_de_b'], $cal, $rng);
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    private static function evaluarInfidelidadActor(
        array &$partida,
        string $actor,
        string $tercero,
        string $pareja,
        array $cal,
        RngService $rng
    ): ?array {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.infidelidad', []);
        if (!is_array($cfg)) {
            return null;
        }
        if (HitoRelacionalContexto::enCooldown($partida, $actor, $tercero, RelacionBitacora::INFIDELIDAD, $cal)) {
            return null;
        }
        $romAT = RelacionEngine::romanceHacia($partida, $actor, $tercero) ?? 0;
        $romTA = RelacionEngine::romanceHacia($partida, $tercero, $actor) ?? 0;
        if ($romAT < (int) ($cfg['romance_hacia_tercero_min'] ?? 35)) {
            return null;
        }
        if ($romTA < (int) ($cfg['romance_tercero_hacia_min'] ?? 22)) {
            return null;
        }
        $dias = HitoRelacionalContexto::diasDesdeContacto($partida, $actor, $tercero);
        $opD = (int) CalibracionConfig::get($cal, 'hitos_relacionales.oportunidad_dias_contacto', 5);
        if ($dias === null || $dias > $opD) {
            return null;
        }
        $relP = RelacionEngine::obtenerEntre($partida, $actor, $pareja)['romance'] ?? null;
        $estab = is_array($relP) && is_numeric($relP['estabilidad_pareja']['valor'] ?? null)
            ? (int) $relP['estabilidad_pareja']['valor']
            : 50;
        $estP = ParejaEngine::estado($partida, $actor, $pareja);
        $vuln = $estab <= (int) ($cfg['estabilidad_pareja_max'] ?? 45)
            || ($estP === ParejaEngine::CRISIS && !empty($cfg['o_estado_crisis']));
        if (!$vuln) {
            return null;
        }
        $p = (float) ($cfg['p_base'] ?? 0.0012);
        $p *= HitoRelacionalContexto::multRasgos($partida, $actor, (array) ($cfg['rasgos_mult'] ?? []));
        $p *= TerceroRomantico::multiplicador($partida, $actor, $tercero, $cal);
        $p *= ($romAT / 100.0) * (0.5 + $romTA / 200.0);
        $p = HitoRelacionalContexto::clampP($p, $cal, isset($cfg['p_cap']) ? (float) $cfg['p_cap'] : 0.12);
        if ($rng->nextFloat() > $p) {
            return null;
        }
        return self::aplicarInfidelidad($partida, $actor, $tercero, $pareja, $cal, $rng, false, $p);
    }

    /** @return array<string, mixed> */
    private static function aplicarInfidelidad(
        array &$partida,
        string $actor,
        string $tercero,
        string $pareja,
        array $cal,
        RngService $rng,
        bool $forzar,
        float $p
    ): array {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.infidelidad', []);
        HitoRelacionalContexto::bumpRomance($partida, $actor, $tercero, 4);
        HitoRelacionalContexto::bumpRomance($partida, $tercero, $actor, 3);
        HitoRelacionalContexto::bumpRomance($partida, $actor, $pareja, -10);
        $h = RelacionBitacora::registrar(
            $partida,
            RelacionBitacora::INFIDELIDAD,
            [$actor, $tercero],
            $actor . '>' . $tercero,
            'ocurrida',
            null,
            [
                'pareja' => $pareja,
                'p' => round($p, 5),
                'forzado' => $forzar,
                'familia_copy' => is_array($cfg) ? ($cfg['familia_copy'] ?? 'infidelidad') : 'infidelidad',
            ]
        );
        $pCrisis = (float) (is_array($cfg) ? ($cfg['disparar_crisis_con_pareja_p'] ?? 0.55) : 0.55);
        if ($forzar || $rng->nextFloat() <= $pCrisis) {
            ParejaEngine::crisis($partida, $actor, $pareja);
            RelacionBitacora::registrar(
                $partida,
                RelacionBitacora::CELOS,
                [$pareja, $actor],
                $pareja . '>' . $actor,
                'tras_infidelidad',
                null,
                ['tercero' => $tercero]
            );
        }
        return ['tipo' => RelacionBitacora::INFIDELIDAD, 'entry' => $h, 'p' => $p, 'pareja' => $pareja];
    }

    /** @return array<string, mixed>|null */
    private static function tryCelos(array &$partida, string $a, string $b, array $cal, RngService $rng, array $snap): ?array
    {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.celos', []);
        if (!is_array($cfg)) {
            return null;
        }
        if ($snap['estado_pareja'] !== ParejaEngine::PAREJA && $snap['estado_pareja'] !== ParejaEngine::CRISIS) {
            return null;
        }
        $info = false;
        $p = (float) ($cfg['p_base'] ?? 0.02);
        foreach ($partida['bitacora_relaciones'] ?? [] as $h) {
            if (!is_array($h)) {
                continue;
            }
            $tipo = (string) ($h['tipo'] ?? '');
            $parts = $h['participantes'] ?? [];
            if (!is_array($parts)) {
                continue;
            }
            foreach ([$a, $b] as $quien) {
                $otro = $quien === $a ? $b : $a;
                if ($tipo === RelacionBitacora::INFIDELIDAD && in_array($quien, $parts, true) && !in_array($otro, $parts, true)) {
                    $info = true;
                    $p = (float) ($cfg['si_infidelidad_conocida'] ?? 0.55);
                    break 2;
                }
                if (($tipo === RelacionBitacora::TENSION_ROMANTICA || $tipo === RelacionBitacora::COQUETEO)
                    && in_array($quien, $parts, true)
                    && !in_array($otro, $parts, true)
                ) {
                    $diaH = (int) ($h['fecha']['dia'] ?? 0);
                    $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
                    if (($dia - $diaH) <= 14) {
                        $info = true;
                        $p = max($p, (float) ($cfg['si_tension_tercero_visible'] ?? 0.12));
                    }
                }
            }
        }
        if (!$info) {
            return null;
        }
        if (HitoRelacionalContexto::enCooldown($partida, $a, $b, RelacionBitacora::CELOS, $cal)) {
            return null;
        }
        $p = HitoRelacionalContexto::clampP($p, $cal);
        if ($rng->nextFloat() > $p) {
            return null;
        }
        $d = HitoRelacionalContexto::randRango($rng, is_array($cfg['delta_romance_pareja'] ?? null) ? $cfg['delta_romance_pareja'] : [-6, -2]);
        HitoRelacionalContexto::bumpRomance($partida, $a, $b, $d);
        HitoRelacionalContexto::bumpRomance($partida, $b, $a, $d);
        $entry = RelacionBitacora::registrar(
            $partida,
            RelacionBitacora::CELOS,
            [$a, $b],
            null,
            'reaccion',
            null,
            ['familia_copy' => $cfg['familia_copy'] ?? 'celos']
        );
        if ($snap['estado_pareja'] === ParejaEngine::PAREJA && $rng->nextFloat() <= (float) ($cfg['p_crisis'] ?? 0.25)) {
            ParejaEngine::crisis($partida, $a, $b);
        }
        return ['tipo' => RelacionBitacora::CELOS, 'entry' => $entry, 'p' => $p];
    }

    /**
     * @param array<string, mixed> $cal
     * @param list<array<string, mixed>> $ocurridos
     */
        private static function crushTerceros(array &$partida, array $cal, RngService $rng, array &$ocurridos): void
    {
        $cfg = CalibracionConfig::get($cal, 'hitos_relacionales.crush_tercero', []);
        if (!is_array($cfg)) {
            return;
        }
        $pBump = (float) ($cfg['p_bump_base'] ?? 0.015);
        $maxIntentos = (int) ($cfg['max_intentos_por_dia'] ?? 12);
        $emparejados = [];
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $est = (string) ($rel['estado_pareja'] ?? '');
            if ($est !== ParejaEngine::PAREJA && $est !== ParejaEngine::CRISIS) {
                continue;
            }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            if ($a !== '') {
                $emparejados[$a] = $b;
            }
            if ($b !== '') {
                $emparejados[$b] = $a;
            }
        }
        if ($emparejados === []) {
            return;
        }
        $intentos = 0;
        foreach ($emparejados as $id => $pareja) {
            foreach ($partida['relaciones_sociales'] ?? [] as $soc) {
                if ($intentos >= $maxIntentos) {
                    return;
                }
                if (!is_array($soc) || empty($soc['conocidos'])) {
                    continue;
                }
                $x = (string) ($soc['persona_a'] ?? '');
                $y = (string) ($soc['persona_b'] ?? '');
                $otro = null;
                if ($x === $id && $y !== $pareja) {
                    $otro = $y;
                } elseif ($y === $id && $x !== $pareja) {
                    $otro = $x;
                }
                if ($otro === null) {
                    continue;
                }
                $intentos++;
                if (ParentescoVeto::bloqueaRomance($partida, $id, $otro, $cal)) {
                    continue;
                }
                $p = $pBump * TerceroRomantico::multiplicador($partida, $id, $otro, $cal);
                if ($rng->nextFloat() > $p) {
                    continue;
                }
                $d = HitoRelacionalContexto::randRango($rng, is_array($cfg['romance_bump'] ?? null) ? $cfg['romance_bump'] : [2, 5]);
                HitoRelacionalContexto::bumpRomance($partida, $id, $otro, $d);
                $ocurridos[] = [
                    'tipo' => 'crush_tercero',
                    'desde' => $id,
                    'hacia' => $otro,
                    'pareja' => $pareja,
                    'delta' => $d,
                ];
            }
        }
    }
private static function paresCandidatos(array $partida): array
    {
        $out = [];
        $seen = [];
        foreach (['relaciones_sociales', 'relaciones_romanticas'] as $bag) {
            foreach ($partida[$bag] ?? [] as $rel) {
                if (!is_array($rel)) {
                    continue;
                }
                if ($bag === 'relaciones_sociales' && empty($rel['conocidos'])) {
                    continue;
                }
                $a = (string) ($rel['persona_a'] ?? '');
                $b = (string) ($rel['persona_b'] ?? '');
                if ($a === '' || $b === '') {
                    continue;
                }
                $k = $a < $b ? "$a|$b" : "$b|$a";
                if (isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $out[] = [$a, $b];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $snap
     * @return list<string>
     */
    private static function ordenHitos(array $snap): array
    {
        $est = (string) ($snap['estado_pareja'] ?? ParejaEngine::NINGUNA);
        if ($est === ParejaEngine::CRISIS) {
            return [RelacionBitacora::RUPTURA, RelacionBitacora::CELOS, RelacionBitacora::INFIDELIDAD];
        }
        if ($est === ParejaEngine::PAREJA) {
            return [
                RelacionBitacora::CELOS,
                RelacionBitacora::CRISIS,
                RelacionBitacora::INFIDELIDAD,
                RelacionBitacora::RUPTURA,
            ];
        }
        if ($est === ParejaEngine::EX) {
            return [RelacionBitacora::RECONCILIACION, RelacionBitacora::TENSION_ROMANTICA, RelacionBitacora::COQUETEO];
        }
        return [
            RelacionBitacora::INICIO_PAREJA,
            RelacionBitacora::BESO,
            RelacionBitacora::CONFESION,
            RelacionBitacora::COQUETEO,
            RelacionBitacora::TENSION_ROMANTICA,
            RelacionBitacora::INFIDELIDAD,
        ];
    }

    /**
     * @param list<array{0:string,1:string}> $pares
     */
    private static function shuffleInPlace(array &$pares, RngService $rng): void
    {
        $n = count($pares);
        for ($i = $n - 1; $i > 0; $i--) {
            $j = $rng->nextInt(0, $i);
            $tmp = $pares[$i];
            $pares[$i] = $pares[$j];
            $pares[$j] = $tmp;
        }
    }
}
