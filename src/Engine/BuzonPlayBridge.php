<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Convierte acontecimientos reales del motor en mensajes de buzón. Sin decoración falsa. */
final class BuzonPlayBridge
{
    public static function register(): void
    {
        $eventos = [
            DomainEvents::ENCUENTRO_TERMINADO,
            DomainEvents::COINCIDENCIA_RESIDENTES,
            DomainEvents::PROPUESTA_ENCUENTRO,
            DomainEvents::NPC_AUTONOMO_PLAN,
            DomainEvents::PETICION_CREADA,
            DomainEvents::DISCUSION,
            DomainEvents::SENAL_ROMANTICA,
        ];
        foreach ($eventos as $evento) {
            EventBus::on($evento, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
                return self::handle($partida, $envelope, $logger);
            });
        }
    }

    /**
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    private static function handle(array &$partida, array $envelope, ?GameLogger $logger): array
    {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return ['ok' => true, 'skipped' => 'buzon_disabled'];
        }
        $evento = (string) ($envelope['evento'] ?? '');
        $msg = self::mensajeDe($partida, $evento, $envelope);
        if ($msg === null) {
            return ['ok' => true, 'skipped' => 'sin_copy'];
        }
        $r = BuzonEngine::crear($partida, $msg);
        DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
            'mensaje' => $r['mensaje'] ?? null,
            'origen_evento' => $evento,
        ], $logger, 'BuzonPlayBridge');
        return $r;
    }

    /**
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>|null
     */
    private static function mensajeDe(array &$partida, string $evento, array $envelope): ?array
    {
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $envelope = array_merge($envelope, $payload);
        $actores = is_array($envelope['actores'] ?? null) ? $envelope['actores'] : [];
        $nombres = [];
        foreach ($actores as $id) {
            if (is_string($id) && $id !== '') {
                $nombres[] = IdentidadPublica::nombre($partida, $id);
            }
        }
        $quien = self::yNombres($nombres);
        if ($evento === DomainEvents::COINCIDENCIA_RESIDENTES) {
            return CotilleoNarrativo::mensajeCoincidencia($partida, $envelope, []);
        }
        if ($evento === DomainEvents::ENCUENTRO_TERMINADO) {
            return self::mensajeEncuentroTerminado($partida, $envelope, $quien);
        }
        if ($evento === DomainEvents::PROPUESTA_ENCUENTRO) {
            $prop = is_array($envelope['propuesta'] ?? null) ? $envelope['propuesta'] : [];
            if (($prop['estado'] ?? '') !== 'rechazada') {
                return null;
            }
            $canon = PropuestaEncuentroEngine::rechazoCanonico($prop);
            $hablante = $canon['hablante'];
            if (!is_array($hablante)) {
                return null;
            }
            $de = (string) ($hablante['residente_id'] ?? '');
            $nom = $de !== '' ? IdentidadPublica::nombre($partida, $de) : '';
            if ($nom === '') {
                return null;
            }
            $copyId = !empty($hablante['copy_id']) ? (string) $hablante['copy_id'] : '';
            $copy = self::textoRespuestaPlan($partida, $prop);
            if ($copy === null || $copy === '') {
                $copy = !empty($prop['mensaje_rechazo_ui'])
                    ? (string) $prop['mensaje_rechazo_ui']
                    : ($copyId !== ''
                        ? CopyVoluntad::rechazoConHablante($nom, $copyId)
                        : CopyRechazoPropuesta::mensajeRechazo(
                            $partida,
                            $prop,
                            is_array($prop['contrapropuesta'] ?? null) ? $prop['contrapropuesta'] : null
                        ));
            }
            $partes = is_array($prop['participantes'] ?? null) ? $prop['participantes'] : [];
            $habriaAceptado = $canon['habria_aceptado'];
            return [
                'clasificacion' => BuzonEngine::IMPORTANTE,
                'tipo' => 'respuesta_plan',
                'canal' => BuzonEngine::CANAL_BUZON,
                'de_persona' => $de,
                'actores' => self::idsDe($partes),
                'texto' => $copy,
                'copy_id' => $copyId !== '' ? $copyId : null,
                'origen' => [
                    'evento_id' => $prop['id'] ?? null,
                    'tipo_evento' => $evento,
                    'es_narrativo' => false,
                    'informacion_revelada' => [
                        'rechazado_por' => $de,
                        'otro_habria_aceptado' => is_array($habriaAceptado)
                            ? (string) ($habriaAceptado['residente_id'] ?? '')
                            : null,
                        'copy_id' => $copyId !== '' ? $copyId : null,
                        'rechazo_tipo' => $prop['rechazo_tipo'] ?? null,
                        'contrapropuesta' => $prop['contrapropuesta'] ?? null,
                    ],
                    '_placeholder' => false,
                ],
                '_placeholder_contenido' => false,
            ];
        }
        if ($evento === DomainEvents::NPC_AUTONOMO_PLAN) {
            return null;
        }
        if ($evento === DomainEvents::PETICION_CREADA) {
            $pet = is_array($envelope['peticion'] ?? null) ? $envelope['peticion'] : [];
            if (!empty($pet['buzon_id'])) {
                return null;
            }
            $de = (string) ($pet['residente_id'] ?? $envelope['de_persona'] ?? ($actores[0] ?? ''));
            $nom = $de !== '' ? IdentidadPublica::nombre($partida, $de) : 'Alguien';
            $texto = (string) ($pet['texto'] ?? '');
            if ($texto !== '') {
                $copy = $nom . ': ' . $texto;
                $plazo = PeticionPuebloEngine::plazoHumano($pet);
                if ($plazo !== '') {
                    $copy .= ' ' . $plazo;
                }
            } else {
                $copy = $nom . ' quiere hablar contigo.';
            }
            return [
                'clasificacion' => BuzonEngine::PETICION,
                'tipo' => 'peticion',
                'de_persona' => $de !== '' ? $de : null,
                'texto' => $copy,
                'peticion_id' => $pet['id'] ?? ($envelope['peticion_id'] ?? null),
                'origen' => ['evento_id' => $pet['id'] ?? null, 'tipo_evento' => $evento, 'es_narrativo' => false, '_placeholder' => false],
                '_placeholder_contenido' => false,
            ];
        }
        if ($evento === DomainEvents::DISCUSION) {
            $lugar = $envelope['lugar_id'] ?? $envelope['lugar'] ?? null;
            return [
                'clasificacion' => BuzonEngine::COTILLEO,
                'tipo' => 'discusion',
                'texto' => $quien !== '' ? $quien . ' se han enfadado.' : 'Ha habido una discusión.',
                'cotilleo_meta' => CotilleoCategoria::meta(CotilleoCategoria::DRAMA, true),
                'actores' => self::idsDe($actores),
                'lugar_id' => is_string($lugar) && $lugar !== '' ? $lugar : null,
                'origen' => ['evento_id' => null, 'tipo_evento' => $evento, 'es_narrativo' => false, '_placeholder' => false],
                '_placeholder_contenido' => false,
            ];
        }
        if ($evento === DomainEvents::SENAL_ROMANTICA) {
            $texto = (string) ($envelope['texto'] ?? '');
            if ($texto === '') {
                return null;
            }
            $desde = $envelope['desde'] ?? null;
            $hacia = $envelope['hacia'] ?? null;
            $ts = is_array($envelope['ts_juego'] ?? null) ? $envelope['ts_juego'] : [];
            if ($ts === []) {
                $reloj = $partida['reloj'] ?? [];
                $ts = [
                    'dia' => (int) ($reloj['dia_pueblo'] ?? 1),
                    'hora' => (int) ($reloj['hora_actual'] ?? 0),
                ];
            }
            return [
                'clasificacion' => BuzonEngine::COTILLEO,
                'tipo' => 'senal_romantica',
                'texto' => $texto,
                'ts_juego' => $ts,
                'cotilleo_meta' => CotilleoCategoria::meta(CotilleoCategoria::ROMANCE, true),
                'de_persona' => $desde,
                'actores' => self::idsDe([$desde, $hacia]),
                'origen' => [
                    'evento_id' => null,
                    'tipo_evento' => $evento,
                    'regla' => 'SenalRomantica::avisarSiAplica',
                    'es_narrativo' => false,
                    'informacion_revelada' => [
                        'desde' => $desde,
                        'hacia' => $hacia,
                        'motivo' => $envelope['motivo'] ?? null,
                        'ts_juego' => $ts,
                    ],
                    '_placeholder' => false,
                ],
                '_placeholder_contenido' => false,
            ];
        }
        return null;
    }

    /**
     * Cotilleo solo si hay algo que contar. Vacío → ningún aviso.
     *
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>|null
     */
    private static function mensajeEncuentroTerminado(array &$partida, array $envelope, string $quien): ?array
    {
        $enc = is_array($envelope['encuentro'] ?? null) ? $envelope['encuentro'] : [];
        $res = is_array($envelope['resultado'] ?? null) ? $envelope['resultado'] : [];
        if ($res === [] && is_array($enc['resultado'] ?? null)) {
            $res = $enc['resultado'];
        }
        $encId = (string) ($enc['id'] ?? '');
        if ($encId !== '') {
            foreach ($partida['encuentros'] ?? [] as $e) {
                if (!is_array($e) || (string) ($e['id'] ?? '') !== $encId) {
                    continue;
                }
                $em = $e['resultado']['emociones'] ?? null;
                if (is_array($em) && $em !== []) {
                    $res['emociones'] = $em;
                }
                break;
            }
        }
        if (($enc['tipo'] ?? '') === 'individual') {
            $intencion = (string) ($enc['intencion'] ?? '');
            $catalog = new Catalog(dirname(__DIR__, 2));
            if (in_array($intencion, ['autonomo', 'autonomo_relacion'], true)) {
                $texto = EncuentroCotilleoCopy::mensajeAutonomo($partida, $enc, $catalog);
                if ($texto === null || $texto === '') {
                    return null;
                }
                $partes = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
                $lugar = $enc['lugar'] ?? $enc['lugar_id'] ?? $envelope['lugar'] ?? $envelope['lugar_id'] ?? null;
                $msg = [
                    'clasificacion' => BuzonEngine::COTILLEO,
                    'tipo' => CotilleoAutonomoCadencia::TIPO_BUZON,
                    'texto' => $texto,
                    'cotilleo_meta' => CotilleoCategoria::meta(CotilleoCategoria::PUEBLO, false),
                    'actores' => self::idsDe($partes),
                    'lugar_id' => is_string($lugar) && $lugar !== '' ? $lugar : null,
                    'origen' => [
                        'evento_id' => $enc['id'] ?? null,
                        'tipo_evento' => DomainEvents::ENCUENTRO_TERMINADO,
                        'es_narrativo' => false,
                        '_placeholder' => false,
                    ],
                    '_placeholder_contenido' => false,
                ];
                CotilleoAutonomoCadencia::registrar($partida, $enc, $msg, $catalog);
                return null;
            }
            $hobbyLinea = self::lineaHobbyAnimoIndividual($partida, $enc, $res, $catalog);
            if ($hobbyLinea !== null && $hobbyLinea !== '') {
                $partes = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
                $lugar = $enc['lugar'] ?? $enc['lugar_id'] ?? null;
                return [
                    'clasificacion' => BuzonEngine::COTILLEO,
                    'tipo' => 'cotilleo',
                    'texto' => $hobbyLinea,
                    'actores' => self::idsDe($partes),
                    'lugar_id' => is_string($lugar) && $lugar !== '' ? $lugar : null,
                    'origen' => [
                        'evento_id' => $enc['id'] ?? null,
                        'tipo_evento' => DomainEvents::ENCUENTRO_TERMINADO,
                        'es_narrativo' => false,
                        '_placeholder' => false,
                    ],
                    '_placeholder_contenido' => false,
                ];
            }
            return null;
        }
        $catalog = new Catalog(dirname(__DIR__, 2));
        $comp = EncuentroCotilleoCopy::compilar($partida, $enc, $res, $catalog);
        $texto = is_array($comp) ? (string) ($comp['texto'] ?? '') : '';
        $cotilleoMeta = is_array($comp) ? ($comp['cotilleo_meta'] ?? null) : null;
        $hobbyLinea = self::lineaHobbyAnimoDeResultado($partida, $enc, $res, $catalog);
        if ($hobbyLinea !== null && $hobbyLinea !== '' && substr_count($texto, '.') < 2) {
            $texto = $texto !== '' ? $texto . ' ' . $hobbyLinea : $hobbyLinea;
        }
        if ($texto === '') {
            return null;
        }
        $partes = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
        if ($partes === [] && is_array($envelope['actores'] ?? null)) {
            $partes = $envelope['actores'];
        }
        $lugar = $enc['lugar'] ?? $enc['lugar_id'] ?? $envelope['lugar'] ?? $envelope['lugar_id'] ?? null;
        return [
            'clasificacion' => BuzonEngine::COTILLEO,
            'tipo' => 'cotilleo',
            'texto' => $texto,
            'cotilleo_meta' => is_array($cotilleoMeta) ? $cotilleoMeta : CotilleoCategoria::meta(CotilleoCategoria::ENCUENTRO, false),
            'actores' => self::idsDe($partes),
            'lugar_id' => is_string($lugar) && $lugar !== '' ? $lugar : null,
            'origen' => [
                'evento_id' => $enc['id'] ?? null,
                'tipo_evento' => DomainEvents::ENCUENTRO_TERMINADO,
                'es_narrativo' => false,
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ];
    }

    /**
     * @param array $raw
     * @return list<string>
     */
    private static function idsDe(array $raw): array
    {
        $ids = [];
        foreach ($raw as $id) {
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Texto del Mensajito de respuesta a un plan. Delega la atribución en la
     * fuente canónica. Cuando el motor marcó "el otro habría aceptado",
     * compone causa humana + esa información real (no duplica literalmente
     * el toast: variante propia y datos internos aparte). Sin datos ocultos.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $propuesta
     */
    public static function textoRespuestaPlan(array $partida, array $propuesta): ?string
    {
        $canon = PropuestaEncuentroEngine::rechazoCanonico($propuesta);
        $hablante = $canon['hablante'];
        if (!is_array($hablante)) {
            return null;
        }
        if ($canon['habria_aceptado'] !== null) {
            $otroId = (string) ($canon['habria_aceptado']['residente_id'] ?? '');
            $causa = CopyRechazoPropuesta::fraseCausaHumana($partida, $hablante, $otroId);
            if ($causa !== '') {
                $dispuesta = CopyRechazoPropuesta::lineaOtroDispuesto($partida, $propuesta, 'buzon:');
                return trim($causa . ($dispuesta !== '' ? ' ' . $dispuesta : ''));
            }
        }
        return null;
    }

    /**
     * Delega en la fuente canónica única (PropuestaEncuentroEngine::rechazoCanonico).
     * Nunca decide por posición A/B.
     *
     * @param array<string, mixed> $propuesta
     * @return array{residente_id: string, copy_id: ?string}|null
     */
    private static function rechazoDePropuesta(array $propuesta): ?array
    {
        $hablante = PropuestaEncuentroEngine::rechazoCanonico($propuesta)['hablante'];
        if (!is_array($hablante)) {
            return null;
        }
        return [
            'residente_id' => (string) ($hablante['residente_id'] ?? ''),
            'copy_id' => !empty($hablante['copy_id']) ? (string) $hablante['copy_id'] : null,
        ];
    }

    /**
     * @param list<string> $nombres
     */
    private static function yNombres(array $nombres): string
    {
        $nombres = array_values(array_filter($nombres, static function ($n) {
            return is_string($n) && $n !== '';
        }));
        $n = count($nombres);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $nombres[0];
        }
        if ($n === 2) {
            return $nombres[0] . ' y ' . $nombres[1];
        }
        $last = array_pop($nombres);
        return implode(', ', $nombres) . ' y ' . $last;
    }

    /**
     * @param array<string, mixed> $enc
     * @param array<string, mixed> $res
     */
    private static function lineaHobbyAnimoIndividual(array $partida, array $enc, array $res, Catalog $catalog): ?string
    {
        $ctx = self::ctxHobbyAnimo($res);
        if ($ctx === null) {
            return null;
        }
        return HobbyAnimoCopy::linea($partida, $enc, $ctx, $catalog);
    }

    /**
     * @param array<string, mixed> $enc
     * @param array<string, mixed> $res
     */
    private static function lineaHobbyAnimoDeResultado(array $partida, array $enc, array $res, Catalog $catalog): ?string
    {
        $ctx = self::ctxHobbyAnimo($res);
        if ($ctx === null) {
            return null;
        }
        return HobbyAnimoCopy::linea($partida, $enc, $ctx, $catalog);
    }

    /**
     * @param array<string, mixed> $res
     * @return array{estado_antes: string, estado_despues: string, hobby_match: bool}|null
     */
    private static function ctxHobbyAnimo(array $res): ?array
    {
        $list = is_array($res['emociones'] ?? null) ? $res['emociones'] : [];
        foreach ($list as $em) {
            if (!is_array($em) || empty($em['hobby_match'])) {
                continue;
            }
            $antes = (string) ($em['antes'] ?? '');
            $despues = (string) ($em['estado'] ?? '');
            if ($antes === '' || $despues === '') {
                continue;
            }
            return [
                'estado_antes' => $antes,
                'estado_despues' => $despues,
                'hobby_match' => true,
            ];
        }
        return null;
    }
}
