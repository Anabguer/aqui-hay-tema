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
     * Temas que la persona influida (A) puede sacar para interesar al interlocutor (B):
     * hobbies reales de B que A conoce y el jugador ya ha descubierto de B.
     *
     * @param array<string, mixed> $enc
     * @return list<array{id: string, etiqueta: string, residente_id: string, residente_nombre: string, objetivo_id: string}>
     */
    public static function hobbiesTemaConocidos(
        array $partida,
        array $enc,
        string $objetivoId,
        ?Catalog $catalog
    ): array {
        $objetivoId = (string) $objetivoId;
        $interlocutor = self::interlocutorDe($enc, $objetivoId);
        if ($objetivoId === '' || $interlocutor === '') {
            return [];
        }
        $out = [];
        foreach (ConocimientoNpc::hobbiesConocidos($partida, $objetivoId, $interlocutor) as $hid) {
            if (!DiscoveryReveal::jugadorSabeHobby($partida, $interlocutor, $hid)) {
                continue;
            }
            $label = $hid;
            if ($catalog !== null) {
                try {
                    $label = EtiquetaFicha::hobby($hid, $catalog->store());
                } catch (\Throwable $ignored) {
                }
            }
            $out[] = [
                'id' => $hid,
                'etiqueta' => $label,
                'residente_id' => $interlocutor,
                'residente_nombre' => IdentidadPublica::nombre($partida, $interlocutor),
                'objetivo_id' => $objetivoId,
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $enc
     */
    public static function interlocutorDe(array $enc, string $objetivoId): string
    {
        $objetivoId = (string) $objetivoId;
        [$a, $b] = self::par($enc);
        if ($objetivoId === $a) {
            return $b;
        }
        if ($objetivoId === $b) {
            return $a;
        }
        return '';
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
            if ($id === self::HABLAR) {
                continue;
            }
            $req = self::requisitos($partida, $enc, $id, $a, $b, $cal, $catalog);
            $row = [
                'id' => $id,
                'etiqueta' => self::etiqueta($id),
                'disponible' => (bool) ($req['ok'] ?? false),
                'motivo' => $req['motivo'] ?? null,
            ];
            if ($id === self::HOBBY) {
                $temasA = MentesTemas::temasElegibles($partida, $enc, $a, $catalog);
                $temasB = MentesTemas::temasElegibles($partida, $enc, $b, $catalog);
                $row['temas_por_objetivo'] = [
                    $a => $temasA,
                    $b => $temasB,
                ];
                $row['disponible'] = $temasA !== [] || $temasB !== [];
                if (!$row['disponible']) {
                    $row['motivo'] = 'sin_temas_conocidos';
                }
                $row['kickers_rompe'] = [
                    'A ver, %s… ¿por dónde tiramos?',
                    'Venga, %s… a ver si damos en el clavo.',
                    '%s, esta es la tuya. ¿Por dónde entramos?',
                    '¿Con qué nos la jugamos, %s?',
                    'Venga, %s… elige bien.',
                ];
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
        if ($accionId === self::HOBBY && isset($params['hobby_id'])) {
            $optsHobby = $objetivo !== ''
                ? MentesTemas::temasElegibles($partida, $enc, $objetivo, $catalog)
                : [];
            $hid = (string) $params['hobby_id'];
            $encontrado = false;
            foreach ($optsHobby as $opt) {
                if (($opt['id'] ?? '') === $hid) {
                    $params['residente_id'] = (string) ($opt['interlocutor_id'] ?? '');
                    $params['rompe_hielo'] = (string) ($opt['rompe_hielo_id'] ?? $objetivo);
                    $encontrado = true;
                    break;
                }
            }
            if (!$encontrado && $objetivo === '') {
                foreach (self::hobbiesConocidos($partida, $a, $b, $catalog) as $opt) {
                    if (($opt['id'] ?? '') === $hid) {
                        $params['residente_id'] = (string) ($opt['residente_id'] ?? '');
                        $encontrado = true;
                        break;
                    }
                }
            }
            if (!$encontrado) {
                return GameError::respuesta(GameError::VALIDACION_FALLIDA, ['detalle' => 'tema_no_disponible']);
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
        if ($accionId === self::HOBBY && $objetivo !== '') {
            $interlocutorPre = self::interlocutorDe($enc, $objetivo);
            $hidPre = (string) ($params['hobby_id'] ?? '');
            if ($interlocutorPre !== '' && $hidPre !== '') {
                $params['afinidad_tema'] = MentesTemas::evaluarAfinidad(
                    $partida,
                    $interlocutorPre,
                    $hidPre,
                    $catalog
                );
                $params['residente_id'] = $interlocutorPre;
            }
        }
        $res = self::resolverAccion($partida, $enc, $accionId, $a, $b, $params, $cal, $catalog, $rng);
        $rng->persistToPartida($partida);

        foreach ($partida['encuentros'] as &$row) {
            if (($row['id'] ?? '') !== $encuentroId) {
                continue;
            }
            $interlocutor = $objetivo !== '' ? self::interlocutorDe($enc, $objetivo) : null;
            $row['intervencion_celeste'] = [
                'usada' => true,
                'accion' => $accionId,
                'tono' => $res['tono'],
                'resultado' => $res['resultado'],
                'texto' => $res['texto'],
                'hobby_id' => $params['hobby_id'] ?? null,
                'tema_id' => $params['hobby_id'] ?? null,
                'objetivo' => $objetivo !== '' ? $objetivo : null,
                'rompe_hielo' => $objetivo !== '' ? $objetivo : null,
                'interlocutor' => $interlocutor !== '' ? $interlocutor : null,
                'afinidad_tema' => $params['afinidad_tema'] ?? null,
                'beneficiario' => isset($params['residente_id']) ? (string) $params['residente_id'] : null,
                'carga' => $res['carga'] ?? 0.0,
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
            'objetivo' => $objetivo !== '' ? $objetivo : null,
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
                return 'Sacar un tema que le guste';
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
                return self::requisitosHobby($partida, $enc, $a, $b, $catalog, $params);
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
        array $enc,
        string $a,
        string $b,
        ?Catalog $catalog,
        array $params
    ): array {
        $objetivo = isset($params['objetivo']) ? (string) $params['objetivo'] : '';
        if ($objetivo !== '') {
            $opts = MentesTemas::temasElegibles($partida, $enc, $objetivo, $catalog);
            if ($opts === []) {
                return ['ok' => false, 'motivo' => 'sin_temas_conocidos'];
            }
            $hid = isset($params['hobby_id']) ? (string) $params['hobby_id'] : '';
            if ($hid === '') {
                return ['ok' => true];
            }
            foreach ($opts as $o) {
                if (($o['id'] ?? '') === $hid) {
                    return ['ok' => true];
                }
            }
            return ['ok' => false, 'motivo' => 'tema_no_disponible'];
        }
        $legacy = self::hobbiesConocidos($partida, $a, $b, $catalog);
        if ($legacy === []) {
            return ['ok' => false, 'motivo' => 'sin_hobby_conocido'];
        }
        $hid = isset($params['hobby_id']) ? (string) $params['hobby_id'] : '';
        if ($hid === '') {
            return ['ok' => true, 'hobbies' => $legacy];
        }
        foreach ($legacy as $o) {
            if (($o['id'] ?? '') === $hid) {
                return ['ok' => true, 'hobbies' => $legacy];
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
     * @return list<array{id: string, etiqueta: string, residente_id: string}>
     */
    private static function hobbiesConocidos(array $partida, string $a, string $b, ?Catalog $catalog): array
    {
        $out = [];
        foreach ([$a, $b] as $rid) {
            $perfil = $catalog !== null
                ? PerfilPartida::deOLegacy($partida, $rid, $catalog)
                : (PerfilPartida::de($partida, $rid) ?? []);
            foreach ($perfil['hobbies'] ?? [] as $hid) {
                if (!is_string($hid) || $hid === '') {
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
                $out[] = [
                    'id' => $hid,
                    'etiqueta' => $label,
                    'residente_id' => $rid,
                    'residente_nombre' => IdentidadPublica::nombre($partida, $rid),
                ];
            }
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
        RngService $rng
    ): array {
        $resultados = CalibracionConfig::get($cal, 'resolucion_encuentro.resultados', ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien']);
        if (!is_array($resultados) || $resultados === []) {
            $resultados = ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien'];
        }

        $carga = self::cargaBase($partida, $enc, $accionId, $a, $b, $params, $cal, $catalog);
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

        if ($accionId === self::HOBBY) {
            $hid = (string) ($params['hobby_id'] ?? '');
            $influido = isset($params['objetivo']) ? (string) $params['objetivo'] : '';
            $interlocutor = $influido !== '' ? self::interlocutorDe($enc, $influido) : '';
            $afinidad = (string) ($params['afinidad_tema'] ?? 'neutro');
            if ($hid !== '' && $interlocutor !== '' && $tono !== 'mal' && $afinidad === 'afin') {
                self::aplicarEmocion(
                    $partida,
                    $interlocutor,
                    EmotionalRecovery::estadoDesdeResultado($resultado) ?? EstadoEmocional::NEUTRO
                );
                $efectos['emocion'] = [
                    'residente' => $interlocutor,
                    'afinidad_tema' => $afinidad,
                    'rompe_hielo' => $influido !== '' ? $influido : null,
                ];
            }
            if ($influido !== '' && $interlocutor !== '' && $tono === 'bien' && $afinidad === 'afin') {
                $perfilA = PerfilPartida::deOLegacy($partida, $influido, $catalog);
                if (in_array($hid, $perfilA['hobbies'] ?? [], true)) {
                    self::aplicarEmocion(
                        $partida,
                        $influido,
                        EmotionalRecovery::estadoDesdeResultado($resultado) ?? EstadoEmocional::NEUTRO
                    );
                    $efectos['emocion_compartida'] = ['residente' => $influido];
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
            self::aplicarEmocion($partida, $hacia, EstadoEmocional::ENFADADO);
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
     * @param array<string, mixed> $cal
     */
    private static function cargaBase(
        array $partida,
        array $enc,
        string $accionId,
        string $a,
        string $b,
        array $params,
        array $cal,
        Catalog $catalog
    ): float {
        $snap = EncuentroPonderacion::snapshot($partida, $enc, $catalog);
        $carga = (EncuentroExperiencia::cargaDe($snap, $a, $cal) + EncuentroExperiencia::cargaDe($snap, $b, $cal)) / 2.0;
        if ($accionId === self::HABLAR) {
            $carga += 0.06;
        }
        if ($accionId === self::BROMA) {
            $carga += 0.08;
        }
        if ($accionId === self::PERSONAL) {
            $carga += 0.05;
        }
        if ($accionId === self::COQUETEAR) {
            $carga += 0.1;
        }
        if ($accionId === self::HOBBY) {
            $hid = (string) ($params['hobby_id'] ?? '');
            $afinidad = (string) ($params['afinidad_tema'] ?? 'neutro');
            $calM = CalibracionConfig::get($cal, 'mentes.carga_' . $afinidad, null);
            if ($calM !== null) {
                $carga += (float) $calM;
            } elseif ($afinidad === 'afin') {
                $carga += 0.22;
            } elseif ($afinidad === 'aversion') {
                $carga -= 0.18;
            } else {
                $carga += 0.04;
            }
            $interlocutor = '';
            $objetivoCarga = isset($params['objetivo']) ? (string) $params['objetivo'] : '';
            if ($objetivoCarga !== '') {
                $interlocutor = self::interlocutorDe($enc, $objetivoCarga);
            }
            if ($interlocutor !== '' && $hid !== '') {
                $lugar = (string) ($enc['lugar'] ?? '');
                $plan = PlanAfinidad::paraParticipante($partida, $interlocutor, $lugar, $catalog);
                if (is_array($plan) && !empty($plan['relacionado']) && $afinidad === 'afin') {
                    $carga += 0.08;
                }
            }
        }
        return max(-1.0, min(1.0, $carga));
    }

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
        if ($tono === 'mal') {
            return -$base;
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

    private static function aplicarEmocion(array &$partida, string $rid, string $estadoId): void
    {
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
            [],
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
        $objetivo = isset($params['objetivo']) ? (string) $params['objetivo'] : '';
        $nInfluido = $objetivo !== '' ? IdentidadPublica::nombre($partida, $objetivo) : '';
        $beneficiario = (string) ($params['residente_id'] ?? '');
        $nBenef = $beneficiario !== '' ? IdentidadPublica::nombre($partida, $beneficiario) : '';

        if ($accionId === self::HABLAR) {
            if ($objetivo !== '') {
                switch ($tono) {
                    case 'bien':
                        return 'Le has dado un empujoncito a ' . $nInfluido . ' y la charla fluye con más soltura.';
                    case 'mal':
                        return 'Le has dado un empujoncito a ' . $nInfluido . ', pero la conversación se enfría un poco.';
                    default:
                        return 'Le has dado un empujoncito a ' . $nInfluido . '. Charlan sin más sobresaltos.';
                }
            }
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
                    $label = MentesTemas::etiquetaTema($hid, $catalog->store());
                } catch (\Throwable $ignored) {
                }
            }
            if ($objetivo !== '') {
                $interlocutor = self::interlocutorDe($enc, $objetivo);
                if ($interlocutor !== '') {
                    $afinidad = (string) ($params['afinidad_tema'] ?? 'neutro');
                    $rngCopy = RngService::fromPartida($partida);
                    return MentesTemas::copyResultado(
                        $partida,
                        $objetivo,
                        $interlocutor,
                        $label,
                        $afinidad,
                        $tono,
                        $rngCopy
                    );
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
