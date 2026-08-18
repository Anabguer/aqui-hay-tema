<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Compatibilidad direccional A→B: encaje de características de B con lo que A valora/rechaza.
 * NO es relación. NO sube con citas. Pesos = calibración.
 */
final class CompatibilidadCalculator
{
    /**
     * @param array<string, mixed> $perfilA
     * @param array<string, mixed> $perfilB
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function aHaciaB(array $perfilA, array $perfilB, array $cal): array
    {
        $prefs = is_array($perfilA['preferencias'] ?? null) ? $perfilA['preferencias'] : [];
        $rasgosB = self::canonList($perfilB['rasgos'] ?? []);
        $visB = self::canonList($perfilB['indicadores_visuales'] ?? []);
        $hobA = self::canonList($perfilA['hobbies'] ?? []);
        $hobB = self::canonList($perfilB['hobbies'] ?? []);

        $posP = array_values(array_intersect(self::canonList($prefs['personalidad_pos'] ?? []), $rasgosB));
        $negP = array_values(array_intersect(self::canonList($prefs['personalidad_neg'] ?? []), $rasgosB));
        $posV = array_values(array_intersect(self::canonList($prefs['visual_pos'] ?? []), $visB));
        $negV = array_values(array_intersect(self::canonList($prefs['visual_neg'] ?? []), $visB));
        $sharedH = array_values(array_intersect($hobA, $hobB));

        $pPos = (int) CalibracionConfig::get($cal, 'compatibilidad.peso_personalidad_positivo', 8);
        $pNeg = (int) CalibracionConfig::get($cal, 'compatibilidad.peso_personalidad_negativo', 12);
        $pHob = (int) CalibracionConfig::get($cal, 'compatibilidad.peso_hobby_compartido', 6);
        $pVPos = (int) CalibracionConfig::get($cal, 'compatibilidad.peso_visual_positivo', 4);
        $pVNeg = (int) CalibracionConfig::get($cal, 'compatibilidad.peso_visual_negativo', 7);
        $base = (int) CalibracionConfig::get($cal, 'compatibilidad.base', 50);
        $min = (int) CalibracionConfig::get($cal, 'compatibilidad.min', 0);
        $max = (int) CalibracionConfig::get($cal, 'compatibilidad.max', 100);

        $aporteP = (count($posP) * $pPos) - (count($negP) * $pNeg);
        $aporteH = count($sharedH) * $pHob;
        $aporteV = (count($posV) * $pVPos) - (count($negV) * $pVNeg);
        $total = $base + $aporteP + $aporteH + $aporteV;
        if ($total < $min) {
            $total = $min;
        }
        if ($total > $max) {
            $total = $max;
        }

        $edad = EdadPolitica::clasificar(
            isset($perfilA['edad']) ? (int) $perfilA['edad'] : null,
            isset($perfilB['edad']) ? (int) $perfilB['edad'] : null,
            $cal
        );

        return [
            '_provisional' => true,
            'total' => $total,
            'personalidad' => [
                'positivos_coincidentes' => $posP,
                'negativos_coincidentes' => $negP,
                'aporte' => $aporteP,
            ],
            'hobbies' => [
                'compartidos' => $sharedH,
                'aporte' => $aporteH,
            ],
            'visual' => [
                'positivos_coincidentes' => $posV,
                'negativos_coincidentes' => $negV,
                'aporte' => $aporteV,
            ],
            'edad' => $edad,
            'romance_elegible' => (bool) ($edad['romance_elegible'] ?? true),
        ];
    }

    /**
     * @param mixed $list
     * @return list<string>
     */
    private static function canonList($list): array
    {
        $out = [];
        if (!is_array($list)) {
            return $out;
        }
        foreach ($list as $v) {
            if (is_string($v) && $v !== '') {
                $out[] = $v;
            }
        }
        return array_values(array_unique($out));
    }
}
