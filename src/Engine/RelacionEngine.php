<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class RelacionEngine
{
    public static function upsertSocial(
        array &$partida,
        string $personaA,
        string $personaB,
        string $tipo,
        ?int $intensidad = null,
        ?bool $seSoportan = null,
        ?string $eventoOrigen = 'manual',
        ?string $correlacionId = null
    ): array {
        [$a, $b] = self::ordenarPar($personaA, $personaB);
        $id = "soc_{$a}_{$b}";
        $antes = self::obtenerEntre($partida, $a, $b)['social'];

        foreach ($partida['relaciones_sociales'] as &$rel) {
            if ($rel['id'] === $id) {
                $rel['tipo'] = $tipo;
                if ($intensidad !== null) {
                    $rel['intensidad'] = $intensidad;
                }
                if ($seSoportan !== null) {
                    $rel['se_soportan'] = $seSoportan;
                }
                self::postCambio($partida, $a, $b, 'social', $eventoOrigen, $antes, $rel, $correlacionId);
                return ['ok' => true, 'relacion' => $rel, 'creada' => false];
            }
        }

        $rel = [
            'id' => $id,
            'persona_a' => $a,
            'persona_b' => $b,
            'tipo' => $tipo,
            'es_familiar' => false,
            'veta_romance' => false,
            'intensidad' => $intensidad,
            'se_soportan' => $seSoportan,
            'notas' => '',
            '_placeholder_balance' => true,
        ];
        $partida['relaciones_sociales'][] = $rel;
        self::postCambio($partida, $a, $b, 'social', $eventoOrigen, $antes, $rel, $correlacionId);
        return ['ok' => true, 'relacion' => $rel, 'creada' => true];
    }

    public static function upsertRomance(
        array &$partida,
        string $personaA,
        string $personaB,
        array $valores = [],
        ?string $eventoOrigen = 'manual',
        ?string $correlacionId = null
    ): array {
        [$a, $b] = self::ordenarPar($personaA, $personaB);
        $id = "rel_{$a}_{$b}";
        $antes = self::obtenerEntre($partida, $a, $b)['romance'];

        $defaults = [
            'atraccion_a_hacia_b' => null,
            'atraccion_b_hacia_a' => null,
            'vinculo' => null,
            'conflicto' => null,
            'necesidad_contacto_a' => null,
            'necesidad_contacto_b' => null,
            'estado_actual' => 'solteros_con_interes',
        ];

        foreach ($partida['relaciones_romanticas'] as &$rel) {
            if ($rel['id'] === $id) {
                foreach ($valores as $k => $v) {
                    if (array_key_exists($k, $defaults) || str_starts_with($k, 'atraccion_') || str_starts_with($k, 'necesidad_')) {
                        $rel[$k] = $v;
                    }
                }
                self::postCambio($partida, $a, $b, 'romance', $eventoOrigen, $antes, $rel, $correlacionId, $valores);
                return ['ok' => true, 'relacion' => $rel, 'creada' => false];
            }
        }

        $rel = array_merge([
            'id' => $id,
            'persona_a' => $a,
            'persona_b' => $b,
            'recuerdos_compartidos' => [],
            'historial_citas' => [],
            'historial_encuentros' => [],
            'fecha_inicio' => null,
            '_placeholder_formulas' => true,
        ], $defaults, $valores);

        $partida['relaciones_romanticas'][] = $rel;
        self::postCambio($partida, $a, $b, 'romance', $eventoOrigen, $antes, $rel, $correlacionId, $valores);
        return ['ok' => true, 'relacion' => $rel, 'creada' => true];
    }

    public static function obtenerEntre(array $partida, string $personaA, string $personaB): array
    {
        [$a, $b] = self::ordenarPar($personaA, $personaB);
        $socId = "soc_{$a}_{$b}";
        $romId = "rel_{$a}_{$b}";
        $social = null;
        $romance = null;
        foreach ($partida['relaciones_sociales'] as $rel) {
            if ($rel['id'] === $socId) {
                $social = $rel;
            }
        }
        foreach ($partida['relaciones_romanticas'] as $rel) {
            if ($rel['id'] === $romId) {
                $romance = $rel;
            }
        }
        return ['social' => $social, 'romance' => $romance];
    }

    private static function postCambio(
        array &$partida,
        string $a,
        string $b,
        string $canal,
        ?string $eventoOrigen,
        ?array $antes,
        array $despues,
        ?string $correlacionId,
        array $deltas = []
    ): void {
        RelacionHistorial::registrar(
            $partida,
            $a,
            $b,
            $canal,
            $eventoOrigen ?? 'manual',
            $deltas,
            $antes,
            $despues,
            $correlacionId
        );
        DomainEventDispatcher::emit($partida, DomainEvents::RELACION_MODIFICADA, [
            'canal' => $canal,
            'persona_a' => $a,
            'persona_b' => $b,
            'antes' => $antes,
            'despues' => $despues,
            'actores' => [$a, $b],
        ], null, 'RelacionEngine::upsert', [$a, $b]);
    }

    /** @return array{0: string, 1: string} */
    private static function ordenarPar(string $a, string $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }
}
