<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * El Cotilleo = hechos dignos de contar. Fuente: canal cotilleo del buzón
 * (BuzonPlayBridge). El diario técnico solo se usa si hay entradas reales.
 * No inventa titulares.
 */
final class VistaCotilleoV3
{
    /**
     * @param array<string, mixed> $partida
     * @return array{hoy: list<array>, ayer: list<array>, viejos: list<array>}
     */
    public static function de(array $partida): array
    {
        $hoy = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $buckets = ['hoy' => [], 'ayer' => [], 'viejos' => []];

        foreach (self::entradasDesdeBuzon($partida) as $e) {
            $d = (int) ($e['dia'] ?? 0);
            if ($d === $hoy) {
                $buckets['hoy'][] = $e;
            } elseif ($d === $hoy - 1 && $hoy > 1) {
                $buckets['ayer'][] = $e;
            } elseif ($d > 0 && $d < $hoy - 1) {
                $buckets['viejos'][] = $e;
            }
        }

        foreach ($partida['diario'] ?? [] as $e) {
            if (!is_array($e)) {
                continue;
            }
            $d = (int) ($e['dia'] ?? 0);
            $row = self::normalizarDiario($e);
            if ($d === $hoy) {
                $buckets['hoy'][] = $row;
            } elseif ($d === $hoy - 1 && $hoy > 1) {
                $buckets['ayer'][] = $row;
            } elseif ($d > 0 && $d < $hoy - 1) {
                $buckets['viejos'][] = $row;
            }
        }

        return $buckets;
    }

    /**
     * @param array<string, mixed> $partida
     * @return list<array<string, mixed>>
     */
    private static function entradasDesdeBuzon(array $partida): array
    {
        $out = [];
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            $clas = (string) ($m['clasificacion'] ?? '');
            $canal = (string) ($m['canal'] ?? BuzonEngine::canalDe($clas));
            if ($clas !== BuzonEngine::COTILLEO && $canal !== BuzonEngine::CANAL_COTILLEO) {
                continue;
            }
            $texto = trim((string) ($m['texto'] ?? ''));
            if ($texto === '') {
                continue;
            }
            $dia = (int) ($m['dia'] ?? 0);
            $ts = is_array($m['ts_juego'] ?? null) ? $m['ts_juego'] : [];
            if ($dia === 0) {
                $dia = (int) ($ts['dia'] ?? 0);
            }
            $out[] = [
                'id' => (string) ($m['id'] ?? ''),
                'dia' => $dia,
                'texto' => $texto,
                'tipo' => (string) ($m['tipo'] ?? 'cotilleo'),
                'fecha_corta' => (string) ($m['fecha_corta'] ?? ''),
                'origen' => 'buzon_cotilleo',
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    private static function normalizarDiario(array $e): array
    {
        return [
            'id' => (string) ($e['id'] ?? ''),
            'dia' => (int) ($e['dia'] ?? 0),
            'texto' => (string) ($e['texto'] ?? ''),
            'tipo' => (string) ($e['tipo'] ?? 'diario'),
            'fecha_corta' => (string) ($e['fecha_corta'] ?? ''),
            'origen' => 'diario',
        ];
    }
}
