<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Presentación jugable de llegadas (§24.1): perfil previo, acompañante, franja bienvenida, visibilidad.
 * No altera curva de llegadas ni cap de población.
 */
final class LlegadaPresentacionEngine
{
    public const FRANJA_HORAS = 4;
    public const LUGAR_BIENVENIDA = 'lug_parque';

  /** @var list<string> */
    private const PRESENTACION_BANCO = [
        'Busco un sitio tranquilo donde empezar de cero.',
        'Me mudé por cambio de aires. El pueblo me ha llamado.',
        'Vengo con buena energía y ganas de conocer gente.',
        'Dicen que aquí la vida es más humana. Quiero comprobarlo.',
        'Llevo tiempo buscando un lugar donde sentirme parte de algo.',
    ];

    public static function ensure(array &$partida): void
    {
        CandidatoLlegadaEngine::ensure($partida);
        $partida['llegadas']['presentacion'] ??= [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function perfilCandidato(string $root, string $catalogId): array
    {
        if (!PoolJugableCanon::esIdCanonico($catalogId)) {
            return ['ok' => false, 'error' => 'candidato_no_canonico'];
        }
        try {
            $catalog = new Catalog($root);
            $ficha = $catalog->loadPersonaje($catalogId);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'ficha_no_encontrada'];
        }
        $ident = is_array($ficha['identidad'] ?? null) ? $ficha['identidad'] : [];
        $nombre = (string) ($ident['nombre'] ?? NombresReservadosPartida::nombreCatalogo($root, $catalogId));
        $genero = (string) ($ident['genero'] ?? '');
        $edad = isset($ident['edad']) ? (int) $ident['edad'] : null;
        $store = $catalog->store();
        $indicadores = IndicadoresVisuales::desdeCatalogo($ficha, $store);
        $rasgosVisibles = [];
        foreach (array_slice($indicadores, 0, 2) as $ind) {
            $rasgosVisibles[] = EtiquetaFicha::rasgoParaGenero((string) $ind, $genero, $store);
        }
        $hobbyId = self::hobbyPublicoPreview($store, $catalogId);
        $hobbyLabel = $hobbyId !== '' ? EtiquetaFicha::hobby($hobbyId, $store) : '';
        $idx = abs(crc32('presentacion|' . $catalogId)) % count(self::PRESENTACION_BANCO);
        $presentacion = self::PRESENTACION_BANCO[$idx];
        $packs = new VisualPackStore($root);
        $packId = (string) ($ficha['visual']['pack_id'] ?? '');
        $retratoUrl = self::retratoNeutroUrl($packs, $packId, $root, $store);

        return [
            'ok' => true,
            'catalog_id' => $catalogId,
            'nombre' => $nombre,
            'genero' => $genero,
            'edad' => $edad,
            'presentacion' => $presentacion,
            'hobby_visible' => $hobbyLabel,
            'rasgos_visibles' => $rasgosVisibles,
            'retrato_url' => $retratoUrl,
            'pack_id' => $packId !== '' ? $packId : null,
        ];
    }

    /**
     * @return list<array{personaje_id: string, nombre: string, pista: ?string}>
     */
    public static function acompanantesDisponibles(
        array $partida,
        string $root,
        ?int $llegaMinutosAbs = null
    ): array {
        self::ensure($partida);
        $abs = $llegaMinutosAbs ?? CandidatoLlegadaEngine::minutosAbs($partida);
        $dia = 1 + intdiv($abs, 24 * 60);
        $hora = intdiv($abs % (24 * 60), 60);
        $out = [];
        foreach (TutorialIncorporaciones::residentesActivos($partida) as $rid) {
            $rid = (string) $rid;
            $disp = AgendaEngine::estaDisponibleIntervalo(
                $partida,
                $rid,
                $dia,
                $hora,
                self::FRANJA_HORAS
            );
            if (!($disp['disponible'] ?? false)) {
                continue;
            }
            $out[] = self::opcionAcompanante($partida, $rid, $root);
        }
        return $out;
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function validarAcompanante(
        array $partida,
        string $acompananteId,
        int $llegaMinutosAbs
    ): array {
        if ($acompananteId === '') {
            return ['ok' => false, 'error' => 'falta_acompanante'];
        }
        if (!isset($partida['residentes'][$acompananteId])) {
            return ['ok' => false, 'error' => 'acompanante_no_encontrado'];
        }
        if (($partida['residentes'][$acompananteId]['presencia'] ?? '') !== 'residente') {
            return ['ok' => false, 'error' => 'acompanante_no_activo'];
        }
        $dia = 1 + intdiv($llegaMinutosAbs, 24 * 60);
        $hora = intdiv($llegaMinutosAbs % (24 * 60), 60);
        $disp = AgendaEngine::estaDisponibleIntervalo(
            $partida,
            $acompananteId,
            $dia,
            $hora,
            self::FRANJA_HORAS
        );
        if (!($disp['disponible'] ?? false)) {
            return ['ok' => false, 'error' => 'acompanante_no_disponible', 'detalle' => $disp];
        }
        return ['ok' => true];
    }

    public static function adjuntarUiMensajeCandidato(
        array &$partida,
        string $mensajeId,
        string $catalogId,
        string $root
    ): void {
        $perfil = self::perfilCandidato($root, $catalogId);
        if (!($perfil['ok'] ?? false)) {
            return;
        }
        $opciones = self::acompanantesDisponibles($partida, $root);
        foreach ($partida['buzon'] ?? [] as &$m) {
            if (!is_array($m) || (string) ($m['id'] ?? '') !== $mensajeId) {
                continue;
            }
            $m['perfil_candidato'] = $perfil;
            $m['acompanantes_opciones'] = $opciones;
            $m['selector_titulo_acompanante'] = '¿Quién le enseña Villaborde?';
            break;
        }
        unset($m);
    }

    /**
     * Cotilleo destacado + franja bienvenida tras incorporación efectiva.
     *
     * @param array<string, mixed> $enCamino
     */
    public static function alLlegadaEfectiva(
        array &$partida,
        string $residenteId,
        string $root,
        ?array $enCamino = null,
        ?GameLogger $logger = null
    ): void {
        self::ensure($partida);
        $nombre = IdentidadPublica::nombre($partida, $residenteId);
        $acompananteId = is_array($enCamino) ? (string) ($enCamino['acompanante_id'] ?? '') : '';

        BuzonEngine::crear($partida, [
            'id' => 'msg_cotilleo_llegada_' . $residenteId . '_' . bin2hex(random_bytes(2)),
            'clasificacion' => BuzonEngine::COTILLEO,
            'canal' => BuzonEngine::CANAL_COTILLEO,
            'tipo' => 'llegada_pueblo',
            'texto' => CotilleoLlegadaCopy::texto($nombre, $residenteId),
            'cotilleo_meta' => CotilleoCategoria::meta(CotilleoCategoria::PUEBLO, true),
            'de_persona' => $residenteId,
            'actores' => [$residenteId],
            'origen' => ['tipo_evento' => DomainEvents::RESIDENTE_INCORPORADO, 'es_narrativo' => true],
        ]);

        if ($acompananteId !== '' && isset($partida['residentes'][$acompananteId])) {
            $nomA = IdentidadPublica::nombre($partida, $acompananteId);
            $seed = 'bienvenida_tour|' . $acompananteId . '|' . $residenteId;
            $textoTour = CopyCotilleoFamilias::linea('bienvenida_tour', [
                'guia' => $nomA,
                'nuevo' => $nombre,
            ], $seed);
            if ($textoTour === '') {
                $textoTour = $nomA . ' le enseña el pueblo a ' . $nombre . '. Franja de bienvenida en marcha.';
            }
            BuzonEngine::crear($partida, [
                'clasificacion' => BuzonEngine::COTILLEO,
                'canal' => BuzonEngine::CANAL_COTILLEO,
                'tipo' => 'llegada_bienvenida',
                'texto' => $textoTour,
                'cotilleo_meta' => CotilleoCategoria::meta(CotilleoCategoria::PUEBLO, true),
                'actores' => [$acompananteId, $residenteId],
                'de_persona' => $acompananteId,
                'origen' => ['tipo_evento' => 'llegada_bienvenida', 'es_narrativo' => true],
            ]);
            self::activarFranjaBienvenida($partida, $residenteId, $acompananteId, $logger);
        }

        $partida['llegadas']['presentacion']['ultima'] = [
            'residente_id' => $residenteId,
            'nombre' => $nombre,
            'acompanante_id' => $acompananteId !== '' ? $acompananteId : null,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
        ];
    }

    private static function activarFranjaBienvenida(
        array &$partida,
        string $nuevoId,
        string $acompananteId,
        ?GameLogger $logger
    ): void {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $lugar = self::LUGAR_BIENVENIDA;
        if (!ComplejoCatalog::estaAbierto($lugar, $hora)) {
            foreach (LugaresCanonicos::IDS as $lug) {
                if (ComplejoCatalog::estaAbierto($lug, $hora)) {
                    $lugar = $lug;
                    break;
                }
            }
        }
        $attr = LugarAtributos::de($lugar);
        $rest = ComplejoCatalog::horasRestantesAbiertas($lugar, $hora);
        $durH = min(self::FRANJA_HORAS, max(1, $rest));

        $rng = RngService::fromPartida($partida);
        $encId = 'enc_bienvenida_' . bin2hex(substr(pack('N', $rng->next()), 0, 4));
        $rng->persistToPartida($partida);

        $encuentro = [
            'id' => $encId,
            'tipo' => 'conocerse',
            'intencion' => 'bienvenida_llegada',
            'participantes' => [$nuevoId, $acompananteId],
            'lugar' => $lugar,
            'hora' => $hora,
            'dia' => $dia,
            'duracion_minutos' => min((int) ($attr['duracion_minutos'] ?? 60), $durH * 60),
            'duracion_horas' => $durH,
            'estado' => 'en_curso',
            'reserva_agenda' => ['tipo' => 'bienvenida', 'origen' => 'llegada'],
            'resultado' => null,
            '_placeholder_resultado' => true,
        ];
        $partida['encuentros'] ??= [];
        $partida['encuentros'][] = $encuentro;

        \aht_log_optional($logger, $partida, 'llegada_bienvenida_activa', [
            'encuentro_id' => $encId,
            'participantes' => [$nuevoId, $acompananteId],
            'duracion_horas' => $durH,
        ]);
    }

    /**
     * @return array{personaje_id: string, nombre: string, pista: ?string}
     */
    private static function opcionAcompanante(array $partida, string $rid, string $root): array
    {
        $catalog = new Catalog($root);
        $store = $catalog->store();
        $perfil = PerfilPartida::de($partida, $rid);
        $hobbies = is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : [];
        $pista = null;
        foreach ($hobbies as $h) {
            $h = (string) $h;
            if ($h === '') {
                continue;
            }
            if (DiscoveryReveal::jugadorSabeHobby($partida, $rid, $h)) {
                $pista = HobbyAccionable::pista($h, $store)
                    ?: ('Le gusta ' . EtiquetaFicha::hobby($h, $store) . '.');
                break;
            }
        }
        return [
            'personaje_id' => $rid,
            'nombre' => IdentidadPublica::nombre($partida, $rid),
            'pista' => $pista,
        ];
    }

    private static function hobbyPublicoPreview(CatalogStore $store, string $catalogId): string
    {
        $pool = HobbyAccionable::idsGenerables($store);
        if ($pool === []) {
            return '';
        }
        $idx = abs(crc32('hobby_preview|' . $catalogId)) % count($pool);
        return (string) $pool[$idx];
    }

    private static function retratoNeutroUrl(
        VisualPackStore $packs,
        string $packId,
        string $root,
        CatalogStore $store
    ): ?string {
        if ($packId === '') {
            return null;
        }
        $pack = $packs->pack($packId);
        if (!is_array($pack)) {
            return null;
        }
        $resolved = ExpressionResolver::resolver([
            'estado_emocional_id' => EstadoEmocional::NEUTRO,
            'intensidad' => null,
            'override_dev' => null,
            'pack' => $pack,
            'pack_id' => $packId,
        ], $packs, $store);
        $asset = is_array($resolved['asset'] ?? null) ? $resolved['asset'] : null;
        if (
            is_array($asset)
            && !empty($asset['existe'])
            && is_string($asset['url_relativa'] ?? null)
            && $asset['url_relativa'] !== ''
        ) {
            return $asset['url_relativa'];
        }
        return null;
    }
}
