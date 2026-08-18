<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Edad: rango preferente y límite duro configurables. Cifras = calibración, no canon. */
final class EdadPolitica
{
    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function clasificar(?int $edadA, ?int $edadB, array $cal): array
    {
        $pref = (int) CalibracionConfig::get($cal, 'edad.preferencia_anos', 10);
        $duro = (int) CalibracionConfig::get($cal, 'edad.limite_duro_anos', 25);
        if ($edadA === null || $edadB === null) {
            return [
                'delta' => null,
                'en_preferencia' => null,
                'dentro_limite_duro' => true,
                'romance_elegible' => true,
                '_nota' => 'edad desconocida: no se aplica filtro',
            ];
        }
        $delta = abs($edadA - $edadB);
        $dentro = $delta <= $duro;
        return [
            'delta' => $delta,
            'en_preferencia' => $delta <= $pref,
            'dentro_limite_duro' => $dentro,
            'romance_elegible' => $dentro,
            'preferencia_anos' => $pref,
            'limite_duro_anos' => $duro,
            '_provisional' => true,
        ];
    }
}
