<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Instrumentación diagnóstica temporal.
 * Registra trazas en data/logs/trace_creation.log para depurar 3→5 residentes.
 * RETIRAR una vez obtengamos causa raíz.
 */
final class DiagnosticTrace
{
    private static ?string $partidaId = null;
    private static ?string $configId = null;
    private static int $seq = 0;

    public static function setPartida(string $partidaId, string $configId = ''): void
    {
        self::$partidaId = $partidaId;
        self::$configId = $configId;
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function log(string $punto, array $partida, string $nota = ''): void
    {
        self::$seq++;
        $pid = self::$partidaId ?? ($partida['meta']['partida_id'] ?? '?');
        $cid = self::$configId ?? ($partida['meta']['config_id'] ?? '?');
        $dia = $partida['reloj']['dia_pueblo'] ?? '?';
        $hora = $partida['reloj']['hora_actual'] ?? '?';
        $nRes = count(CapacidadViviendas::residentesActivos($partida));
        $nResTotal = count($partida['residentes'] ?? []);
        $ids = array_keys($partida['residentes'] ?? []);
        $ts = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u');

        $line = sprintf(
            "[%s] #%03d | %-35s | pid=%s config=%s D%sH%s | activos=%d total=%d | ids=[%s]%s\n",
            $ts,
            self::$seq,
            $punto,
            substr($pid, 0, 12),
            $cid,
            $dia,
            $hora,
            $nRes,
            $nResTotal,
            implode(',', array_map(fn($id) => substr($id, 0, 10), $ids)),
            $nota !== '' ? ' | ' . $nota : ''
        );

        self::write($line);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function logRaw(string $punto, array $extra = []): void
    {
        self::$seq++;
        $ts = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u');
        $extraStr = $extra !== '' ? ' | ' . http_build_query($extra) : '';

        $line = sprintf(
            "[%s] #%03d | %-35s%s\n",
            $ts,
            self::$seq,
            $punto,
            $extraStr
        );

        self::write($line);
    }

    private static function write(string $line): void
    {
        $dir = dirname(__DIR__, 2) . '/data/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/trace_creation.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function clear(): void
    {
        $path = dirname(__DIR__, 2) . '/data/logs/trace_creation.log';
        if (is_file($path)) {
            @unlink($path);
        }
        self::$seq = 0;
    }
}
