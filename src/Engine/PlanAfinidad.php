<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Contrato lugar/actividad ↔ hobbies. Sin economía ni copy.
 * Penalizar a quien no comparte el plan: BLOQUEADO_DECISION.
 */
final class PlanAfinidad
{
    /**
     * @return array<string, mixed>
     */
    public static function paraParticipante(
        array $partida,
        string $residenteId,
        ?string $lugarId,
        Catalog $catalog
    ): array {
        $perfil = PerfilPartida::deOLegacy($partida, $residenteId, $catalog);
        $hobbies = is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : [];
        $relacionados = [];
        if (is_string($lugarId) && $lugarId !== '') {
            $store = $catalog->store();
            foreach ($hobbies as $h) {
                $item = $store->hobby((string) $h);
                $lugs = is_array($item['lugar_ids'] ?? null) ? $item['lugar_ids'] : [];
                if (in_array($lugarId, $lugs, true)) {
                    $relacionados[] = (string) $h;
                }
            }
        }
        return [
            'residente_id' => $residenteId,
            'lugar' => $lugarId,
            'hobbies_relacionados' => $relacionados,
            'relacionado' => $relacionados !== [],
            'aporte' => null,
            'penalizacion_si_ajeno' => null,
            '_bloqueado_decision' => ['aporte_si_relacionado', 'penalizacion_si_ajeno'],
        ];
    }
}
