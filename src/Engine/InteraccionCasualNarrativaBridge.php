<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Cotilleo solo cuando una interacción casual produce descubrimiento real para el jugador.
 * No narra contacto leve ni coincidencias sin pista nueva.
 */
final class InteraccionCasualNarrativaBridge
{
    public static function ensure(array &$partida): void
    {
        $partida['narrativa_casual_publicados'] ??= [];
    }

    /**
     * @param array<string, mixed> $hecho Resultado de InteraccionCasual::ejecutarPar()
     * @return array<string, mixed>|null mensaje de buzón creado
     */
    public static function alHecho(
        array &$partida,
        array $hecho,
        int $dia,
        int $hora,
        ?Catalog $catalog = null,
        ?GameLogger $logger = null
    ): ?array {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return null;
        }
        $desc = $hecho['descubrimientos'] ?? null;
        if (!is_array($desc)) {
            return null;
        }
        $descubiertos = $desc['descubiertos'] ?? [];
        if (!is_array($descubiertos) || $descubiertos === []) {
            return null;
        }

        $jugador = null;
        foreach ($descubiertos as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((string) ($row['quien'] ?? '') !== 'jugador') {
                continue;
            }
            $de = (string) ($row['de'] ?? '');
            $campo = (string) ($row['campo'] ?? '');
            if ($de !== '' && $campo !== '') {
                $jugador = $row;
                break;
            }
        }
        if ($jugador === null) {
            return null;
        }

        $a = (string) ($hecho['a'] ?? '');
        $b = (string) ($hecho['b'] ?? '');
        $lugar = (string) ($hecho['lugar'] ?? '');
        if ($a === '' || $b === '') {
            return null;
        }

        $residenteId = (string) $jugador['de'];
        $campo = (string) $jugador['campo'];
        $lo = $a < $b ? $a : $b;
        $hi = $a < $b ? $b : $a;
        $eventoId = 'casual_desc:' . $dia . ':' . $hora . ':' . $lugar . ':' . $lo . '|' . $hi . ':' . $residenteId . ':' . $campo;

        self::ensure($partida);
        if (!empty($partida['narrativa_casual_publicados'][$eventoId])) {
            return null;
        }

        if ($catalog === null) {
            return null;
        }
        $nombre = IdentidadPublica::nombre($partida, $residenteId);
        $genero = (string) ($partida['residentes'][$residenteId]['identidad_publica']['genero'] ?? '');
        $valor = CopyDescubrimiento::idDeCampo($campo);
        $texto = CopyDescubrimiento::textoCotilleo(
            $nombre,
            $campo,
            $valor,
            $catalog->store(),
            $genero !== '' ? $genero : null
        );
        if ($texto === null || trim($texto) === '') {
            return null;
        }

        $msg = [
            'clasificacion' => BuzonEngine::COTILLEO,
            'tipo' => 'cotilleo_casual_descubrimiento',
            'texto' => $texto,
            'cotilleo_meta' => CotilleoCategoria::meta(CotilleoCategoria::DESCUBRIMIENTO, false),
            'actores' => array_values(array_unique([$a, $b])),
            'lugar_id' => $lugar !== '' ? $lugar : null,
            'dia' => $dia,
            'origen' => [
                'evento_id' => $eventoId,
                'tipo_evento' => 'interaccion_casual',
                'es_narrativo' => true,
                'informacion_revelada' => [
                    'descubrimiento' => [
                        'residente_id' => $residenteId,
                        'campo' => $campo,
                    ],
                    'par' => [$a, $b],
                    'lugar' => $lugar,
                ],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ];

        $r = BuzonEngine::crear($partida, $msg);
        DiarioNarrativaBridge::mirrorCotilleoBuzon($partida, $r);
        if ($r['ok'] ?? false) {
            $partida['narrativa_casual_publicados'][$eventoId] = $dia;
            DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
                'mensaje' => $r['mensaje'] ?? null,
                'origen_evento' => 'interaccion_casual',
                'evento_id' => $eventoId,
            ], $logger, 'InteraccionCasualNarrativaBridge');
            return is_array($r['mensaje'] ?? null) ? $r['mensaje'] : null;
        }
        return null;
    }
}
