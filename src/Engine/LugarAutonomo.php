<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Elige lugar sin usar secretos no descubiertos.
 * Con poco conocimiento puede meter la pata (elige lo que le gusta a él).
 */
final class LugarAutonomo
{
    /**
     * @param array<string, mixed> $cal
     * @param list<string> $operativos
     */
    public static function elegir(
        array $partida,
        string $quien,
        ?string $otro,
        array $operativos,
        RngService $rng,
        ?Catalog $catalog = null,
        array $cal = []
    ): ?string {
        $cands = [];
        foreach ($operativos as $lug) {
            $lug = (string) $lug;
            if ($lug === '' || $lug === 'lug_casa') {
                continue;
            }
            $w = 1.0;
            $afinYo = PlanAfinidad::paraParticipante($partida, $quien, $lug, $catalog);
            if (!empty($afinYo['relacionado'])) {
                $w += 3.0;
            }
            if ((int) ($afinYo['penalizacion'] ?? 0) > 0) {
                $w *= 0.35;
            }
            if ($otro !== null && $otro !== '') {
                $hobbiesSabidos = ConocimientoNpc::hobbiesConocidos($partida, $quien, $otro);
                $rechazosSabidos = ConocimientoNpc::hobbiesRechazadosConocidos($partida, $quien, $otro);
                $relLug = [];
                if ($catalog !== null) {
                    $item = $catalog->store()->item('lugares', $lug);
                    // lugares catalog is loadLugares, not store item. Use hobbies_lugares.
                    $relLug = self::hobbiesDeLugar($catalog, $lug);
                }
                foreach ($hobbiesSabidos as $h) {
                    if (in_array($h, $relLug, true)) {
                        $w += 4.0;
                    }
                }
                foreach ($rechazosSabidos as $h) {
                    if (in_array($h, $relLug, true)) {
                        $w *= 0.25;
                    }
                }
                if ($hobbiesSabidos === []) {
                    $w += $rng->nextFloat() * 0.8;
                }
            }
            $cands[] = ['lugar' => $lug, 'w' => max(0.05, $w)];
        }
        if ($cands === []) {
            return $operativos[0] ?? null;
        }
        $sum = 0.0;
        foreach ($cands as $c) {
            $sum += $c['w'];
        }
        $pick = $rng->nextFloat() * $sum;
        $acc = 0.0;
        foreach ($cands as $c) {
            $acc += $c['w'];
            if ($pick <= $acc) {
                return $c['lugar'];
            }
        }
        return $cands[count($cands) - 1]['lugar'];
    }

    /**
     * @return list<string>
     */
    public static function hobbiesDeLugar(Catalog $catalog, string $lugarId): array
    {
        $out = [];
        foreach ($catalog->store()->items('hobbies') as $item) {
            $lugs = is_array($item['lugar_ids'] ?? null) ? $item['lugar_ids'] : [];
            if (in_array($lugarId, $lugs, true) && isset($item['id'])) {
                $out[] = (string) $item['id'];
            }
        }
        return $out;
    }
}
