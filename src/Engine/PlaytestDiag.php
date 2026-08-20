<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Registro técnico del playtest (solo playtest_01 / guía activa).
 * Causas reales del motor; sin copy inventado.
 */
final class PlaytestDiag
{
    private const MAX = 250;

    public static function activa(array $partida): bool
    {
        return PlaytestGuia::activa($partida);
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $datos
     */
    public static function push(array &$partida, string $tipo, array $datos = []): void
    {
        if (!self::activa($partida)) {
            return;
        }
        $reloj = is_array($partida['reloj'] ?? null) ? $partida['reloj'] : [];
        $entry = [
            'ts' => date('H:i:s'),
            'iso' => date('c'),
            'tipo' => $tipo,
            'dia' => (int) ($reloj['dia_pueblo'] ?? 0),
            'hora' => (int) ($reloj['hora_actual'] ?? $reloj['hora_pueblo'] ?? 0),
            'datos' => $datos,
        ];
        $log = is_array($partida['playtest_diag']['log'] ?? null) ? $partida['playtest_diag']['log'] : [];
        $log[] = $entry;
        if (count($log) > self::MAX) {
            $log = array_slice($log, -self::MAX);
        }
        $partida['playtest_diag'] = [
            'log' => $log,
            'actualizado_iso' => $entry['iso'],
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @return array{activo:bool,lineas:list<string>,entradas:list<array>,texto:string}
     */
    public static function vista(array $partida): array
    {
        if (!self::activa($partida)) {
            return ['activo' => false, 'lineas' => [], 'entradas' => [], 'texto' => ''];
        }
        $log = is_array($partida['playtest_diag']['log'] ?? null) ? $partida['playtest_diag']['log'] : [];
        $lineas = [];
        foreach ($log as $e) {
            if (!is_array($e)) {
                continue;
            }
            $lineas[] = self::formatearEntrada($partida, $e);
        }
        return [
            'activo' => true,
            'lineas' => $lineas,
            'entradas' => $log,
            'texto' => implode("\n\n", $lineas),
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $e
     */
    private static function formatearEntrada(array $partida, array $e): string
    {
        $ts = (string) ($e['ts'] ?? '');
        $tipo = strtoupper((string) ($e['tipo'] ?? 'EVENTO'));
        $dia = (int) ($e['dia'] ?? 0);
        $hora = (int) ($e['hora'] ?? 0);
        $d = is_array($e['datos'] ?? null) ? $e['datos'] : [];
        $out = sprintf('%s | %s (día %d · %02d:00)', $ts, $tipo, $dia, $hora);

        if ($tipo === 'PLAN_PROPUESTO' || $tipo === 'PLAN') {
            $out .= "\n" . self::lineaPlan($partida, $d);
            $resultado = (string) ($d['resultado'] ?? '');
            if ($resultado !== '') {
                $out .= "\nRESULTADO: " . $resultado;
            }
            if (!empty($d['motivo_motor'])) {
                $out .= "\nMOTIVO MOTOR:\n" . self::indent((string) $d['motivo_motor']);
            }
            if (!empty($d['factores']) && is_array($d['factores'])) {
                $out .= "\nFACTORES:";
                foreach ($d['factores'] as $f) {
                    $out .= "\n- " . (is_string($f) ? $f : json_encode($f, JSON_UNESCAPED_UNICODE));
                }
            }
            if (!empty($d['reacciones']) && is_array($d['reacciones'])) {
                $out .= "\nREACCIONES:";
                foreach ($d['reacciones'] as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $out .= sprintf(
                        "\n- %s: decision=%s clase=%s motivo=%s score=%s p=%s",
                        (string) ($r['nombre'] ?? $r['residente_id'] ?? '?'),
                        (string) ($r['decision'] ?? ''),
                        (string) ($r['clase'] ?? ''),
                        (string) ($r['motivo_tecnico'] ?? ''),
                        json_encode($r['score'] ?? null),
                        json_encode($r['p'] ?? null)
                    );
                    if (!empty($r['factores']) && is_array($r['factores'])) {
                        foreach ($r['factores'] as $fk => $fv) {
                            $out .= "\n    " . $fk . ': ' . (is_scalar($fv) ? (string) $fv : json_encode($fv, JSON_UNESCAPED_UNICODE));
                        }
                    }
                }
            }
            if (!empty($d['error_api'])) {
                $out .= "\nERROR: " . json_encode($d['error_api'], JSON_UNESCAPED_UNICODE);
            }
            return $out;
        }

        if ($tipo === 'API_ERROR') {
            $out .= sprintf(
                "\n%s %s → HTTP %s\nreq: %s\nresp: %s\ncausa: %s",
                (string) ($d['method'] ?? ''),
                (string) ($d['action'] ?? ''),
                (string) ($d['status'] ?? ''),
                json_encode($d['payload'] ?? null, JSON_UNESCAPED_UNICODE),
                self::cortar((string) ($d['respuesta'] ?? ''), 500),
                (string) ($d['causa'] ?? '')
            );
            return $out;
        }

        if ($d !== []) {
            $out .= "\n" . json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $d
     */
    private static function lineaPlan(array $partida, array $d): string
    {
        $a = (string) ($d['residente_a'] ?? '');
        $b = (string) ($d['residente_b'] ?? '');
        $na = $a !== '' ? IdentidadPublica::nombre($partida, $a) : '?';
        $nb = $b !== '' ? IdentidadPublica::nombre($partida, $b) : '?';
        return sprintf(
            "%s → %s\ntipo: %s\nlugar: %s\nhora: %02d:00 (día %s)",
            $na,
            $nb,
            (string) ($d['tipo_encuentro'] ?? $d['tipo'] ?? ''),
            (string) ($d['lugar'] ?? ''),
            (int) ($d['hora_plan'] ?? 0),
            (string) ($d['dia_plan'] ?? '')
        );
    }

    private static function indent(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '(sin detalle)';
        }
        return preg_replace('/^/m', '  ', $s) ?? $s;
    }

    private static function cortar(string $s, int $n): string
    {
        if (strlen($s) <= $n) {
            return $s;
        }
        return substr($s, 0, $n) . '…';
    }
}
