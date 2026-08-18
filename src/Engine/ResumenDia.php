<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Vista de resumen del día para play. No decide reglas de reloj. */
final class ResumenDia
{
    public static function proximoEncuentro(array $partida, ?Catalog $catalog = null): ?array
    {
        $enc = RelojOperations::proximoEncuentroProgramado($partida);
        return $enc === null ? null : self::vistaEncuentro($partida, $enc, $catalog);
    }

    public static function encuentroEnCurso(array $partida, ?Catalog $catalog = null): ?array
    {
        $now = RelojOperations::ahoraAbsoluto($partida);
        foreach (EncuentroEngine::list($partida) as $enc) {
            if (($enc['estado'] ?? '') !== 'en_curso') {
                continue;
            }
            $t = ((int) ($enc['dia'] ?? 0)) * 24 + (int) ($enc['hora'] ?? 0);
            if ($t === $now) {
                return self::vistaEncuentro($partida, $enc, $catalog);
            }
        }
        return null;
    }

    public static function encuentrosHoy(array $partida): int
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $n = 0;
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if ((int) ($enc['dia'] ?? 0) === $dia) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * @param array<string, mixed> $enc
     * @return array<string, mixed>
     */
    public static function vistaEncuentro(array $partida, array $enc, ?Catalog $catalog = null): array
    {
        $ids = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
        $nombres = [];
        foreach ($ids as $id) {
            $nombres[] = IdentidadPublica::nombre($partida, (string) $id);
        }
        $lugarId = isset($enc['lugar']) ? (string) $enc['lugar'] : '';
        return [
            'id' => $enc['id'] ?? null,
            'tipo' => $enc['tipo'] ?? null,
            'intencion' => $enc['intencion'] ?? null,
            'estado' => $enc['estado'] ?? null,
            'dia' => $enc['dia'] ?? null,
            'hora' => $enc['hora'] ?? null,
            'lugar' => $lugarId !== '' ? $lugarId : null,
            'lugar_nombre' => self::nombreLugar($catalog, $lugarId),
            'participantes' => array_values($ids),
            'participantes_nombres' => $nombres,
        ];
    }

    private static function nombreLugar(?Catalog $catalog, string $lugarId): string
    {
        if ($lugarId === '') {
            return '—';
        }
        if ($catalog === null) {
            return $lugarId;
        }
        try {
            foreach ($catalog->loadLugares()['items'] ?? [] as $lug) {
                if (($lug['id'] ?? '') === $lugarId) {
                    return (string) ($lug['nombre'] ?? $lugarId);
                }
            }
        } catch (\Throwable) {
            return $lugarId;
        }
        return $lugarId;
    }
}
