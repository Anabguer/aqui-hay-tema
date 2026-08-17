<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class RelojDev
{
    /** Salto a fecha/hora. Sin rewind salvo snapshot restore. */
    public static function irA(array &$partida, int $dia, int $hora, bool $permitirRewind = false, ?GameLogger $logger = null): array
    {
        $hora = max(0, min(23, $hora));
        $dia = max(1, $dia);

        $actual = (int) $partida['reloj']['dia_pueblo'] * 24 + (int) $partida['reloj']['hora_actual'];
        $objetivo = $dia * 24 + $hora;

        if ($objetivo < $actual && !$permitirRewind) {
            return GameError::respuesta(GameError::RELOJ_NO_REWIND, [
                'actual' => ['dia' => $partida['reloj']['dia_pueblo'], 'hora' => $partida['reloj']['hora_actual']],
                'objetivo' => ['dia' => $dia, 'hora' => $hora],
            ]);
        }

        $antes = $partida['reloj'];
        if ($objetivo > $actual) {
            Reloj::avanzarHoras($partida, $objetivo - $actual);
        } elseif ($permitirRewind) {
            $partida['reloj']['dia_pueblo'] = $dia;
            $partida['reloj']['hora_actual'] = $hora;
        }

        AuditTrail::record($partida, 'reloj_dev_ir_a', [], 'RelojDev', 'irA', $antes, $partida['reloj']);
        $sync = EncuentroLifecycle::sincronizarConReloj($partida, $logger);

        return [
            'ok' => true,
            'reloj' => $partida['reloj'],
            'texto' => Reloj::formatear($partida['reloj']),
            'encuentros_resueltos' => $sync['resueltos'],
        ];
    }
}
