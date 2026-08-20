<?php
/**
 * Fusiona bloque hitos_relacionales en calibracion_vida.json (provisional).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root . '/data/configs/calibracion_vida.json';
$j = json_decode(file_get_contents($path), true);
if (!is_array($j)) {
    fwrite(STDERR, "JSON inválido\n");
    exit(1);
}

$j['hitos_relacionales'] = [
    '_provisional' => true,
    '_nota' => 'Canon pendiente de simulación Neni+ChatGPT. Nunca romance>=X = pareja. Crisis/ruptura por hito con tirada, no por umbral solo.',
    'activo' => true,
    'max_hitos_grandes_por_par_dia' => 1,
    'max_intentos_por_residente_dia' => 2,
    'oportunidad_dias_contacto' => 5,
    'p_cap' => 0.85,

    'drift_lab' => [
        '_nota' => 'Solo laboratorio/sim: contacto sintético para que existan trayectorias. Play real usa encuentros/coincidencias.',
        'activo_en_sim' => true,
        'pares_contacto_por_dia_factor' => 0.35,
        'delta_social' => [1, 4],
        'delta_romance_si_afinidad' => [0, 3],
        'p_bump_romance' => 0.22,
    ],

    'cooldowns_dias' => [
        'tension_romantica' => 7,
        'coqueteo' => 5,
        'confesion' => 14,
        'beso' => 7,
        'rechazo_romantico' => 10,
        'inicio_pareja' => 999,
        'crisis' => 21,
        'infidelidad' => 45,
        'ruptura' => 999,
        'reconciliacion' => 30,
        'celos' => 10,
    ],

    'tension_romantica' => [
        'p_base' => 0.04,
        'romance_min_emisor' => 12,
        'requiere_conocidos' => true,
        'repetible' => true,
        'delta_romance_emisor' => [1, 3],
        'familia_copy' => 'tension',
    ],

    'coqueteo' => [
        'p_base' => 0.035,
        'romance_min_emisor' => 18,
        'romance_min_receptor_acepta' => 10,
        'p_aceptacion_base' => 0.55,
        'requiere_oportunidad' => true,
        'repetible' => true,
        'delta_romance_si_ok' => [2, 5],
        'delta_romance_si_freno' => [0, 1],
        'familia_copy' => 'coqueteo',
    ],

    'confesion' => [
        'p_base' => 0.012,
        'romance_min_emisor' => 40,
        'romance_min_receptor_acepta' => 22,
        'p_aceptacion_base' => 0.45,
        'requiere_oportunidad' => true,
        'repetible' => false,
        'si_rechazo_hito' => 'rechazo_romantico',
        'delta_romance_aceptada_emisor' => [4, 8],
        'delta_romance_aceptada_receptor' => [3, 7],
        'delta_romance_rechazo_emisor' => [-8, -3],
        'familia_copy' => 'confesion',
    ],

    'beso' => [
        'p_base' => 0.018,
        'romance_min_emisor' => 35,
        'romance_min_receptor' => 28,
        'requiere_hito_previo' => ['coqueteo', 'confesion', 'beso', 'tension_romantica'],
        'o_romance_mutuo_min' => 50,
        'requiere_oportunidad' => true,
        'permite_sin_pareja' => true,
        'repetible' => true,
        'delta_romance' => [3, 6],
        'familia_copy' => 'beso',
    ],

    'inicio_pareja' => [
        'p_base' => 0.02,
        'romance_min_ambos' => 42,
        'social_min_media' => 15,
        'requiere_trayectoria' => true,
        'hitos_positivos_min' => 1,
        'hitos_positivos' => ['coqueteo', 'confesion', 'beso', 'primera_cita', 'plan_significativo'],
        'requiere_oportunidad' => true,
        'ambos_deben_aceptar' => true,
        'p_aceptacion_base' => 0.7,
        'nunca_por_umbral_solo' => true,
        'estabilidad_ini_usa_cal_pareja' => true,
        'familia_copy' => 'inicio_pareja',
    ],

    'crisis' => [
        'p_base' => 0.008,
        'solo_si_pareja' => true,
        'estabilidad_max_para_habilitar' => 40,
        'o_romance_min_caido' => 25,
        'senales_min' => 1,
        'p_bonus_estabilidad_baja' => 0.025,
        'nunca_por_umbral_solo' => true,
        'familia_copy' => 'crisis',
    ],

    'ruptura' => [
        'p_base' => 0.015,
        'solo_si_crisis' => true,
        'o_desde_pareja_si_estabilidad_max' => 12,
        'p_desde_pareja_muy_baja' => 0.004,
        'nunca_por_umbral_solo' => true,
        'familia_copy' => 'ruptura',
    ],

    'reconciliacion' => [
        'p_base' => 0.01,
        'solo_si_ex' => true,
        'romance_min_ambos' => 30,
        'p_aceptacion_base' => 0.55,
        'cooldown_tras_ruptura_dias' => 7,
        'familia_copy' => 'reconciliacion',
    ],

    'crush_tercero' => [
        '_nota' => 'Interés hacia tercero estando en pareja: no es acción. Usa drift + freno TerceroRomantico.',
        'p_bump_base' => 0.015,
        'romance_bump' => [2, 5],
    ],

    'infidelidad' => [
        'p_base' => 0.0012,
        'requiere_pareja_propia' => true,
        'romance_hacia_tercero_min' => 35,
        'romance_tercero_hacia_min' => 22,
        'estabilidad_pareja_max' => 45,
        'o_estado_crisis' => true,
        'requiere_oportunidad_con_tercero' => true,
        'p_cap' => 0.12,
        'rasgos_mult' => [
            'leal' => 0.25,
            'vanidoso' => 1.35,
            'impulsivo' => 1.5,
            'reservado' => 0.7,
            'timido' => 0.65,
            'cabezota' => 0.85,
        ],
        'disparar_crisis_con_pareja_p' => 0.55,
        'familia_copy' => 'infidelidad',
        '_nota_balance' => 'Rara por diseño. No subir sin datos.',
    ],

    'celos' => [
        'p_base' => 0.02,
        'requiere_info_coherente' => true,
        'si_infidelidad_conocida' => 0.55,
        'si_tension_tercero_visible' => 0.12,
        'delta_romance_pareja' => [-6, -2],
        'p_crisis' => 0.25,
        'familia_copy' => 'celos',
    ],

    'factores' => [
        'interes_emisor' => [
            '0' => 0.15,
            '8' => 0.4,
            '22' => 0.75,
            '45' => 1.0,
            '72' => 1.25,
        ],
        'interes_receptor' => [
            '0' => 0.35,
            '8' => 0.55,
            '22' => 0.85,
            '45' => 1.05,
            '72' => 1.2,
        ],
        'social_media' => [
            '-20' => 0.5,
            '0' => 0.75,
            '18' => 0.95,
            '40' => 1.1,
            '62' => 1.2,
        ],
        'emocion_iniciativa_romantica_peso' => 0.08,
        'sin_oportunidad_mult' => 0.08,
        'historia_rechazo_reciente_mult' => 0.35,
        'historia_hitos_positivos_bonus_por_hito' => 0.06,
        'historia_hitos_positivos_bonus_cap' => 0.3,
    ],

    'rasgos_modulan' => [
        'confesion' => ['directo' => 1.35, 'timido' => 0.45, 'reservado' => 0.55, 'sociable' => 1.15],
        'coqueteo' => ['sociable' => 1.3, 'vanidoso' => 1.2, 'timido' => 0.5, 'reservado' => 0.6, 'bromista' => 1.15],
        'crisis' => ['ansioso' => 1.35, 'nervioso' => 1.25, 'tranquilo' => 0.7, 'leal' => 0.85],
        'reconciliacion' => ['empatico' => 1.3, 'cabezota' => 0.55, 'leal' => 1.2],
        'inicio_pareja' => ['leal' => 1.15, 'timido' => 0.85, 'directo' => 1.1],
    ],
];

// Mantener flags: crisis/ruptura siguen NUNCA solo por umbral; el motor usa tirada.
$j['crisis']['nunca_auto_por_umbral'] = true;
$j['crisis']['_nota_hitos'] = 'Habilitada por causas + tirada (HitoRelacionalEngine), no por cruzar estabilidad.';
$j['ruptura']['nunca_auto_por_umbral'] = true;
$j['pareja']['nunca_auto_por_umbral'] = true;
$j['pareja']['_nota_hitos'] = 'inicio_pareja solo vía hito con trayectoria + aceptación + tirada.';

$out = json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
file_put_contents($path, $out);
echo "OK hitos_relacionales merge\n";
