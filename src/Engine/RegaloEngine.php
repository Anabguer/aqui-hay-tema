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

    /** @return array<string, mixed>|null */
    private static function aplicarEmocion(array &$partida, string $residenteId, string $reaccion, string $objectId, array $cal, ?GameLogger $logger = null): ?array
    {
        if ($reaccion === self::INDIFERENTE) {
            return null;
        }
        $estadoId = $reaccion === self::NO_LE_GUSTA ? EstadoEmocional::ENFADADO : EstadoEmocional::ALEGRE;
        $fallback = $reaccion === self::NO_LE_GUSTA
            ? (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas_default.enfadado', 4)
            : (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas_default.alegre', 6);
        $dur = (int) CalibracionConfig::get($cal, 'regalos.duracion_horas.' . $reaccion, $fallback);
        $dur = max(1, $dur);
        $reloj = $partida['reloj'] ?? [];
        $hasta = EstadoEmocional::hastaDesdeDuracion($reloj, $dur);
        $root = dirname(__DIR__, 2);
        $emoSvc = new EmotionalStateService(
            new VisualPackStore($root),
            new CatalogStore($root),
            $logger
        );
        $res = $emoSvc->aplicar(
            $partida,
            $residenteId,
            $estadoId,
            'regalo',
            null,
            $hasta,
            ['objeto_id' => $objectId],
            $dur
        );
        return !empty($res['ok']) ? ($res['estado_emocional'] ?? null) : null;
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
}
