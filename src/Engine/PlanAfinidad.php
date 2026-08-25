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
        ?Catalog $catalog = null
    ): array {
        $perfil = PerfilPartida::deOLegacy($partida, $residenteId, $catalog);
        $hobbies = is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : [];
        $relacionados = [];
        if (is_string($lugarId) && $lugarId !== '' && $catalog !== null) {
            $store = $catalog->store();
            foreach ($hobbies as $h) {
                $item = $store->hobby((string) $h);
                if (!is_array($item)) {
                    continue;
                }
                $lugs = is_array($item['lugar_ids'] ?? null) ? $item['lugar_ids'] : [];
                if (in_array($lugarId, $lugs, true)) {
                    $relacionados[] = (string) $h;
                }
            }
        }
        $rechazos = [];
        $prefs = is_array($perfil['preferencias'] ?? null) ? $perfil['preferencias'] : [];
        foreach (['hobbies_neg'] as $k) {
            foreach ($prefs[$k] ?? [] as $h) {
                if (is_string($h) && $h !== '') {
                    $rechazos[] = $h;
                }
            }
        }
        $penaliza = false;
        if (is_string($lugarId) && $lugarId !== '' && $catalog !== null) {
            $hLugar = LugarAutonomo::hobbiesDeLugar($catalog, $lugarId);
            foreach ($rechazos as $h) {
                if (in_array($h, $hLugar, true)) {
                    $penaliza = true;
                    break;
                }
            }
        }
        $cal = $catalog !== null ? CalibracionConfig::load($catalog->getRoot()) : [];
        $bonus = (int) CalibracionConfig::get($cal, 'plan_afinidad.bonus_hobby', 8);
        $pen = (int) CalibracionConfig::get($cal, 'plan_afinidad.penalizacion_rechazo', 12);
        $aporte = $relacionados !== [] ? $bonus : 0;
        $penalizacion = $penaliza ? $pen : 0;
        return [
            'residente_id' => $residenteId,
            'lugar' => $lugarId,
            'hobbies_relacionados' => $relacionados,
            'relacionado' => $relacionados !== [],
            'plan_lugar_match' => $relacionados !== [],
            'aporte' => $aporte,
            'penalizacion' => $penalizacion,
            'penalizacion_si_ajeno' => 0,
            'rechazo_explicito' => $penaliza,
            'rechazo_no_es_veto' => true,
        ];
    }
}
