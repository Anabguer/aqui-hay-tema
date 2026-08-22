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
            $rechazo = self::rechazoDePropuesta($prop);
            if ($rechazo === null) {
                return null;
            }
            $de = (string) ($rechazo['residente_id'] ?? '');
            $nom = $de !== '' ? IdentidadPublica::nombre($partida, $de) : '';
            if ($nom === '') {
                return null;
            }
            $copyId = !empty($rechazo['copy_id']) ? (string) $rechazo['copy_id'] : '';
            $copy = $copyId !== ''
                ? CopyVoluntad::rechazoConHablante($nom, $copyId)
                : $nom . ' ha rechazado la propuesta.';
            $partes = is_array($prop['participantes'] ?? null) ? $prop['participantes'] : [];
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
                        'copy_id' => $copyId !== '' ? $copyId : null,
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
            return [
                'clasificacion' => BuzonEngine::COTILLEO,
                'tipo' => 'senal_romantica',
                'texto' => $texto,
                'de_persona' => $desde,
                'actores' => self::idsDe([$desde, $hacia]),
                'origen' => [
                    'evento_id' => null,
                    'tipo_evento' => $evento,
                    'es_narrativo' => false,
                    'informacion_revelada' => [
                        'desde' => $desde,
                        'hacia' => $hacia,
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
        if (($enc['tipo'] ?? '') === 'individual') {
            $intencion = (string) ($enc['intencion'] ?? '');
            if (in_array($intencion, ['autonomo', 'autonomo_relacion'], true)) {
                $catalog = new Catalog(dirname(__DIR__, 2));
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
            return null;
        }
        $catalog = new Catalog(dirname(__DIR__, 2));
        $texto = EncuentroCotilleoCopy::mensaje($partida, $enc, $res, $catalog);
        if ($texto === null || $texto === '') {
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
     * @param array<string, mixed> $propuesta
     * @return array{residente_id: string, copy_id: ?string}|null
     */
    private static function rechazoDePropuesta(array $propuesta): ?array
    {
        $ids = is_array($propuesta['participantes'] ?? null) ? $propuesta['participantes'] : [];
        $preferido = (string) ($ids[1] ?? '');
        $elegido = null;
        foreach ($propuesta['reacciones'] ?? [] as $reac) {
            if (!is_array($reac) || ($reac['decision'] ?? '') !== PropuestaEncuentro::DECISION_RECHAZA) {
                continue;
            }
            $row = [
                'residente_id' => (string) ($reac['residente_id'] ?? ''),
                'copy_id' => !empty($reac['copy_id']) ? (string) $reac['copy_id'] : null,
            ];
            if (($row['residente_id'] ?? '') === '') {
                continue;
            }
            $elegido = $elegido ?? $row;
            if ($preferido !== '' && $row['residente_id'] === $preferido) {
                return $row;
            }
        }
        return $elegido;
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
}
