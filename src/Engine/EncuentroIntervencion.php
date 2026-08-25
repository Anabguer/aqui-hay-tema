<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Intervención de Celeste durante un encuentro organizado en curso.
 * Una acción significativa por encuentro; consecuencias vía motores canónicos.
 */
final class EncuentroIntervencion
{
    public const HABLAR = 'hablar';
    public const BROMA = 'broma';
    public const HOBBY = 'hobby';
    public const PERSONAL = 'personal';
    public const COQUETEAR = 'coquetear';
    public const BESO = 'beso';

  /** @var list<string> */
    private const ACCIONES = [
        self::HABLAR,
        self::BROMA,
        self::HOBBY,
        self::PERSONAL,
        self::COQUETEAR,
        self::BESO,
    ];

    /**
     * @return array<string, mixed>|null
     */
    public static function buscar(array $partida, string $encuentroId): ?array
    {
        foreach (EncuentroEngine::list($partida) as $enc) {
            if (($enc['id'] ?? '') === $encuentroId) {
                return $enc;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $enc
     */
    public static function puedeIntervenir(array $partida, array $enc): bool
    {
        if (($enc['estado'] ?? '') !== 'en_curso') {
            return false;
        }
        if (($enc['intencion'] ?? '') !== 'celeste_organizado') {
            return false;
        }
        $ids = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
        if (count($ids) !== 2) {
            return false;
        }
        if (!empty($enc['intervencion_celeste']['usada'])) {
            return false;
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        return LugarAtributos::ocupaHora($enc, $dia, $hora);
    }

    /**
     * Hobbies YA conocidos por el jugador de UNA persona concreta del encuentro.
     * Nunca revela conocimiento oculto: solo descartes por DiscoveryReveal.
     *
     * @param array<string, mixed> $enc
     * @return list<array{id: string, etiqueta: string, residente_id: string}>
     */
    public static function hobbiesConocidosDe(array $partida, array $enc, string $residenteId, ?Catalog $catalog): array
    {
        $residenteId = (string) $residenteId;
        if ($residenteId === '') {
            return [];
        }
        [$a, $b] = self::par($enc);
        if ($residenteId !== $a && $residenteId !== $b) {
            return [];
        }
        $out = [];
        foreach (self::hobbiesConocidos($partida, $a, $b, $catalog) as $opt) {
            if ((string) ($opt['residente_id'] ?? '') === $residenteId) {
                $out[] = $opt;
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $enc
     * @return array<string, mixed>
     */
    public static function vistaParaPlay(array $partida, array $enc, ?Catalog $catalog = null): array
    {
        $usada = !empty($enc['intervencion_celeste']['usada']);
        $puede = self::puedeIntervenir($partida, $enc);
        $prev = is_array($enc['intervencion_celeste'] ?? null) ? $enc['intervencion_celeste'] : null;
        return [
            'disponible' => $puede,
            'usada' => $usada,
            'acciones' => $puede ? self::accionesDisponibles($partida, $enc, $catalog) : [],
            'ultimo' => $usada && $prev !== null ? [
                'accion' => $prev['accion'] ?? null,
                'tono' => $prev['tono'] ?? null,
                'texto' => $prev['texto'] ?? null,
                'objetivo' => $prev['objetivo'] ?? null,
            ] : null,
        ];
    }

    /**
     * @param array<string, mixed> $enc
     * @return list<array<string, mixed>>
     */
    public static function accionesDisponibles(array $partida, array $enc, ?Catalog $catalog = null): array
    {
        if (!self::puedeIntervenir($partida, $enc)) {
            return [];
        }
        [$a, $b] = self::par($enc);
        $cal = $catalog !== null ? CalibracionConfig::load($catalog->getRoot()) : [];
        $out = [];
        foreach (self::ACCIONES as $id) {
            $req = self::requisitos($partida, $enc, $id, $a, $b, $cal, $catalog);
            $row = [
                'id' => $id,
                'etiqueta' => self::etiqueta($id),
                'disponible' => (bool) ($req['ok'] ?? false),
                'motivo' => $req['motivo'] ?? null,
            ];
            if ($id === self::HOBBY && !empty($req['hobbies'])) {
                $row['hobbies'] = $req['hobbies'];
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function ejecutar(
        array &$partida,
        string $encuentroId,
        string $accionId,
        array $params,
        Catalog $catalog,
        ?GameLogger $logger = null
    ): array {
        $enc = self::buscar($partida, $encuentroId);
        if ($enc === null) {
            return GameError::respuesta(GameError::VALIDACION_FALLIDA, ['detalle' => 'encuentro_no_encontrado']);
        }
        if (!self::puedeIntervenir($partida, $enc)) {
            if (!empty($enc['intervencion_celeste']['usada'])) {
                return GameError::respuesta(GameError::INTERVENCION_YA_USADA);
            }
            return GameError::respuesta(GameError::INTERVENCION_NO_DISPONIBLE);
        }
        if (!in_array($accionId, self::ACCIONES, true)) {
            return GameError::respuesta(GameError::INTERVENCION_ACCION_INVALIDA, ['accion' => $accionId]);
        }

        [$a, $b] = self::par($enc);
        $cal = CalibracionConfig::load($catalog->getRoot());
        /* "Meterme en su cabeza": persona objetivo OPCIONAL (ENCUENTRO + PERSONA).
           Si viene, debe ser participante de ESTE encuentro. No cambia cargas,
           probabilidades ni efectos: el motor resuelve igual que siempre. */
        $objetivo = isset($params['objetivo']) ? (string) $params['objetivo'] : '';
        if ($objetivo !== '' && $objetivo !== $a && $objetivo !== $b) {
            return GameError::respuesta(GameError::VALIDACION_FALLIDA, ['detalle' => 'objetivo_no_participante']);
        }
        if ($accionId === self::HOBBY && !isset($params['hobby_id'])) {
            return GameError::respuesta(GameError::INTERVENCION_ACCION_INVALIDA, ['motivo' => 'elige_hobby']);
        }
        if ($accionId === self::HOBBY && isset($params['hobby_id']) && !isset($params['residente_id'])) {
            /* Con objetivo, el tema solo puede salir de lo YA conocido de ESA persona. */
            $optsHobby = $objetivo !== ''
                ? self::hobbiesConocidosDe($partida, $enc, $objetivo, $catalog)
                : self::hobbiesConocidos($partida, $a, $b, $catalog);
            foreach ($optsHobby as $opt) {
                if (($opt['id'] ?? '') === (string) $params['hobby_id']) {
                    $params['residente_id'] = (string) ($opt['residente_id'] ?? '');
                    break;
                }
            }
        }
        if ($accionId === self::HOBBY && $objetivo !== ''
            && isset($params['residente_id'])
            && (string) $params['residente_id'] !== $objetivo) {
            return GameError::respuesta(GameError::VALIDACION_FALLIDA, ['detalle' => 'hobby_de_otro_residente']);
        }
        if ($accionId === self::HOBBY && $objetivo !== '' && isset($params['hobby_id'])) {
            /* El tema metido en la cabeza debe ser de la persona elegida (dato YA conocido). */
            $delObjetivo = false;
            foreach (self::hobbiesConocidosDe($partida, $enc, $objetivo, $catalog) as $opt) {
                if (($opt['id'] ?? '') === (string) $params['hobby_id']) {
                    $delObjetivo = true;
                    break;
                }
            }
            if (!$delObjetivo) {
                return GameError::respuesta(GameError::VALIDACION_FALLIDA, ['detalle' => 'hobby_de_otro_residente']);
            }
        }
        $req = self::requisitos($partida, $enc, $accionId, $a, $b, $cal, $catalog, $params);
        if (!($req['ok'] ?? false)) {
            return array_merge(
                GameError::respuesta(GameError::INTERVENCION_ACCION_INVALIDA, ['motivo' => $req['motivo'] ?? 'bloqueada']),
                ['motivo' => $req['motivo'] ?? 'bloqueada']
            );
        }

        $rng = RngService::fromPartida($partida);
        // Afinidad temática por participante (solo accion 'hobby' con tema elegido).
        $temaCargas = [];
        if ($accionId === self::HOBBY) {
            $hidTema = (string) ($params['hobby_id'] ?? '');
            if ($hidTema !== '') {
                $temaCargas = self::temaCargas($partida, [$a, $b], $hidTema, $cal);
            }
        }
        $res = self::resolverAccion($partida, $enc, $accionId, $a, $b, $params, $cal, $catalog, $rng, $temaCargas);
        $rng->persistToPartida($partida);

        foreach ($partida['encuentros'] as &$row) {
            if (($row['id'] ?? '') !== $encuentroId) {
                continue;
            }
            $row['intervencion_celeste'] = [
                'usada' => true,
                'accion' => $accionId,
                'tono' => $res['tono'],
                'resultado' => $res['resultado'],
                'texto' => $res['texto'],
                'hobby_id' => $params['hobby_id'] ?? null,
                'objetivo' => $objetivo !== '' ? $objetivo : null,
                'carga' => $res['carga'] ?? 0.0,
                'tema_cargas' => $temaCargas,
                'hito' => $res['hito'] ?? null,
                'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            ];
            $enc = $row;
            break;
        }
        unset($row);

        \aht_log_optional($logger, $partida, 'encuentro_intervencion', [
            'encuentro_id' => $encuentroId,
            'accion' => $accionId,
            'tono' => $res['tono'],
            'resultado' => $res['resultado'],
        ]);

        return [
            'ok' => true,
            'intervencion' => $enc['intervencion_celeste'] ?? null,
            'efectos' => $res['efectos'] ?? [],
            'cotilleo' => $res['cotilleo'] ?? null,
            'vista' => self::vistaParaPlay($partida, $enc, $catalog),
        ];
    }

    /**
     * @param array<string, mixed> $enc
     * @return array{0: string, 1: string}
     */
    private static function par(array $enc): array
    {
        $ids = array_values($enc['participantes'] ?? []);
        return [(string) ($ids[0] ?? ''), (string) ($ids[1] ?? '')];
    }

    private static function etiqueta(string $id): string
    {
        switch ($id) {
            case self::HABLAR:
                return 'Animar la conversación';
            case self::BROMA:
                return 'Soltar una broma';
            case self::HOBBY:
                return 'Hablar de un hobby';
            case self::PERSONAL:
                return 'Contar algo personal';
            case self::COQUETEAR:
                return 'Coquetear';
            case self::BESO:
                return 'Intentar un beso';
            default:
                return $id;
        }
    }

    /**
     * @param array<string, mixed> $enc
     * @param array<string, mixed> $cal
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function requisitos(
        array $partida,
        array $enc,
        string $accionId,
        string $a,
        string $b,
        array $cal,
        ?Catalog $catalog,
        array $params = []
    ): array {
        if ($a === '' || $b === '') {
            return ['ok' => false, 'motivo' => 'participantes'];
        }

        switch ($accionId) {
            case self::HABLAR:
                return ['ok' => true];
            case self::BROMA:
                return RelacionEngine::seConocen($partida, $a, $b)
                    ? ['ok' => true]
                    : ['ok' => false, 'motivo' => 'aun_no_se_conocen'];
            case self::HOBBY:
                return self::requisitosHobby($partida, $a, $b, $catalog, $params);
            case self::PERSONAL:
                return self::requisitosPersonal($partida, $a, $b);
            case self::COQUETEAR:
                return self::requisitosCoquetear($partida, $a, $b, $cal);
            case self::BESO:
                return self::requisitosBeso($partida, $enc, $a, $b, $cal);
            default:
                return ['ok' => false, 'motivo' => 'accion_desconocida'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function requisitosHobby(
        array $partida,
        string $a,
        string $b,
        ?Catalog $catalog,
        array $params
    ): array {
        $opts = self::hobbiesConocidos($partida, $a, $b, $catalog);
        if ($opts === []) {
            return ['ok' => false, 'motivo' => 'sin_hobby_conocido'];
        }
        $hid = isset($params['hobby_id']) ? (string) $params['hobby_id'] : '';
        if ($hid === '') {
            return [
                'ok' => $opts !== [],
                'motivo' => $opts === [] ? 'sin_hobby_conocido' : null,
                'hobbies' => $opts,
            ];
        }
        foreach ($opts as $o) {
            if (($o['id'] ?? '') === $hid) {
                return ['ok' => true, 'hobbies' => $opts];
            }
        }
        return ['ok' => false, 'motivo' => 'hobby_no_conocido'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function requisitosPersonal(array $partida, string $a, string $b): array
    {
        if (!RelacionEngine::seConocen($partida, $a, $b)) {
            return ['ok' => false, 'motivo' => 'aun_no_se_conocen'];
        }
        $min = 8;
        $ab = RelacionEngine::valorSocialHacia($partida, $a, $b);
        $ba = RelacionEngine::valorSocialHacia($partida, $b, $a);
        if ($ab >= $min && $ba >= $min) {
            return ['ok' => true];
        }
        return ['ok' => false, 'motivo' => 'vinculo_insuficiente'];
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function requisitosCoquetear(array $partida, string $a, string $b, array $cal): array
    {
        $el = RomanceElegibilidad::par($partida, $a, $b, $cal);
        if (empty($el['ok'])) {
            return ['ok' => false, 'motivo' => 'romance_no_elegible'];
        }
        if (!RelacionEngine::seConocen($partida, $a, $b)) {
            return ['ok' => false, 'motivo' => 'aun_no_se_conocen'];
        }
        if (SenalRomantica::direccionVisible($partida, $a, $b, $cal) !== null) {
            return ['ok' => true];
        }
        return ['ok' => false, 'motivo' => 'sin_contexto_romantico'];
    }

    /**
     * @param array<string, mixed> $enc
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function requisitosBeso(array $partida, array $enc, string $a, string $b, array $cal): array
    {
        $el = RomanceElegibilidad::par($partida, $a, $b, $cal);
        if (empty($el['ok'])) {
            return ['ok' => false, 'motivo' => 'romance_no_elegible'];
        }
        if (!RelacionEngine::seConocen($partida, $a, $b)) {
            return ['ok' => false, 'motivo' => 'aun_no_se_conocen'];
        }
        $tipo = PropuestaNivel::aliasTipo((string) ($enc['tipo'] ?? ''));
        $pareja = ParejaEngine::estado($partida, $a, $b);
        if ($pareja === ParejaEngine::PAREJA || $pareja === ParejaEngine::CRISIS) {
            return ['ok' => true];
        }
        if (PropuestaNivel::esTipoCita($tipo)) {
            return ['ok' => true];
        }
        $ab = SenalRomantica::desdeHacia($partida, $a, $b, $cal);
        $ba = SenalRomantica::desdeHacia($partida, $b, $a, $cal);
        if (!empty($ab['ok']) && !empty($ba['ok'])) {
            return ['ok' => true];
        }
        return ['ok' => false, 'motivo' => 'beso_prematuro'];
    }

    /**
     * Temas/hobbies que Celestine puede elegir legítimamente por participante:
     * hobbies propios descubiertos + gustos/rechazos de hobby descubiertos.
     * Nunca expone lo no descubierto.
     *
     * @return list<array{id: string, etiqueta: string, residente_id: string, residente_nombre: string, origen: string}>
     */
    private static function hobbiesConocidos(array $partida, string $a, string $b, ?Catalog $catalog): array
    {
        $out = [];
        $seen = [];
        $seenGlobal = [];
        foreach ([$a, $b] as $rid) {
            $seen[$rid] = [];
            $perfil = $catalog !== null
                ? PerfilPartida::deOLegacy($partida, $rid, $catalog)
                : (PerfilPartida::de($partida, $rid) ?? []);
            foreach ($perfil['hobbies'] ?? [] as $hid) {
                if (!is_string($hid) || $hid === '' || isset($seen[$rid][$hid]) || isset($seenGlobal[$hid])) {
                    continue;
                }
                if (!DiscoveryReveal::jugadorSabeHobby($partida, $rid, $hid)) {
                    continue;
                }
                $label = $hid;
                if ($catalog !== null) {
                    try {
                        $label = EtiquetaFicha::hobby($hid, $catalog->store());
                    } catch (\Throwable $ignored) {
                    }
                }
                $seen[$rid][$hid] = true;
                $seenGlobal[$hid] = true;
                $out[] = [
                    'id' => $hid,
                    'etiqueta' => $label,
                    'residente_id' => $rid,
                    'residente_nombre' => IdentidadPublica::nombre($partida, $rid),
                    'origen' => 'propio',
                ];
            }
            foreach (DiscoveryEngine::listarPorResidente($partida, $rid) as $d) {
                if (!is_array($d) || (($d['estado'] ?? DiscoveryEngine::DESCUBIERTO) !== DiscoveryEngine::DESCUBIERTO)) {
                    continue;
                }
                $campo = (string) ($d['campo'] ?? '');
                $esGusto = str_starts_with($campo, 'gusto_hobby:');
                $esRechazo = str_starts_with($campo, 'rechazo_hobby:');
                if (!$esGusto && !$esRechazo) {
                    continue;
                }
                $hid = CopyDescubrimiento::idDeCampo($campo);
                if ($hid === '' || isset($seen[$rid][$hid]) || isset($seenGlobal[$hid])) {
                    continue;
                }
                $label = $hid;
                if ($catalog !== null) {
                    try {
                        $label = EtiquetaFicha::hobby($hid, $catalog->store());
                    } catch (\Throwable $ignored) {
                    }
                }
                $seen[$rid][$hid] = true;
                $seenGlobal[$hid] = true;
                $out[] = [
                    'id' => $hid,
                    'etiqueta' => $label,
                    'residente_id' => $rid,
                    'residente_nombre' => IdentidadPublica::nombre($partida, $rid),
                    'origen' => $esGusto ? 'gusto' : 'rechazo',
                ];
            }
        }
        return $out;
    }

    /**
     * Favorabilidad de un tema para UN residente, con precedencia canónica
     * rechazo > gusto > propio. Devuelve el signo y si es hobby propio.
     *
     * @return array{favor: int, propio: bool}
     */
    public static function temaFavorabilidad(array $partida, string $rid, string $hobbyId): array
    {
        if ($rid === '' || $hobbyId === '') {
            return ['favor' => 0, 'propio' => false];
        }
        $perfil = PerfilPartida::de($partida, $rid)
            ?? PerfilPartida::deOLegacy($partida, $rid, null);
        $prefs = is_array($perfil['preferencias'] ?? null) ? $perfil['preferencias'] : [];
        $neg = is_array($prefs['hobbies_neg'] ?? null) ? $prefs['hobbies_neg'] : [];
        if (in_array($hobbyId, $neg, true)) {
            return ['favor' => -1, 'propio' => false];
        }
        $pos = is_array($prefs['hobbies_pos'] ?? null) ? $prefs['hobbies_pos'] : [];
        if (in_array($hobbyId, $pos, true)) {
            return ['favor' => 1, 'propio' => false];
        }
        $propios = is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : [];
        if (in_array($hobbyId, $propios, true)) {
            return ['favor' => 1, 'propio' => true];
        }
        return ['favor' => 0, 'propio' => false];
    }

    /**
     * Carga temática INDIVIDUAL por participante para un hobby elegido.
     * Se aplica a la tirada de experiencia de cada quien al terminar el encuentro,
     * de modo que un mismo tema pueda sentar bien a uno y mal al otro.
     *
     * @param list<string> $participantes
     * @return array<string, float>
     */
    public static function temaCargas(
        array $partida,
        array $participantes,
        string $hobbyId,
        array $cal
    ): array {
        $out = [];
        if ($hobbyId === '') {
            return $out;
        }
        $maxAbs = (float) CalibracionConfig::get($cal, 'intervencion_tema.max_abs', 0.35);
        foreach ($participantes as $rid) {
            $rid = (string) $rid;
            if ($rid === '' || !isset($partida['residentes'][$rid])) {
                continue;
            }
            $fav = self::temaFavorabilidad($partida, $rid, $hobbyId);
            $v = 0.0;
            if ($fav['favor'] < 0) {
                $v = (float) CalibracionConfig::get($cal, 'intervencion_tema.carga_rechazo', -0.28);
            } elseif ($fav['favor'] > 0) {
                $k = $fav['propio'] ? 'intervencion_tema.carga_propio' : 'intervencion_tema.carga_gusto';
                $def = $fav['propio'] ? 0.18 : 0.22;
                $v = (float) CalibracionConfig::get($cal, $k, $def);
            }
            if ($maxAbs > 0) {
                $v = max(-$maxAbs, min($maxAbs, $v));
            }
            $out[$rid] = $v;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $enc
     * @param array<string, mixed> $params
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function resolverAccion(
        array &$partida,
        array $enc,
        string $accionId,
        string $a,
        string $b,
        array $params,
        array $cal,
        Catalog $catalog,
        RngService $rng,
        array $temaCargas = []
    ): array {
        $resultados = CalibracionConfig::get($cal, 'resolucion_encuentro.resultados', ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien']);
        if (!is_array($resultados) || $resultados === []) {
            $resultados = ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien'];
        }

        $carga = self::cargaBase($partida, $enc, $accionId, $a, $b, $params, $cal, $catalog, $temaCargas);
        $tirada = AzarPonderado::tirar($rng, $resultados, $carga, $cal);
        $resultado = (string) ($tirada['resultado'] ?? 'normal');
        $tono = self::tonoDe($resultado);

        $efectos = [];
        $hito = null;
        $cotilleo = null;

        $deltas = EncuentroDeltasReales::deResultado($resultado, (string) ($enc['tipo'] ?? 'conocerse'), $cal);
        $factor = self::factorAccion($accionId, $tono);

        if ($accionId === self::BESO) {
            return self::resolverBeso($partida, $enc, $a, $b, $cal, $catalog, $rng);
        }

        $calidad = $deltas['calidad'];
        if ($tono === 'mal') {
            $calidad = ContactoCalidad::LEVE;
        } elseif ($tono === 'bien') {
            $calidad = ContactoCalidad::SIGNIFICATIVO;
        }

        $dSocial = (int) round($deltas['social'] * $factor);
        $dRom = (int) round($deltas['romance'] * $factor);

        if ($accionId !== self::COQUETEAR) {
            RelacionEngine::registrarContacto($partida, $a, $b, $calidad, $cal, $dSocial >= 0 ? 1 : -1, $dSocial);
            RelacionEngine::registrarContacto($partida, $b, $a, $calidad, $cal, $dSocial >= 0 ? 1 : -1, $dSocial);
            $efectos['social'] = ['a_hacia_b' => $dSocial, 'b_hacia_a' => $dSocial];
        }

        if ($accionId === self::COQUETEAR) {
            $dir = SenalRomantica::direccionVisible($partida, $a, $b, $cal);
            if ($dir !== null) {
                $desde = $dir['desde'];
                $hacia = $dir['hacia'];
                $rom = max(1, (int) round(($dRom ?: 2) * $factor));
                if ($tono === 'mal') {
                    $rom = -1;
                }
                $act = RelacionEngine::romanceHacia($partida, $desde, $hacia) ?? 0;
                RelacionEngine::setRomanceHacia($partida, $desde, $hacia, RelacionBandas::clampRomance($act + $rom));
                $efectos['romance'] = ['desde' => $desde, 'hacia' => $hacia, 'delta' => $rom];
                SenalRomantica::avisarSiAplica($partida, $desde, $hacia, $cal);
                if ($tono === 'bien' && $rom > 0) {
                    RelacionBitacora::registrar($partida, RelacionBitacora::HITO_ROMANTICO, [$desde, $hacia], $desde . '>' . $hacia);
                    $hito = RelacionBitacora::HITO_ROMANTICO;
                    $cotilleo = RelacionNarrativaBridge::alHito($partida, RelacionBitacora::HITO_ROMANTICO, [$desde, $hacia]);
                }
            }
        }

        // Reacción emocional inmediata al TEMA elegido (por participante):
        // - a quien le favorece y el tono no es malo: se anima según resultado;
        // - a quien el tema le repugna y sale mal: se enfada con el otro.
        // El veredicto final por participante llega al terminar el encuentro
        // vía tema_cargas en EncuentroExperiencia.
        if ($accionId === self::HOBBY && $temaCargas !== []) {
            foreach ($temaCargas as $rid => $tc) {
                $rid = (string) $rid;
                if (!isset($partida['residentes'][$rid]) || (float) $tc === 0.0) {
                    continue;
                }
                $otro = $rid === $a ? $b : $a;
                if ((float) $tc > 0.0 && $tono !== 'mal') {
                    self::aplicarEmocion(
                        $partida,
                        $rid,
                        EmotionalRecovery::estadoDesdeResultado($resultado) ?? EstadoEmocional::NEUTRO
                    );
                    $efectos['tema_emocion'][] = ['residente' => $rid, 'tema_match' => true];
                } elseif ((float) $tc < 0.0 && $tono === 'mal') {
                    self::aplicarEmocion($partida, $rid, EstadoEmocional::ENFADADO, ['hacia' => $otro]);
                    $efectos['tema_emocion'][] = ['residente' => $rid, 'tema_rechazo' => true];
                }
            }
        }

        if ($accionId === self::BROMA && $tono === 'mal') {
            $conf = max(1, (int) ($deltas['conflicto'] ?? 1));
            RelacionEngine::upsertConflicto($partida, $a, $b, $conf, 'roce', 'intervencion_broma');
            $efectos['conflicto'] = $conf;
            RelacionBitacora::registrar($partida, RelacionBitacora::DISCUSION_FUERTE, [$a, $b]);
            $hito = RelacionBitacora::DISCUSION_FUERTE;
            $cotilleo = RelacionNarrativaBridge::alHito($partida, RelacionBitacora::DISCUSION_FUERTE, [$a, $b]);
        }

        if ($accionId === self::PERSONAL && $tono === 'bien') {
            RelacionEngine::registrarContacto($partida, $a, $b, ContactoCalidad::SIGNIFICATIVO, $cal, 1, max(2, $dSocial));
            RelacionEngine::registrarContacto($partida, $b, $a, ContactoCalidad::SIGNIFICATIVO, $cal, 1, max(2, $dSocial));
        }

        $texto = self::copyResultado($partida, $enc, $accionId, $tono, $a, $b, $params, $catalog);

        return [
            'tono' => $tono,
            'resultado' => $resultado,
            'texto' => $texto,
            'carga' => $carga,
            'efectos' => $efectos,
            'hito' => $hito,
            'cotilleo' => $cotilleo !== null,
        ];
    }

    /**
     * @param array<string, mixed> $enc
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function resolverBeso(
        array &$partida,
        array $enc,
        string $a,
        string $b,
        array $cal,
        Catalog $catalog,
        RngService $rng
    ): array {
        $desde = $a;
        $hacia = $b;
        $ra = RelacionEngine::romanceHacia($partida, $a, $b) ?? 0;
        $rb = RelacionEngine::romanceHacia($partida, $b, $a) ?? 0;
        if ($rb > $ra) {
            $desde = $b;
            $hacia = $a;
        }

        $p = 0.28;
        if (ParejaEngine::estado($partida, $a, $b) === ParejaEngine::PAREJA) {
            $p += 0.35;
        }
        if (PropuestaNivel::esTipoCita((string) ($enc['tipo'] ?? ''))) {
            $p += 0.15;
        }
        $ab = SenalRomantica::desdeHacia($partida, $desde, $hacia, $cal);
        $ba = SenalRomantica::desdeHacia($partida, $hacia, $desde, $cal);
        if (!empty($ab['ok'])) {
            $p += 0.12;
        }
        if (!empty($ba['ok'])) {
            $p += 0.18;
        }
        $q = QuimicaEngine::valorHacia($partida, $desde, $hacia);
        if ($q !== null) {
            $p += $q / 250.0;
        }
        $emo = (string) ($partida['residentes'][$hacia]['runtime']['estado_emocional']['id'] ?? EstadoEmocional::NEUTRO);
        if ($emo === EstadoEmocional::ENFADADO) {
            $p -= 0.2;
        }
        $p = max(0.05, min(0.92, $p));

        $aceptado = $rng->nextFloat() < $p;
        $efectos = [];
        $hito = null;
        $cotilleo = null;

        if ($aceptado) {
            RelacionBitacora::registrar($partida, RelacionBitacora::HITO_ROMANTICO, [$desde, $hacia], $desde . '>' . $hacia);
            $act = RelacionEngine::romanceHacia($partida, $desde, $hacia) ?? 0;
            RelacionEngine::setRomanceHacia($partida, $desde, $hacia, RelacionBandas::clampRomance($act + 3));
            $actR = RelacionEngine::romanceHacia($partida, $hacia, $desde) ?? 0;
            RelacionEngine::setRomanceHacia($partida, $hacia, $desde, RelacionBandas::clampRomance($actR + 2));
            SenalRomantica::avisarSiAplica($partida, $desde, $hacia, $cal);
            $efectos['romance'] = ['aceptado' => true, 'desde' => $desde, 'hacia' => $hacia];
            $hito = RelacionBitacora::HITO_ROMANTICO;
            $cotilleo = RelacionNarrativaBridge::alHito($partida, RelacionBitacora::HITO_ROMANTICO, [$desde, $hacia]);
            $tono = 'bien';
            $texto = IdentidadPublica::nombre($partida, $desde) . ' y ' . IdentidadPublica::nombre($partida, $hacia)
                . ' se besan. El plan se calienta.';
        } else {
            RelacionBitacora::registrar($partida, RelacionBitacora::RECHAZO_IMPORTANTE, [$desde, $hacia], $desde . '>' . $hacia);
            RelacionEngine::registrarContacto($partida, $desde, $hacia, ContactoCalidad::LEVE, $cal, -1, -2);
            RelacionEngine::registrarContacto($partida, $hacia, $desde, ContactoCalidad::LEVE, $cal, -1, -1);
            RelacionEngine::upsertConflicto($partida, $a, $b, 2, 'roce', 'beso_rechazado');
            self::aplicarEmocion($partida, $hacia, EstadoEmocional::ENFADADO, ['hacia' => $desde]);
            $efectos['romance'] = ['aceptado' => false];
            $efectos['conflicto'] = 2;
            $hito = RelacionBitacora::RECHAZO_IMPORTANTE;
            $cotilleo = RelacionNarrativaBridge::alHito($partida, RelacionBitacora::DISCUSION_FUERTE, [$a, $b]);
            $tono = 'mal';
            $texto = IdentidadPublica::nombre($partida, $hacia) . ' frena el beso. Momentazo incómodo.';
        }

        return [
            'tono' => $tono,
            'resultado' => $aceptado ? 'bien' : 'mal',
            'texto' => $texto,
            'carga' => $aceptado ? 0.35 : -0.4,
            'efectos' => $efectos,
            'hito' => $hito,
            'cotilleo' => $cotilleo !== null,
        ];
    }

    /**
     * @param array<string, mixed> $enc
     * @param array<string, mixed> $params
     * @param array<string, float> $temaCargas
     */
    private static function cargaBase(
        array $partida,
        array $enc,
        string $accionId,
        string $a,
        string $b,
        array $params,
        array $cal,
        Catalog $catalog,
        array $temaCargas = []
    ): float {
        $snap = EncuentroPonderacion::snapshot($partida, $enc, $catalog);
        $carga = (EncuentroExperiencia::cargaDe($snap, $a, $cal) + EncuentroExperiencia::cargaDe($snap, $b, $cal)) / 2.0;
        if ($accionId === self::BROMA) {
            $carga += 0.08;
        }
        if ($accionId === self::PERSONAL) {
            $carga += 0.05;
        }
        if ($accionId === self::COQUETEAR) {
            $carga += 0.1;
        }
        if ($accionId === self::HOBBY && $temaCargas !== []) {
            // Acierto temático: la mejor favorabilidad individual carga la tirada global.
            // Sustituye el antiguo check muerto plan['hobby_match'] (clave inexistente).
            $mejor = max(0.0, (float) ($temaCargas[$a] ?? 0.0), (float) ($temaCargas[$b] ?? 0.0));
            if ($mejor > 0.0) {
                $carga += min(0.2, $mejor);
            }
        }
        return max(-1.0, min(1.0, $carga));
    }

    /**
     * Magnitud del efecto de la acción según tono. El SIGNO lo aporta el delta base
     * (ya negativo para mal/muy_mal): nunca negar el signo del delta.
     */
    private static function factorAccion(string $accionId, string $tono): float
    {
        switch ($accionId) {
            case self::HABLAR:
                $base = 0.85;
                break;
            case self::BROMA:
                $base = 1.0;
                break;
            case self::HOBBY:
                $base = 1.1;
                break;
            case self::PERSONAL:
                $base = 1.25;
                break;
            case self::COQUETEAR:
                $base = 1.0;
                break;
            default:
                $base = 1.0;
                break;
        }
        if ($tono === 'neutral') {
            return $base * 0.5;
        }
        return $base;
    }

    private static function tonoDe(string $resultado): string
    {
        if ($resultado === 'muy_bien' || $resultado === 'bien') {
            return 'bien';
        }
        if ($resultado === 'muy_mal' || $resultado === 'mal') {
            return 'mal';
        }
        return 'neutral';
    }

    private static function aplicarEmocion(
        array &$partida,
        string $rid,
        string $estadoId,
        array $contexto = []
    ): void {
        if (!isset($partida['residentes'][$rid])) {
            return;
        }
        EstadoEmocional::ensureResidente($partida['residentes'][$rid], $partida['reloj'] ?? null);
        $partida['residentes'][$rid]['runtime']['estado_emocional'] = EstadoEmocional::estructura(
            $estadoId,
            null,
            'encuentro_intervencion',
            EstadoEmocional::marcaReloj($partida['reloj'] ?? null),
            EstadoEmocional::hastaDesdeDuracion($partida['reloj'] ?? [], 3),
            $contexto,
            3
        );
    }

    /**
     * @param array<string, mixed> $enc
     * @param array<string, mixed> $params
     */
    private static function copyResultado(
        array $partida,
        array $enc,
        string $accionId,
        string $tono,
        string $a,
        string $b,
        array $params,
        Catalog $catalog
    ): string {
        $na = IdentidadPublica::nombre($partida, $a);
        $nb = IdentidadPublica::nombre($partida, $b);
        $par = $na . ' y ' . $nb;

        if ($accionId === self::HABLAR) {
            switch ($tono) {
                case 'bien':
                    return 'La charla fluye entre ' . $par . '.';
                case 'mal':
                    return 'La conversación se enfría un poco.';
                default:
                    return 'Charlan sin más sobresaltos.';
            }
        }
        if ($accionId === self::BROMA) {
            switch ($tono) {
                case 'bien':
                    return 'La broma cae bien y sueltan una risa.';
                case 'mal':
                    return 'La broma no pega y queda un silencio raro.';
                default:
                    return 'La broma pasa sin pena ni gloria.';
            }
        }
        if ($accionId === self::HOBBY) {
            $hid = (string) ($params['hobby_id'] ?? '');
            $label = $hid;
            if ($hid !== '') {
                try {
                    $label = EtiquetaFicha::hobby($hid, $catalog->store());
                } catch (\Throwable $ignored) {
                }
            }
            switch ($tono) {
                case 'bien':
                    return 'Sacan el tema de ' . $label . ' y conectan.';
                case 'mal':
                    return 'El tema de ' . $label . ' no despega.';
                default:
                    return 'Mencionan ' . $label . ' sin mucho brillo.';
            }
        }
        if ($accionId === self::PERSONAL) {
            switch ($tono) {
                case 'bien':
                    return $par . ' se abren un poco más de lo habitual.';
                case 'mal':
                    return 'Algo personal cae mal y cierran filas.';
                default:
                    return 'Comparten algo personal con cautela.';
            }
        }
        if ($accionId === self::COQUETEAR) {
            switch ($tono) {
                case 'bien':
                    return 'Hay guiños que no pasan desapercibidos.';
                case 'mal':
                    return 'El coqueteo queda torpe.';
                default:
                    return 'Un comentario con doble sentido… y poco más.';
            }
        }
        return 'Momento en el encuentro.';
    }
}
