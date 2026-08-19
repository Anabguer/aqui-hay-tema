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
    private static function mensajeDe(array $partida, string $evento, array $envelope): ?array
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
            $lugar = (string) ($envelope['lugar'] ?? $envelope['coincidencia']['lugar'] ?? '');
            $sitio = $lugar !== '' ? ' en ' . str_replace('lug_', '', $lugar) : '';
            return [
                'clasificacion' => BuzonEngine::COTILLEO,
                'tipo' => 'cotilleo',
                'texto' => $quien !== '' ? $quien . ' han coincidido' . $sitio . '.' : 'Han coincidido dos residentes.',
                'origen' => ['evento_id' => null, 'tipo_evento' => $evento, 'es_narrativo' => false, '_placeholder' => false],
                '_placeholder_contenido' => false,
            ];
        }
        if ($evento === DomainEvents::ENCUENTRO_TERMINADO) {
            return [
                'clasificacion' => BuzonEngine::IMPORTANTE,
                'tipo' => 'encuentro',
                'texto' => $quien !== '' ? 'Ha ocurrido algo entre ' . $quien . '.' : 'Ha terminado un encuentro.',
                'origen' => ['evento_id' => $envelope['encuentro']['id'] ?? null, 'tipo_evento' => $evento, 'es_narrativo' => false, '_placeholder' => false],
                '_placeholder_contenido' => false,
            ];
        }
        if ($evento === DomainEvents::PROPUESTA_ENCUENTRO) {
            $prop = is_array($envelope['propuesta'] ?? null) ? $envelope['propuesta'] : [];
            if (($prop['estado'] ?? '') !== 'rechazada') {
                return null;
            }
            return [
                'clasificacion' => BuzonEngine::OPORTUNIDAD,
                'tipo' => 'propuesta',
                'texto' => $quien !== '' ? $quien . ' no han quedado.' : 'Una propuesta de encuentro se ha rechazado.',
                'origen' => ['evento_id' => $prop['id'] ?? null, 'tipo_evento' => $evento, 'es_narrativo' => false, '_placeholder' => false],
                '_placeholder_contenido' => false,
            ];
        }
        if ($evento === DomainEvents::NPC_AUTONOMO_PLAN) {
            $rid = (string) ($envelope['residente_id'] ?? ($actores[0] ?? ''));
            $nom = $rid !== '' ? IdentidadPublica::nombre($partida, $rid) : 'Alguien';
            return [
                'clasificacion' => BuzonEngine::COTILLEO,
                'tipo' => 'autonomo',
                'texto' => $nom . ' ha salido por su cuenta.',
                'origen' => ['evento_id' => null, 'tipo_evento' => $evento, 'es_narrativo' => false, '_placeholder' => false],
                '_placeholder_contenido' => false,
            ];
        }
        if ($evento === DomainEvents::PETICION_CREADA) {
            $de = (string) ($envelope['de_persona'] ?? ($actores[0] ?? ''));
            $nom = $de !== '' ? IdentidadPublica::nombre($partida, $de) : 'Alguien';
            return [
                'clasificacion' => BuzonEngine::PETICION,
                'tipo' => 'peticion',
                'texto' => $nom . ' quiere hablar contigo.',
                'origen' => ['evento_id' => $envelope['peticion_id'] ?? null, 'tipo_evento' => $evento, 'es_narrativo' => false, '_placeholder' => false],
                '_placeholder_contenido' => false,
            ];
        }
        if ($evento === DomainEvents::DISCUSION) {
            return [
                'clasificacion' => BuzonEngine::IMPORTANTE,
                'tipo' => 'discusion',
                'texto' => $quien !== '' ? $quien . ' se han enfadado.' : 'Ha habido una discusión.',
                'origen' => ['evento_id' => null, 'tipo_evento' => $evento, 'es_narrativo' => false, '_placeholder' => false],
                '_placeholder_contenido' => false,
            ];
        }
        if ($evento === DomainEvents::SENAL_ROMANTICA) {
            $texto = (string) ($envelope['texto'] ?? '');
            if ($texto === '') {
                return null;
            }
            return [
                'clasificacion' => BuzonEngine::COTILLEO,
                'tipo' => 'senal_romantica',
                'texto' => $texto,
                'de_persona' => $envelope['desde'] ?? null,
                'origen' => [
                    'evento_id' => null,
                    'tipo_evento' => $evento,
                    'es_narrativo' => false,
                    'informacion_revelada' => [
                        'desde' => $envelope['desde'] ?? null,
                        'hacia' => $envelope['hacia'] ?? null,
                    ],
                    '_placeholder' => false,
                ],
                '_placeholder_contenido' => false,
            ];
        }
        return null;
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
