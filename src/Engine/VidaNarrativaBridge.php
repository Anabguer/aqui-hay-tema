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
            $clas = AcontecimientoDiario::clasificacionVisibilidad($vis);
            // CONTRATO NARRATIVO: canal cotilleo = 3.ª persona; canal Mensajitos
            // = el propio vecino escribe a Celestine en primera persona.
            $texto = $clas === BuzonEngine::COTILLEO
                ? EmocionalNarrativa::cotilleoParaOrigen($partida, $rid, 'perder_trabajo')
                : EmocionalNarrativa::mensajitoParaOrigen($partida, $rid, 'perder_trabajo');
            if ($texto === null || $texto === '') {
                return null;
            }
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
            if ($clas === BuzonEngine::COTILLEO) {
                DiarioNarrativaBridge::mirrorCotilleoBuzon($partida, $r);
            }
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
            $clas = AcontecimientoDiario::clasificacionVisibilidad($vis);
            $texto = $clas === BuzonEngine::COTILLEO
                ? EmocionalNarrativa::cotilleoParaOrigen($partida, $rid, 'encontrar_trabajo')
                : EmocionalNarrativa::mensajitoParaOrigen($partida, $rid, 'encontrar_trabajo');
            if ($texto === null || $texto === '') {
                return null;
            }
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
            if ($clas === BuzonEngine::COTILLEO) {
                DiarioNarrativaBridge::mirrorCotilleoBuzon($partida, $r);
            }
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
