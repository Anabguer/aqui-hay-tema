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

    /** Marca genérica de garantía pedagógica tutorial (reemplaza la M1-específica). */
    public const MARCA_COMPROMISO_TUTORIAL = 'compromiso_tutorial';

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
        if (!empty($partida['tutorial']['jugable_completado'])) {
            self::reconciliarMisionesNormales($partida, new Catalog($root));
            return false;
        }
        self::asegurarMisiones($partida, new Catalog($root));
        return true;
    }

    /**
     * Las misiones de Primeros pasos sobreviven al cambio de día mientras el
     * tutorial jugable siga abierto. También repara saves donde una misión
     * quedó caducada o desapareció sin resetear el progreso ya conseguido.
     */
    public static function asegurarMisiones(array &$partida, ?Catalog $catalog = null): void
    {
        if (!self::debeConservarMisiones($partida)) {
            return;
        }
        MisionDiariaEngine::ensure($partida);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida['misiones_diarias']['dia'] = $dia;
        $base = self::filasMision($partida, $dia);
        $indices = [];
        $estados = [];
        foreach ($partida['misiones_diarias']['items'] as $i => $m) {
            if (!is_array($m) || !self::esMisionTutorial($m)) {
                continue;
            }
            $id = (string) ($m['id'] ?? $m['plantilla_id'] ?? '');
            if (isset($indices[$id])) {
                continue;
            }
            $indices[$id] = $i;
            $estados[$id] = (string) ($m['estado'] ?? '');
        }

        foreach ($base as $fila) {
            $id = (string) $fila['id'];
            $necesario = self::estadoNecesario($id, $estados);
            if (isset($indices[$id])) {
                $i = $indices[$id];
                $actual = $estados[$id];
                // Copy canónico desde $fila; solo conservar progreso guardado (estado).
                $partida['misiones_diarias']['items'][$i] = array_merge($fila, [
                    'estado' => $actual !== '' ? $actual : $necesario,
                ]);
                if ($actual === MisionDiariaEngine::EST_CADUCADA
                    || ($actual === 'bloqueada' && $necesario === MisionDiariaEngine::EST_PENDIENTE)
                ) {
                    $partida['misiones_diarias']['items'][$i]['estado'] = $necesario;
                    $estados[$id] = $necesario;
                }
                continue;
            }
            $fila['estado'] = $necesario;
            $partida['misiones_diarias']['items'][] = $fila;
            $estados[$id] = $necesario;
        }

        if (self::estadoMision($partida, self::M2) === MisionDiariaEngine::EST_PENDIENTE) {
            self::asegurarMensajito($partida, $catalog);
        }
    }

    public static function esMisionTutorial(array $mision): bool
    {
        $id = (string) ($mision['id'] ?? '');
        $plantilla = (string) ($mision['plantilla_id'] ?? '');
        return in_array($id, [self::M1, self::M2, self::M3], true)
            || in_array($plantilla, [self::M1, self::M2, self::M3], true);
    }

    public static function debeConservarMisiones(array $partida): bool
    {
        return self::activo($partida) && empty($partida['tutorial']['jugable_completado']);
    }

    public static function conservaMision(array $partida, array $mision): bool
    {
        return self::debeConservarMisiones($partida) && self::esMisionTutorial($mision);
    }

    public static function bloqueaMisionesNormales(array $partida): bool
    {
        if (!self::activo($partida)) {
            return false;
        }
        return empty($partida['tutorial']['jugable_completado']);
    }

    /**
     * Tras completar el tutorial jugable, genera el paquete normal del día actual si falta.
     * Idempotente: no duplica si ya existe paquete normal para ese día.
     */
    public static function reconciliarMisionesNormales(array &$partida, Catalog $catalog): void
    {
        if (!self::activo($partida) || empty($partida['tutorial']['jugable_completado'])) {
            return;
        }
        if (!MisionDiariaEngine::activa($partida)) {
            return;
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        if (MisionDiariaEngine::tienePaqueteNormalDelDia($partida, $dia)) {
            return;
        }
        $cal = CalibracionConfig::load($catalog->getRoot());
        MisionDiariaEngine::alComenzarDia($partida, $cal, RngService::fromPartida($partida));
    }

    public static function bloqueaIncorporaciones(array $partida): bool
    {
        if (!self::activo($partida)) {
            return false;
        }
        return empty($partida['tutorial']['jugable_completado']);
    }

    /**
     * Mientras M1 siga pendiente, la pareja del tutorial no debe recibir encuentros
     * autónomos (p. ej. iniciativa_social → autonomo_npc_social). El primer plan de
     * ese par debe nacer del pipeline canónico del jugador (celeste_organizado).
     */
    public static function bloqueaAutonomiaSobreParejaMision1(array $partida, string $a, string $b): bool
    {
        if (!self::activo($partida) || !empty($partida['tutorial']['jugable_completado'])) {
            return false;
        }
        if (self::estadoMision($partida, self::M1) === MisionDiariaEngine::EST_CUMPLIDA) {
            return false;
        }
        $pareja = $partida['tutorial']['pareja_mision1'] ?? [];
        $ta = (string) ($pareja['a'] ?? '');
        $tb = (string) ($pareja['b'] ?? '');
        if ($ta === '' || $tb === '') {
            return false;
        }
        $ids = [$a, $b];
        sort($ids);
        $par = [$ta, $tb];
        sort($par);
        return $ids === $par;
    }

    /**
     * Detecta si una propuesta satisface los requisitos de la misión tutorial activa.
     * Devuelve el ID de la misión o '' si no coincide ninguna.
     *
     * @param list<string> $participantes
     */
    public static function esPropuestaPedagogicaTutorial(array $partida, array $participantes, string $tipo): string
    {
        if (!self::activo($partida) || !empty($partida['tutorial']['jugable_completado'])) {
            return '';
        }
        if (self::estadoMision($partida, self::M1) === MisionDiariaEngine::EST_PENDIENTE) {
            if (self::esPropuestaPedagogicaM1($partida, $participantes, $tipo)) {
                return self::M1;
            }
        }
        if (self::estadoMision($partida, self::M3) === MisionDiariaEngine::EST_PENDIENTE) {
            $tercero = (string) ($partida['tutorial']['tercero'] ?? '');
            if ($tercero !== '' && PropuestaNivel::aliasTipo($tipo) === 'individual'
                && count($participantes) === 1 && (string) $participantes[0] === $tercero
            ) {
                return self::M3;
            }
        }
        return '';
    }

    /**
     * Garantía pedagógica genérica: aceptación determinista para cualquier misión tutorial activa.
     * Solo indisponibilidad real de agenda se respeta.
     *
     * @param array<string, mixed> $propuesta
     */
    public static function aplicarGarantiaPedagogica(array &$partida, array &$propuesta, string $misionId): void
    {
        $parts = is_array($propuesta['participantes'] ?? null) ? $propuesta['participantes'] : [];
        $tipo = (string) ($propuesta['tipo'] ?? '');
        $lugar = (string) ($propuesta['lugar'] ?? '');

        $detectada = self::esPropuestaPedagogicaTutorial($partida, $parts, $tipo);
        if ($detectada === '' || $detectada !== $misionId) {
            return;
        }

        if ($misionId === self::M3) {
            $lugM3 = (string) ($partida['tutorial']['lugar_mision3'] ?? 'lug_cine');
            if ($lugar !== $lugM3) {
                return;
            }
        }

        $esM1 = ($misionId === self::M1);
        $marca = $esM1
            ? PropuestaEncuentroEngine::MARCA_COMPROMISO_TUTORIAL_M1
            : self::MARCA_COMPROMISO_TUTORIAL;
        $factorKey = $esM1 ? 'compromiso_tutorial_m1' : 'compromiso_tutorial';

        foreach ($propuesta['reacciones'] as $i => $reac) {
            if (!is_array($reac)) {
                continue;
            }
            if (($reac['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA
                && ($reac['clase'] ?? '') === PropuestaEncuentro::CLASE_INDISPONIBILIDAD
            ) {
                continue;
            }
            $pAntes = $reac['p'] ?? null;
            $propuesta['reacciones'][$i]['decision'] = PropuestaEncuentro::DECISION_ACEPTA;
            $propuesta['reacciones'][$i]['clase'] = null;
            $propuesta['reacciones'][$i]['motivo_tecnico'] = $marca;
            $propuesta['reacciones'][$i]['motivo_tipo'] = null;
            $propuesta['reacciones'][$i]['copy_id'] = null;
            $propuesta['reacciones'][$i]['_bloqueado_decision'] = false;
            if (!isset($propuesta['reacciones'][$i]['factores']) || !is_array($propuesta['reacciones'][$i]['factores'])) {
                $propuesta['reacciones'][$i]['factores'] = [];
            }
            if ($pAntes !== null) {
                $propuesta['reacciones'][$i]['factores']['p_sin_garantia_tutorial'] = $pAntes;
                if ($esM1) {
                    $propuesta['reacciones'][$i]['factores']['p_sin_garantia_tutorial_m1'] = $pAntes;
                }
            }
            $propuesta['reacciones'][$i]['factores'][$factorKey] = $misionId;
            unset($propuesta['reacciones'][$i]['_joint_plan']);
        }
        $propuesta['garantia_tutorial'] = true;
        $propuesta['garantia_tutorial_mision'] = $misionId;
        if ($esM1) {
            $propuesta['garantia_tutorial_m1'] = true;
        }
    }

    /**
     * Propuesta pedagógica M1: pareja tutorial + presentar mientras M1 siga pendiente.
     *
     * @param list<string> $participantes
     */
    public static function esPropuestaPedagogicaM1(array $partida, array $participantes, string $tipo): bool
    {
        if (!self::activo($partida) || !empty($partida['tutorial']['jugable_completado'])) {
            return false;
        }
        if (self::estadoMision($partida, self::M1) !== MisionDiariaEngine::EST_PENDIENTE) {
            return false;
        }
        if (PropuestaNivel::aliasTipo($tipo) !== PropuestaNivel::PRESENTAR) {
            return false;
        }
        $crudos = [];
        foreach ($participantes as $rid) {
            if (is_string($rid) && $rid !== '') {
                $crudos[] = $rid;
            }
        }
        $crudos = array_values(array_unique($crudos));
        if (count($crudos) !== 2) {
            return false;
        }
        return self::bloqueaAutonomiaSobreParejaMision1($partida, $crudos[0], $crudos[1]);
    }

    /**
     * Wrapper backward-compat: aplica garantía M1 (delega a la genérica).
     *
     * @param array<string, mixed> $propuesta
     */
    public static function aplicarGarantiaPedagogicaM1(array &$partida, array &$propuesta): void
    {
        self::aplicarGarantiaPedagogica($partida, $propuesta, self::M1);
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
        if (!self::esMensajitoTutorialLeido($partida, $mensajeId)) {
            return;
        }
        self::completarMision($partida, self::M2, $catalog);
    }

    public static function marcarFinaleVisto(array &$partida): void
    {
        if (!is_array($partida['tutorial'] ?? null)) {
            return;
        }
        $partida['tutorial']['finale_visto'] = true;
        $partida['tutorial']['activo'] = false;
        HistoriaPuebloEngine::registrarEmpezoCotarroSiToca($partida);
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
            'finale_visto' => !empty($partida['tutorial']['finale_visto']),
            'finale_pendiente' => !empty($partida['tutorial']['jugable_completado'])
                && empty($partida['tutorial']['finale_visto']),
            'intro' => [
                'pasos' => [
                    [
                        'tit' => 'Bienvenida al pueblo',
                        'intro' => 'Parece tranquilo. No te fíes.',
                        'intro_extra' => 'Aquí viven personas con gustos, manías, crushes, dramas y una capacidad sorprendente para complicarse la vida sin ayuda.',
                        'bloques' => [
                            ['simbolo' => '◉', 'tit' => 'OBSERVA', 'txt' => 'Descubre qué está pasando.'],
                            ['simbolo' => '✉', 'tit' => 'PROPÓN', 'txt' => 'Organiza planes cuando quieras meter un poco la mano.'],
                            ['simbolo' => '◎', 'tit' => 'MIRA QUÉ PASA', 'txt' => 'Porque tú propones. Ellos deciden.'],
                        ],
                        'cierre' => 'No controlas a nadie. Y ahí está la gracia.',
                    ],
                    [
                        'tit' => 'Tus primeros vecinos',
                        'intro' => 'De momento tienes a ' . $n1 . ', ' . $n2 . ' y ' . $n3 . '.',
                        'caras' => $caras,
                        'bloques_prefijo' => 'Cada uno ha llegado con:',
                        'bloques_estilo' => 'inline',
                        'bloques' => [
                            ['simbolo' => '♥', 'txt' => 'su personalidad'],
                            ['simbolo' => '★', 'txt' => 'sus gustos'],
                            ['simbolo' => '?', 'txt' => 'sus cosas raras'],
                        ],
                        'cierre' => 'Tú irás descubriendo quién encaja con quién… y quién parecía una idea estupenda hasta que abrió la boca.',
                    ],
                    [
                        'tit' => 'Lo básico',
                        'intro' => 'Con tres sitios te apañas para empezar.',
                        'bloques' => [
                            ['simbolo' => '⌖', 'tit' => 'MAPA', 'txt' => 'Mira por dónde anda la gente.'],
                            ['simbolo' => '◉', 'tit' => 'VECINOS', 'txt' => 'Cotillea sus fichas y descubre cómo son.'],
                            ['simbolo' => '✉', 'tit' => 'MENSAJITOS', 'txt' => 'Aquí llegan recados, peticiones y cosas que requieren tu atención.'],
                            ['simbolo' => '+', 'tit' => 'NUEVO PLAN', 'txt' => 'Junta a dos personas… o manda a alguien por su cuenta.'],
                        ],
                        'cierre' => 'Después ellos harán lo que les dé la gana. Como debe ser.',
                    ],
                    [
                        'tit' => 'Empieza por aquí',
                        'intro' => 'Ya está. No necesitas un máster.',
                        'intro_extra' => 'Te he dejado tres misiones de Primeros pasos en «Hoy en el pueblo».',
                        'tareas' => 3,
                        'cierre' => 'Hazlas. El resto lo aprenderás jugando.',
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

    private static function sembrarMisiones(array &$partida, Catalog $catalog): void
    {
        MisionDiariaEngine::ensure($partida);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida['misiones_diarias']['dia'] = $dia;
        $partida['misiones_diarias']['items'] = self::filasMision($partida, $dia);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function filasMision(array $partida, int $dia): array
    {
        $pareja = $partida['tutorial']['pareja_mision1'] ?? [];
        $a = (string) ($pareja['a'] ?? '');
        $b = (string) ($pareja['b'] ?? '');
        $tercero = (string) ($partida['tutorial']['tercero'] ?? '');
        $na = IdentidadPublica::nombre($partida, $a);
        $nb = IdentidadPublica::nombre($partida, $b);
        $nc = IdentidadPublica::nombre($partida, $tercero);

        return [
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

    private static function estadoNecesario(string $id, array $estados): string
    {
        if ($id === self::M1) {
            return MisionDiariaEngine::EST_PENDIENTE;
        }
        if ($id === self::M2) {
            return ($estados[self::M1] ?? '') === MisionDiariaEngine::EST_CUMPLIDA
                ? MisionDiariaEngine::EST_PENDIENTE
                : 'bloqueada';
        }
        return ($estados[self::M2] ?? '') === MisionDiariaEngine::EST_CUMPLIDA
            ? MisionDiariaEngine::EST_PENDIENTE
            : 'bloqueada';
    }

    private static function mensajitoIdCanonico(array $partida, string $tercero): string
    {
        $guardado = (string) ($partida['tutorial']['mensajito_id'] ?? '');
        if ($guardado !== '') {
            return $guardado;
        }

        return 'msg_pp_' . substr(md5((string) ($partida['meta']['partida_id'] ?? '') . '|' . $tercero), 0, 10);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function mensajitoTutorialExistente(array $partida, string $tercero): ?array
    {
        foreach ($partida['buzon'] ?? [] as $mensaje) {
            if (!is_array($mensaje)) {
                continue;
            }
            if (self::esMensajitoTutorial($mensaje, $tercero)) {
                return $mensaje;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $mensaje
     */
    private static function esMensajitoTutorial(array $mensaje, string $tercero): bool
    {
        return ($mensaje['tipo'] ?? '') === 'tutorial_primeros_pasos'
            && (string) ($mensaje['de_persona'] ?? '') === $tercero;
    }

    private static function esMensajitoTutorialLeido(array &$partida, string $mensajeId): bool
    {
        $tercero = (string) ($partida['tutorial']['tercero'] ?? '');
        if ($tercero === '') {
            return false;
        }
        $msgId = (string) ($partida['tutorial']['mensajito_id'] ?? '');
        if ($msgId !== '' && $mensajeId === $msgId) {
            return true;
        }
        foreach ($partida['buzon'] ?? [] as $mensaje) {
            if (!is_array($mensaje) || ($mensaje['id'] ?? '') !== $mensajeId) {
                continue;
            }
            if (!self::esMensajitoTutorial($mensaje, $tercero)) {
                return false;
            }
            $partida['tutorial']['mensajito_id'] = (string) ($mensaje['id'] ?? $mensajeId);
            return true;
        }

        return false;
    }

    private static function asegurarMensajito(array &$partida, ?Catalog $catalog): void
    {
        $tercero = (string) ($partida['tutorial']['tercero'] ?? '');
        if ($tercero === '') {
            return;
        }
        $msgId = self::mensajitoIdCanonico($partida, $tercero);
        $existente = self::mensajitoTutorialExistente($partida, $tercero);
        if ($existente !== null) {
            $partida['tutorial']['mensajito_id'] = (string) ($existente['id'] ?? $msgId);
            return;
        }
        $partida['tutorial']['mensajito_id'] = $msgId;
        self::activarMision2($partida, $catalog ?? new Catalog(dirname(__DIR__, 2)));
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
        $msgId = self::mensajitoIdCanonico($partida, $tercero);
        $existente = self::mensajitoTutorialExistente($partida, $tercero);
        if ($existente !== null) {
            $partida['tutorial']['mensajito_id'] = (string) ($existente['id'] ?? $msgId);
            return (string) ($existente['id'] ?? $msgId);
        }
        $nombre = IdentidadPublica::nombre($partida, $tercero);
        $partida['tutorial']['mensajito_id'] = $msgId;
        // CONTRATO NARRATIVO: primera persona del vecino hacia Celestine.
        // La línea de instrucción del tutorial se conserva tal cual (es guía
        // funcional de UI, no narrativa del NPC).
        $texto = MensajitoVoz::linea(
            $partida,
            'tutorial_primeros_pasos',
            ['nombre' => $nombre],
            'tutorial_primeros_pasos|' . $tercero,
            $tercero
        );
        if ($texto === '') {
            $texto = 'Oye, Celestine: me apetece ir al cine. Por si te da por meter las narices.';
        }
        BuzonEngine::crear($partida, [
            'id' => $msgId,
            'clasificacion' => BuzonEngine::OPORTUNIDAD,
            'tipo' => 'tutorial_primeros_pasos',
            'de_persona' => $tercero,
            'actores' => [$tercero],
            'texto' => $texto,
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
        self::reconciliarMisionesNormales($partida, $catalog);
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
        $tok = RetratoResolver::resolver($res, $id, $packs, $catalog->getRoot());
        return $tok['url'];
    }
}
