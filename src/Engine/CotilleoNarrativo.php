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
        if (!self::patronParLugar($partida, $ids, $lugar, $dia, $cal)) {
            return false;
        }
        return !self::yaPublicadoHoy($partida, $ids, $lugar, $dia);
    }

    /**
     * Mismo par + mismo lugar en ≥ N días de la ventana. No exige inédito en El Cotilleo.
     *
     * @param list<string> $ids
     */
    public static function patronParLugar(array $partida, array $ids, string $lugar, int $dia, array $cal = []): bool
    {
        $ids = array_values(array_unique($ids));
        sort($ids);
        if (count($ids) < 2 || $lugar === '') {
            return false;
        }
        $minDias = (int) CalibracionConfig::get($cal, 'coincidencias.cotilleo_min_dias_par_lugar', 3);
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
        return count($dias) >= $minDias;
    }

    /**
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
        $sitio = $lugar !== '' ? ' en ' . str_replace('lug_', '', $lugar) : '';
        return [
            'clasificacion' => BuzonEngine::COTILLEO,
            'tipo' => 'cotilleo_patron',
            'texto' => $quien !== ''
                ? $quien . ' llevan varios días coincidiendo' . $sitio . '.'
                : 'Hay caras que se repiten demasiado en el mismo sitio.',
            'patron_clave' => self::clavePar($ids, $lugar),
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
