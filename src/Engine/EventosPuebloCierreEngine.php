<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * B2 — Cierre post-evento del pueblo mediante Mensajito.
 *
 * Emite un mensaje cuando el encuentro evento_pueblo llega a estado final fiable.
 */
final class EventosPuebloCierreEngine
{
    public const TIPO_EVENTO = 'cierre_evento_pueblo';
    public const FAMILIA_VOZ = 'cierre_evento_pueblo';

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function onEncuentroTerminado(
        array &$partida,
        array $encuentro,
        array $resultado,
        array $cal,
        ?Catalog $catalog = null,
        ?GameLogger $logger = null
    ): array {
        if ((string) ($encuentro['intencion'] ?? '') !== EventosPuebloEngine::INTENCION) {
            return ['ok' => true, 'skipped' => 'no_evento_pueblo'];
        }
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return ['ok' => true, 'skipped' => 'buzon_disabled'];
        }
        $catalog = $catalog ?? new Catalog(dirname(__DIR__, 2));

        return self::emitirCierre($partida, $encuentro, $resultado, 'terminado', $catalog, $cal, $logger);
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function onEncuentroCancelado(
        array &$partida,
        array $encuentro,
        array $cal,
        ?Catalog $catalog = null,
        ?GameLogger $logger = null
    ): array {
        if ((string) ($encuentro['intencion'] ?? '') !== EventosPuebloEngine::INTENCION) {
            return ['ok' => true, 'skipped' => 'no_evento_pueblo'];
        }
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return ['ok' => true, 'skipped' => 'buzon_disabled'];
        }
        $catalog = $catalog ?? new Catalog(dirname(__DIR__, 2));

        return self::emitirCierre($partida, $encuentro, [], 'cancelado', $catalog, $cal, $logger);
    }

    /**
     * @param array<string, mixed> $resultado
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function emitirCierre(
        array &$partida,
        array $encuentro,
        array $resultado,
        string $estadoFinal,
        Catalog $catalog,
        array $cal,
        ?GameLogger $logger
    ): array {
        $encId = (string) ($encuentro['id'] ?? '');
        $evento = EventosPuebloEngine::buscarProgramadoPorEncuentro($partida, $encId);
        if ($evento === null) {
            return ['ok' => true, 'skipped' => 'sin_fila_evento'];
        }
        $evtId = (string) ($evento['id'] ?? '');
        if ($evtId === '' || self::yaCerrado($partida, $evtId)) {
            return ['ok' => true, 'skipped' => 'ya_cerrado'];
        }

        $participantes = is_array($encuentro['participantes'] ?? null) ? $encuentro['participantes'] : [];
        if ($participantes === []) {
            return ['ok' => true, 'skipped' => 'sin_participantes'];
        }

        $rng = RngService::fromPartida($partida);
        $remitente = (string) $participantes[$rng->nextInt(0, count($participantes) - 1)];
        $rng->persistToPartida($partida);

        $catalogoId = (string) ($evento['catalogo_id'] ?? ($encuentro['evento_pueblo_catalogo_id'] ?? ''));
        $nombreEvt = (string) ($evento['nombre'] ?? '');
        if ($nombreEvt === '' && $catalogoId !== '') {
            $def = EventosPuebloEngine::catalogItem($catalog, $catalogoId);
            $nombreEvt = (string) ($def['nombre'] ?? $catalogoId);
        }
        $nombreNatural = EventosPuebloAnuncioEngine::nombreNaturalPublico($nombreEvt);
        $tono = $estadoFinal === 'cancelado' ? 'cancelado' : self::clasificarExperiencia($resultado);
        $asistencia = self::textoAsistenciaReal($partida, $participantes);

        $seed = self::FAMILIA_VOZ . '|' . $evtId . '|' . $tono . '|' . $remitente;
        $texto = MensajitoVoz::linea($partida, self::FAMILIA_VOZ, [
            'nombre_evento' => $nombreNatural,
            'asistencia' => $asistencia,
            'tono' => $tono,
        ], $seed, $remitente);
        if ($texto === '') {
            return ['ok' => true, 'skipped' => 'sin_copy'];
        }

        $eventoCanonId = 'cierre_evt_pueblo_' . $evtId;
        if (CanalDeduplicador::yaPublicado($partida, $eventoCanonId, BuzonEngine::CANAL_BUZON)) {
            self::marcarCerrado($partida, $evtId, $estadoFinal, null);
            return ['ok' => true, 'skipped' => 'dedup_canal'];
        }

        $anuncioId = (string) ($evento['anuncio_mensajito_id'] ?? '');
        $hiloId = (string) ($evento['hilo_mensajito_id'] ?? '');
        if ($hiloId === '' && $anuncioId !== '') {
            $hiloId = $anuncioId;
        }
        if ($hiloId === '') {
            $hiloId = 'hilo_evt_pueblo_' . $evtId;
        }

        $datos = [
            'evento_pueblo_id' => $evtId,
            'evento_pueblo_catalogo_id' => $catalogoId,
            'encuentro_id' => $encId,
            'dia' => (int) ($evento['dia'] ?? ($encuentro['dia'] ?? 0)),
            'hora' => (int) ($evento['hora'] ?? ($encuentro['hora'] ?? 0)),
            'lugar' => (string) ($evento['lugar'] ?? ($encuentro['lugar'] ?? '')),
            'participantes' => array_values(array_map('strval', $participantes)),
            'participantes_n' => count($participantes),
            'estado_final' => $estadoFinal,
            'tono_experiencia' => $tono,
            'anuncio_mensajito_id' => $anuncioId !== '' ? $anuncioId : null,
        ];

        $r = CanalDeduplicador::crearSiAplica($partida, [
            'clasificacion' => BuzonEngine::OPORTUNIDAD,
            'tipo' => self::TIPO_EVENTO,
            'canal' => BuzonEngine::CANAL_BUZON,
            'de_persona' => $remitente,
            'actores' => $datos['participantes'],
            'texto' => $texto,
            'acciones' => [],
            'familia_mensajito' => self::FAMILIA_VOZ,
            'datos_familia' => $datos,
            'hilo_id' => $hiloId,
            'mensaje_origen_id' => $anuncioId !== '' ? $anuncioId : null,
            'seguimiento_pendiente' => false,
            'origen' => [
                'evento_id' => $eventoCanonId,
                'tipo_evento' => self::TIPO_EVENTO,
                'es_narrativo' => true,
                'informacion_revelada' => [
                    'evento_pueblo_id' => $evtId,
                    'catalogo_id' => $catalogoId,
                    'estado_final' => $estadoFinal,
                    'tono_experiencia' => $tono,
                ],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);

        if ($r !== null && ($r['ok'] ?? false)) {
            $msgId = (string) (($r['mensaje']['id'] ?? '') ?: '');
            self::marcarCerrado($partida, $evtId, $estadoFinal, $msgId !== '' ? $msgId : null);
            if ($logger !== null) {
                $logger->info('evento_pueblo_cierre_mensajito', [
                    'evento_pueblo_id' => $evtId,
                    'mensajito_id' => $msgId,
                    'tono' => $tono,
                ]);
            }
            return ['ok' => true, 'mensajito_id' => $msgId, 'tono' => $tono];
        }

        return ['ok' => true, 'skipped' => 'no_creado'];
    }

    public static function yaCerrado(array $partida, string $eventoPuebloId): bool
    {
        EventosPuebloEngine::ensure($partida);
        foreach ($partida['eventos_pueblo']['programados'] as $ev) {
            if (!is_array($ev) || (string) ($ev['id'] ?? '') !== $eventoPuebloId) {
                continue;
            }
            if (!empty($ev['cierre_emitido'])) {
                return true;
            }
        }
        $eventoCanonId = 'cierre_evt_pueblo_' . $eventoPuebloId;
        if (CanalDeduplicador::yaPublicado($partida, $eventoCanonId, BuzonEngine::CANAL_BUZON)) {
            return true;
        }
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m) || (string) ($m['tipo'] ?? '') !== self::TIPO_EVENTO) {
                continue;
            }
            $datos = is_array($m['datos_familia'] ?? null) ? $m['datos_familia'] : [];
            if ((string) ($datos['evento_pueblo_id'] ?? '') === $eventoPuebloId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $resultado
     */
    public static function clasificarExperiencia(array $resultado): string
    {
        $por = is_array($resultado['por_participante'] ?? null) ? $resultado['por_participante'] : [];
        if ($por === []) {
            return 'ocurrio';
        }
        $scores = [
            'muy_mal' => -2,
            'mal' => -1,
            'normal' => 0,
            'bien' => 1,
            'muy_bien' => 2,
        ];
        $sum = 0.0;
        $n = 0;
        foreach ($por as $row) {
            if (!is_array($row)) {
                continue;
            }
            $r = (string) ($row['resultado'] ?? 'normal');
            $sum += $scores[$r] ?? 0;
            $n++;
        }
        if ($n === 0) {
            return 'ocurrio';
        }
        $avg = $sum / $n;
        if ($avg >= 0.8) {
            return 'celebrado_fuerte';
        }
        if ($avg >= 0.2) {
            return 'celebrado_normal';
        }
        if ($avg >= -0.6) {
            return 'celebrado_tenue';
        }

        return 'celebrado_tenue';
    }

    /**
     * @param list<string> $participantes
     */
    private static function textoAsistenciaReal(array $partida, array $participantes): string
    {
        $n = count($participantes);
        $total = 0;
        foreach ($partida['residentes'] ?? [] as $res) {
            if (is_array($res) && (string) ($res['presencia'] ?? '') === 'residente') {
                $total++;
            }
        }
        if ($total > 0 && $n >= max(3, (int) ceil($total * 0.45))) {
            return 'se apuntó un montón de gente';
        }
        if ($n >= 4) {
            return 'fuimos un buen grupo';
        }

        return 'éramos poquitos';
    }

    private static function marcarCerrado(
        array &$partida,
        string $eventoPuebloId,
        string $estadoFinal,
        ?string $mensajitoId
    ): void {
        EventosPuebloEngine::ensure($partida);
        foreach ($partida['eventos_pueblo']['programados'] as &$ev) {
            if (!is_array($ev) || (string) ($ev['id'] ?? '') !== $eventoPuebloId) {
                continue;
            }
            $ev['cierre_emitido'] = true;
            $ev['estado_final'] = $estadoFinal;
            if ($mensajitoId !== null && $mensajitoId !== '') {
                $ev['cierre_mensajito_id'] = $mensajitoId;
            }
            break;
        }
        unset($ev);
    }
}
