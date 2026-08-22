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

     * @return array{hoy: list<array>, ayer: list<array>, viejos: list<array>, ultimo: ?array}

     */

    public static function de(array $partida): array

    {

        $hoy = (int) ($partida['reloj']['dia_pueblo'] ?? 1);

        $buckets = ['hoy' => [], 'ayer' => [], 'viejos' => []];

        $seq = 0;



        foreach (self::entradasDesdeBuzon($partida, $seq) as $e) {

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

            $row['_seq'] = $seq++;

            $ts = is_array($e['ts_juego'] ?? null) ? $e['ts_juego'] : [];

            if ($ts !== []) {

                $row['ts_juego'] = $ts;

            }

            if ($d === $hoy) {

                $buckets['hoy'][] = $row;

            } elseif ($d === $hoy - 1 && $hoy > 1) {

                $buckets['ayer'][] = $row;

            } elseif ($d > 0 && $d < $hoy - 1) {

                $buckets['viejos'][] = $row;

            }

        }



        foreach (array_keys($buckets) as $bucket) {

            $buckets[$bucket] = self::ordenarRecientes($buckets[$bucket]);

        }



        $buckets['ultimo'] = self::ultimoDe($buckets);



        return $buckets;

    }



    /**

     * @param array{hoy: list<array>, ayer: list<array>, viejos: list<array>} $buckets

     * @return ?array<string, mixed>

     */

    public static function ultimoDe(array $buckets): ?array

    {

        foreach (['hoy', 'ayer', 'viejos'] as $bucket) {

            $items = $buckets[$bucket] ?? [];

            if ($items !== []) {

                return $items[0];

            }

        }

        return null;

    }



    /**

     * @param list<array<string, mixed>> $items

     * @return list<array<string, mixed>>

     */

    private static function ordenarRecientes(array $items): array

    {

        usort($items, static function (array $a, array $b): int {

            $cmp = ((int) ($b['dia'] ?? 0)) <=> ((int) ($a['dia'] ?? 0));

            if ($cmp !== 0) {

                return $cmp;

            }

            $ta = self::marcaTemporal($a);

            $tb = self::marcaTemporal($b);

            if ($ta !== $tb) {

                return $tb <=> $ta;

            }

            return ((int) ($b['_seq'] ?? 0)) <=> ((int) ($a['_seq'] ?? 0));

        });

        foreach ($items as &$item) {

            unset($item['_seq'], $item['ts_juego'], $item['cotilleo_meta']);

        }

        unset($item);



        return array_values($items);

    }



    /**

     * @param array<string, mixed> $e

     */

    private static function marcaTemporal(array $e): int

    {

        $ts = is_array($e['ts_juego'] ?? null) ? $e['ts_juego'] : [];

        $dia = (int) ($ts['dia'] ?? $e['dia'] ?? 0);

        $hora = (int) ($ts['hora'] ?? 0);



        return $dia * 24 + $hora;

    }



    /**

     * @param array<string, mixed> $partida

     * @return list<array<string, mixed>>

     */

    private static function entradasDesdeBuzon(array $partida, int &$seq): array

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

            $out[] = self::filaPublica($partida, $m, $dia, $ts, $seq++);

        }

        return $out;

    }



    /**

     * @param array<string, mixed> $m

     * @param array<string, mixed> $ts

     * @return array<string, mixed>

     */

    private static function filaPublica(array $partida, array $m, int $dia, array $ts, int $seq): array

    {

        $cat = CotilleoCategoria::de($m);

        $tipo = (string) ($m['tipo'] ?? 'cotilleo');

        $texto = trim((string) ($m['texto'] ?? ''));

        if ($tipo === 'senal_romantica') {

            $texto = CopySenalRomantica::textoDeMensaje($partida, $m);

        }

        $row = [

            'id' => (string) ($m['id'] ?? ''),

            'dia' => $dia,

            'texto' => $texto,

            'tipo' => $tipo,

            'fecha_corta' => (string) ($m['fecha_corta'] ?? ''),

            'origen' => 'buzon_cotilleo',

            'actores' => self::actoresDe($m),

            'nuevo' => !((bool) ($m['leido'] ?? false)) && (($m['estado'] ?? 'pendiente') !== 'leido'),

            'categoria' => $cat['id'],

            'categoria_etiqueta' => $cat['etiqueta'],

            'categoria_icono' => $cat['icono'],

            'destacado' => $cat['destacado'],

            '_seq' => $seq,

        ];

        if ($ts !== []) {

            $row['ts_juego'] = $ts;

        }

        return $row;

    }



    /**

     * @param array<string, mixed> $e

     * @return array<string, mixed>

     */

    private static function normalizarDiario(array $e): array

    {

        $cat = CotilleoCategoria::de($e);

        return [

            'id' => (string) ($e['id'] ?? ''),

            'dia' => (int) ($e['dia'] ?? 0),

            'texto' => (string) ($e['texto'] ?? ''),

            'tipo' => (string) ($e['tipo'] ?? 'diario'),

            'fecha_corta' => (string) ($e['fecha_corta'] ?? ''),

            'origen' => 'diario',

            'actores' => self::actoresDe($e),

            'nuevo' => false,

            'categoria' => $cat['id'],

            'categoria_etiqueta' => $cat['etiqueta'],

            'categoria_icono' => $cat['icono'],

            'destacado' => $cat['destacado'],

        ];

    }



    /**

     * @param array<string, mixed> $m

     * @return list<string>

     */

    private static function actoresDe(array $m): array

    {

        $raw = $m['actores'] ?? [];

        if (!is_array($raw)) {

            return [];

        }

        $out = [];

        foreach ($raw as $id) {

            if (is_string($id) && $id !== '') {

                $out[] = $id;

            }

        }

        return array_values(array_unique($out));

    }

}


