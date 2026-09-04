<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Sincroniza estados de encuentro con el reloj actual de partida. */
final class EncuentroLifecycle
{
    public static function sincronizarConReloj(array &$partida, ?GameLogger $logger = null, ?Catalog $catalog = null): array
    {
        $partida['encuentros'] ??= [];
        if ($catalog !== null) {
            $calEvt = CalibracionConfig::load($catalog->getRoot());
            EventosPuebloEngine::resolverAsistentesPendientesConReloj($partida, $calEvt, $catalog, $logger);
        }
        $dia = (int) $partida['reloj']['dia_pueblo'];
        $hora = (int) $partida['reloj']['hora_actual'];
        $now = $dia * 24 + $hora;
        $resueltos = [];

        foreach ($partida['encuentros'] as &$enc) {
            $estado = $enc['estado'] ?? '';
            if (!in_array($estado, ['programado', 'en_curso'], true)) {
                continue;
            }

            $start = (int) ($enc['dia'] ?? 0) * 24 + (int) ($enc['hora'] ?? ($enc['hora_inicio'] ?? 0));
            $durH = LugarAtributos::horasDeEncuentro($enc);
            $end = $start + $durH;

            if ($estado === 'programado' && $now >= $start && $now < $end) {
                if (EncuentroEngine::transicionValida('programado', 'en_curso')) {
                    $enc['estado'] = 'en_curso';
                    DomainEventDispatcher::emit($partida, DomainEvents::ENCUENTRO_INICIADO, [
                        'encuentro' => $enc,
                        'actores' => $enc['participantes'] ?? [],
                    ], $logger, 'EncuentroLifecycle::iniciado', $enc['participantes'] ?? []);
                    \aht_log_optional($logger, $partida, 'encuentro_en_curso', ['encuentro_id' => $enc['id']]);
                }
            }

            if ($now >= $end && in_array($enc['estado'] ?? '', ['programado', 'en_curso'], true)) {
                $enc['estado'] = 'terminado';
                $resultado = EncuentroResolver::resolver($partida, $enc, $logger, $catalog);
                EncuentroResolver::aplicarResultado($partida, $enc, $resultado, $logger, $catalog);
                $calVp = $catalog !== null ? CalibracionConfig::load($catalog->getRoot()) : [];
                $vpR = VidaPuebloEngine::aplicarEncuentroOrganizado($partida, $enc, $resultado, $calVp, $logger);
                if ($vpR !== null) {
                    $enc['vida_pueblo_aplicada'] = true;
                }
                $enc['resultado'] = $resultado;
                DomainBootstrap::boot();
                DomainEventDispatcher::emit($partida, DomainEvents::ENCUENTRO_TERMINADO, [
                    'encuentro' => $enc,
                    'resultado' => $resultado,
                    'actores' => $enc['participantes'] ?? [],
                ], $logger, 'EncuentroLifecycle::terminado', $enc['participantes'] ?? []);
                $resueltos[] = $enc;
                \aht_log_optional($logger, $partida, 'encuentro_terminado', [
                    'encuentro_id' => $enc['id'],
                    '_placeholder_resultado' => true,
                ]);
            }
        }
        unset($enc);

        return ['resueltos' => count($resueltos), 'encuentros' => $resueltos];
    }
}

