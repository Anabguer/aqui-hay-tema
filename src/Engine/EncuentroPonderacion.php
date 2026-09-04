<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Snapshot auditable de factores de un encuentro. No aplica fórmula a play.
 * Una cita puede vivirse distinto por A y por B (compatibilidad direccional).
 */
final class EncuentroPonderacion
{
    /**
     * @return array<string, mixed>
     */
    public static function snapshot(array $partida, array $encuentro, Catalog $catalog): array
    {
        $ids = array_values($encuentro['participantes'] ?? []);
        $a = $ids[0] ?? '';
        $b = $ids[1] ?? '';
        $lugar = isset($encuentro['lugar']) ? (string) $encuentro['lugar'] : null;
        $rel = ($a !== '' && $b !== '') ? RelacionEngine::obtenerEntre($partida, $a, $b) : ['social' => null, 'romance' => null];
        $emoA = $partida['residentes'][$a]['runtime']['estado_emocional']['id'] ?? null;
        $emoB = $partida['residentes'][$b]['runtime']['estado_emocional']['id'] ?? null;

        $socAb = ($a !== '' && $b !== '') ? RelacionEngine::socialHacia($partida, $a, $b) : null;
        $socBa = ($a !== '' && $b !== '') ? RelacionEngine::socialHacia($partida, $b, $a) : null;
        $factores = [
            'compat_ab' => $a !== '' && $b !== '' ? CompatibilidadOculta::hacia($partida, $a, $b) : null,
            'compat_ba' => $a !== '' && $b !== '' ? CompatibilidadOculta::hacia($partida, $b, $a) : null,
            'quimica' => $a !== '' && $b !== '' ? QuimicaEngine::obtener($partida, $a, $b) : null,
            'social_ab' => $socAb,
            'social_ba' => $socBa,
            'romance_ab' => $a !== '' && $b !== '' ? RelacionEngine::romanceHacia($partida, $a, $b) : null,
            'romance_ba' => $a !== '' && $b !== '' ? RelacionEngine::romanceHacia($partida, $b, $a) : null,
            'conflicto' => $rel['conflicto']['intensidad'] ?? null,
            'vinculo' => $rel['romance']['vinculo'] ?? ($rel['social']['intensidad'] ?? null),
            'lugar' => $lugar,
            'plan_a' => $a !== '' ? PlanAfinidad::paraParticipante($partida, $a, $lugar, $catalog) : null,
            'plan_b' => $b !== '' ? PlanAfinidad::paraParticipante($partida, $b, $lugar, $catalog) : null,
            'emocional_a' => $emoA,
            'emocional_b' => $emoB,
            'historial_reciente' => MemoriaEventos::recientes($partida, $ids, 5),
            /* FASE 1: Barra de quedada como factor adicional. */
            'barra_quedada' => self::normalizarBarra((int) ($encuentro['barra_quedada'] ?? 0)),
            'azar' => null,
        ];

        return [
            '_provisional' => true,
            '_bloqueado_decision' => ['formula_definitiva', 'pesos_resolucion'],
            'factores' => $factores,
            'por_participante' => [
                $a => [
                    'satisfaccion' => null,
                    'texto' => null,
                    'compatibilidad_hacia_otro' => $factores['compat_ab']['total'] ?? null,
                ],
                $b => [
                    'satisfaccion' => null,
                    'texto' => null,
                    'compatibilidad_hacia_otro' => $factores['compat_ba']['total'] ?? null,
                ],
            ],
        ];
    }

    /**
     * FASE 1: Normaliza barra de quedada (-12..+12) a valor -1..+1 para el motor de experiencia.
     */
    private static function normalizarBarra(int $barra): float
    {
        /* Rango esperado: -12 (4 turnos muy_mal) a +12 (4 turnos muy_bien). */
        $max = 12.0;
        return max(-1.0, min(1.0, $barra / $max));
    }
}
