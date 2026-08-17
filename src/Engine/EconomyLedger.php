<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Ledger economía — dinero y fama son recursos distintos. Sin cifras balanceadas. */
final class EconomyLedger
{
    public static function registrar(array &$partida, string $recurso, float $delta, string $motivo, array $meta = []): array
    {
        if (!in_array($recurso, ['dinero', 'fama'], true)) {
            return ['ok' => false, 'error' => 'recurso_invalido'];
        }

        $partida['economia'] ??= [
            'dinero' => ['balance' => null, 'historial' => []],
            'fama' => ['balance' => null, 'historial' => []],
            '_placeholder' => true,
        ];

        $actual = $partida['economia'][$recurso]['balance'];
        $nuevo = $actual === null ? $delta : (float) $actual + $delta;
        $partida['economia'][$recurso]['balance'] = $nuevo;

        $tx = [
            'id' => 'tx_' . bin2hex(random_bytes(3)),
            'recurso' => $recurso,
            'delta' => $delta,
            'balance_post' => $nuevo,
            'motivo' => $motivo,
            'dia' => $partida['reloj']['dia_pueblo'] ?? null,
            'meta' => $meta,
            '_placeholder' => true,
        ];
        $partida['economia'][$recurso]['historial'][] = $tx;

        return ['ok' => true, 'transaccion' => $tx];
    }
}
