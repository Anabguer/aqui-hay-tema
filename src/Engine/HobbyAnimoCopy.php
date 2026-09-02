<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Copy breve cuando un hobby acertado ayuda al ánimo. Una sola señal por encuentro. */
final class HobbyAnimoCopy
{
    /**
     * @param array<string, mixed> $enc
     * @param array{estado_antes: string, estado_despues: string, hobby_match: bool} $ctx
     */
    public static function linea(
        array $partida,
        array $enc,
        array $ctx,
        Catalog $catalog
    ): ?string {
        if (empty($ctx['hobby_match'])) {
            return null;
        }
        $antes = EstadoEmocional::canonId((string) ($ctx['estado_antes'] ?? ''));
        $despues = EstadoEmocional::canonId((string) ($ctx['estado_despues'] ?? ''));
        if (!in_array($antes, [EstadoEmocional::TRISTE, EstadoEmocional::ENFADADO], true)) {
            return null;
        }
        if (self::rank($despues) <= self::rank($antes)) {
            return null;
        }

        $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
        if ($parts === []) {
            return null;
        }
        $rid = (string) $parts[0];
        $nombre = IdentidadPublica::nombre($partida, $rid);
        if ($nombre === '') {
            return null;
        }
        $lugarId = (string) ($enc['lugar'] ?? $enc['lugar_id'] ?? '');
        $lugar = $lugarId !== '' ? EtiquetaFicha::lugar($lugarId, $catalog->store()) : '';
        $lugarTxt = $lugar !== '' ? EncuentroCotilleoCopy::prepLugarPublico($lugarId, $lugar) : '';

        $variantes = [];
        if ($lugarTxt !== '') {
            $variantes[] = 'Parece que a ' . $nombre . ' le ha sentado bien pasar un rato' . $lugarTxt . '.';
            $variantes[] = 'Después de un rato' . $lugarTxt . ', ' . $nombre . ' está bastante más despejad' . GeneroConcordancia::oa($partida, $rid) . '.';
        }
        $variantes[] = 'Eso era justo lo que ' . $nombre . ' necesitaba.';
        if ($despues === EstadoEmocional::ALEGRE) {
            $variantes[] = $nombre . ' ha recuperado el buen humor.';
        }

        $seed = (string) ($enc['id'] ?? '') . '|' . $rid . '|' . $antes;
        $idx = abs(crc32($seed)) % count($variantes);
        return $variantes[$idx];
    }

    private static function rank(string $estado): int
    {
        static $map = [
            EstadoEmocional::TRISTE => 0,
            EstadoEmocional::ENFADADO => 0,
            EstadoEmocional::NEUTRO => 1,
            EstadoEmocional::ALEGRE => 2,
        ];
        return $map[EstadoEmocional::canonId($estado)] ?? 0;
    }
}
