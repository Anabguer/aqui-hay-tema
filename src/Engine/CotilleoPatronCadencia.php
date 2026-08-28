<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Cadencia de cotilleos de patrón par+lugar.
 * Un patrón no debe repetirse cada día sin novedad real.
 */
final class CotilleoPatronCadencia
{
    public const MAX_POR_DIA = 1;
    public const COOLDOWN_DIAS = 3;
    public const SALTO_DIAS_NIVEL = 3;

    /**
     * @param list<string> $ids
     */
    public static function debePublicar(array $partida, array $ids, string $lugar, int $dia, array $cal = []): bool
    {
        $ids = array_values(array_unique($ids));
        sort($ids);
        if (count($ids) < 2 || $lugar === '') {
            return false;
        }
        if (!CotilleoNarrativo::patronParLugar($partida, $ids, $lugar, $dia, $cal)) {
            return false;
        }
        if (self::yaPublicadoHoy($partida, $ids, $lugar, $dia)) {
            return false;
        }
        if (self::patronesPublicadosHoy($partida, $dia) >= self::maxPorDia($cal)) {
            return false;
        }

        $clave = CotilleoNarrativo::clavePar($ids, $lugar);
        $ultima = self::ultimaPublicacion($partida, $clave);
        if ($ultima === null) {
            return true;
        }

        return self::hayEvolucion($partida, $ids, $lugar, $dia, $ultima, $cal);
    }

    /**
     * @param list<string> $ids
     * @return array{dia: int, nivel: int, se_conocen: bool, familia: string, romance: bool}|null
     */
    public static function ultimaPublicacion(array $partida, string $clave): ?array
    {
        $ultima = null;
        foreach ($partida['buzon'] ?? [] as $msg) {
            if (!is_array($msg) || (string) ($msg['tipo'] ?? '') !== 'cotilleo_patron') {
                continue;
            }
            if ((string) ($msg['patron_clave'] ?? '') !== $clave) {
                continue;
            }
            $d = (int) ($msg['dia'] ?? 0);
            $estado = is_array($msg['patron_estado'] ?? null) ? $msg['patron_estado'] : [];
            $row = [
                'dia' => $d,
                'nivel' => (int) ($msg['patron_nivel'] ?? 0),
                'se_conocen' => (bool) ($estado['se_conocen'] ?? false),
                'familia' => (string) ($estado['familia'] ?? 'desconocidos'),
                'romance' => (bool) ($estado['romance'] ?? false),
            ];
            if ($ultima === null || $d > $ultima['dia']) {
                $ultima = $row;
            }
        }
        return $ultima;
    }

    /**
     * @param list<string> $ids
     * @return array{se_conocen: bool, familia: string, romance: bool}
     */
    public static function estadoRelacional(array $partida, array $ids): array
    {
        if (count($ids) < 2) {
            return ['se_conocen' => false, 'familia' => 'desconocidos', 'romance' => false];
        }
        $a = $ids[0];
        $b = $ids[1];
        $romance = false;
        $ra = (int) (RelacionEngine::romanceHacia($partida, $a, $b) ?? 0);
        $rb = (int) (RelacionEngine::romanceHacia($partida, $b, $a) ?? 0);
        $romance = max($ra, $rb) >= 18;

        return [
            'se_conocen' => RelacionEngine::seConocen($partida, $a, $b),
            'familia' => RelacionBitacora::familiaCopy($partida, $a, $b),
            'romance' => $romance,
        ];
    }

    /**
     * @param list<string> $ids
     * @return array{
     *   patron_nivel: int,
     *   patron_estado: array{se_conocen: bool, familia: string, romance: bool},
     *   interes_narrativo: array{interes: bool, score: int, razon: string, familia: string, se_conocen: bool, romance: bool, hito_reciente: bool, emocion_reciente: bool}
     * }
     */
    public static function metaPublicacion(array $partida, array $ids, string $lugar, int $dia, array $cal = []): array
    {
        return [
            'patron_nivel' => CotilleoNarrativo::diasPatronParLugar($partida, $ids, $lugar, $dia, $cal),
            'patron_estado' => self::estadoRelacional($partida, $ids),
            'interes_narrativo' => CotilleoNarrativo::esInteresNarrativo($partida, $ids),
        ];
    }

    public static function patronesPublicadosHoy(array $partida, int $dia): int
    {
        $n = 0;
        foreach ($partida['buzon'] ?? [] as $msg) {
            if (!is_array($msg) || (string) ($msg['tipo'] ?? '') !== 'cotilleo_patron') {
                continue;
            }
            if ((int) ($msg['dia'] ?? 0) === $dia) {
                $n++;
            }
        }
        return $n;
    }

    private static function maxPorDia(array $cal): int
    {
        $n = (int) CalibracionConfig::get($cal, 'coincidencias.cotilleo_max_patrones_dia', self::MAX_POR_DIA);
        return max(0, $n);
    }

    /**
     * @param list<string> $ids
     */
    private static function yaPublicadoHoy(array $partida, array $ids, string $lugar, int $dia): bool
    {
        $clave = CotilleoNarrativo::clavePar($ids, $lugar);
        foreach ($partida['buzon'] ?? [] as $msg) {
            if (!is_array($msg) || (string) ($msg['tipo'] ?? '') !== 'cotilleo_patron') {
                continue;
            }
            if ((int) ($msg['dia'] ?? 0) !== $dia) {
                continue;
            }
            if ((string) ($msg['patron_clave'] ?? '') === $clave) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<string> $ids
     * @param array{dia: int, nivel: int, se_conocen: bool, familia: string, romance: bool} $ultima
     */
    private static function hayEvolucion(
        array $partida,
        array $ids,
        string $lugar,
        int $dia,
        array $ultima,
        array $cal
    ): bool {
        if ($dia - $ultima['dia'] < self::cooldownDias($cal)) {
            return false;
        }

        $nivelActual = CotilleoNarrativo::diasPatronParLugar($partida, $ids, $lugar, $dia, $cal);
        if ($nivelActual >= $ultima['nivel'] + self::saltoDiasNivel($cal)) {
            return true;
        }

        $estado = self::estadoRelacional($partida, $ids);
        if ($estado['se_conocen'] && !$ultima['se_conocen']) {
            return true;
        }
        if ($estado['familia'] !== $ultima['familia']) {
            return true;
        }
        if ($estado['romance'] && !$ultima['romance']) {
            return true;
        }

        if (count($ids) >= 2 && self::hitoNuevoEntre($partida, $ids[0], $ids[1], $ultima['dia'])) {
            return true;
        }

        $interes = CotilleoNarrativo::esInteresNarrativo($partida, $ids);
        if ($interes['hito_reciente'] || $interes['emocion_reciente']) {
            return true;
        }

        return false;
    }

    private static function hitoNuevoEntre(array $partida, string $a, string $b, int $desdeDia): bool
    {
        foreach (RelacionBitacora::entre($partida, $a, $b) as $h) {
            if (!is_array($h)) {
                continue;
            }
            $fd = (int) (($h['fecha']['dia'] ?? 0));
            if ($fd > $desdeDia) {
                return true;
            }
        }
        return false;
    }

    private static function cooldownDias(array $cal): int
    {
        return max(1, (int) CalibracionConfig::get($cal, 'coincidencias.cotilleo_patron_cooldown_dias', self::COOLDOWN_DIAS));
    }

    private static function saltoDiasNivel(array $cal): int
    {
        return max(1, (int) CalibracionConfig::get($cal, 'coincidencias.cotilleo_patron_salto_dias', self::SALTO_DIAS_NIVEL));
    }
}
