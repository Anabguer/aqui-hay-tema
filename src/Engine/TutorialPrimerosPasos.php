<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

/**
 * Bienvenida breve + 3 misiones de Primeros pasos (día 1).
 * Sustituye TutorialBucle cuando config tutorial_primeros_pasos.
 */
final class TutorialPrimerosPasos
{
    public const ID = 'primeros_pasos';
    public const M1 = 'pp_romper_hielo';
    public const M2 = 'pp_mensajito';
    public const M3 = 'pp_plan_solo_cine';

    /**
     * @param array<string, mixed> $config
     */
    public static function debeArrancar(array $config): bool
    {
        return !empty($config['tutorial_primeros_pasos']);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function arrancar(array &$partida, array $config, Catalog $catalog): void
    {
        if (!self::debeArrancar($config)) {
            return;
        }
        $ids = self::residentesIniciales($partida);
        if (count($ids) < 3) {
            return;
        }
        $sel = self::elegirParejaMision1($partida, $ids, $catalog);
        $tercero = $sel['tercero'];
        $lugarM3 = 'lug_cine';

        $partida['tutorial'] = [
            'id' => self::ID,
            'activo' => true,
            'jugable_completado' => false,
            'finale_visto' => false,
            'pareja_mision1' => ['a' => $sel['a'], 'b' => $sel['b']],
            'tercero' => $tercero,
            'lugar_mision3' => $lugarM3,
            'mensajito_id' => null,
            'seleccion_pareja' => $sel['auditoria'],
        ];

        self::sembrarMisiones($partida, $catalog);
    }

    /**
     * Si devuelve true, no generar misiones normales.
     */
    public static function sembrarSiToca(array &$partida, string $root): bool
    {
        if (!self::activo($partida)) {
            return false;
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        if ($dia !== 1 || !empty($partida['tutorial']['jugable_completado'])) {
            return false;
        }
        if ((int) ($partida['misiones_diarias']['dia'] ?? 0) === $dia
            && self::tieneMisionesPrimerosPasos($partida)
        ) {
            return true;
        }
        self::sembrarMisiones($partida, new Catalog($root));
        return true;
    }

    public static function bloqueaMisionesNormales(array $partida): bool
    {
        if (!self::activo($partida)) {
            return false;
        }
        if (empty($partida['tutorial']['jugable_completado'])) {
            return true;
        }
        return empty($partida['tutorial']['finale_visto']);
    }

    public static function bloqueaIncorporaciones(array $partida): bool
    {
        if (!self::activo($partida)) {
            return false;
        }
        return empty($partida['tutorial']['jugable_completado']);
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function alProponer(array &$partida, array &$respuestaApi, Catalog $catalog): void
    {
        if (!self::activo($partida) || !empty($partida['tutorial']['jugable_completado'])) {
            return;
        }
        $prop = is_array($respuestaApi['propuesta'] ?? null) ? $respuestaApi['propuesta'] : [];
        $parts = is_array($prop['participantes'] ?? null) ? $prop['participantes'] : [];
        $tipo = (string) ($prop['tipo'] ?? '');
        $lugar = (string) ($prop['lugar'] ?? '');
        $programado = !empty($respuestaApi['programado']) || ($prop['estado'] ?? '') === 'programada';
        $ok = (bool) ($respuestaApi['ok'] ?? false);
        if (!$ok) {
            return;
        }

        $pareja = $partida['tutorial']['pareja_mision1'] ?? [];
        $a = (string) ($pareja['a'] ?? '');
        $b = (string) ($pareja['b'] ?? '');
        $tercero = (string) ($partida['tutorial']['tercero'] ?? '');
        $lugM3 = (string) ($partida['tutorial']['lugar_mision3'] ?? 'lug_cine');

        if ($tipo !== 'individual' && count($parts) === 2) {
            sort($parts);
            $par = [$a, $b];
            sort($par);
            if ($parts === $par) {
                $msgId = self::completarMision($partida, self::M1, $catalog);
                if ($msgId !== '') {
                    $respuestaApi['nuevo_mensajito'] = true;
                    $respuestaApi['mensajito_id'] = $msgId;
                    $respuestaApi['mensajito_aviso_ui'] = 'Tienes un nuevo Mensajito.';
                }
            }
        }
        if ($tipo === 'individual' && count($parts) === 1 && (string) $parts[0] === $tercero && $lugar === $lugM3) {
            if (self::estadoMision($partida, self::M2) === MisionDiariaEngine::EST_CUMPLIDA) {
                self::completarMision($partida, self::M3, $catalog);
            }
        }
    }

    private static function estadoMision(array $partida, string $misionId): string
    {
        foreach ($partida['misiones_diarias']['items'] ?? [] as $m) {
            if (($m['id'] ?? '') === $misionId) {
                return (string) ($m['estado'] ?? '');
            }
        }
        return '';
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function alLeerMensaje(array &$partida, string $mensajeId, Catalog $catalog): void
    {
        if (!self::activo($partida) || !empty($partida['tutorial']['jugable_completado'])) {
            return;
        }
        $mid = (string) ($partida['tutorial']['mensajito_id'] ?? '');
        if ($mid !== '' && $mensajeId === $mid) {
            self::completarMision($partida, self::M2, $catalog);
        }
    }

    public static function marcarFinaleVisto(array &$partida): void
    {
        if (!is_array($partida['tutorial'] ?? null)) {
            return;
        }
        $partida['tutorial']['finale_visto'] = true;
        $partida['tutorial']['activo'] = false;
        if (LabAudit::activaEnRequest()) {
            LabAudit::eventoTutorial($partida, 'FINALE_VISTO', new Catalog(dirname(__DIR__, 2)));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function vistaPublica(array $partida, ?Catalog $catalog = null): array
    {
        if (($partida['tutorial']['id'] ?? '') !== self::ID) {
            return [];
        }
        $catalog ??= new Catalog(dirname(__DIR__, 2));
        $ids = self::residentesIniciales($partida);
        $nombres = array_map(static fn(string $id): string => IdentidadPublica::nombre($partida, $id), $ids);
        $caras = [];
        foreach ($ids as $id) {
            $caras[] = [
                'id' => $id,
                'nombre' => IdentidadPublica::nombre($partida, $id),
                'token_url' => self::tokenUrl($partida, $id, $catalog),
            ];
        }
        $n1 = $nombres[0] ?? 'alguien';
        $n2 = $nombres[1] ?? 'alguien';
        $n3 = $nombres[2] ?? 'alguien';

        return [
            'id' => self::ID,
            'activo' => !empty($partida['tutorial']['activo']),
            'jugable_completado' => !empty($partida['tutorial']['jugable_completado']),
            'finale_pendiente' => !empty($partida['tutorial']['jugable_completado'])
                && empty($partida['tutorial']['finale_visto']),
            'intro' => [
                'pasos' => [
                    [
                        'tit' => 'Bienvenida al pueblo',
                        'txt' => 'Este sitio parece tranquilo. No te fíes.'
                            . "\nAquí viven personas con gustos, manías, crushes, dramas y una capacidad sorprendente para complicarse la vida sin ayuda."
                            . "\nTú no controlas a nadie. Solo observas, propones planes y, de vez en cuando, intentas evitar un desastre sentimental.",
                    ],
                    [
                        'tit' => 'Tus primeros vecinos',
                        'txt' => 'De momento tienes a ' . $n1 . ', ' . $n2 . ' y ' . $n3 . '.'
                            . "\nCada uno ha llegado con su propia personalidad, sus gustos y sus cosas raras. Tú irás descubriendo quién encaja con quién… y quién parecía buena idea hasta que abrió la boca.",
                        'caras' => $caras,
                    ],
                    [
                        'tit' => 'Lo básico',
                        'txt' => "En el mapa puedes ver por dónde anda la gente.\n"
                            . "En Vecinos puedes cotillear sus fichas.\n"
                            . "En Mensajitos te llegarán recados, peticiones y alguna cosa que requerirá atención.\n"
                            . "Y con Nuevo plan puedes proponer que dos personas queden o montar un plan para una sola persona, elegir dónde y cuándo.\n"
                            . 'Ellos luego harán lo que les dé la gana. Como debe ser.',
                    ],
                    [
                        'tit' => 'Empieza por aquí',
                        'txt' => "Ya está. No necesitas un máster.\n"
                            . "Te he dejado tres misiones de Primeros pasos en ‘Hoy en el pueblo’.\n"
                            . 'Hazlas y aprenderás el resto jugando.',
                        'boton_final' => 'A ver qué se cuece',
                    ],
                ],
            ],
            'finale' => [
                'tit' => 'Bueno. Ya sabes lo básico.',
                'txt' => "Ya sabes mirar, cotillear y organizar planes.\n\n"
                    . "Ahora pásate por Vecinos y échales un ojo a sus fichas. Al principio no vas a saberlo todo: cuanto más los conozcas, más irás descubriendo sobre sus hobbies, gustos, manías y demás miserias humanas.\n\n"
                    . "Y por cierto… he oído que pronto llegan nuevos vecinos al pueblo.\n\n"
                    . 'Suerte. La vas a necesitar.',
                'boton' => 'Que empiece el tema',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function residentesIniciales(array $partida): array
    {
        $ids = [];
        foreach ($partida['residentes'] ?? [] as $id => $res) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            if (!is_array($res)) {
                continue;
            }
            if (($res['presencia'] ?? 'residente') !== 'residente') {
                continue;
            }
            $ids[] = $id;
        }
        sort($ids);
        return $ids;
    }

    private static function activo(array $partida): bool
    {
        return is_array($partida['tutorial'] ?? null)
            && ($partida['tutorial']['id'] ?? '') === self::ID;
    }

    private static function tieneMisionesPrimerosPasos(array $partida): bool
    {
        foreach ($partida['misiones_diarias']['items'] ?? [] as $m) {
            if (($m['familia'] ?? '') === 'primeros_pasos') {
                return true;
            }
        }
        return false;
    }

    private static function sembrarMisiones(array &$partida, Catalog $catalog): void
    {
        MisionDiariaEngine::ensure($partida);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $pareja = $partida['tutorial']['pareja_mision1'] ?? [];
        $a = (string) ($pareja['a'] ?? '');
        $b = (string) ($pareja['b'] ?? '');
        $tercero = (string) ($partida['tutorial']['tercero'] ?? '');
        $na = IdentidadPublica::nombre($partida, $a);
        $nb = IdentidadPublica::nombre($partida, $b);
        $nc = IdentidadPublica::nombre($partida, $tercero);

        $partida['misiones_diarias']['dia'] = $dia;
        $partida['misiones_diarias']['items'] = [
            self::filaMision($dia, self::M1, 'Romper el hielo',
                "Empecemos con algo fácil.\nCreo que {$na} y {$nb} podrían aguantar una cita sin provocar una evacuación.\nPropón un plan entre ellos.",
                MisionDiariaEngine::EST_PENDIENTE, 1, [
                    'accion' => 'organizar_pareja',
                    'accion_params' => ['a' => $a, 'b' => $b],
                    'accion_label' => 'Abrir Nuevo Plan',
                ]),
            self::filaMision($dia, self::M2, 'Alguien quiere contarte algo',
                "Te ha llegado un Mensajito.\nSí, ya tienes vecinos escribiéndote. Esto ha escalado rápido.\nÁbrelo y mira qué quieren.",
                'bloqueada', 2, [
                    'accion' => 'buzon',
                    'accion_label' => 'Abrir Mensajitos',
                ]),
            self::filaMision($dia, self::M3, 'Pues habrá que hacerle caso',
                "{$nc} quiere ir al cine. Por una vez alguien ha pedido algo sencillo.\nOrganízale un plan en solitario en el Cine.",
                'bloqueada', 3, [
                    'accion' => 'organizar_solo',
                    'accion_params' => ['a' => $tercero, 'lugar' => 'lug_cine'],
                    'accion_label' => 'Organizar plan en solitario',
                ]),
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function filaMision(int $dia, string $id, string $titulo, string $texto, string $estado, int $orden, array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'plantilla_id' => $id,
            'dia' => $dia,
            'titulo' => $titulo,
            'texto' => $texto,
            'estado' => $estado,
            'familia' => 'primeros_pasos',
            'orden' => $orden,
        ], $extra);
    }

    private static function completarMision(array &$partida, string $misionId, Catalog $catalog): string
    {
        MisionDiariaEngine::ensure($partida);
        foreach ($partida['misiones_diarias']['items'] as $i => $m) {
            if (($m['id'] ?? '') !== $misionId) {
                continue;
            }
            $partida['misiones_diarias']['items'][$i]['estado'] = MisionDiariaEngine::EST_CUMPLIDA;
            break;
        }
        if (LabAudit::activaEnRequest()) {
            LabAudit::eventoTutorial($partida, 'MISION_CUMPLIDA:' . $misionId, $catalog);
        }
        if ($misionId === self::M1) {
            return self::activarMision2($partida, $catalog);
        }
        if ($misionId === self::M2) {
            self::activarMision3($partida);
        } elseif ($misionId === self::M3) {
            self::cerrarJugable($partida, $catalog);
        }
        return '';
    }

    private static function activarMision2(array &$partida, Catalog $catalog): string
    {
        $tercero = (string) ($partida['tutorial']['tercero'] ?? '');
        if ($tercero === '') {
            return '';
        }
        $nombre = IdentidadPublica::nombre($partida, $tercero);
        $msgId = 'msg_pp_' . substr(md5($partida['meta']['partida_id'] . '|' . $tercero), 0, 10);
        $partida['tutorial']['mensajito_id'] = $msgId;
        BuzonEngine::crear($partida, [
            'id' => $msgId,
            'clasificacion' => BuzonEngine::OPORTUNIDAD,
            'tipo' => 'tutorial_primeros_pasos',
            'de_persona' => $tercero,
            'actores' => [$tercero],
            'texto' => $nombre . ' lleva un rato pensando que le apetecería ir al cine. Por si te da por meter las narices.'
                . "\n\nDale a Nuevo plan y elige a " . $nombre . ' para una salida por su cuenta.',
            'origen' => [
                'evento_id' => null,
                'tipo_evento' => DomainEvents::PARTIDA_CREADA,
                'es_narrativo' => false,
                'tutorial' => self::ID,
            ],
        ]);
        foreach ($partida['misiones_diarias']['items'] as $i => $m) {
            if (($m['id'] ?? '') === self::M2) {
                $partida['misiones_diarias']['items'][$i]['estado'] = MisionDiariaEngine::EST_PENDIENTE;
            }
        }
        if (LabAudit::activaEnRequest()) {
            LabAudit::eventoTutorial($partida, 'MENSAJITO_M2', $catalog);
        }
        return $msgId;
    }

    private static function activarMision3(array &$partida): void
    {
        foreach ($partida['misiones_diarias']['items'] as $i => $m) {
            if (($m['id'] ?? '') === self::M3) {
                $partida['misiones_diarias']['items'][$i]['estado'] = MisionDiariaEngine::EST_PENDIENTE;
            }
        }
    }

    private static function cerrarJugable(array &$partida, Catalog $catalog): void
    {
        $partida['tutorial']['jugable_completado'] = true;
        if (!empty($partida['llegadas']['tutorial_cola'])) {
            TutorialIncorporaciones::alCompletarTutorial($partida, $catalog->getRoot());
        }
        if (LabAudit::activaEnRequest()) {
            LabAudit::eventoTutorial($partida, 'JUGABLE_COMPLETADO', $catalog);
        }
    }

    /**
     * @param list<string> $ids
     * @return array{a: string, b: string, tercero: string, auditoria: array<string, mixed>}
     */
    private static function elegirParejaMision1(array $partida, array $ids, Catalog $catalog): array
    {
        $cal = CalibracionConfig::load($catalog->getRoot());
        $ranking = [];
        $n = count($ids);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $ids[$i];
                $b = $ids[$j];
                $ev = self::evaluarPar($partida, $a, $b, $cal, $catalog);
                if (!($ev['elegible'] ?? false)) {
                    continue;
                }
                $ranking[] = $ev;
            }
        }
        usort($ranking, static function ($x, $y) {
            return ((float) ($y['viabilidad_mutua'] ?? 0)) <=> ((float) ($x['viabilidad_mutua'] ?? 0));
        });
        $best = $ranking[0] ?? null;
        if ($best === null) {
            $a = $ids[0];
            $b = $ids[1];
            $tercero = $ids[2];
            return [
                'a' => $a,
                'b' => $b,
                'tercero' => $tercero,
                'auditoria' => ['fallback' => true, 'ranking' => []],
            ];
        }
        $a = (string) $best['a'];
        $b = (string) $best['b'];
        $tercero = '';
        foreach ($ids as $id) {
            if ($id !== $a && $id !== $b) {
                $tercero = $id;
                break;
            }
        }
        return [
            'a' => $a,
            'b' => $b,
            'tercero' => $tercero,
            'auditoria' => ['ranking' => $ranking, 'elegida' => $best],
        ];
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function evaluarPar(array $partida, string $a, string $b, array $cal, Catalog $catalog): array
    {
        $rom = RomanceElegibilidad::par($partida, $a, $b, $cal);
        $pa = PerfilPartida::de($partida, $a) ?? [];
        $pb = PerfilPartida::de($partida, $b) ?? [];
        $compatAb = CompatibilidadCalculator::aHaciaB($pa, $pb, $cal);
        $compatBa = CompatibilidadCalculator::aHaciaB($pb, $pa, $cal);
        $quimAb = QuimicaEngine::valorHacia($partida, $a, $b);
        $quimBa = QuimicaEngine::valorHacia($partida, $b, $a);
        $quimParAb = QuimicaEngine::obtener($partida, $a, $b);
        $quimParBa = QuimicaEngine::obtener($partida, $b, $a);

        $slot = self::slotPropuesta($partida, $a, $b);
        $prop = [
            'participantes' => [$a, $b],
            'tipo' => PropuestaNivel::PRESENTAR,
            'lugar' => $slot['lugar'],
            'dia' => $slot['dia'],
            'hora' => $slot['hora'],
        ];
        $desgA = VoluntadPonderadaEvaluator::desglose($partida, $prop, $a, $b, $cal);
        $desgB = VoluntadPonderadaEvaluator::desglose($partida, $prop, $b, $a, $cal);
        $pA = self::scoreAP($desgA['score'] ?? 0, $cal);
        $pB = self::scoreAP($desgB['score'] ?? 0, $cal);
        $viab = sqrt(max(0.0, $pA) * max(0.0, $pB));

        $catA = self::identidadCatalogo($partida, $a, $catalog);
        $catB = self::identidadCatalogo($partida, $b, $catalog);

        return [
            'a' => $a,
            'b' => $b,
            'a_nombre' => IdentidadPublica::nombre($partida, $a),
            'b_nombre' => IdentidadPublica::nombre($partida, $b),
            'elegible' => (bool) ($rom['ok'] ?? false),
            'romance_elegibilidad' => $rom,
            'compat_a_hacia_b' => $compatAb,
            'compat_b_hacia_a' => $compatBa,
            'quimica_a_hacia_b' => $quimAb,
            'quimica_b_hacia_a' => $quimBa,
            'quimica_par_a_b' => $quimParAb,
            'quimica_par_b_a' => $quimParBa,
            'voluntad_desglose_a' => $desgA,
            'voluntad_desglose_b' => $desgB,
            'p_a' => $pA,
            'p_b' => $pB,
            'viabilidad_mutua' => $viab,
            'orientacion_a' => $catA['genero'] ?? null,
            'orientacion_b' => $catB['genero'] ?? null,
            'dealbreakers_a' => $pa['preferencias']['dealbreakers'] ?? [],
            'dealbreakers_b' => $pb['preferencias']['dealbreakers'] ?? [],
            'slot_propuesta' => $slot,
            'nota' => 'Viabilidad = √(pA·pB) con desglose VoluntadPonderadaEvaluator; compat/química del motor real.',
        ];
    }

    /**
     * @return array{dia: int, hora: int, lugar: string}
     */
    private static function slotPropuesta(array $partida, string $a, string $b): array
    {
        $lugar = 'lug_cafeteria';
        $slots = DisponibilidadEngine::slotsCompatibles(
            $partida,
            [$a, $b],
            PropuestaNivel::PRESENTAR,
            null,
            null,
            7,
            48
        );
        foreach ($slots['slots'] ?? [] as $s) {
            if (!is_array($s)) {
                continue;
            }
            $h = (int) ($s['hora'] ?? -1);
            $lug = (string) ($s['lugar'] ?? $lugar);
            if (ComplejoCatalog::estaAbierto($lug !== '' ? $lug : $lugar, $h)) {
                return [
                    'dia' => (int) ($s['dia'] ?? 1),
                    'hora' => $h,
                    'lugar' => $lug !== '' ? $lug : $lugar,
                ];
            }
        }
        return ['dia' => 1, 'hora' => 18, 'lugar' => $lugar];
    }

  /**
     * @param array<string, mixed> $cal
     */
    private static function scoreAP(int $score, array $cal): float
    {
        $pMin = (float) CalibracionConfig::get($cal, 'voluntad.p_min', 0.08);
        $pMax = (float) CalibracionConfig::get($cal, 'voluntad.p_max', 0.94);
        $excelente = (int) CalibracionConfig::get($cal, 'voluntad.score_excelente', 88);
        $pExc = (float) CalibracionConfig::get($cal, 'voluntad.p_excelente', 0.92);
        $p = $pMin + (max(0, min(100, $score)) / 100.0) * ($pMax - $pMin);
        if ($score >= $excelente) {
            $p = $pExc;
        }
        return min($pMax, max($pMin, $p));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function identidadCatalogo(array $partida, string $id, Catalog $catalog): ?array
    {
        $res = $partida['residentes'][$id] ?? null;
        if (!is_array($res)) {
            return null;
        }
        try {
            $cat = ResidenteRuntime::catalogoParaRuntime($res, $catalog);
            return is_array($cat['identidad'] ?? null) ? $cat['identidad'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function tokenUrl(array $partida, string $id, Catalog $catalog): ?string
    {
        $res = $partida['residentes'][$id] ?? null;
        if (!is_array($res)) {
            return null;
        }
        $packs = new VisualPackStore($catalog->getRoot());
        $tok = RetratoResolver::resolver($res, $id, $packs);
        return $tok['url'];
    }
}
