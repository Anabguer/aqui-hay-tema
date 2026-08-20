<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Laboratorio de economía (ANÁLISIS). No escribe partidas. No enciende economy_enabled.
 * Cifras = hipótesis de lab en unidades abstractas U. No canonizar.
 */
final class SimuladorEconomia
{
    public const PERFILES = ['A', 'B', 'C', 'D', 'E', 'F'];
    public const HORIZONTES = [30, 100, 365];
    public const MODELOS = ['generosa', 'media', 'lenta'];

    /**
     * @param list<int> $horizontes
     * @return array<string, mixed>
     */
    public static function ejecutar(
        string $projectRoot,
        array $horizontes = [30, 100, 365],
        int $seeds = 8,
        string $seedBase = 'lab-economia'
    ): array {
        unset($projectRoot);
        $out = [
            '_provisional' => true,
            '_canon' => false,
            '_nota' => 'LAB. Unidad abstracta U. No implementa economía jugable. No canonizar cifras.',
            'seeds' => $seeds,
            'horizontes' => $horizontes,
            'hipotesis' => self::hipotesis(),
            'reglas_cerradas' => self::reglasCerradas(),
            'catalogo_rangos' => self::catalogoBase(),
            'modelos' => [],
        ];

        foreach (self::MODELOS as $modeloId) {
            $modelo = self::modelo($modeloId);
            $porPerfil = [];
            foreach (self::PERFILES as $perfil) {
                $runs = [];
                for ($s = 0; $s < $seeds; $s++) {
                    $rng = new RngService($seedBase . '-' . $modeloId . '-' . $perfil . '-' . $s);
                    $runs[] = self::correr($modelo, $perfil, $horizontes, $rng);
                }
                $porPerfil[$perfil] = [
                    'nombre' => self::nombrePerfil($perfil),
                    'por_horizonte' => self::agregarRuns($runs, $horizontes),
                    'ingresos_teoricos_dia' => self::ingresoTeoricoDia($modelo, $perfil),
                ];
            }
            $out['modelos'][$modeloId] = [
                'id' => $modeloId,
                'nombre' => $modelo['nombre'],
                'ingresos' => $modelo['ingresos'],
                'precios' => $modelo['precios'],
                'gates' => $modelo['gates'],
                'por_perfil' => $porPerfil,
                'lectura' => self::lecturaModelo($modeloId, $porPerfil, $modelo),
            ];
        }

        $out['comparativa'] = self::comparativa($out);
        $out['riesgos'] = self::riesgos($out);
        $out['recomendacion'] = self::recomendar($out);
        $out['bloqueado_decision'] = self::bloqueadoDecision();
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function reglasCerradas(): array
    {
        return [
            'principio' => 'DINERO = POSIBILIDADES. No supervivencia, no tycoon, no facturas/alquiler/salarios/mantenimiento.',
            'citas_sin_dinero' => 'Encuentros/citas normales no dan dinero por celebrarse.',
            'no_cita_premium' => 'Descartada la cita premium que compra probabilidad romántica.',
            'fuentes_concepto' => [
                'renta_periodica_por_banda_vida' => 'cerrada como TIPO de fuente; cifras no',
                'recompensa_3_misiones_diarias' => 'cerrada como TIPO de fuente; cifras no; B3 Vida no se retoca',
                'encargos_extra_opcionales' => 'cerrada como TIPO de fuente; no alimentan Vida ni Latidos',
            ],
            'no_fuentes' => [
                'bingo_del_residente' => 'el premio pertenece al residente/evento, no a Celestine',
                'goteo_laboral_por_residente' => 'candidato histórico en docs viejos; NO está en el bloque económico 19/08 del Maestro',
            ],
            'sumideros_concepto' => [
                'lugares', 'ampliaciones', 'bloque_B', 'bloque_C',
                'experiencias_actividades_especiales', 'escapadas', 'viajes',
                'deseos', 'regalos_cumpleanos',
            ],
            'bloques' => 'A 16 + B 16 + C 16 = 48. B/C exigen tiempo + ocupación + dinero. El dinero solo no basta.',
            'llegadas' => 'Presión por huecos. Comprar B/C reabre llegadas. Vacío no daña Vida.',
            'complejos_20_08' => [
                'Cafe & Libros: Cafeteria → Biblioteca → Tienda',
                'El Rincon de Lola: Restaurante → Bingo',
                'Cine Game: Cine → Arcade',
                'La Mala Idea: Bar → Discoteca → Karaoke',
                'Parque: Parque → Picnic → Mirador',
                'Gimnasio & Spa: Gimnasio → Spa',
            ],
            'dia_1_playtest' => 'Solo cafetería operativa el día 1 (primera prueba). Economía jugable OFF.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function hipotesis(): array
    {
        return [
            '_etiqueta' => 'HIPOTESIS_DE_LAB',
            'unidad' => 'U abstracta. No es un euro. 1 misión diaria cumplida = M del modelo.',
            'misiones' => 'p_cumplir reutiliza el lab B3 (A 1.00 / B 0.55 / C 0.22) y se adapta a D/E/F. Máx. 3/día. Caducadas no pagan.',
            'encargos' => 'Cola máx. 2. Tope diario independiente del número de edificios (evita farmear desbloqueos). 1 hueco si <3 complejos fase 1; 2 si ≥3.',
            'renta_vida' => 'Diaria, también si la jugadora es pasiva (el reloj sigue). Bandas sintéticas, no el ledger B1 real.',
            'latido_dinero' => 'NO entra en la simulación principal. El Maestro no lo lista como fuente. Ver propuestas.',
            'gates_B' => 'día≥50 AND residentes≥12 AND dinero≥precio_B',
            'gates_C' => 'día≥160 AND residentes≥26 AND dinero≥precio_C',
            'reserva_B_C' => 'Los perfiles A/B/D/F reservan el precio de B/C al acercarse al gate (la UI lo anunciaría). C y E no planifican: gastan lo que hay.',
            'llegadas' => 'p ≈ min(0.45, 0.08+0.04*huecos) por día si no hay cooldown. Aceptación según perfil.',
            'parque_fase1' => 'Se compra (no es público gratis). Plaza y Casa se tratan como siempre disponibles y sin coste.',
            'mirador_hito' => 'Además del precio: primera cita positiva. Día del hito según perfil (no se simula el relacional V1).',
            'experiencias' => 'Oportunidades periódicas, no un catálogo cerrado. Escapada ~cada 12d desde d10; viaje ~cada 70d desde d40; actividad ~cada 8d; regalos ≈ n/365 por día.',
            'no_retoca' => 'B1 Vida, B3 misiones (Vida), E3 peticiones, relacional V1, B5, UI Carlos II, assets Carlos III.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function modelo(string $id): array
    {
        $precios = [
            'lugar_fase1' => 160,
            'ampliacion' => 200,
            'ampliacion_premium' => 280,
            'bloque_b' => 480,
            'bloque_c' => 880,
            'escapada' => 70,
            'viaje' => 350,
            'actividad' => 20,
            'regalo' => 12,
        ];
        $gates = [
            'b_dia_min' => 50,
            'b_ocupacion_min' => 12,
            'c_dia_min' => 160,
            'c_residentes_min' => 26,
        ];

        if ($id === 'generosa') {
            $ing = [
                'mision' => 12,
                'renta_alta' => 14,
                'renta_media' => 9,
                'renta_baja' => 4,
                'encargo' => 16,
            ];
            $nombre = 'Economía generosa';
        } elseif ($id === 'lenta') {
            $ing = [
                'mision' => 5,
                'renta_alta' => 5,
                'renta_media' => 3,
                'renta_baja' => 1,
                'encargo' => 6,
            ];
            $nombre = 'Economía más lenta';
        } else {
            $ing = [
                'mision' => 8,
                'renta_alta' => 10,
                'renta_media' => 6,
                'renta_baja' => 2,
                'encargo' => 10,
            ];
            $nombre = 'Economía media';
        }

        return [
            'id' => $id,
            'nombre' => $nombre,
            'ingresos' => $ing,
            'precios' => $precios,
            'gates' => $gates,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function catalogoBase(): array
    {
        return [
            ['id' => 'fase1_restaurante', 'cat' => 'lugar', 'nombre' => 'Restaurante (Rincón de Lola)', 'tier' => 'fase1', 'req' => null],
            ['id' => 'fase1_cine', 'cat' => 'lugar', 'nombre' => 'Cine (Cine Game)', 'tier' => 'fase1', 'req' => null],
            ['id' => 'fase1_bar', 'cat' => 'lugar', 'nombre' => 'Bar (La Mala Idea)', 'tier' => 'fase1', 'req' => null],
            ['id' => 'fase1_parque', 'cat' => 'lugar', 'nombre' => 'Parque', 'tier' => 'fase1', 'req' => null],
            ['id' => 'fase1_gimnasio', 'cat' => 'lugar', 'nombre' => 'Gimnasio', 'tier' => 'fase1', 'req' => null],
            ['id' => 'amp_biblioteca', 'cat' => 'ampliacion', 'nombre' => 'Biblioteca', 'tier' => 'amp', 'req' => 'start_cafeteria'],
            ['id' => 'amp_tienda', 'cat' => 'ampliacion', 'nombre' => 'Tienda', 'tier' => 'amp', 'req' => 'amp_biblioteca'],
            ['id' => 'amp_bingo', 'cat' => 'ampliacion', 'nombre' => 'Bingo', 'tier' => 'amp', 'req' => 'fase1_restaurante'],
            ['id' => 'amp_arcade', 'cat' => 'ampliacion', 'nombre' => 'Arcade', 'tier' => 'amp', 'req' => 'fase1_cine'],
            ['id' => 'amp_discoteca', 'cat' => 'ampliacion', 'nombre' => 'Discoteca', 'tier' => 'amp', 'req' => 'fase1_bar'],
            ['id' => 'amp_picnic', 'cat' => 'ampliacion', 'nombre' => 'Picnic', 'tier' => 'amp', 'req' => 'fase1_parque'],
            ['id' => 'amp_karaoke', 'cat' => 'ampliacion', 'nombre' => 'Karaoke', 'tier' => 'premium', 'req' => 'amp_discoteca'],
            ['id' => 'amp_spa', 'cat' => 'ampliacion', 'nombre' => 'Spa', 'tier' => 'premium', 'req' => 'fase1_gimnasio'],
            ['id' => 'amp_mirador', 'cat' => 'ampliacion', 'nombre' => 'Mirador', 'tier' => 'premium', 'req' => 'amp_picnic', 'hito' => 'cita_positiva'],
        ];
    }

    public static function nombrePerfil(string $perfil): string
    {
        $n = [
            'A' => 'Excelente / activa',
            'B' => 'Normal',
            'C' => 'Casual / poco activa',
            'D' => 'Ahorradora',
            'E' => 'Gastadora',
            'F' => 'Intenta farmear dinero',
        ];
        return $n[$perfil] ?? $perfil;
    }

    /**
     * @return array<string, mixed>
     */
    public static function paramsPerfil(string $perfil): array
    {
        $base = [
            'p_mision' => 0.55,
            'p_encargo' => 0.35,
            'p_aceptar' => 0.85,
            'banda' => 'media',
            'p_escapada' => 0.50,
            'p_viaje' => 0.30,
            'p_actividad' => 0.25,
            'p_regalo' => 0.40,
            'compra_estructural' => true,
            'reserva_factor' => 1.05,
            'prioriza_fase1' => true,
            'gasta_experiencias' => true,
            'reserva_bloques' => true,
        ];
        if ($perfil === 'A') {
            return array_merge($base, [
                'p_mision' => 1.0,
                'p_encargo' => 0.85,
                'p_aceptar' => 0.95,
                'banda' => 'alta',
                'p_escapada' => 0.75,
                'p_viaje' => 0.55,
                'p_actividad' => 0.45,
                'p_regalo' => 0.75,
                'reserva_factor' => 1.0,
            ]);
        }
        if ($perfil === 'C') {
            return array_merge($base, [
                'p_mision' => 0.22,
                'p_encargo' => 0.05,
                'p_aceptar' => 0.40,
                'banda' => 'baja',
                'p_escapada' => 0.12,
                'p_viaje' => 0.04,
                'p_actividad' => 0.06,
                'p_regalo' => 0.10,
                'reserva_factor' => 1.35,
                'reserva_bloques' => false,
            ]);
        }
        if ($perfil === 'D') {
            return array_merge($base, [
                'p_mision' => 0.55,
                'p_encargo' => 0.20,
                'p_aceptar' => 0.80,
                'banda' => 'media',
                'p_escapada' => 0.0,
                'p_viaje' => 0.0,
                'p_actividad' => 0.0,
                'p_regalo' => 0.0,
                'compra_estructural' => false,
                'gasta_experiencias' => false,
                'reserva_bloques' => true,
            ]);
        }
        if ($perfil === 'E') {
            return array_merge($base, [
                'p_mision' => 0.70,
                'p_encargo' => 0.40,
                'p_aceptar' => 0.80,
                'banda' => 'media',
                'p_escapada' => 0.95,
                'p_viaje' => 0.90,
                'p_actividad' => 0.80,
                'p_regalo' => 0.90,
                'reserva_factor' => 1.0,
                'reserva_bloques' => false,
            ]);
        }
        if ($perfil === 'F') {
            return array_merge($base, [
                'p_mision' => 1.0,
                'p_encargo' => 1.0,
                'p_aceptar' => 0.98,
                'banda' => 'alta',
                'p_escapada' => 0.0,
                'p_viaje' => 0.0,
                'p_actividad' => 0.0,
                'p_regalo' => 0.0,
                'gasta_experiencias' => false,
                'reserva_bloques' => true,
                'reserva_factor' => 1.0,
                'prioriza_fase1' => false,
            ]);
        }
        return $base;
    }

    /**
     * @return array<string, int>
     */
    public static function ingresoTeoricoDia(array $modelo, string $perfil): array
    {
        $p = self::paramsPerfil($perfil);
        $ing = $modelo['ingresos'];
        $misiones = 3.0 * (float) $p['p_mision'] * (int) $ing['mision'];
        $renta = (int) $ing['renta_media'];
        if ($p['banda'] === 'alta') {
            $renta = (int) $ing['renta_alta'];
        } elseif ($p['banda'] === 'baja') {
            $renta = (int) $ing['renta_baja'];
        }
        $topeEncargos = 1.5;
        $encargos = $topeEncargos * (float) $p['p_encargo'] * (int) $ing['encargo'];
        $total = $misiones + $renta + $encargos;
        return [
            'misiones' => (int) round($misiones),
            'renta' => $renta,
            'encargos' => (int) round($encargos),
            'total' => (int) round($total),
        ];
    }

    /**
     * @param list<int> $horizontes
     * @return array<string, mixed>
     */
    public static function correr(array $modelo, string $perfil, array $horizontes, RngService $rng): array
    {
        $params = self::paramsPerfil($perfil);
        $ing = $modelo['ingresos'];
        $pre = $modelo['precios'];
        $gates = $modelo['gates'];
        $maxH = 0;
        foreach ($horizontes as $h) {
            if ((int) $h > $maxH) {
                $maxH = (int) $h;
            }
        }

        $owned = ['start_cafeteria' => true];
        $gold = 0;
        $earned = 0;
        $spent = 0;
        $n = 8;
        $cap = 16;
        $bloqueB = false;
        $bloqueC = false;
        $diaB = null;
        $diaC = null;
        $bLock = null;
        $cLock = null;
        $cooldownLlegada = 0;
        $hitoCita = false;
        $diaHitoCita = $perfil === 'C' ? 40 : 14;
        if ($perfil === 'F' || $perfil === 'A') {
            $diaHitoCita = 10;
        }

        $acc = [
            'misiones_ok' => 0,
            'encargos_ok' => 0,
            'renta' => 0,
            'lugares' => 0,
            'ampliaciones' => 0,
            'escapadas' => 0,
            'viajes' => 0,
            'actividades' => 0,
            'regalos' => 0,
            'gasto_lugares' => 0,
            'gasto_amp' => 0,
            'gasto_bloques' => 0,
            'gasto_exp' => 0,
        ];
        $proxEscapada = 10;
        $proxViaje = 40;
        $proxAct = 8;
        $snap = [];

        for ($dia = 1; $dia <= $maxH; $dia++) {
            if ($dia >= $diaHitoCita) {
                $hitoCita = true;
            }

            $rentaHoy = (int) $ing['renta_media'];
            if ($params['banda'] === 'alta') {
                $rentaHoy = (int) $ing['renta_alta'];
            } elseif ($params['banda'] === 'baja') {
                $rentaHoy = (int) $ing['renta_baja'];
            }
            $gold += $rentaHoy;
            $earned += $rentaHoy;
            $acc['renta'] += $rentaHoy;

            $mOk = 0;
            for ($i = 0; $i < 3; $i++) {
                if ($rng->nextFloat() < (float) $params['p_mision']) {
                    $mOk++;
                }
            }
            $payM = $mOk * (int) $ing['mision'];
            $gold += $payM;
            $earned += $payM;
            $acc['misiones_ok'] += $mOk;

            $fase1 = self::contarOwned($owned, 'fase1');
            $topeEnc = $fase1 >= 3 ? 2 : 1;
            $encOk = 0;
            for ($i = 0; $i < $topeEnc; $i++) {
                if ($rng->nextFloat() < (float) $params['p_encargo']) {
                    $encOk++;
                }
            }
            $payE = $encOk * (int) $ing['encargo'];
            $gold += $payE;
            $earned += $payE;
            $acc['encargos_ok'] += $encOk;

            if ($cooldownLlegada > 0) {
                $cooldownLlegada--;
            } else {
                $huecos = max(0, $cap - $n);
                $pCand = $huecos <= 0 ? 0.0 : min(0.45, 0.08 + 0.04 * $huecos);
                if ($huecos > 0 && $rng->nextFloat() < $pCand) {
                    if ($rng->nextFloat() < (float) $params['p_aceptar']) {
                        $n++;
                    }
                    $cooldownLlegada = $rng->nextFloat() < 0.40 ? 1 : 0;
                }
            }

            $reserva = 0;
            if (!empty($params['reserva_bloques'])) {
                if (!$bloqueB && $dia >= ((int) $gates['b_dia_min'] - 12)) {
                    $reserva += (int) $pre['bloque_b'];
                }
                if ($bloqueB && !$bloqueC && $dia >= ((int) $gates['c_dia_min'] - 20)) {
                    $reserva += (int) $pre['bloque_c'];
                }
            }

            if (!empty($params['gasta_experiencias'])) {
                if ($dia >= $proxEscapada) {
                    $disp = $gold - $reserva;
                    if ($disp >= (int) $pre['escapada'] && $rng->nextFloat() < (float) $params['p_escapada']) {
                        $gold -= (int) $pre['escapada'];
                        $spent += (int) $pre['escapada'];
                        $acc['escapadas']++;
                        $acc['gasto_exp'] += (int) $pre['escapada'];
                    }
                    $proxEscapada = $dia + 12;
                }
                if ($dia >= $proxViaje) {
                    $disp = $gold - $reserva;
                    if ($disp >= (int) $pre['viaje'] && $rng->nextFloat() < (float) $params['p_viaje']) {
                        $gold -= (int) $pre['viaje'];
                        $spent += (int) $pre['viaje'];
                        $acc['viajes']++;
                        $acc['gasto_exp'] += (int) $pre['viaje'];
                    }
                    $proxViaje = $dia + 70;
                }
                if ($dia >= $proxAct) {
                    $disp = $gold - $reserva;
                    if ($disp >= (int) $pre['actividad'] && $rng->nextFloat() < (float) $params['p_actividad']) {
                        $gold -= (int) $pre['actividad'];
                        $spent += (int) $pre['actividad'];
                        $acc['actividades']++;
                        $acc['gasto_exp'] += (int) $pre['actividad'];
                    }
                    $proxAct = $dia + 8;
                }
                if ($n > 0 && $rng->nextFloat() < ($n / 365.0) && $gold - $reserva >= (int) $pre['regalo'] && $rng->nextFloat() < (float) $params['p_regalo']) {
                    $gold -= (int) $pre['regalo'];
                    $spent += (int) $pre['regalo'];
                    $acc['regalos']++;
                    $acc['gasto_exp'] += (int) $pre['regalo'];
                }
            }

            $bOkT = $dia >= (int) $gates['b_dia_min'];
            $bOkO = $n >= (int) $gates['b_ocupacion_min'];
            $bOkM = $gold >= (int) $pre['bloque_b'];
            if (!$bloqueB) {
                if ($bOkT && $bOkO && $bOkM) {
                    $gold -= (int) $pre['bloque_b'];
                    $spent += (int) $pre['bloque_b'];
                    $acc['gasto_bloques'] += (int) $pre['bloque_b'];
                    $bloqueB = true;
                    $diaB = $dia;
                    $cap = 32;
                } elseif ($dia === (int) $gates['b_dia_min'] || ($dia > (int) $gates['b_dia_min'] && $bLock === null && $bOkT)) {
                    if (!$bOkO) {
                        $bLock = 'ocupacion';
                    } elseif (!$bOkM) {
                        $bLock = 'dinero';
                    } else {
                        $bLock = 'tiempo';
                    }
                }
            }

            $cOkT = $dia >= (int) $gates['c_dia_min'];
            $cOkO = $n >= (int) $gates['c_residentes_min'];
            $cOkM = $gold >= (int) $pre['bloque_c'];
            if ($bloqueB && !$bloqueC) {
                if ($cOkT && $cOkO && $cOkM) {
                    $gold -= (int) $pre['bloque_c'];
                    $spent += (int) $pre['bloque_c'];
                    $acc['gasto_bloques'] += (int) $pre['bloque_c'];
                    $bloqueC = true;
                    $diaC = $dia;
                    $cap = 48;
                } elseif ($cOkT && $cLock === null) {
                    if (!$cOkO) {
                        $cLock = 'ocupacion';
                    } elseif (!$cOkM) {
                        $cLock = 'dinero';
                    } else {
                        $cLock = 'tiempo';
                    }
                }
            }

            if (!empty($params['compra_estructural']) || $perfil === 'D') {
                $guard = 0;
                while ($guard < 6) {
                    $guard++;
                    $item = self::siguienteCompra($owned, $hitoCita, $params, $pre);
                    if ($item === null) {
                        break;
                    }
                    if ($perfil === 'D' && ($item['cat'] === 'lugar' || $item['cat'] === 'ampliacion')) {
                        break;
                    }
                    $need = (int) $item['precio'] * (float) $params['reserva_factor'];
                    $disp = $gold - $reserva;
                    if ($disp < $need) {
                        break;
                    }
                    $gold -= (int) $item['precio'];
                    $spent += (int) $item['precio'];
                    $owned[$item['id']] = true;
                    if ($item['cat'] === 'lugar') {
                        $acc['lugares']++;
                        $acc['gasto_lugares'] += (int) $item['precio'];
                    } else {
                        $acc['ampliaciones']++;
                        $acc['gasto_amp'] += (int) $item['precio'];
                    }
                }
            }

            foreach ($horizontes as $h) {
                $h = (int) $h;
                if ($dia === $h) {
                    $snap[(string) $h] = self::snapshot(
                        $gold,
                        $earned,
                        $spent,
                        $n,
                        $cap,
                        $bloqueB,
                        $bloqueC,
                        $diaB,
                        $diaC,
                        $bLock,
                        $cLock,
                        $owned,
                        $acc,
                        $fase1 = self::contarOwned($owned, 'fase1')
                    );
                }
            }
        }

        if ($bLock === null && $diaB === null) {
            $bLock = 'no_alcanzado';
        }
        if ($cLock === null && $diaC === null) {
            $cLock = 'no_alcanzado';
        }

        return [
            'snapshots' => $snap,
            'dia_bloque_b' => $diaB,
            'dia_bloque_c' => $diaC,
            'bloqueo_b' => $diaB === null ? $bLock : 'ok',
            'bloqueo_c' => $diaC === null ? $cLock : 'ok',
        ];
    }

    /**
     * @param array<string, bool> $owned
     * @param array<string, mixed> $params
     * @param array<string, int> $pre
     * @return array<string, mixed>|null
     */
    private static function siguienteCompra(array $owned, bool $hitoCita, array $params, array $pre): ?array
    {
        $cands = [];
        foreach (self::catalogoBase() as $it) {
            if (!empty($owned[$it['id']])) {
                continue;
            }
            $req = $it['req'] ?? null;
            if ($req !== null && empty($owned[$req])) {
                continue;
            }
            if (($it['hito'] ?? null) === 'cita_positiva' && !$hitoCita) {
                continue;
            }
            $precio = (int) $pre['lugar_fase1'];
            if (($it['tier'] ?? '') === 'amp') {
                $precio = (int) $pre['ampliacion'];
            } elseif (($it['tier'] ?? '') === 'premium') {
                $precio = (int) $pre['ampliacion_premium'];
            }
            $it['precio'] = $precio;
            $cands[] = $it;
        }
        if ($cands === []) {
            return null;
        }
        if (!empty($params['prioriza_fase1'])) {
            $fase1 = [];
            foreach ($cands as $c) {
                if (($c['tier'] ?? '') === 'fase1') {
                    $fase1[] = $c;
                }
            }
            if ($fase1 !== []) {
                $cands = $fase1;
            }
        }
        usort($cands, static function ($a, $b) {
            return $a['precio'] <=> $b['precio'];
        });
        return $cands[0];
    }

    /**
     * @param array<string, bool> $owned
     */
    private static function contarOwned(array $owned, string $tier): int
    {
        $n = 0;
        foreach (self::catalogoBase() as $it) {
            if (($it['tier'] ?? '') === $tier && !empty($owned[$it['id']])) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * @param array<string, mixed> $acc
     * @param array<string, bool> $owned
     * @return array<string, mixed>
     */
    private static function snapshot(
        int $gold,
        int $earned,
        int $spent,
        int $n,
        int $cap,
        bool $bloqueB,
        bool $bloqueC,
        $diaB,
        $diaC,
        $bLock,
        $cLock,
        array $owned,
        array $acc,
        int $fase1
    ): array {
        $amps = 0;
        foreach (self::catalogoBase() as $it) {
            if (($it['cat'] ?? '') === 'ampliacion' && !empty($owned[$it['id']])) {
                $amps++;
            }
        }
        $totalCat = count(self::catalogoBase());
        $ownedN = 0;
        foreach (self::catalogoBase() as $it) {
            if (!empty($owned[$it['id']])) {
                $ownedN++;
            }
        }
        return [
            'gold' => $gold,
            'earned' => $earned,
            'spent' => $spent,
            'residentes' => $n,
            'capacidad' => $cap,
            'bloque_b' => $bloqueB,
            'bloque_c' => $bloqueC,
            'dia_bloque_b' => $diaB,
            'dia_bloque_c' => $diaC,
            'bloqueo_b' => $bloqueB ? 'ok' : $bLock,
            'bloqueo_c' => $bloqueC ? 'ok' : $cLock,
            'lugares_fase1' => $fase1,
            'ampliaciones' => $amps,
            'catalogo_owned' => $ownedN,
            'catalogo_total' => $totalCat,
            'misiones_ok' => $acc['misiones_ok'],
            'encargos_ok' => $acc['encargos_ok'],
            'escapadas' => $acc['escapadas'],
            'viajes' => $acc['viajes'],
            'actividades' => $acc['actividades'],
            'regalos' => $acc['regalos'],
            'gasto_lugares' => $acc['gasto_lugares'],
            'gasto_amp' => $acc['gasto_amp'],
            'gasto_bloques' => $acc['gasto_bloques'],
            'gasto_exp' => $acc['gasto_exp'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @param list<int> $horizontes
     * @return array<string, mixed>
     */
    private static function agregarRuns(array $runs, array $horizontes): array
    {
        $out = [];
        foreach ($horizontes as $h) {
            $h = (int) $h;
            $keys = [
                'gold', 'earned', 'spent', 'residentes', 'capacidad',
                'lugares_fase1', 'ampliaciones', 'catalogo_owned',
                'misiones_ok', 'encargos_ok', 'escapadas', 'viajes',
                'actividades', 'regalos', 'gasto_lugares', 'gasto_amp',
                'gasto_bloques', 'gasto_exp',
            ];
            $agg = [];
            foreach ($keys as $k) {
                $agg[$k] = 0.0;
            }
            $nB = 0;
            $nC = 0;
            $sumB = 0;
            $sumC = 0;
            $locksB = [];
            $locksC = [];
            $n = count($runs);
            foreach ($runs as $run) {
                $s = $run['snapshots'][(string) $h] ?? [];
                foreach ($keys as $k) {
                    $agg[$k] += (float) ($s[$k] ?? 0);
                }
                if (!empty($s['bloque_b']) && $s['dia_bloque_b'] !== null) {
                    $nB++;
                    $sumB += (int) $s['dia_bloque_b'];
                }
                if (!empty($s['bloque_c']) && $s['dia_bloque_c'] !== null) {
                    $nC++;
                    $sumC += (int) $s['dia_bloque_c'];
                }
                $lb = (string) ($s['bloqueo_b'] ?? 'na');
                $lc = (string) ($s['bloqueo_c'] ?? 'na');
                $locksB[$lb] = ($locksB[$lb] ?? 0) + 1;
                $locksC[$lc] = ($locksC[$lc] ?? 0) + 1;
            }
            foreach ($keys as $k) {
                $agg[$k] = $n > 0 ? round($agg[$k] / $n, 1) : 0;
            }
            $agg['pct_bloque_b'] = $n > 0 ? round(100.0 * $nB / $n, 0) : 0;
            $agg['pct_bloque_c'] = $n > 0 ? round(100.0 * $nC / $n, 0) : 0;
            $agg['dia_bloque_b_media'] = $nB > 0 ? (int) round($sumB / $nB) : null;
            $agg['dia_bloque_c_media'] = $nC > 0 ? (int) round($sumC / $nC) : null;
            $agg['bloqueo_b'] = self::modo($locksB);
            $agg['bloqueo_c'] = self::modo($locksC);
            $ingDia = $h > 0 ? round($agg['earned'] / $h, 1) : 0;
            $agg['ingreso_dia'] = $ingDia;
            $agg['sobrante_sobre_ingreso_anual'] = $ingDia > 0 ? round($agg['gold'] / max(1.0, $ingDia * 30.0), 2) : 0;
            $out[(string) $h] = $agg;
        }
        return $out;
    }

    /**
     * @param array<string, int> $freq
     */
    private static function modo(array $freq): string
    {
        $best = 'na';
        $n = -1;
        foreach ($freq as $k => $v) {
            if ($v > $n) {
                $n = $v;
                $best = $k;
            }
        }
        return $best;
    }

    /**
     * @param array<string, mixed> $porPerfil
     * @param array<string, mixed> $modelo
     * @return array<string, mixed>
     */
    private static function lecturaModelo(string $id, array $porPerfil, array $modelo): array
    {
        $b = $porPerfil['B']['por_horizonte']['365'] ?? [];
        $a = $porPerfil['A']['por_horizonte']['365'] ?? [];
        $c = $porPerfil['C']['por_horizonte']['365'] ?? [];
        $f = $porPerfil['F']['por_horizonte']['365'] ?? [];
        $d = $porPerfil['D']['por_horizonte']['365'] ?? [];
        $e = $porPerfil['E']['por_horizonte']['365'] ?? [];
        $b100 = $porPerfil['B']['por_horizonte']['100'] ?? [];
        $f30 = $porPerfil['F']['por_horizonte']['30'] ?? [];
        $f100 = $porPerfil['F']['por_horizonte']['100'] ?? [];
        $pre = $modelo['precios'];
        $ingB = (float) ($porPerfil['B']['ingresos_teoricos_dia']['total'] ?? 1);
        return [
            'ingreso_normal_dia' => $porPerfil['B']['ingresos_teoricos_dia']['total'] ?? null,
            'dias_para_lugar' => $ingB > 0 ? round($pre['lugar_fase1'] / $ingB, 1) : null,
            'dias_para_amp' => $ingB > 0 ? round($pre['ampliacion'] / $ingB, 1) : null,
            'dias_para_amp_premium' => $ingB > 0 ? round($pre['ampliacion_premium'] / $ingB, 1) : null,
            'dias_para_escapada' => $ingB > 0 ? round($pre['escapada'] / $ingB, 1) : null,
            'dias_para_viaje' => $ingB > 0 ? round($pre['viaje'] / $ingB, 1) : null,
            'dias_para_B_solo_dinero' => $ingB > 0 ? round($pre['bloque_b'] / $ingB, 1) : null,
            'dias_para_C_solo_dinero' => $ingB > 0 ? round($pre['bloque_c'] / $ingB, 1) : null,
            'normal_B_dia' => $b['dia_bloque_b_media'] ?? null,
            'normal_C_dia' => $b['dia_bloque_c_media'] ?? null,
            'normal_B_pct_100d' => $b100['pct_bloque_b'] ?? null,
            'excelente_B_dia' => $a['dia_bloque_b_media'] ?? null,
            'excelente_C_dia' => $a['dia_bloque_c_media'] ?? null,
            'farmer_B_30d' => $f30['pct_bloque_b'] ?? null,
            'farmer_B_100d' => $f100['pct_bloque_b'] ?? null,
            'farmer_C_dia' => $f['dia_bloque_c_media'] ?? null,
            'casual_B_pct_365' => $c['pct_bloque_b'] ?? null,
            'ahorradora_gold_365' => $d['gold'] ?? null,
            'gastadora_catalogo_365' => $e['catalogo_owned'] ?? null,
            'normal_catalogo_365' => $b['catalogo_owned'] ?? null,
            'farmer_gold_365' => $f['gold'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $lab
     * @return array<string, mixed>
     */
    private static function comparativa(array $lab): array
    {
        $rows = [];
        foreach (self::MODELOS as $m) {
            $lec = $lab['modelos'][$m]['lectura'] ?? [];
            $rows[$m] = $lec;
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $lab
     * @return array<string, mixed>
     */
    private static function riesgos(array $lab): array
    {
        $media = $lab['modelos']['media'] ?? [];
        $f30 = $media['por_perfil']['F']['por_horizonte']['30'] ?? [];
        $f365 = $media['por_perfil']['F']['por_horizonte']['365'] ?? [];
        $c100 = $media['por_perfil']['C']['por_horizonte']['100'] ?? [];
        $d365 = $media['por_perfil']['D']['por_horizonte']['365'] ?? [];
        $a365 = $media['por_perfil']['A']['por_horizonte']['365'] ?? [];
        $e365 = $media['por_perfil']['E']['por_horizonte']['365'] ?? [];
        return [
            'farmer_compra_B_antes_de_gate' => ((int) ($f30['pct_bloque_b'] ?? 0) > 0),
            'farmer_acumula_tras_catalogo' => ((float) ($f365['gold'] ?? 0) > 2000),
            'casual_bloqueada_100d' => ((float) ($c100['lugares_fase1'] ?? 0) < 1),
            'ahorradora_montaña_dinero' => ((float) ($d365['gold'] ?? 0) > 4000),
            'excelente_dinero_irrelevante_365' => ((float) ($a365['catalogo_owned'] ?? 0) >= 13 && (float) ($a365['gold'] ?? 0) > 1500),
            'gastadora_se_queda_sin_B' => ((int) ($e365['pct_bloque_b'] ?? 100) < 80),
            'grind_de_citas' => false,
            'nota_grind_citas' => 'Cerrado: las citas no pagan. El único grind posible es encargos extra, topeados a 1–2/día.',
        ];
    }

    /**
     * @param array<string, mixed> $lab
     * @return array<string, mixed>
     */
    private static function recomendar(array $lab): array
    {
        unset($lab);
        return [
            'modelo' => 'media',
            'por_que' => [
                'La generosa deja el dinero irrelevante antes del primer año en perfiles activos.',
                'La lenta convierte una mala racha de misiones en sequía de contenido para la casual.',
                'La media hace que un lugar cueste ~una semana de juego normal, una ampliación ~9 días, una escapada ~3 días y un viaje ~2 semanas. Duele sin bloquear.',
                'B queda anclado al gate temporal (~día 50), no al farmeo. C al gate ~día 160 + ocupación de B.',
                'Los encargos extra aceleran compras de lugares/experiencias, no saltan A→B→C.',
            ],
            'siguiente_paso' => 'Neni + ChatGPT eligen modelo y cierran gates B/C. Después, y solo después, implementar ledger con esas bandas. No B5.',
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function bloqueadoDecision(): array
    {
        return [
            [
                'id' => 'gates_B_C',
                'pregunta' => '¿Confirmáis gates de lab B: día ≥ 50 y ≥ 12/16 en A; C: día ≥ 160 y ≥ 26 residentes (≈10 plazas de B ocupadas)? El Maestro deja exactos pendientes.',
            ],
            [
                'id' => 'renta_offline',
                'pregunta' => '¿La renta periódica por banda de Vida llega cada día de calendario del pueblo aunque Celestine no juegue (buzón de ingresos), o solo en días con sesión?',
            ],
            [
                'id' => 'parque_inicial',
                'pregunta' => '¿El Parque fase 1 se compra como el resto de complejos, o es espacio público ya abierto el día 1 junto a la cafetería?',
            ],
            [
                'id' => 'fama_en_desbloqueos',
                'pregunta' => '¿La fama entra como requisito AND junto al dinero en algún desbloqueo, o se reserva a otra capa y los lugares van solo a dinero + hitos (tipo Mirador)?',
            ],
            [
                'id' => 'latido_paga',
                'pregunta' => '¿Un Latido debe otorgar un bono de dinero (hito raro) o solo Vida/narrativa/buzón? El Maestro no lo lista como fuente.',
            ],
        ];
    }
}
