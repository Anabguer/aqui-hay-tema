<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Todos los residentes aparecen en la ficha de relaciones de otro.
 * Sin contacto: social 0 + conocidos=false. No es lo mismo que 0 tras conocerse.
 */
final class RelacionGrafo
{
    public static function asegurarTodos(array &$partida, array $cal = []): int
    {
        $ids = array_keys($partida['residentes'] ?? []);
        $n = 0;
        $m = count($ids);
        for ($i = 0; $i < $m; $i++) {
            for ($j = $i + 1; $j < $m; $j++) {
                if (self::asegurarParLatente($partida, (string) $ids[$i], (string) $ids[$j], $cal)) {
                    $n++;
                }
            }
        }
        return $n;
    }

    /**
     * Crea fila social 0 / desconocido. No dispara hito se_conocieron.
     */
    public static function asegurarParLatente(array &$partida, string $a, string $b, array $cal = []): bool
    {
        if ($a === $b || $a === '' || $b === '') {
            return false;
        }
        $par = RelacionEngine::obtenerEntre($partida, $a, $b);
        if (is_array($par['social'] ?? null)) {
            RelacionEngine::ensureSocialCampos($par['social']);
            RelacionEngine::persistirSocial($partida, $par['social']);
            return false;
        }
        [$lo, $hi] = $a < $b ? [$a, $b] : [$b, $a];
        $rel = [
            'id' => "soc_{$lo}_{$hi}",
            'persona_a' => $lo,
            'persona_b' => $hi,
            'tipo' => 'desconocido',
            'es_familiar' => false,
            'veta_romance' => false,
            'intensidad' => 0,
            'se_soportan' => null,
            'notas' => '',
            'conocidos' => false,
            'conocido_desde' => null,
            'ultimo_contacto' => null,
            'ultimo_contacto_significativo' => null,
            'ultimo_contacto_calidad' => null,
            'consolidacion' => null,
            'a_hacia_b' => [
                'valor' => 0,
                'banda' => RelacionBandas::social(0, false, $cal),
                'desgaste_resto' => 0.0,
            ],
            'b_hacia_a' => [
                'valor' => 0,
                'banda' => RelacionBandas::social(0, false, $cal),
                'desgaste_resto' => 0.0,
            ],
            '_latente' => true,
        ];
        RelacionFase::ensure($rel);
        RelacionEngine::ensureSocialCampos($rel);
        $rel['conocidos'] = false;
        $partida['relaciones_sociales'] ??= [];
        $partida['relaciones_sociales'][] = $rel;
        CompatibilidadOculta::ensurePar($partida, $lo, $hi);
        return true;
    }
}
