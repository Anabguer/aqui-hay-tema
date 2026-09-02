<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Regalos de Celestine a un vecino (Fase 1).
 * Resolucion SOLO con datos reales del residente (perfil_partida):
 *   1. hobby_ids ∩ preferencias.hobbies_neg -> NO_LE_GUSTA (el negativo siempre gana)
 *   2. hobby_ids ∩ preferencias.hobbies_pos -> LE_ENCANTA
 *   3. hobby_ids ∩ hobbies propios          -> LE_GUSTA
 *   4. sin match                            -> INDIFERENTE
 * Sin edad, profesion, rasgos, romance ni RNG. Determinista.
 * La UI previa NO puede usar este motor para orientar: eso sera Fase 2 (Discovery).
 *
 * Regalos v2 (2026-09-02): contrato emocional con EmotionalRecovery::evaluarRegalo.
 * El regalo puede MEJORAR un estado triste/enfadado sin borrar la causa histórica
 * (preservada en contexto.estado_antes_origen / estado_antes_contexto).
 * NUNCA empeora un estado triste/enfadado. Duración reducida al 50% cuando hay
 * causa fuerte preservada (alivio temporal, no borrado).
 * Copy: añadir escena humana + eco emocional al feedback.
 */
final class RegaloEngine
{
    public const LE_ENCANTA = 'le_encanta';
    public const LE_GUSTA = 'le_gusta';
    public const INDIFERENTE = 'indiferente';
    public const NO_LE_GUSTA = 'no_le_gusta';
    public const REACCIONES = [self::LE_ENCANTA, self::LE_GUSTA, self::INDIFERENTE, self::NO_LE_GUSTA];

    /**
     * @param array<string, mixed> $regalo fila del catalogo regalos
     */
    public static function resolverReaccion(array $partida, string $residenteId, array $regalo, array $cal = []): string
    {
        $ids = [];
        foreach (($regalo['hobby_ids'] ?? []) as $h) {
            if (is_string($h) && $h !== '') {
                $ids[] = $h;
            }
        }
        if ($ids === []) {
            return self::INDIFERENTE;
        }
        $perfil = PerfilPartida::deOLegacy($partida, $residenteId);
        $prefs = is_array($perfil['preferencias'] ?? null) ? $perfil['preferencias'] : [];
        $neg = is_array($prefs['hobbies_neg'] ?? null) ? $prefs['hobbies_neg'] : [];
        $pos = is_array($prefs['hobbies_pos'] ?? null) ? $prefs['hobbies_pos'] : [];
        $propios = is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : [];

        if (array_intersect($ids, $neg) !== []) {
            return self::NO_LE_GUSTA;
        }
        if (array_intersect($ids, $pos) !== []) {
            return self::LE_ENCANTA;
        }
        if (array_intersect($ids, $propios) !== []) {
            return self::LE_GUSTA;
        }
        return self::INDIFERENTE;
    }

    /** Veces que ese vecino ya recibio ese objeto (memoria via bitacora). */
    public static function vecesObjeto(array $partida, string $residenteId, string $objectId): int
    {
        $n = 0;
        foreach ($partida['bitacora_relaciones'] ?? [] as $h) {
            if (!is_array($h) || ($h['tipo'] ?? '') !== RelacionBitacora::REGALO) {
                continue;
            }
            if (($h['meta']['objeto_id'] ?? '') !== $objectId) {
                continue;
            }
            if (!in_array($residenteId, $h['participantes'] ?? [], true)) {
                continue;
            }
            $n++;
        }
        return $n;
    }

    /**
     * Entrega completa: valida, consume 1 unidad, resuelve reaccion,
     * aplica emocion y aprecio, registra memoria (MemoriaEventos + bitacora).
     * Todo sobre el mismo &$partida: el handler guarda una sola vez (atomico).
     *
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function entregar(
        array &$partida,
        string $residenteId,
        string $objectId,
        array $cal = [],
        ?CatalogStore $catalog = null,
        ?GameLogger $logger = null
    ): array {
        $catalog = $catalog ?? new CatalogStore(dirname(__DIR__, 2));
        $regalo = $catalog->item('regalos', $objectId);
        if (!is_array($regalo)) {
            return ['ok' => false, 'error' => 'regalo_objeto_desconocido'];
        }
        if (!isset($partida['residentes'][$residenteId])) {
            return ['ok' => false, 'error' => 'residente_no_encontrado'];
        }
        if (MemoriaEventos::enCooldown($partida, 'regalo', [$residenteId], $cal)) {
            return ['ok' => false, 'error' => 'regalo_cooldown'];
        }

        $consumo = InventarioEngine::consumir($partida, $objectId, 1);
        if (!$consumo['ok']) {
            return ['ok' => false, 'error' => $consumo['error'], 'cantidad' => $consumo['cantidad'] ?? 0];
        }

        $reaccion = self::resolverReaccion($partida, $residenteId, $regalo, $cal);
        $veces = self::vecesObjeto($partida, $residenteId, $objectId);
        $repetido = $veces > 0;
        if ($repetido && (bool) CalibracionConfig::get($cal, 'regalos.degradar_repetido', true)) {
            $reaccion = self::degradar($reaccion);
        }

        $emocion = self::aplicarEmocion($partida, $residenteId, $reaccion, $objectId, $cal, $logger);
        $delta = (int) CalibracionConfig::get($cal, 'regalos.deltas_aprecio.' . $reaccion, 0);
        $aprecio = self::ajustarAprecio($partida, $residenteId, $delta);

        MemoriaEventos::registrar($partida, 'regalo', [$residenteId], null, $objectId, $reaccion);
        $hito = RelacionBitacora::registrar(
            $partida,
            RelacionBitacora::REGALO,
            [$residenteId],
            'celeste>' . $residenteId,
            $reaccion,
            null,
            ['objeto_id' => $objectId, 'reaccion' => $reaccion, 'repetido' => $repetido]
        );

        // Fase 2: una reaccion fuerte puede descubrir un gusto/rechazo REAL del vecino.
        $descubrimientos = self::descubrirPorRegalo($partida, $residenteId, $regalo, $reaccion, $cal, $catalog);

        // Fase 2: agradecimiento por Mensajitos solo en el caso mas positivo y sin abuso.
        $graciasId = self::mensajitoGracias($partida, $residenteId, $objectId, $reaccion, $repetido, $cal);

        return [
            'ok' => true,
            'objeto_id' => $objectId,
            'objeto_nombre' => (string) ($regalo['nombre'] ?? $objectId),
            'residente_id' => $residenteId,
            'reaccion' => $reaccion,
            'repetido' => $repetido,
            'texto' => self::texto($partida, $residenteId, (string) ($regalo['nombre'] ?? $objectId), $reaccion),
            'escena' => self::textoEscena($partida, $residenteId, (string) ($regalo['nombre'] ?? $objectId), $reaccion),
            'eco_emocional' => self::textoEcoEmocional($partida, $residenteId, (string) ($regalo['nombre'] ?? $objectId), $reaccion, $emocion),
            'emocion' => $emocion,
            'delta_aprecio' => $delta,
            'aprecio_celeste' => $aprecio,
            'restante' => (int) ($consumo['restante'] ?? 0),
            'hito_id' => (string) ($hito['id'] ?? ''),
            'descubrimientos' => $descubrimientos,
            'gracias_mensaje_id' => $graciasId,
        ];
    }

    /**
     * Fase 2 - Discovery por regalos. SOLO informacion real del perfil:
     *   le_encanta -> gusto_hobby:{id} del primer hobbies_pos aun no descubierto que comparta el regalo
     *   no_le_gusta-> rechazo_hobby:{id} del primer hobbies_neg aun no descubierto que comparta el regalo
     * Maximo 1 candidato por regalo, origen 'regalo', mismo motor que encuentros
     * (DiscoveryReveal::aplicarEvento). Sin scores, sin compatibilidad, sin datos privados.
     *
     * @param array<string, mixed> $regalo
     * @return list<array{campo: string, texto: string}>
     */
    private static function descubrirPorRegalo(
        array &$partida,
        string $residenteId,
        array $regalo,
        string $reaccion,
        array $cal,
        CatalogStore $catalog
    ): array {
        if (!FeatureConfig::isEnabled($partida, 'discovery_enabled')) {
            return [];
        }
        if ($reaccion !== self::LE_ENCANTA && $reaccion !== self::NO_LE_GUSTA) {
            return [];
        }
        $ids = [];
        foreach (($regalo['hobby_ids'] ?? []) as $h) {
            if (is_string($h) && $h !== '') {
                $ids[] = $h;
            }
        }
        if ($ids === []) {
            return [];
        }
        $esPos = $reaccion === self::LE_ENCANTA;
        $perfil = PerfilPartida::deOLegacy($partida, $residenteId);
        $prefs = is_array($perfil['preferencias'] ?? null) ? $perfil['preferencias'] : [];
        $lista = is_array($prefs[$esPos ? 'hobbies_pos' : 'hobbies_neg'] ?? null)
            ? $prefs[$esPos ? 'hobbies_pos' : 'hobbies_neg']
            : [];
        foreach ($lista as $id) {
            if (!is_string($id) || $id === '' || !in_array($id, $ids, true)) {
                continue;
            }
            $campo = $esPos ? ConocimientoNpc::campoGusto('hobby', $id) : ConocimientoNpc::campoRechazo('hobby', $id);
            if (DiscoveryEngine::estado($partida, $residenteId, $campo) === DiscoveryEngine::DESCUBIERTO) {
                continue;
            }
            DiscoveryReveal::aplicarEvento(
                $partida,
                [['residente_id' => $residenteId, 'campo' => $campo, 'valor' => $id, 'observadores' => ['jugador']]],
                $cal,
                'regalo'
            );
            $nombre = IdentidadPublica::nombre($partida, $residenteId);
            $txt = CopyDescubrimiento::texto($nombre, $campo, $id, $catalog);
            return is_string($txt) && $txt !== '' ? [['campo' => $campo, 'texto' => $txt]] : [];
        }
        return [];
    }

    /**
     * Fase 2 - Mensajito de gracias. Solo si la reaccion final es le_encanta y
     * el objeto NO es repetido para ese vecino. Cooldown propio familia
     * 'regalo_gracias' (calibracion) para que nunca sea spam. Clasificacion
     * oportunidad: nunca compite con lo importante del buzon.
     */
    private static function mensajitoGracias(
        array &$partida,
        string $residenteId,
        string $objectId,
        string $reaccion,
        bool $repetido,
        array $cal
    ): ?string {
        if ($reaccion !== self::LE_ENCANTA || $repetido) {
            return null;
        }
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return null;
        }
        if (!empty($partida['_lab_peticiones_b4']) || !empty($partida['_lab_misiones_b3'])) {
            return null;
        }
        if (MemoriaEventos::enCooldown($partida, 'regalo_gracias', [$residenteId], $cal)) {
            return null;
        }
        $nombre = IdentidadPublica::nombre($partida, $residenteId);
        $seedTexto = crc32($residenteId . '|' . $objectId) % 2;
        $texto = $seedTexto === 0
            ? 'Oye, lo del regalo… me ha hecho mucha ilusión. Gracias de verdad.'
            : 'No se me pasa: justo lo que apetecía. GRACIAS.';
        $r = BuzonEngine::crear($partida, [
            'clasificacion' => BuzonEngine::OPORTUNIDAD,
            'tipo' => 'gracias_regalo',
            'canal' => BuzonEngine::CANAL_BUZON,
            'de_persona' => $residenteId,
            'actores' => [$residenteId],
            'texto' => $nombre === '' ? $texto : $nombre . ': "' . $texto . '"',
            'origen' => [
                'evento_id' => 'gracias_regalo:' . $residenteId,
                'tipo_evento' => 'gracias_regalo',
                'es_narrativo' => false,
                'informacion_revelada' => [],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);
        if (empty($r['ok'])) {
            return null;
        }
        MemoriaEventos::registrar($partida, 'regalo_gracias', [$residenteId], null, 'gracias_regalo');
        $mid = '';
        if (is_array($r['mensaje'] ?? null)) {
            $mid = (string) ($r['mensaje']['id'] ?? '');
        }
        return $mid !== '' ? $mid : null;
    }

    /** Degradacion determinista por objeto repetido: encanta>gusta>indiferente. */
    public static function degradar(string $reaccion): string
    {
        if ($reaccion === self::LE_ENCANTA) {
            return self::LE_GUSTA;
        }
        if ($reaccion === self::LE_GUSTA) {
            return self::INDIFERENTE;
        }
        return $reaccion;
    }

    /**
     * Regalos v2: aplica emoción reutilizando EmotionalRecovery::evaluarRegalo.
     *
     * - indiferente: no aplica nada.
     * - no_le_gusta:
     *      · sobre triste/enfadado → NO aplica emoción nueva (devuelve payload 'mantiene').
     *      · sobre neutro/alegre → legacy: aplica enfadado 4h.
     * - le_gusta / le_encanta sobre triste/enfadado: aplica la transición permitida
     *   por evaluarRegalo (nunca empeora). Preserva la causa histórica en
     *   contexto.estado_antes_origen / estado_antes_contexto. Si la causa previa es
     *   "fuerte" (lista cerrada), reduce la duración al 50% (alivio temporal).
     * - le_gusta / le_encanta sobre neutro/alegre: comportamiento legacy (alegre 6h/3h).
     *
     * @return array<string, mixed>|null
     */
    private static function aplicarEmocion(array &$partida, string $residenteId, string $reaccion, string $objectId, array $cal, ?GameLogger $logger = null): ?array
    {
        if ($reaccion === self::INDIFERENTE) {
            return null;
        }

        $root = dirname(__DIR__, 2);
        $residente = &$partida['residentes'][$residenteId];
        EstadoEmocional::ensureResidente($residente, $partida['reloj'] ?? null);
        $estadoAntes = $residente['runtime']['estado_emocional'];
        $estadoAntesId = EstadoEmocional::canonId((string) ($estadoAntes['id'] ?? EstadoEmocional::NEUTRO));

        // Determinar si el receptor tiene un hobby/gusto conocido que coincida con el regalo.
        $hobbyMatch = self::regaloActivaHobbyConocido($partida, $residenteId, $objectId);

        $estadoAntesNegativo = in_array($estadoAntesId, [EstadoEmocional::TRISTE, EstadoEmocional::ENFADADO], true);

        // -- Caso 1: no_le_gusta sobre estado negativo → MANTENER (no empeorar) --
        if ($reaccion === self::NO_LE_GUSTA && $estadoAntesNegativo) {
            return [
                'id' => $estadoAntesId,
                'origen' => $estadoAntes['origen'] ?? '',
                'motivo' => 'mantiene',
                'contexto' => [
                    'estado_antes' => $estadoAntesId,
                    'estado_antes_origen' => (string) ($estadoAntes['origen'] ?? ''),
                    'estado_antes_contexto' => is_array($estadoAntes['contexto'] ?? null) ? $estadoAntes['contexto'] : [],
                    'causa_fuerte' => self::esCausaFuerte($estadoAntes),
                ],
                'mantiene' => true,
            ];
        }

        // -- Caso 2: no_le_gusta sobre estado NEUTRO/ALEGRE → legacy enfadado 4h --
        if ($reaccion === self::NO_LE_GUSTA) {
            $estadoId = EstadoEmocional::ENFADADO;
            $fallback = (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas_default.enfadado', 4);
            $dur = (int) CalibracionConfig::get($cal, 'regalos.duracion_horas.' . $reaccion, $fallback);
            $dur = max(1, $dur);
            return self::aplicarEstadoConCausa(
                $partida,
                $residenteId,
                $estadoId,
                'regalo',
                $dur,
                $objectId,
                $estadoAntes,
                false,
                $cal,
                $logger
            );
        }

        // -- Caso 3: le_gusta / le_encanta --
        $eval = EmotionalRecovery::evaluarRegalo($estadoAntesId, $reaccion, $hobbyMatch);

        // eval devuelve transición → aplicar con causa preservada + duración reducida si aplica.
        if (is_array($eval)) {
            $estadoDestino = $eval['estado'];
            $motivo = $eval['motivo'];
            $fallback = (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas_default.' . $estadoDestino, 6);
            $dur = (int) CalibracionConfig::get($cal, 'regalos.duracion_horas.' . $reaccion, $fallback);
            $dur = max(1, $dur);

            $causaFuerte = self::esCausaFuerte($estadoAntes);
            if ($causaFuerte) {
                $dur = max(1, (int) floor($dur * 0.5));
            }

            $aplicado = self::aplicarEstadoConCausa(
                $partida,
                $residenteId,
                $estadoDestino,
                'regalo',
                $dur,
                $objectId,
                $estadoAntes,
                $causaFuerte,
                $cal,
                $logger
            );

            if (is_array($aplicado)) {
                $aplicado['motivo'] = $motivo;
                $aplicado['hobby_match'] = $hobbyMatch;
            }
            return $aplicado;
        }

        // eval null + estado NEUTRO/ALEGRE → legacy alegre (6h/3h).
        if (!$estadoAntesNegativo) {
            $estadoId = EstadoEmocional::ALEGRE;
            $fallback = (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas_default.alegre', 6);
            $dur = (int) CalibracionConfig::get($cal, 'regalos.duracion_horas.' . $reaccion, $fallback);
            $dur = max(1, $dur);
            return self::aplicarEstadoConCausa(
                $partida,
                $residenteId,
                $estadoId,
                'regalo',
                $dur,
                $objectId,
                $estadoAntes,
                false,
                $cal,
                $logger
            );
        }

        // eval null + estado TRISTE/ENFADADO + le_gusta sin afin / le_encanta defensivo →
        // mantener (no empeorar, no mejorar).
        return [
            'id' => $estadoAntesId,
            'origen' => $estadoAntes['origen'] ?? '',
            'motivo' => 'mantiene',
            'contexto' => [
                'estado_antes' => $estadoAntesId,
                'estado_antes_origen' => (string) ($estadoAntes['origen'] ?? ''),
                'estado_antes_contexto' => is_array($estadoAntes['contexto'] ?? null) ? $estadoAntes['contexto'] : [],
                'causa_fuerte' => self::esCausaFuerte($estadoAntes),
            ],
            'mantiene' => true,
        ];
    }

    /**
     * Wrapper sobre EmotionalStateService::aplicar que preserva la causa histórica
     * en contexto.estado_antes_origen / contexto.estado_antes_contexto.
     */
    private static function aplicarEstadoConCausa(
        array &$partida,
        string $residenteId,
        string $estadoId,
        string $origen,
        int $durHoras,
        string $objectId,
        array $estadoAntes,
        bool $causaFuerte,
        array $cal,
        ?GameLogger $logger
    ): ?array {
        $root = dirname(__DIR__, 2);
        $reloj = $partida['reloj'] ?? [];
        $hasta = EstadoEmocional::hastaDesdeDuracion($reloj, $durHoras);
        $emoSvc = new EmotionalStateService(
            new VisualPackStore($root),
            new CatalogStore($root),
            $logger
        );
        $contexto = [
            'objeto_id' => $objectId,
            'estado_antes' => EstadoEmocional::canonId((string) ($estadoAntes['id'] ?? EstadoEmocional::NEUTRO)),
            'estado_antes_origen' => (string) ($estadoAntes['origen'] ?? ''),
            'estado_antes_contexto' => is_array($estadoAntes['contexto'] ?? null) ? $estadoAntes['contexto'] : [],
            'causa_fuerte' => $causaFuerte,
        ];
        $res = $emoSvc->aplicar(
            $partida,
            $residenteId,
            $estadoId,
            $origen,
            null,
            $hasta,
            $contexto,
            $durHoras
        );
        if (empty($res['ok'])) {
            return null;
        }
        $estadoAplicado = $res['estado_emocional'] ?? null;
        if (is_array($estadoAplicado) && is_array($partida['residentes'][$residenteId]['runtime']['estado_emocional'] ?? null)) {
            // Reemplazar el campo contexto escrito por EmotionalStateService para
            // preservar la causa histórica. El nuevo contexto ya fue pasado en
            // $contexto arriba, así que esta rama es defensiva.
        }
        return is_array($estadoAplicado) ? $estadoAplicado : null;
    }

    /**
     * Determina si el regalo activa un hobby/gusto ya conocido por el jugador.
     * Reutiliza exactamente el orden de precedencia de RegaloHints::paraObjeto
     * pero solo para el campo hobby_match, sin copy.
     */
    private static function regaloActivaHobbyConocido(array $partida, string $residenteId, string $objectId): bool
    {
        $regalo = (new CatalogStore(dirname(__DIR__, 2)))->item('regalos', $objectId);
        if (!is_array($regalo)) {
            return false;
        }
        $hobbyIds = is_array($regalo['hobby_ids'] ?? null) ? $regalo['hobby_ids'] : [];
        if ($hobbyIds === []) {
            return false;
        }
        foreach ($hobbyIds as $hid) {
            if (!is_string($hid) || $hid === '') {
                continue;
            }
            $campoGusto = ConocimientoNpc::campoGusto('hobby', $hid);
            $campoRechazo = ConocimientoNpc::campoRechazo('hobby', $hid);
            if (DiscoveryEngine::estado($partida, $residenteId, $campoGusto) === DiscoveryEngine::DESCUBIERTO) {
                return true;
            }
            if (DiscoveryEngine::estado($partida, $residenteId, $campoRechazo) === DiscoveryEngine::DESCUBIERTO) {
                return true;
            }
            if (DiscoveryReveal::jugadorSabeHobby($partida, $residenteId, $hid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Lista cerrada de orígenes cuyo contexto NO debe borrarse al mejorar el ánimo.
     * Si la emoción actual del receptor viene de uno de estos orígenes, conservar
     * la causa histórica (en contexto) y aplicar alivio temporal (duración 50%).
     */
    private static function esCausaFuerte(array $estadoAntes): bool
    {
        $origen = (string) ($estadoAntes['origen'] ?? '');
        $ctx = is_array($estadoAntes['contexto'] ?? null) ? $estadoAntes['contexto'] : [];

        // Encuentro con resultado negativo.
        if (in_array($origen, ['encuentro', 'encuentro_intervencion'], true)) {
            $res = (string) ($ctx['resultado_experiencia'] ?? '');
            if (in_array($res, ['muy_mal', 'mal'], true)) {
                return true;
            }
        }
        // Lista directa de orígenes fuertes.
        $fuertes = [
            'perder_trabajo',
            'rechazo_repetido',
            'discusion_fuerte',
            'crisis_pareja',
            'ruptura',
            'marcha_testigo',
            'encontrar_trabajo', // causa positiva pero estado actual es negativo: improbable; defensivo.
        ];
        if (in_array($origen, $fuertes, true)) {
            return true;
        }
        return false;
    }

    private static function ajustarAprecio(array &$partida, string $residenteId, int $delta): int
    {
        $rt = &$partida['residentes'][$residenteId]['runtime'];
        $rt['aprecio_celeste'] ??= 0;
        $v = (int) $rt['aprecio_celeste'] + $delta;
        if ($v > 100) {
            $v = 100;
        }
        if ($v < -100) {
            $v = -100;
        }
        $rt['aprecio_celeste'] = $v;
        return $v;
    }

    /** Copy Fase 1: 4 familias, 2 variantes deterministicas (sin RNG). */
    private static function texto(array $partida, string $residenteId, string $objetoNombre, string $reaccion): string
    {
        $nombre = IdentidadPublica::nombre($partida, $residenteId);
        $seed = crc32($residenteId . '|' . $objetoNombre) % 2;
        switch ($reaccion) {
            case self::LE_ENCANTA:
                return $seed === 0
                    ? $nombre . ' mira ' . $objetoNombre . ' y se le ilumina la cara. Le ha encantado.'
                    : 'A ' . $nombre . ' no le cabe la alegría al ver ' . $objetoNombre . '. Le ha encantado.';
            case self::LE_GUSTA:
                return $seed === 0
                    ? 'A ' . $nombre . ' le hace ilusión ' . $objetoNombre . '.'
                    : $nombre . ' agradece ' . $objetoNombre . ' con una sonrisa genuina.';
            case self::NO_LE_GUSTA:
                return $seed === 0
                    ? $nombre . ' mira ' . $objetoNombre . ' y no sabe muy bien qué decir.'
                    : $nombre . ' acepta ' . $objetoNombre . ' con una mueca difícil de interpretar.';
            default:
                return $seed === 0
                    ? $nombre . ' agradece el detalle, aunque no parece entusiasmarle especialmente.'
                    : $nombre . ' guarda el regalo con educación, sin demasiado asombro.';
        }
    }

    /**
     * Regalos v2 — Escena humana previa al veredicto (1 línea).
     * Determinista por (residente, objeto). Sin IDs técnicos ni métricas.
     * El objeto se menciona respetando su capitalización del catálogo.
     */
    public static function textoEscena(array $partida, string $residenteId, string $objetoNombre, string $reaccion): string
    {
        $nombre = IdentidadPublica::nombre($partida, $residenteId);
        $seed = crc32('escena|' . $residenteId . '|' . $objetoNombre . '|' . $reaccion) % 3;
        switch ($reaccion) {
            case self::LE_ENCANTA:
                if ($seed === 0) {
                    return $nombre . ' ha abierto el paquete delante de ti.';
                }
                if ($seed === 1) {
                    return $nombre . ' se ha quedado en silencio un segundo, mirando ' . $objetoNombre . '.';
                }
                return $nombre . ' lo ha cogido con las dos manos.';
            case self::LE_GUSTA:
                if ($seed === 0) {
                    return $nombre . ' lo ha mirado por encima, asintiendo.';
                }
                if ($seed === 1) {
                    return $nombre . ' lo ha guardado enseguida, sin abrirlo del todo.';
                }
                return $nombre . ' ha sonreído sin decir nada.';
            case self::NO_LE_GUSTA:
                if ($seed === 0) {
                    return $nombre . ' ha mirado ' . $objetoNombre . ' sin entender bien qué hacer con ello.';
                }
                if ($seed === 1) {
                    return $nombre . ' lo ha aceptado con cara de circunstancias.';
                }
                return $nombre . ' ha fruncido un poco el ceño al verlo.';
            default:
                if ($seed === 0) {
                    return $nombre . ' lo ha cogido por educación.';
                }
                if ($seed === 1) {
                    return $nombre . ' lo ha dejado encima de la mesa sin comentario.';
                }
                return $nombre . ' ha asentido, sin más.';
        }
    }

    /**
     * Regalos v2 — Eco emocional: refleja el efecto observado en el ánimo
     * cuando ha habido cambio, o el mantenimiento cuando no lo ha habido.
     * Usa el campo `mantiene` / `motivo` del payload de aplicarEmocion.
     *
     * Convenciones de copy:
     * - Sin fórmulas. Sin "+X".
     * - Cuando cambia a alegre: "Se le nota en la cara — está mejor."
     * - Si la causa histórica sigue activa (mantiene_causa): matiz "lo agradece, aunque sigue pensando en lo de X".
     * - Si no_le_gusta sobre triste/enfadado: "El gesto no levanta el ánimo, pero te lo ha aceptado."
     * - Si ya estaba bien y el regalo le gusta: "le ha arrancado otra sonrisa."
     *
     * @param array<string, mixed>|null $emocionPayload resultado de aplicarEmocion()
     */
    public static function textoEcoEmocional(
        array $partida,
        string $residenteId,
        string $objetoNombre,
        string $reaccion,
        ?array $emocionPayload
    ): string {
        $nombre = IdentidadPublica::nombre($partida, $residenteId);
        if (!is_array($emocionPayload)) {
            return '';
        }

        $motivo = (string) ($emocionPayload['motivo'] ?? '');
        $nuevoId = EstadoEmocional::canonId((string) ($emocionPayload['id'] ?? ''));
        $mantiene = (bool) ($emocionPayload['mantiene'] ?? false);
        $causaFuerte = (bool) ($emocionPayload['contexto']['causa_fuerte'] ?? false);
        $estadoAntes = (string) ($emocionPayload['contexto']['estado_antes'] ?? '');
        $estadoAntesOrigen = (string) ($emocionPayload['contexto']['estado_antes_origen'] ?? '');

        // Regalo le_encanta o le_gusta con cambio a alegre/neutro (motivo regalo_animó / regalo_alivia).
        if (in_array($motivo, ['regalo_animó', 'regalo_animó_sin_match', 'regalo_alivia', 'regalo_alivia_sin_match'], true)) {
            if ($nuevoId === EstadoEmocional::ALEGRE) {
                if ($causaFuerte && $estadoAntes === EstadoEmocional::TRISTE) {
                    return $nombre . ' lo agradece, pero sigue pensándoselo.';
                }
                if ($causaFuerte && $estadoAntes === EstadoEmocional::ENFADADO && $estadoAntesOrigen !== '') {
                    return $nombre . ' lo agradece; el enfado con lo de antes sigue ahí, pero menos.';
                }
                return 'A ' . $nombre . ' se le nota en la cara — está mejor.';
            }
            if ($nuevoId === EstadoEmocional::NEUTRO) {
                if ($causaFuerte) {
                    return $nombre . ' lo agradece; lo otro no se le ha pasado, pero algo es algo.';
                }
                return $nombre . ' se ha quedado más tranquil' . GeneroConcordancia::oa($partida, $residenteId) . '.';
            }
        }

        // Regalo le_gusta o le_encanta sin cambio emocional (estado antes ya era NEUTRO/ALEGRE).
        // Caso "ya estaba bien" → copy "le ha arrancado otra sonrisa".
        if (in_array($estadoAntes, [EstadoEmocional::NEUTRO, EstadoEmocional::ALEGRE], true)
            && in_array($reaccion, [self::LE_ENCANTA, self::LE_GUSTA], true)
            && !in_array($motivo, ['regalo_animó', 'regalo_animó_sin_match', 'regalo_alivia', 'regalo_alivia_sin_match'], true)
        ) {
            return 'Está content' . GeneroConcordancia::oa($partida, $residenteId) . ', y el detalle le ha arrancado otra sonrisa.';
        }

        // Mantiene por no_le_gusta sobre cualquier estado (nunca se empeora).
        if ($mantiene && in_array($reaccion, [self::NO_LE_GUSTA], true)) {
            if ($estadoAntes === EstadoEmocional::TRISTE) {
                return 'El gesto no levanta el ánimo de ' . $nombre . ', pero te lo ha aceptado.';
            }
            if ($estadoAntes === EstadoEmocional::ENFADADO) {
                return $nombre . ' lo coge sin comentar; sigue mosca.';
            }
            if ($estadoAntes === EstadoEmocional::NEUTRO || $estadoAntes === EstadoEmocional::ALEGRE) {
                return $nombre . ' lo coge sin comentar; el detalle no le ha hecho ni fu ni fa.';
            }
        }

        // Indiferente: nada.
        return '';
    }
}
