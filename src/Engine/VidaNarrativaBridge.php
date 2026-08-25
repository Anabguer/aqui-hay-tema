<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente narrativo: acontecimientos de VIDA con visibilidad al jugador → buzón/cotilleo.
 * Solo copy real; no placeholders vacíos (BuzonEngine rechaza texto vacío).
 */
final class VidaNarrativaBridge
{
    /**
     * @param list<string> $participantes
     * @param array<string, mixed> $item
     * @param list<string> $efectos
     * @return array<string, mixed>|null mensaje creado o null
     */
    public static function alAcontecimiento(
        array &$partida,
        string $eventoId,
        array $participantes,
        array $item,
        array $efectos,
        ?GameLogger $logger = null
    ): ?array {
        $vis = (string) ($item['visibilidad_jugador'] ?? 'ninguna');
        if ($vis === 'ninguna') {
            return null;
        }
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return null;
        }

        if ($eventoId === 'perder_trabajo' && count($participantes) >= 1) {
            $rid = (string) $participantes[0];
            $texto = EmocionalNarrativa::cotilleoParaOrigen($partida, $rid, 'perder_trabajo');
            if ($texto === null || $texto === '') {
                return null;
            }
            $clas = AcontecimientoDiario::clasificacionVisibilidad($vis);
            $msg = [
                'clasificacion' => $clas,
                'tipo' => 'acontecimiento_perder_trabajo',
                'texto' => $texto,
                'de_persona' => $rid,
                'actores' => [$rid],
                'importancia' => $item['importancia'] ?? 'relevante',
                'origen' => [
                    'evento_id' => $eventoId,
                    'tipo_evento' => 'acontecimiento_diario',
                    'es_narrativo' => false,
                    'informacion_revelada' => [
                        'acontecimiento' => $eventoId,
                        'efectos' => $efectos,
                    ],
                    '_placeholder' => false,
                ],
                '_placeholder_contenido' => false,
            ];
            if ($clas === BuzonEngine::COTILLEO) {
                $msg['cotilleo_meta'] = CotilleoCategoria::meta(CotilleoCategoria::DRAMA, true);
            }
            $r = BuzonEngine::crear($partida, $msg);
            if ($r['ok'] ?? false) {
                DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
                    'mensaje' => $r['mensaje'] ?? null,
                    'origen_evento' => 'acontecimiento_diario',
                    'acontecimiento_id' => $eventoId,
                ], $logger, 'VidaNarrativaBridge');
                return $r['mensaje'] ?? null;
            }
            return null;
        }

        if ($eventoId === 'encontrar_trabajo' && count($participantes) >= 1) {
            $rid = (string) $participantes[0];
            $texto = EmocionalNarrativa::cotilleoParaOrigen($partida, $rid, 'encontrar_trabajo');
            if ($texto === null || $texto === '') {
                return null;
            }
            $clas = AcontecimientoDiario::clasificacionVisibilidad($vis);
            $msg = [
                'clasificacion' => $clas,
                'tipo' => 'acontecimiento_encontrar_trabajo',
                'texto' => $texto,
                'de_persona' => $rid,
                'actores' => [$rid],
                'importancia' => $item['importancia'] ?? 'relevante',
                'origen' => [
                    'evento_id' => $eventoId,
                    'tipo_evento' => 'acontecimiento_diario',
                    'es_narrativo' => false,
                    'informacion_revelada' => [
                        'acontecimiento' => $eventoId,
                        'efectos' => $efectos,
                    ],
                    '_placeholder' => false,
                ],
                '_placeholder_contenido' => false,
            ];
            if ($clas === BuzonEngine::COTILLEO) {
                $msg['cotilleo_meta'] = CotilleoCategoria::meta(CotilleoCategoria::DRAMA, true);
            }
            $r = BuzonEngine::crear($partida, $msg);
            if ($r['ok'] ?? false) {
                DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
                    'mensaje' => $r['mensaje'] ?? null,
                    'origen_evento' => 'acontecimiento_diario',
                    'acontecimiento_id' => $eventoId,
                ], $logger, 'VidaNarrativaBridge');
                return $r['mensaje'] ?? null;
            }
            return null;
        }

        return null;
    }
}
