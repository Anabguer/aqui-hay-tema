<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Capa de consulta unificada: "¿Qué pasó entre A y B?"
 *
 * NO crea nueva persistencia. Lee de:
 * - bitacora_relaciones (hitos: se conocieron, primera cita, etc.)
 * - memoria_eventos (encuentros recientes, descubrimientos compartidos)
 * - encuentros (resultado de cada encuentro entre el par)
 * - historial_relaciones (log técnico detallado)
 */
final class HistorialPar
{
    public static function entre(array $partida, string $a, string $b): array
    {
        $ids = [$a, $b];
        sort($ids);
        $clave = $ids[0] . ':' . $ids[1];

        return [
            'clave' => $clave,
            'hitos' => self::hitos($partida, $a, $b),
            'encuentros' => self::encuentros($partida, $a, $b),
            'resumen' => self::resumen($partida, $a, $b),
        ];
    }

    /**
     * Hitos de la relación ordenados cronológicamente.
     *
     * @return list<array{tipo: string, dia: int, meta: array}>
     */
    public static function hitos(array $partida, string $a, string $b): array
    {
        $bitacora = $partida['bitacora_relaciones'] ?? [];
        $resultado = [];
        foreach ($bitacora as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $partes = array_values($entry['participantes'] ?? []);
            sort($partes);
            if ($partes !== [$a, $b] && $partes !== [$b, $a]) {
                continue;
            }
            $resultado[] = [
                'tipo' => (string) ($entry['tipo'] ?? ''),
                'dia' => (int) ($entry['fecha']['dia'] ?? 0),
                'meta' => $entry['meta'] ?? [],
            ];
        }
        usort($resultado, static fn(array $x, array $y) => $x['dia'] <=> $y['dia']);
        return $resultado;
    }

    /**
     * Encuentros entre el par ordenados cronológicamente.
     *
     * @return list<array{id: string, tipo: string, dia: int, resultado_a: string, resultado_b: string, carga_a: float, carga_b: float}>
     */
    public static function encuentros(array $partida, string $a, string $b): array
    {
        $resultado = [];
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc)) {
                continue;
            }
            $parts = array_values($enc['participantes'] ?? []);
            if (!in_array($a, $parts, true) || !in_array($b, $parts, true)) {
                continue;
            }
            if (($enc['estado'] ?? '') !== 'terminado') {
                continue;
            }
            $res = $enc['resultado'] ?? [];
            $por = $res['por_participante'] ?? [];
            $resultado[] = [
                'id' => (string) ($enc['id'] ?? ''),
                'tipo' => (string) ($enc['tipo'] ?? ''),
                'dia' => (int) ($enc['dia'] ?? 0),
                'resultado_a' => (string) ($por[$a]['resultado'] ?? ''),
                'resultado_b' => (string) ($por[$b]['resultado'] ?? ''),
                'carga_a' => (float) ($por[$a]['carga'] ?? 0.0),
                'carga_b' => (float) ($por[$b]['carga'] ?? 0.0),
            ];
        }
        usort($resultado, static fn(array $x, array $y) => $x['dia'] <=> $y['dia']);
        return $resultado;
    }

    /**
     * Resumen legible de la historia entre A y B.
     */
    public static function resumen(array $partida, string $a, string $b): array
    {
        $hitos = self::hitos($partida, $a, $b);
        $encs = self::encuentros($partida, $a, $b);

        $totalEncuentros = count($encs);
        $bien = 0;
        $mal = 0;
        foreach ($encs as $e) {
            $positivos = ['bien', 'muy_bien'];
            if (in_array($e['resultado_a'], $positivos, true)) {
                $bien++;
            }
            if (in_array($e['resultado_b'], $positivos, true)) {
                $bien++;
            }
            $negativos = ['mal', 'muy_mal'];
            if (in_array($e['resultado_a'], $negativos, true)) {
                $mal++;
            }
            if (in_array($e['resultado_b'], $negativos, true)) {
                $mal++;
            }
        }

        $seConocen = false;
        $esPareja = false;
        $haHabidoCita = false;
        foreach ($hitos as $h) {
            if ($h['tipo'] === RelacionBitacora::SE_CONOCIERON) {
                $seConocen = true;
            }
            if (in_array($h['tipo'], [RelacionBitacora::INICIO_PAREJA, RelacionBitacora::DECLARACION], true)) {
                $esPareja = true;
            }
            if ($h['tipo'] === RelacionBitacora::PRIMERA_CITA) {
                $haHabidoCita = true;
            }
        }

        return [
            'se_conocen' => $seConocen,
            'es_pareja' => $esPareja,
            'ha_habido_cita' => $haHabidoCita,
            'total_encuentros' => $totalEncuentros,
            'experiencias_positivas' => $bien,
            'experiencias_negativas' => $mal,
            'ultima_tendencia' => self::tendencia($encs),
        ];
    }

    /**
     * Frase concisa sobre la historia entre A y B, para uso en copy narrativo.
     * NO inventa nada: solo describe lo que realmente ocurrió.
     *
     * @return string Frase en español (sin puntuación final).
     */
    public static function contextoNarrativo(array $partida, string $a, string $b): string
    {
        $res = self::resumen($partida, $a, $b);
        $hitos = self::hitos($partida, $a, $b);

        if (!$res['se_conocen']) {
            return 'aún no nos conocemos';
        }
        if ($res['es_pareja']) {
            return 'somos pareja';
        }
        if ($res['ha_habido_cita']) {
            return 'hemos salido juntos';
        }
        if ($res['total_encuentros'] > 0) {
            $plural = $res['total_encuentros'] > 1 ? 'nos hemos visto' : 'nos hemos visto';
            $cuantos = $res['total_encuentros'] > 1
                ? $res['total_encuentros'] . ' veces'
                : 'una vez';
            return $plural . ' ' . $cuantos;
        }
        if (count($hitos) > 0) {
            $ultimo = end($hitos);
            $tipo = (string) ($ultimo['tipo'] ?? '');
            if ($tipo === RelacionBitacora::SE_CONOCIERON) {
                return 'nos conocimos hace poco';
            }
            if ($tipo === RelacionBitacora::PRIMERA_CITA) {
                return 'tuvimos nuestra primera cita';
            }
        }
        return 'nos conocemos';
    }

    /**
     * Tendencia de los últimos encuentros: 'positiva', 'negativa', 'estable', o ''.
     */
    private static function tendencia(array $encs): string
    {
        $ultimos = array_slice($encs, -3);
        if ($ultimos === []) {
            return '';
        }
        $suma = 0;
        foreach ($ultimos as $e) {
            $suma += $e['carga_a'] + $e['carga_b'];
        }
        $promedio = $suma / max(1, count($ultimos) * 2);
        if ($promedio > 0.1) {
            return 'positiva';
        }
        if ($promedio < -0.1) {
            return 'negativa';
        }
        return 'estable';
    }
}
