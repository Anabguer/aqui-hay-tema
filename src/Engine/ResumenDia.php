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
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $best = null;
        $bestIni = null;
        foreach (EncuentroEngine::list($partida) as $enc) {
            if (($enc['estado'] ?? '') !== 'en_curso') {
                continue;
            }
            if (!LugarAtributos::ocupaHora($enc, $dia, $hora)) {
                continue;
            }
            $ini = ((int) ($enc['dia'] ?? 0)) * 24 + (int) ($enc['hora'] ?? ($enc['hora_inicio'] ?? 0));
            if ($bestIni === null || $ini < $bestIni) {
                $best = $enc;
                $bestIni = $ini;
            }
        }
        return is_array($best) ? self::vistaEncuentro($partida, $best, $catalog) : null;
    }

    /**
     * Todos los encuentros en curso en la franja actual (0..N), con vista e intervención.
     *
     * @return list<array<string, mixed>>
     */
    public static function encuentrosEnCurso(array $partida, ?Catalog $catalog = null): array
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $rows = [];
        foreach (EncuentroEngine::list($partida) as $enc) {
            if (($enc['estado'] ?? '') !== 'en_curso') {
                continue;
            }
            if (!LugarAtributos::ocupaHora($enc, $dia, $hora)) {
                continue;
            }
            $ini = ((int) ($enc['dia'] ?? 0)) * 24 + (int) ($enc['hora'] ?? ($enc['hora_inicio'] ?? 0));
            $rows[] = ['ini' => $ini, 'vista' => self::vistaEncuentro($partida, $enc, $catalog)];
        }
        usort($rows, static function (array $x, array $y): int {
            $cmp = ($x['ini'] <=> $y['ini']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string) ($x['vista']['id'] ?? ''), (string) ($y['vista']['id'] ?? ''));
        });
        return array_values(array_map(static fn(array $row): array => $row['vista'], $rows));
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
     * Marcas de mapa: un lugar, una marca. EN CURSO pisa PRÓXIMO en el mismo sitio.
     *
     * @return array<string, array{marca: string, encuentro: array}>
     */
    public static function marcasPorLugar(array $partida, ?Catalog $catalog = null): array
    {
        $out = [];
        $prox = self::proximoEncuentro($partida, $catalog);
        if (is_array($prox) && !empty($prox['lugar'])) {
            $out[(string) $prox['lugar']] = ['marca' => 'proximo', 'encuentro' => $prox];
        }
        $curso = self::encuentroEnCurso($partida, $catalog);
        if (is_array($curso) && !empty($curso['lugar'])) {
            $out[(string) $curso['lugar']] = ['marca' => 'en_curso', 'encuentro' => $curso];
        }
        return $out;
    }

    public static function residenteEnVista(?array $vista, string $residenteId): bool
    {
        if ($vista === null || $residenteId === '') {
            return false;
        }
        return in_array($residenteId, $vista['participantes'] ?? [], true);
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
        $diaEnc = isset($enc['dia']) ? (int) $enc['dia'] : null;
        $diaAhora = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        return [
            'id' => $enc['id'] ?? null,
            'tipo' => $enc['tipo'] ?? null,
            'intencion' => $enc['intencion'] ?? null,
            'estado' => $enc['estado'] ?? null,
            'dia' => $enc['dia'] ?? null,
            'hora' => $enc['hora'] ?? null,
            'es_hoy' => $diaEnc !== null && $diaEnc === $diaAhora,
            'fecha_iso' => $diaEnc !== null ? Reloj::fechaIso($partida['reloj'] ?? [], $diaEnc) : null,
            'fecha_corta' => $diaEnc !== null ? Reloj::fechaCorta($partida['reloj'] ?? [], $diaEnc) : null,
            'dia_semana_ui' => $diaEnc !== null ? Reloj::diaSemanaUi($diaEnc, $partida['reloj'] ?? []) : null,
            'lugar' => $lugarId !== '' ? $lugarId : null,
            'lugar_nombre' => self::nombreLugar($catalog, $lugarId),
            'participantes' => array_values($ids),
            'participantes_nombres' => $nombres,
            'intervencion' => EncuentroIntervencion::vistaParaPlay($partida, $enc, $catalog),
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
            return EtiquetaFicha::lugar($lugarId, $catalog->store());
        } catch (\Throwable $ignored) {
            return $lugarId;
        }
    }
}
