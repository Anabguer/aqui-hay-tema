<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * El Cotilleo no es un log. Una coincidencia técnica casi nunca se publica.
 * Patrón: mismo par + mismo lugar en ≥ N días distintos de una ventana.
 */
final class CotilleoNarrativo
{
    /**
     * @param array<string, mixed> $envelope
     */
    public static function coincidenciaDigna(array $partida, array $envelope, array $cal = []): bool
    {
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $env = array_merge($envelope, $payload);
        $lugar = (string) ($env['lugar_id'] ?? $env['lugar'] ?? $env['coincidencia']['lugar'] ?? '');
        $res = $env['residentes'] ?? $env['actores'] ?? [];
        if (!is_array($res) || count($res) < 2 || $lugar === '') {
            return false;
        }
        $ids = [];
        foreach ($res as $id) {
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        if (count($ids) < 2) {
            return false;
        }
        $dia = (int) ($env['dia'] ?? $partida['reloj']['dia_pueblo'] ?? 1);
        return CotilleoPatronCadencia::debePublicar($partida, $ids, $lugar, $dia, $cal);
    }

    /**
     * Días distintos con coincidencia técnica del par en el lugar (ventana móvil).
     *
     * @param list<string> $ids
     */
    public static function diasPatronParLugar(array $partida, array $ids, string $lugar, int $dia, array $cal = []): int
    {
        $ids = array_values(array_unique($ids));
        sort($ids);
        if (count($ids) < 2 || $lugar === '') {
            return 0;
        }
        $ventana = (int) CalibracionConfig::get($cal, 'coincidencias.cotilleo_ventana_dias', 7);
        $desde = $dia - $ventana;
        $dias = [];
        foreach ($partida['historial_coincidencias'] ?? [] as $e) {
            if (!is_array($e)) {
                continue;
            }
            $d = (int) ($e['dia'] ?? 0);
            if ($d < $desde || $d > $dia) {
                continue;
            }
            if ((string) ($e['lugar_id'] ?? '') !== $lugar) {
                continue;
            }
            $r = is_array($e['residentes'] ?? null) ? $e['residentes'] : [];
            $rr = [];
            foreach ($r as $id) {
                if (is_string($id) && $id !== '') {
                    $rr[] = $id;
                }
            }
            $rr = array_values(array_unique($rr));
            sort($rr);
            if ($rr !== $ids) {
                continue;
            }
            $dias[$d] = true;
        }
        return count($dias);
    }

    /**
     * Mismo par + mismo lugar en ≥ N días de la ventana.
     *
     * @param list<string> $ids
     */
    public static function patronParLugar(array $partida, array $ids, string $lugar, int $dia, array $cal = []): bool
    {
        $minDias = (int) CalibracionConfig::get($cal, 'coincidencias.cotilleo_min_dias_par_lugar', 3);
        return self::diasPatronParLugar($partida, $ids, $lugar, $dia, $cal) >= $minDias;
    }

    /**
     * @deprecated Usar CotilleoPatronCadencia::debePublicar
     * @param list<string> $ids
     */
    public static function yaPublicadoHoy(array $partida, array $ids, string $lugar, int $dia): bool
    {
        $clave = self::clavePar($ids, $lugar);
        foreach ($partida['buzon'] ?? [] as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            if ((string) ($msg['tipo'] ?? '') !== 'cotilleo_patron') {
                continue;
            }
            $ts = is_array($msg['ts_juego'] ?? null) ? $msg['ts_juego'] : [];
            if ((int) ($ts['dia'] ?? 0) !== $dia) {
                continue;
            }
            if ((string) ($msg['patron_clave'] ?? '') === $clave) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>|null
     */
    public static function mensajeCoincidencia(array $partida, array $envelope, array $cal = []): ?array
    {
        if (!self::coincidenciaDigna($partida, $envelope, $cal)) {
            return null;
        }
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $env = array_merge($envelope, $payload);
        $lugar = (string) ($env['lugar_id'] ?? $env['lugar'] ?? '');
        $res = $env['residentes'] ?? $env['actores'] ?? [];
        $ids = [];
        $nombres = [];
        foreach ($res as $id) {
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
                $nombres[] = IdentidadPublica::nombre($partida, $id);
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        $quien = self::yNombres($nombres);
        $vista = self::vistaPatron($partida, $ids, $lugar, null);
        $metaPub = CotilleoPatronCadencia::metaPublicacion(
            $partida,
            $ids,
            $lugar,
            (int) ($env['dia'] ?? $partida['reloj']['dia_pueblo'] ?? 1),
            $cal
        );
        return [
            'clasificacion' => BuzonEngine::COTILLEO,
            'tipo' => 'cotilleo_patron',
            'texto' => (string) ($vista['texto'] ?? ''),
            'cotilleo_meta' => is_array($vista['cotilleo_meta'] ?? null)
                ? $vista['cotilleo_meta']
                : CotilleoCategoria::meta(CotilleoCategoria::RELACION, false),
            'patron_clave' => self::clavePar($ids, $lugar),
            'patron_nivel' => $metaPub['patron_nivel'],
            'patron_estado' => $metaPub['patron_estado'],
            'actores' => $ids,
            'lugar_id' => $lugar !== '' ? $lugar : null,
            'origen' => [
                'evento_id' => null,
                'tipo_evento' => DomainEvents::COINCIDENCIA_RESIDENTES,
                'es_narrativo' => false,
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ];
    }

    /**
     * Copy y pista de un patrón par+lugar según hecho real (co-presencia vs relación).
     *
     * @param list<string> $ids
     * @return array{
     *   texto: string,
     *   pista: string,
     *   categoria: string,
     *   categoria_etiqueta: string,
     *   categoria_icono: string,
     *   destacado: bool,
     *   cotilleo_meta: array{categoria: string, destacado: bool},
     *   actores: list<string>,
     *   actores_nombres: list<string>,
     *   lugar_id: string,
     *   lugar_nombre: string,
     *   se_conocen: bool,
     *   nivel: string
     * }
     */
    public static function vistaPatron(array $partida, array $ids, string $lugarId, ?Catalog $catalog = null): array
    {
        $ids = array_values(array_unique($ids));
        sort($ids);
        $root = $catalog !== null ? $catalog->getRoot() : dirname(__DIR__, 2);
        if ($catalog === null) {
            $catalog = new Catalog($root);
        }
        $nombres = [];
        foreach ($ids as $id) {
            $nombres[] = IdentidadPublica::nombre($partida, $id);
        }
        $lugarNombre = $lugarId !== '' ? EtiquetaFicha::lugar($lugarId, $catalog->store()) : '';

        $copy = CopyCoincidenciaPatron::vista($partida, $ids, $lugarId, $catalog);

        return array_merge($copy, [
            'actores' => $ids,
            'actores_nombres' => $nombres,
            'lugar_id' => $lugarId,
            'lugar_nombre' => $lugarNombre,
        ]);
    }

    /**
     * @param list<string> $ids
     */
    public static function clavePar(array $ids, string $lugar): string
    {
        $ids = array_values($ids);
        sort($ids);
        return implode('|', $ids) . '@' . $lugar;
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
        $last = $nombres[$n - 1];
        return implode(', ', array_slice($nombres, 0, $n - 1)) . ' y ' . $last;
    }
}
