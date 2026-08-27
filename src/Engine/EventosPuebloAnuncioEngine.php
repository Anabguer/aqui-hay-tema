<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * B2 — Anuncio del evento del pueblo mediante Mensajito.
 *
 * Emite un mensaje informativo en el buzón cuando B1 programa un evento real.
 * No crea ni reprograma encuentros; solo refleja el estado de eventos_pueblo.
 */
final class EventosPuebloAnuncioEngine
{
    public const TIPO_EVENTO = 'anuncio_evento_pueblo';
    public const FAMILIA_VOZ = 'anuncio_evento_pueblo';

    /**
     * @param array<string, mixed> $evento Fila de eventos_pueblo.programados
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null Resultado de BuzonEngine o null si no aplica
     */
    public static function anunciarTrasProgramar(
        array &$partida,
        array $evento,
        Catalog $catalog,
        array $cal,
        RngService $rng,
        ?GameLogger $logger = null
    ): ?array {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return null;
        }
        $evtId = (string) ($evento['id'] ?? '');
        if ($evtId === '' || self::yaAnunciado($partida, $evtId)) {
            return null;
        }

        $participantes = is_array($evento['participantes'] ?? null) ? $evento['participantes'] : [];
        if ($participantes === []) {
            return null;
        }

        $remitente = (string) $participantes[$rng->nextInt(0, count($participantes) - 1)];
        $diaEvt = (int) ($evento['dia'] ?? 0);
        $horaEvt = (int) ($evento['hora'] ?? 0);
        $catalogoId = (string) ($evento['catalogo_id'] ?? '');
        $nombreEvt = (string) ($evento['nombre'] ?? '');
        if ($nombreEvt === '' && $catalogoId !== '') {
            $def = EventosPuebloEngine::catalogItem($catalog, $catalogoId);
            $nombreEvt = (string) ($def['nombre'] ?? $catalogoId);
        }
        $nombreNatural = self::nombreNatural($nombreEvt);
        $diaSemana = strtolower(Reloj::diaSemanaUi($diaEvt, $partida['reloj'] ?? []));
        $asistencia = self::textoAsistencia($partida, $participantes);
        $hiloId = 'hilo_evt_pueblo_' . $evtId;

        $seed = self::FAMILIA_VOZ . '|' . $evtId . '|' . $remitente;
        $texto = MensajitoVoz::linea($partida, self::FAMILIA_VOZ, [
            'dia_semana' => $diaSemana,
            'nombre_evento' => $nombreNatural,
            'asistencia' => $asistencia,
        ], $seed, $remitente);
        if ($texto === '') {
            return null;
        }

        $eventoCanonId = 'anuncio_evt_pueblo_' . $evtId;
        if (CanalDeduplicador::yaPublicado($partida, $eventoCanonId, BuzonEngine::CANAL_BUZON)) {
            self::marcarEmitido($partida, $evtId, null);
            return null;
        }

        $datos = [
            'evento_pueblo_id' => $evtId,
            'evento_pueblo_catalogo_id' => $catalogoId,
            'encuentro_id' => (string) ($evento['encuentro_id'] ?? ''),
            'dia' => $diaEvt,
            'hora' => $horaEvt,
            'lugar' => (string) ($evento['lugar'] ?? ''),
            'participantes' => array_values(array_map('strval', $participantes)),
            'participantes_n' => count($participantes),
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
            'seguimiento_pendiente' => false,
            'origen' => [
                'evento_id' => $eventoCanonId,
                'tipo_evento' => self::TIPO_EVENTO,
                'es_narrativo' => true,
                'informacion_revelada' => [
                    'evento_pueblo_id' => $evtId,
                    'catalogo_id' => $catalogoId,
                ],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);

        if ($r !== null && ($r['ok'] ?? false)) {
            $msgId = (string) (($r['mensaje']['id'] ?? '') ?: '');
            self::marcarEmitido($partida, $evtId, $msgId !== '' ? $msgId : null, $hiloId);
            if ($logger !== null) {
                $logger->info('evento_pueblo_anuncio_mensajito', [
                    'evento_pueblo_id' => $evtId,
                    'mensajito_id' => $msgId,
                    'remitente' => $remitente,
                ]);
            }
        }

        return $r;
    }

    public static function yaAnunciado(array $partida, string $eventoPuebloId): bool
    {
        EventosPuebloEngine::ensure($partida);
        foreach ($partida['eventos_pueblo']['programados'] as $ev) {
            if (!is_array($ev) || (string) ($ev['id'] ?? '') !== $eventoPuebloId) {
                continue;
            }
            if (!empty($ev['anuncio_emitido'])) {
                return true;
            }
        }
        $eventoCanonId = 'anuncio_evt_pueblo_' . $eventoPuebloId;
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
     * @param list<string> $participantes
     */
    private static function textoAsistencia(array $partida, array $participantes): string
    {
        $n = count($participantes);
        $total = 0;
        foreach ($partida['residentes'] ?? [] as $res) {
            if (is_array($res) && (string) ($res['presencia'] ?? '') === 'residente') {
                $total++;
            }
        }
        if ($total > 0 && $n >= max(3, (int) ceil($total * 0.45))) {
            return 'parece que se va a apuntar medio pueblo';
        }
        if ($n >= 4) {
            return 'ya van bastantes apuntados';
        }

        return 'vamos pocos pero con ganas';
    }

    public static function nombreNaturalPublico(string $nombre): string
    {
        return self::nombreNatural($nombre);
    }

    private static function nombreNatural(string $nombre): string
    {
        $n = trim($nombre);
        if ($n === '') {
            return 'algo en el pueblo';
        }

        return function_exists('mb_strtolower') ? mb_strtolower($n, 'UTF-8') : strtolower($n);
    }

    private static function marcarEmitido(array &$partida, string $eventoPuebloId, ?string $mensajitoId, ?string $hiloId = null): void
    {
        EventosPuebloEngine::ensure($partida);
        foreach ($partida['eventos_pueblo']['programados'] as &$ev) {
            if (!is_array($ev) || (string) ($ev['id'] ?? '') !== $eventoPuebloId) {
                continue;
            }
            $ev['anuncio_emitido'] = true;
            if ($mensajitoId !== null && $mensajitoId !== '') {
                $ev['anuncio_mensajito_id'] = $mensajitoId;
            }
            if ($hiloId !== null && $hiloId !== '') {
                $ev['hilo_mensajito_id'] = $hiloId;
            }
            break;
        }
        unset($ev);
    }
}
