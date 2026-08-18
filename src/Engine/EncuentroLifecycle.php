<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Sincroniza estados de encuentro con el reloj actual de partida. */
final class EncuentroLifecycle
{
    public static function sincronizarConReloj(array &$partida, ?GameLogger $logger = null): array
    {
        $partida['encuentros'] ??= [];
        $dia = (int) $partida['reloj']['dia_pueblo'];
        $hora = (int) $partida['reloj']['hora_actual'];
        $now = $dia * 24 + $hora;
        $resueltos = [];

        foreach ($partida['encuentros'] as &$enc) {
            $estado = $enc['estado'] ?? '';
            if (!in_array($estado, ['programado', 'en_curso'], true)) {
                continue;
            }

            $start = (int) ($enc['dia'] ?? 0) * 24 + (int) ($enc['hora'] ?? 0);
            $end = $start + 1;

            if ($estado === 'programado' && $now >= $start && $now < $end) {
                if (EncuentroEngine::transicionValida('programado', 'en_curso')) {
                    $enc['estado'] = 'en_curso';
                    DomainEventDispatcher::emit($partida, DomainEvents::ENCUENTRO_INICIADO, [
                        'encuentro' => $enc,
                        'actores' => $enc['participantes'] ?? [],
                    ], $logger, 'EncuentroLifecycle::iniciado', $enc['participantes'] ?? []);
                    $logger?->log($partida, 'encuentro_en_curso', ['encuentro_id' => $enc['id']]);
                }
            }

            if ($now >= $end && in_array($enc['estado'] ?? '', ['programado', 'en_curso'], true)) {
                $enc['estado'] = 'terminado';
                $resultado = EncuentroResolver::resolver($partida, $enc, $logger);
                EncuentroResolver::aplicarResultado($partida, $enc, $resultado, $logger);
                $enc['resultado'] = $resultado;
                DomainBootstrap::boot();
                DomainEventDispatcher::emit($partida, DomainEvents::ENCUENTRO_TERMINADO, [
                    'encuentro' => $enc,
                    'resultado' => $resultado,
                    'actores' => $enc['participantes'] ?? [],
                ], $logger, 'EncuentroLifecycle::terminado', $enc['participantes'] ?? []);
                $resueltos[] = $enc;
                $logger?->log($partida, 'encuentro_terminado', [
                    'encuentro_id' => $enc['id'],
                    '_placeholder_resultado' => true,
                ]);
            }
        }
        unset($enc);

        return ['resueltos' => count($resueltos), 'encuentros' => $resueltos];
    }
}
