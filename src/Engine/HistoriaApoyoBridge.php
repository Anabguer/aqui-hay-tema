<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente: apoyo entre vecinos → Historia del Pueblo.
 * Registra hito_26 ("NO TODO IBA A SER COTILLEO") la primera vez que
 * se registra un APOYO_IMPORTANTE en la bitácora relacional.
 */
final class HistoriaApoyoBridge
{
    public static function handle(array &$partida, string $tipoBitacora, array $participantes): ?array
    {
        if ($tipoBitacora !== RelacionBitacora::APOYO_IMPORTANTE) {
            return null;
        }
        if (count($participantes) < 2) {
            return null;
        }

        return HistoriaPuebloEngine::registrar(
            $partida,
            'hito_26',
            array_slice($participantes, 0, 2),
            ['origen' => 'bitacora_relaciones', 'tipo_bitacora' => $tipoBitacora]
        );
    }
}
