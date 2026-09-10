<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class DiagnosticExport
{
    public static function export(array $partida, string $projectRoot): array
    {
        FeatureConfig::mergeIntoPartida($partida, $projectRoot);
        $erroresValidacion = PartidaValidator::validar($partida);

        return [
            'ok' => true,
            '_tipo' => 'diagnostico_dev',
            'generado_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'schema_version' => $partida['meta']['schema_version'] ?? null,
            'seed' => $partida['meta']['seed'] ?? null,
            'partida_id' => $partida['meta']['partida_id'] ?? null,
            'reloj' => $partida['reloj'] ?? null,
            'features' => $partida['features'] ?? [],
            'persistencia' => $partida['persistencia'] ?? [],
            'audit_trail_count' => count($partida['audit_trail'] ?? []),
            'audit_archivo_count' => count($partida['audit_trail_archivo'] ?? []),
            'residentes' => self::serializarResidentes($partida),
            'encuentros_count' => count($partida['encuentros'] ?? []),
            'encuentros_activos' => EncuentroEngine::listarActivos($partida),
            'encuentros_recientes' => self::encuentrosRecientes($partida),
            'relaciones_sociales' => self::serializarRelacionesSociales($partida),
            'relaciones_romanticas' => self::serializarRelacionesRomanticas($partida),
            'relaciones_conflicto' => self::serializarRelacionesConflicto($partida),
            'corazon_del_pueblo' => self::serializarCorazon($partida),
            'peticiones' => self::serializarPeticiones($partida),
            'npc_autonomo' => self::serializarNpcAutonomo($partida),
            'propuestas_encuentro' => count($partida['propuestas_encuentro'] ?? []),
            'descubrimientos_count' => count($partida['descubrimientos'] ?? []),
            'historial_relaciones_count' => count($partida['historial_relaciones'] ?? []),
            'emotional_instrumentation' => self::serializarEmotionalInstrumentation($partida),
            'misiones_diarias' => self::serializarMisiones($partida),
            'tutorial' => $partida['tutorial'] ?? null,
            'audit_trail_reciente' => array_slice($partida['audit_trail'] ?? [], -20),
            'domain_events_recientes' => array_slice($partida['domain_events'] ?? [], -20),
            'acontecimientos_recientes' => array_slice($partida['acontecimientos_log'] ?? [], -20),
            'errores_validacion_partida' => $erroresValidacion,
            'rng_state' => $partida['rng']['state'] ?? null,
        ];
    }

    private static function serializarResidentes(array $partida): array
    {
        $resultado = [];
        foreach ($partida['residentes'] ?? [] as $id => $r) {
            $rt = $r['runtime'] ?? [];
            $perfil = $rt['perfil_partida'] ?? [];
            $resultado[$id] = [
                'id' => $r['catalog_id'] ?? $id,
                'nombre' => $r['identidad_publica']['nombre'] ?? null,
                'vivienda' => $r['vivienda_id'] ?? null,
                'placeholder' => $r['_placeholder'] ?? false,
                'presencia' => $r['presencia'] ?? null,
                'estado_en_partida' => $r['estado_en_partida'] ?? null,
                'perfil_partida' => [
                    'hobbies' => $perfil['hobbies'] ?? [],
                    'rasgos' => $perfil['rasgos'] ?? [],
                    'lugares_preferentes' => $perfil['lugares_preferentes'] ?? [],
                    'edad' => $perfil['edad'] ?? null,
                ],
                'ocupacion' => $rt['ocupacion'] ?? null,
                'necesidades' => self::serializarNecesidadesResidente($rt),
                'estado_emocional' => $rt['estado_emocional'] ?? null,
                'expresion_visual' => $rt['expresion_visual'] ?? null,
                'aprecio_celeste' => $rt['aprecio_celeste'] ?? 0,
            ];
        }
        return $resultado;
    }

    private static function serializarNecesidadesResidente(array $rt): array
    {
        $necs = $rt['necesidades'] ?? [];
        $resultado = [];
        foreach (NecesidadEstado::TODAS as $tipo) {
            $n = $necs[$tipo] ?? null;
            if ($n === null) {
                $resultado[$tipo] = null;
                continue;
            }
            $resultado[$tipo] = [
                'valor' => $n['valor'] ?? null,
                'banda' => $n['banda'] ?? null,
                'ultima_actualizacion' => $n['ultima_actualizacion'] ?? null,
            ];
        }
        return $resultado;
    }

    private static function serializarRelacionesSociales(array $partida): array
    {
        $resultado = [];
        foreach ($partida['relaciones_sociales'] ?? [] as $rel) {
            $resultado[] = [
                'id' => $rel['id'] ?? null,
                'persona_a' => $rel['persona_a'] ?? null,
                'persona_b' => $rel['persona_b'] ?? null,
                'tipo' => $rel['tipo'] ?? null,
                'intensidad' => $rel['intensidad'] ?? null,
                'fase' => $rel['fase'] ?? null,
                'a_hacia_b' => $rel['a_hacia_b'] ?? null,
                'b_hacia_a' => $rel['b_hacia_a'] ?? null,
                'conocidos' => $rel['conocidos'] ?? null,
                'conocido_desde' => $rel['conocido_desde'] ?? null,
                'ultimo_contacto' => $rel['ultimo_contacto'] ?? null,
            ];
        }
        return $resultado;
    }

    private static function serializarRelacionesRomanticas(array $partida): array
    {
        $resultado = [];
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            $resultado[] = [
                'id' => $rel['id'] ?? null,
                'persona_a' => $rel['persona_a'] ?? null,
                'persona_b' => $rel['persona_b'] ?? null,
                'atraccion_a_hacia_b' => $rel['atraccion_a_hacia_b'] ?? null,
                'atraccion_b_hacia_a' => $rel['atraccion_b_hacia_a'] ?? null,
                'romance_a_hacia_b' => $rel['romance_a_hacia_b'] ?? null,
                'romance_b_hacia_a' => $rel['romance_b_hacia_a'] ?? null,
                'vinculo' => $rel['vinculo'] ?? null,
                'estado_actual' => $rel['estado_actual'] ?? null,
                'estado_pareja' => $rel['estado_pareja'] ?? null,
                'fase' => $rel['fase'] ?? null,
                'fecha_inicio' => $rel['fecha_inicio'] ?? null,
                'flechazos_count' => count($rel['flechazos'] ?? []),
                'historial_citas_count' => count($rel['historial_citas'] ?? []),
            ];
        }
        return $resultado;
    }

    private static function serializarRelacionesConflicto(array $partida): array
    {
        $resultado = [];
        foreach ($partida['relaciones_conflicto'] ?? [] as $rel) {
            $resultado[] = [
                'id' => $rel['id'] ?? null,
                'persona_a' => $rel['persona_a'] ?? null,
                'persona_b' => $rel['persona_b'] ?? null,
                'tipo' => $rel['tipo'] ?? null,
                'intensidad' => $rel['intensidad'] ?? null,
                'fase' => $rel['fase'] ?? null,
            ];
        }
        return $resultado;
    }

    private static function serializarCorazon(array $partida): array
    {
        $vp = $partida['vida_pueblo'] ?? [];
        $est = $vp['estancamiento'] ?? null;

        return [
            'heart' => $vp['valor'] ?? null,
            'banda' => VidaPuebloEngine::banda($vp['valor'] ?? 65)['id'] ?? null,
            'latidos' => $vp['latidos'] ?? 0,
            'game_over_pendiente' => $vp['game_over_pendiente'] ?? false,
            'game_over_activo' => $vp['game_over_activo'] ?? false,
            'llego_a_cero' => $vp['llego_a_cero'] ?? false,
            'dias_en_critico' => $vp['dias_en_critico'] ?? 0,
            'estancamiento' => $est !== null ? [
                'activo' => $est['activo'] ?? false,
                'dias_bajo_umbral' => $est['dias_bajo_umbral'] ?? 0,
                'dias_activo' => $est['dias_activo'] ?? 0,
                'dia_activacion' => $est['dia_activacion'] ?? null,
                'sh_en_activacion' => $est['sh_en_activacion'] ?? null,
                'ultimo_sh' => $est['ultimo_sh'] ?? null,
            ] : null,
            'ledger_reciente' => array_slice($vp['ledger'] ?? [], -10),
            'ledger_count' => count($vp['ledger'] ?? []),
            'ledger_archivo_count' => count($vp['ledger_archivo'] ?? []),
        ];
    }

    private static function serializarPeticiones(array $partida): array
    {
        $resultado = [];
        foreach ($partida['peticiones'] ?? [] as $pet) {
            $resultado[] = [
                'id' => $pet['id'] ?? null,
                'residente_id' => $pet['residente_id'] ?? null,
                'tipo' => $pet['tipo'] ?? null,
                'estado' => $pet['estado'] ?? null,
                'dia_creada' => $pet['dia_creada'] ?? null,
                'hora_creada' => $pet['hora_creada'] ?? null,
                'plazo_dia' => $pet['plazo_dia'] ?? null,
                'plazo_hora' => $pet['plazo_hora'] ?? null,
                'generacion_via' => $pet['generacion']['via'] ?? null,
            ];
        }
        return $resultado;
    }

    private static function serializarNpcAutonomo(array $partida): array
    {
        $npc = $partida['npc_autonomo'] ?? [];
        return [
            'planes_pendientes_count' => count($npc['planes_pendientes'] ?? []),
            'historial_eventos_count' => count($npc['historial_eventos'] ?? []),
            'historial_reciente' => array_slice($npc['historial_eventos'] ?? [], -10),
        ];
    }

    private static function serializarEmotionalInstrumentation(array $partida): array
    {
        $ep = $partida['runtime']['emotional_playtest'] ?? null;
        if ($ep === null) {
            return ['disponible' => false];
        }
        return [
            'disponible' => true,
            'eventos_count' => count($ep['eventos'] ?? []),
            'eventos_recientes' => array_slice($ep['eventos'] ?? [], -15),
            'expiraciones_count' => count($ep['expiraciones'] ?? []),
            'estadisticas' => EmotionalInstrumentation::estadisticas($partida),
        ];
    }

    private static function serializarMisiones(array $partida): array
    {
        $md = $partida['misiones_diarias'] ?? [];
        $items = $md['items'] ?? [];
        return [
            'items_count' => count($items),
            'items_recientes' => array_slice($items, -10),
        ];
    }

    private static function encuentrosRecientes(array $partida): array
    {
        $encuentros = $partida['encuentros'] ?? [];
        $recientes = array_slice($encuentros, -10);
        $resultado = [];
        foreach ($recientes as $enc) {
            $resultado[] = [
                'id' => $enc['id'] ?? null,
                'tipo' => $enc['tipo'] ?? null,
                'intencion' => $enc['intencion'] ?? null,
                'participantes' => $enc['participantes'] ?? [],
                'lugar' => $enc['lugar'] ?? null,
                'dia' => $enc['dia'] ?? null,
                'hora' => $enc['hora'] ?? null,
                'estado' => $enc['estado'] ?? null,
                'resultado_tipo' => $enc['resultado']['resultado'] ?? null,
            ];
        }
        return $resultado;
    }
}
