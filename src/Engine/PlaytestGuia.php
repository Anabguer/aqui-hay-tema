<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Capa de guía del playtest de sistemas. No altera el motor:
 * solo observa presencia, relaciones, buzón, cotilleo, emociones y Vida.
 * Visible para Neni en taller/lab.
 */
final class PlaytestGuia
{
    /** @var list<string> */
    public const OBJETIVOS = [
        'salida_autonoma',
        'coincidencia',
        'se_conocen',
        'plan_organizado',
        'mensaje_buzon',
        'cotilleo',
        'hay_tema',
        'cambio_emocional',
        'relacion_evoluciona',
    ];

    /** @var array<string, string> */
    private const OBJETIVO_LABEL = [
        'salida_autonoma' => 'Ver una salida autónoma',
        'coincidencia' => 'Ver una coincidencia',
        'se_conocen' => 'Ver dos personas que se conocen',
        'plan_organizado' => 'Organizar un primer plan válido',
        'mensaje_buzon' => 'Recibir una petición/mensaje',
        'cotilleo' => 'Generar un hecho de El Cotilleo',
        'hay_tema' => 'Ver aparecer hay_tema',
        'cambio_emocional' => 'Ver un cambio emocional',
        'relacion_evoluciona' => 'Ver evolucionar una relación',
    ];

    public static function activa(array $partida): bool
    {
        $cfg = (string) ($partida['meta']['config_id'] ?? '');
        return $cfg === 'playtest_01' || !empty($partida['playtest_guia']['forzar']);
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function ensure(array &$partida): void
    {
        if (!self::activa($partida)) {
            return;
        }
        $g = is_array($partida['playtest_guia'] ?? null) ? $partida['playtest_guia'] : [];
        $hechos = is_array($g['objetivos'] ?? null) ? $g['objetivos'] : [];
        foreach (self::OBJETIVOS as $id) {
            if (!array_key_exists($id, $hechos)) {
                $hechos[$id] = false;
            }
        }
        $g['objetivos'] = $hechos;
        $g['ultimo'] = is_array($g['ultimo'] ?? null) ? $g['ultimo'] : null;
        $partida['playtest_guia'] = $g;
    }

    /**
     * Foto del estado jugable (sin scores).
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function snapshot(array $partida, string $root): array
    {
        $mapa = PresenciaEngine::resolver($partida, $root);
        $lugares = [];
        $porPersona = [];
        foreach ($mapa['lugares'] ?? [] as $lug) {
            if (!is_array($lug) || empty($lug['id'])) {
                continue;
            }
            $lid = (string) $lug['id'];
            $nombre = (string) ($lug['nombre'] ?? self::lugarHumano($lid));
            $ids = [];
            foreach ($lug['residentes_presentes'] ?? [] as $p) {
                if (!is_array($p) || empty($p['id'])) {
                    continue;
                }
                $rid = (string) $p['id'];
                $ids[] = $rid;
                $porPersona[$rid] = $lid;
            }
            $lugares[$lid] = ['nombre' => $nombre, 'ids' => $ids];
        }

        $emociones = [];
        foreach ($partida['residentes'] ?? [] as $rid => $res) {
            if (!is_string($rid)) {
                continue;
            }
            $emociones[$rid] = EstadoEmocional::canonId(
                (string) ($res['runtime']['estado_emocional']['id'] ?? EstadoEmocional::NEUTRO)
            );
            if (!isset($porPersona[$rid])) {
                $porPersona[$rid] = null;
            }
        }

        $conocidos = [];
        $social = [];
        $ids = array_keys($partida['residentes'] ?? []);
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $a = (string) $ids[$i];
                $b = (string) $ids[$j];
                $clave = self::parClave($a, $b);
                $conocidos[$clave] = RelacionEngine::seConocen($partida, $a, $b);
                $social[$clave] = RelacionEngine::valorSocialHacia($partida, $a, $b)
                    + RelacionEngine::valorSocialHacia($partida, $b, $a);
            }
        }

        $pueblo = VistaPuebloV3::de($partida, $mapa, $root);
        $hayTema = [];
        foreach ($pueblo['complejos'] ?? [] as $cx) {
            foreach ($cx['personas'] ?? [] as $p) {
                if (!empty($p['hay_tema']) && !empty($p['id'])) {
                    $hayTema[(string) $p['id']] = true;
                }
            }
        }

        $buzon = 0;
        $cotilleo = 0;
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            $clas = (string) ($m['clasificacion'] ?? '');
            $canal = (string) ($m['canal'] ?? BuzonEngine::canalDe($clas));
            if ($clas === BuzonEngine::COTILLEO || $canal === BuzonEngine::CANAL_COTILLEO) {
                $cotilleo++;
            } else {
                $buzon++;
            }
        }

        $coincidencias = count($partida['historial_coincidencias'] ?? []);
        $planesJugador = 0;
        foreach ($partida['propuestas_encuentro'] ?? [] as $pr) {
            if (is_array($pr) && (($pr['origen'] ?? '') === 'jugador' || ($pr['intencion'] ?? '') === 'jugador_propone')) {
                $planesJugador++;
            }
        }
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (is_array($enc) && ($enc['origen'] ?? '') === 'jugador') {
                $planesJugador++;
            }
        }

        return [
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            'por_persona' => $porPersona,
            'lugares' => $lugares,
            'emociones' => $emociones,
            'conocidos' => $conocidos,
            'social' => $social,
            'hay_tema' => $hayTema,
            'buzon_n' => $buzon,
            'cotilleo_n' => $cotilleo,
            'coincidencias_n' => $coincidencias,
            'planes_jugador_n' => $planesJugador,
            'vida' => VidaPuebloEngine::valor($partida),
        ];
    }

    /**
     * Compara antes/después de un avance real del reloj.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $antes
     * @return array<string, mixed>
     */
    public static function trasAvance(array &$partida, string $root, array $antes, int $horas): array
    {
        self::ensure($partida);
        $despues = self::snapshot($partida, $root);
        $lineas = [];
        $marcados = [];

        // Salidas / movimientos
        foreach ($despues['por_persona'] as $rid => $lugNuevo) {
            $lugViejo = $antes['por_persona'][$rid] ?? null;
            if ($lugViejo === $lugNuevo) {
                continue;
            }
            $nom = IdentidadPublica::nombre($partida, (string) $rid);
            $de = $lugViejo === null ? 'casa' : self::nombreLugar($antes, (string) $lugViejo);
            $a = $lugNuevo === null ? 'casa' : self::nombreLugar($despues, (string) $lugNuevo);
            if ($lugViejo === null && $lugNuevo !== null) {
                $lineas[] = $nom . ' ha salido de casa y ha ido a ' . $a . '.';
                $marcados['salida_autonoma'] = true;
            } elseif ($lugViejo !== null && $lugNuevo === null) {
                $lineas[] = $nom . ' ha vuelto a casa (antes estaba en ' . $de . ').';
            } else {
                $lineas[] = $nom . ' se ha movido de ' . $de . ' a ' . $a . '.';
                if ($lugNuevo !== null) {
                    $marcados['salida_autonoma'] = true;
                }
            }
        }

        // Quién sigue en casa (solo si hubo algún movimiento, para no inundar)
        if ($lineas !== []) {
            $enCasa = [];
            foreach ($despues['por_persona'] as $rid => $lug) {
                if ($lug !== null) {
                    continue;
                }
                $antesL = $antes['por_persona'][$rid] ?? null;
                if ($antesL === null) {
                    $enCasa[] = IdentidadPublica::nombre($partida, (string) $rid);
                }
            }
            if (count($enCasa) > 0 && count($enCasa) <= 4) {
                foreach ($enCasa as $n) {
                    $lineas[] = $n . ' sigue en casa.';
                }
            } elseif (count($enCasa) > 4) {
                $lineas[] = count($enCasa) . ' personas siguen en casa.';
            }
        }

        // Coincidencias nuevas
        $coinNuevas = (int) $despues['coincidencias_n'] - (int) $antes['coincidencias_n'];
        if ($coinNuevas > 0) {
            $marcados['coincidencia'] = true;
            $hist = $partida['historial_coincidencias'] ?? [];
            $tail = array_slice(is_array($hist) ? $hist : [], -$coinNuevas);
            foreach ($tail as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $ids = is_array($c['residentes'] ?? null) ? $c['residentes'] : [];
                $nombres = [];
                foreach ($ids as $id) {
                    if (is_string($id) && $id !== '') {
                        $nombres[] = IdentidadPublica::nombre($partida, $id);
                    }
                }
                $sitio = self::lugarHumano((string) ($c['lugar_id'] ?? ''));
                $quien = self::yNombres($nombres);
                $digna = CotilleoNarrativo::patronParLugar(
                    $partida,
                    array_values(array_filter($ids, static function ($x) {
                        return is_string($x) && $x !== '';
                    })),
                    (string) ($c['lugar_id'] ?? ''),
                    (int) ($despues['dia'] ?? 1),
                    []
                );
                if ($digna) {
                    $lineas[] = $quien . ' han coincidido en ' . $sitio . '. Llevan varios días repitiendo sitio: esto puede dar tema.';
                } else {
                    $lineas[] = $quien . ' han coincidido en ' . $sitio . '. Es coincidencia puntual: todavía no hay tema.';
                }
            }
        }

        // Relaciones: se conocen / evolucionan
        foreach ($despues['conocidos'] as $clave => $ahora) {
            $antesC = !empty($antes['conocidos'][$clave]);
            if ($ahora && !$antesC) {
                $par = explode('|', (string) $clave);
                $lineas[] = IdentidadPublica::nombre($partida, $par[0] ?? '')
                    . ' y ' . IdentidadPublica::nombre($partida, $par[1] ?? '')
                    . ' ya se conocen.';
                $marcados['se_conocen'] = true;
            }
            $s0 = (int) ($antes['social'][$clave] ?? 0);
            $s1 = (int) ($despues['social'][$clave] ?? 0);
            if ($s1 !== $s0 && ($ahora || $antesC)) {
                $par = explode('|', (string) $clave);
                $dir = $s1 > $s0 ? 'se llevan un poco mejor' : 'se llevan un poco peor';
                $lineas[] = 'La relación entre '
                    . IdentidadPublica::nombre($partida, $par[0] ?? '')
                    . ' y ' . IdentidadPublica::nombre($partida, $par[1] ?? '')
                    . ': ' . $dir . '.';
                $marcados['relacion_evoluciona'] = true;
            }
        }

        // Emociones
        foreach ($despues['emociones'] as $rid => $emo) {
            $antesE = (string) ($antes['emociones'][$rid] ?? EstadoEmocional::NEUTRO);
            if ($emo === $antesE) {
                continue;
            }
            $lineas[] = IdentidadPublica::nombre($partida, (string) $rid)
                . ' ahora se le nota ' . self::emocionHumana($emo)
                . ' (antes ' . self::emocionHumana($antesE) . ').';
            $marcados['cambio_emocional'] = true;
        }

        // Buzón / cotilleo
        if ((int) $despues['buzon_n'] > (int) $antes['buzon_n']) {
            $n = (int) $despues['buzon_n'] - (int) $antes['buzon_n'];
            $lineas[] = $n === 1
                ? 'Tienes un mensaje nuevo en el buzón.'
                : ('Tienes ' . $n . ' mensajes nuevos en el buzón.');
            $marcados['mensaje_buzon'] = true;
        } else {
            $lineas[] = 'No tienes mensajes nuevos.';
        }
        if ((int) $despues['cotilleo_n'] > (int) $antes['cotilleo_n']) {
            $lineas[] = 'Ha aparecido algo nuevo en El Cotilleo.';
            $marcados['cotilleo'] = true;
        }

        // hay_tema
        $nuevosTema = [];
        foreach ($despues['hay_tema'] as $rid => $_) {
            if (empty($antes['hay_tema'][$rid])) {
                $nuevosTema[] = IdentidadPublica::nombre($partida, (string) $rid);
            }
        }
        if ($nuevosTema !== []) {
            $lineas[] = 'Hay tema con: ' . self::yNombres($nuevosTema) . ' (sello rosa).';
            $marcados['hay_tema'] = true;
        }
        $perdidos = [];
        foreach ($antes['hay_tema'] as $rid => $_) {
            if (empty($despues['hay_tema'][$rid])) {
                $perdidos[] = IdentidadPublica::nombre($partida, (string) $rid);
            }
        }
        if ($perdidos !== []) {
            $lineas[] = 'Ya no hay sello de tema en: ' . self::yNombres($perdidos) . '.';
        }

        // Vida
        $v0 = (int) ($antes['vida'] ?? 0);
        $v1 = (int) ($despues['vida'] ?? 0);
        if ($v1 !== $v0) {
            $lineas[] = 'La Vida del pueblo ha pasado de ' . $v0 . ' a ' . $v1 . '.';
        }

        // Planes
        if ((int) $despues['planes_jugador_n'] > (int) $antes['planes_jugador_n']) {
            $marcados['plan_organizado'] = true;
        }

        // Filtrar la línea de "no mensajes" si es lo único aburrido y ya hay otras líneas útiles
        $lineasUtiles = array_values(array_filter($lineas, static function ($l) {
            return $l !== 'No tienes mensajes nuevos.';
        }));
        if ($lineasUtiles === []) {
            $titulo = self::tituloHoras($horas);
            $lineas = ['No ha pasado nada importante durante este tiempo.'];
        } else {
            $titulo = self::tituloHoras($horas);
            // Si no hubo cambio de buzón, no insistir en "no mensajes" si hay mucho ruido
            if (count($lineasUtiles) >= 3) {
                $lineas = $lineasUtiles;
            }
        }

        foreach ($marcados as $id => $ok) {
            if ($ok) {
                self::marcar($partida, (string) $id);
            }
        }
        // También refrescar objetivos por estado actual (no solo delta)
        self::sincronizarObjetivos($partida, $despues);

        $pistas = self::pistas($partida, $despues);
        $ultimo = [
            'titulo' => $titulo,
            'lineas' => array_values($lineas),
            'horas' => $horas,
            'dia' => $despues['dia'],
            'hora' => $despues['hora'],
            'pistas' => $pistas,
        ];
        $partida['playtest_guia']['ultimo'] = $ultimo;
        return $ultimo;
    }

    /**
     * Panel completo para PLAY.
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function vista(array $partida, string $root): array
    {
        if (!self::activa($partida)) {
            return ['activo' => false];
        }
        self::ensure($partida);
        $snap = self::snapshot($partida, $root);
        self::sincronizarObjetivos($partida, $snap);
        $g = $partida['playtest_guia'];
        $dia = (int) ($snap['dia'] ?? 1);
        $hora = (int) ($snap['hora'] ?? 0);
        $objs = [];
        foreach (self::OBJETIVOS as $id) {
            $objs[] = [
                'id' => $id,
                'label' => self::OBJETIVO_LABEL[$id] ?? $id,
                'hecho' => !empty($g['objetivos'][$id]),
            ];
        }
        $ahora = self::ahoraMismo($partida, $snap);
        $queHacer = self::queHacerAhora($partida, $snap, $g);
        return [
            'activo' => true,
            'titulo' => 'PRUEBA DEL PUEBLO — DÍA ' . $dia,
            'reloj_humano' => Reloj::formatear($partida['reloj'] ?? []),
            'hora' => $hora,
            'ahora_mismo' => $ahora,
            'que_hacer_ahora' => $queHacer,
            'ultimo' => $g['ultimo'] ?? null,
            'pistas' => self::pistas($partida, $snap),
            'objetivos' => $objs,
            'progreso' => [
                'hechos' => count(array_filter($g['objetivos'] ?? [])),
                'total' => count(self::OBJETIVOS),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $snap
     * @return list<string>
     */
    private static function ahoraMismo(array $partida, array $snap): array
    {
        $n = count($partida['residentes'] ?? []);
        $lugares = $partida['celeste']['lugares_desbloqueados'] ?? [];
        $fuera = 0;
        foreach ($snap['por_persona'] as $lug) {
            if ($lug !== null) {
                $fuera++;
            }
        }
        $conocidos = 0;
        foreach ($snap['conocidos'] as $v) {
            if ($v) {
                $conocidos++;
            }
        }
        $out = [
            $n . ' habitantes',
            count($lugares) . ' lugares abiertos (cafetería, parque, biblioteca)',
            $conocidos === 0
                ? 'Todos empiezan como desconocidos. Raúl y Álex son padre e hijo (no pueden ser pareja).'
                : ($conocidos . ' pares ya se conocen'),
            'Vida: ' . (int) ($snap['vida'] ?? 0),
            $fuera === 0
                ? 'Ahora mismo nadie está fuera de casa en el mapa.'
                : ($fuera . ' persona(s) están ahora en un lugar del pueblo.'),
        ];
        return $out;
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $snap
     * @param array<string, mixed> $g
     * @return list<string>
     */
    private static function queHacerAhora(array $partida, array $snap, array $g): array
    {
        $objs = is_array($g['objetivos'] ?? null) ? $g['objetivos'] : [];
        if (empty($objs['salida_autonoma'])) {
            return [
                'Mira quién hay en el pueblo (mapa y Vecinos).',
                'Pulsa +1h (o +8h si quieres más ritmo).',
                'Lee el resumen humano de lo que cambió.',
            ];
        }
        if (empty($objs['coincidencia'])) {
            return [
                'Sigue avanzando horas o un día.',
                'Fíjate si dos personas aparecen en el mismo sitio.',
            ];
        }
        if (empty($objs['se_conocen'])) {
            return [
                'Organiza un plan «Conocerse» entre dos desconocidos en la cafetería.',
                'O espera a que el pueblo genere un encuentro y avanza hasta terminarlo.',
            ];
        }
        if (empty($objs['plan_organizado'])) {
            $par = self::primerParConocido($partida, $snap);
            if ($par !== null) {
                return [
                    'Abre Organizar.',
                    'Propón un plan a ' . $par[0] . ' y ' . $par[1] . ' (ya se conocen).',
                ];
            }
            return ['Organiza un primer plan válido desde Organizar.'];
        }
        return [
            'Sigue jugando y acelerando días.',
            'Comprueba El Cotilleo, el sello rosa (hay_tema) y el buzón.',
            'Si tras muchos días un objetivo no se marca, anótalo: también es resultado del playtest.',
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $snap
     * @return list<array{tipo: string, titulo: string, texto: string}>
     */
    private static function pistas(array $partida, array $snap): array
    {
        $out = [];
        $par = self::primerParConocido($partida, $snap);
        if ($par !== null) {
            $out[] = [
                'tipo' => 'puedes',
                'titulo' => 'YA PUEDES PROBAR ESTO',
                'texto' => $par[0] . ' y ' . $par[1] . ' ya se conocen. Abre Organizar e intenta proponerles un plan.',
            ];
        }
        // Patrón significativo en historial
        $vistos = [];
        foreach ($partida['historial_coincidencias'] ?? [] as $c) {
            if (!is_array($c)) {
                continue;
            }
            $ids = [];
            foreach ($c['residentes'] ?? [] as $id) {
                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }
            $lugar = (string) ($c['lugar_id'] ?? '');
            if (count($ids) < 2 || $lugar === '') {
                continue;
            }
            $clave = CotilleoNarrativo::clavePar($ids, $lugar);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            if (!CotilleoNarrativo::patronParLugar($partida, $ids, $lugar, $dia, [])) {
                continue;
            }
            $nombres = [];
            foreach ($ids as $id) {
                $nombres[] = IdentidadPublica::nombre($partida, $id);
            }
            $out[] = [
                'tipo' => 'ojo',
                'titulo' => 'OJO AQUÍ',
                'texto' => self::yNombres($nombres) . ' llevan varios días coincidiendo en '
                    . self::lugarHumano($lugar)
                    . '. Mira El Cotilleo y comprueba si aparece el sello rosa.',
            ];
            break;
        }
        if ($snap['hay_tema'] !== []) {
            $noms = [];
            foreach (array_keys($snap['hay_tema']) as $rid) {
                $noms[] = IdentidadPublica::nombre($partida, (string) $rid);
            }
            $out[] = [
                'tipo' => 'ojo',
                'titulo' => 'OJO AQUÍ',
                'texto' => 'Hay tema con ' . self::yNombres($noms) . '. Busca el sello rosa en el mapa.',
            ];
        }
        if ((int) ($snap['buzon_n'] ?? 0) > 0) {
            $pend = count(BuzonEngine::listar($partida, 'pendiente'));
            if ($pend > 0) {
                $out[] = [
                    'tipo' => 'puedes',
                    'titulo' => 'YA PUEDES PROBAR ESTO',
                    'texto' => 'Tienes mensajes pendientes. Ábrelos en el buzón.',
                ];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $snap
     */
    private static function sincronizarObjetivos(array &$partida, array $snap): void
    {
        self::ensure($partida);
        if ((int) ($snap['coincidencias_n'] ?? 0) > 0) {
            self::marcar($partida, 'coincidencia');
        }
        foreach ($snap['conocidos'] as $v) {
            if ($v) {
                self::marcar($partida, 'se_conocen');
                break;
            }
        }
        if ((int) ($snap['planes_jugador_n'] ?? 0) > 0) {
            self::marcar($partida, 'plan_organizado');
        }
        if ((int) ($snap['buzon_n'] ?? 0) > 0) {
            self::marcar($partida, 'mensaje_buzon');
        }
        if ((int) ($snap['cotilleo_n'] ?? 0) > 0) {
            self::marcar($partida, 'cotilleo');
        }
        if ($snap['hay_tema'] !== []) {
            self::marcar($partida, 'hay_tema');
        }
        foreach ($snap['por_persona'] as $lug) {
            if ($lug !== null) {
                self::marcar($partida, 'salida_autonoma');
                break;
            }
        }
        foreach ($snap['social'] as $s) {
            if ((int) $s !== 0) {
                self::marcar($partida, 'relacion_evoluciona');
                break;
            }
        }
    }

    private static function marcar(array &$partida, string $id): void
    {
        if (!isset($partida['playtest_guia']['objetivos']) || !is_array($partida['playtest_guia']['objetivos'])) {
            return;
        }
        if (!array_key_exists($id, $partida['playtest_guia']['objetivos'])) {
            return;
        }
        $partida['playtest_guia']['objetivos'][$id] = true;
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $snap
     * @return array{0: string, 1: string}|null
     */
    private static function primerParConocido(array $partida, array $snap): ?array
    {
        foreach ($snap['conocidos'] as $clave => $ok) {
            if (!$ok) {
                continue;
            }
            $par = explode('|', (string) $clave);
            if (count($par) < 2) {
                continue;
            }
            return [
                IdentidadPublica::nombre($partida, $par[0]),
                IdentidadPublica::nombre($partida, $par[1]),
            ];
        }
        return null;
    }

    private static function tituloHoras(int $horas): string
    {
        if ($horas === 1) {
            return 'HA PASADO 1 HORA';
        }
        if ($horas === 8) {
            return 'HAN PASADO 8 HORAS';
        }
        if ($horas === 24) {
            return 'HA PASADO 1 DÍA';
        }
        if ($horas > 1) {
            return 'HAN PASADO ' . $horas . ' HORAS';
        }
        return 'EL TIEMPO HA AVANZADO';
    }

    private static function emocionHumana(string $id): string
    {
        if ($id === EstadoEmocional::ALEGRE) {
            return 'alegre';
        }
        if ($id === EstadoEmocional::TRISTE) {
            return 'triste';
        }
        if ($id === EstadoEmocional::ENFADADO) {
            return 'enfadado/a';
        }
        return 'neutro/a';
    }

    /**
     * @param array<string, mixed> $snap
     */
    private static function nombreLugar(array $snap, string $lid): string
    {
        if (isset($snap['lugares'][$lid]['nombre'])) {
            return (string) $snap['lugares'][$lid]['nombre'];
        }
        return self::lugarHumano($lid);
    }

    private static function lugarHumano(string $lid): string
    {
        if ($lid === '') {
            return 'un sitio';
        }
        $map = [
            'lug_cafeteria' => 'la cafetería',
            'lug_parque' => 'el parque',
            'lug_biblioteca' => 'la biblioteca',
            'lug_cine' => 'el cine',
            'lug_arcade' => 'el arcade',
            'lug_gimnasio' => 'el gimnasio',
            'lug_bar' => 'el bar',
        ];
        if (isset($map[$lid])) {
            return $map[$lid];
        }
        return str_replace('lug_', '', $lid);
    }

    private static function parClave(string $a, string $b): string
    {
        $x = [$a, $b];
        sort($x);
        return $x[0] . '|' . $x[1];
    }

    /** @param list<string> $nombres */
    private static function yNombres(array $nombres): string
    {
        $nombres = array_values(array_filter($nombres, static function ($n) {
            return is_string($n) && $n !== '';
        }));
        $n = count($nombres);
        if ($n === 0) {
            return 'Alguien';
        }
        if ($n === 1) {
            return $nombres[0];
        }
        if ($n === 2) {
            return $nombres[0] . ' y ' . $nombres[1];
        }
        $last = $nombres[$n - 1];
        return implode(', ', array_slice($nombres, 0, $n - 1)) . ' y ' . $last;
    }
}
