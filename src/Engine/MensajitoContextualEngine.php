<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * F10 — Ritual contextual (cumpleaños §19.6 / §22.3).
 *
 * Disparado por calendario real del pueblo (fecha_ancla + dia_pueblo).
 * Variante jugable de aviso contextual: participar / organizar / ignorar.
 * Los eventos colectivos del pueblo (B2) NO son F10; van por EventosPuebloAnuncioEngine.
 */
final class MensajitoContextualEngine
{
    /**
     * Evalúa cumpleaños al comenzar un nuevo día de pueblo.
     *
     * @param array<string, mixed> $cal
     * @return list<array<string, mixed>>
     */
    public static function evaluarAlComenzarDia(
        array &$partida,
        array $cal,
        Catalog $catalog,
        ?GameLogger $logger = null
    ): array {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return [];
        }
        $partida['mensajitos_cumpleanos_emitidos'] ??= [];
        $emitidos = [];

        foreach (array_keys($partida['residentes'] ?? []) as $rid) {
            if (!self::esResidenteActivo($partida, $rid)) {
                continue;
            }
            if (!ResidenteCumpleanosEngine::esCumpleanosHoy($partida, $rid, $catalog)) {
                continue;
            }
            $claveAnual = ResidenteCumpleanosEngine::claveAnual($partida, $rid);
            if (!empty($partida['mensajitos_cumpleanos_emitidos'][$claveAnual])) {
                continue;
            }
            $eventoId = 'f10_cumple_' . $claveAnual;
            if (CanalDeduplicador::yaPublicado($partida, $eventoId, BuzonEngine::CANAL_BUZON)) {
                $partida['mensajitos_cumpleanos_emitidos'][$claveAnual] = true;
                continue;
            }

            $remitente = self::elegirRemitente($partida, $rid);
            if ($remitente === null) {
                continue;
            }
            $nombreCumple = IdentidadPublica::nombre($partida, $rid);
            $esAuto = $remitente === $rid;
            $texto = MensajitoVoz::linea(
                $partida,
                $esAuto ? 'f_ritual_cumple_invitacion' : 'f_ritual_cumple_aviso',
                ['otro' => $nombreCumple],
                'f10_cumple|' . $rid . '|' . $remitente . '|' . $claveAnual,
                $remitente
            );
            if ($texto === '') {
                continue;
            }

            $msgId = 'msg_f10_' . substr(md5($eventoId), 0, 12);
            $datos = [
                'subtipo' => 'cumpleanos',
                'cumpleanero_id' => $rid,
                'cumpleanero_nombre' => $nombreCumple,
                'clave' => $claveAnual,
                'auto_invitacion' => $esAuto,
            ];
            $r = CanalDeduplicador::crearSiAplica($partida, [
                'id' => $msgId,
                'clasificacion' => BuzonEngine::IMPORTANTE,
                'tipo' => 'ritual_contextual_cumpleanos',
                'canal' => BuzonEngine::CANAL_BUZON,
                'de_persona' => $remitente,
                'actores' => array_values(array_unique([$remitente, $rid])),
                'texto' => $texto,
                'acciones' => ['participar_cumple', 'organizar_cumple', 'ignorar_contextual'],
                'familia_mensajito' => 'f_ritual_contextual',
                'datos_familia' => $datos,
                'hilo_id' => $msgId,
                'hilo_estado' => 'abierto',
                'origen' => [
                    'evento_id' => $eventoId,
                    'tipo_evento' => 'ritual_contextual_cumpleanos',
                    'es_narrativo' => true,
                    '_placeholder' => false,
                ],
                '_placeholder_contenido' => false,
            ]);

            if ($r === null || !($r['ok'] ?? false)) {
                continue;
            }

            $partida['mensajitos_cumpleanos_emitidos'][$claveAnual] = true;
            MensajitosCadenciaEngine::registrar($partida, $remitente, 'f_ritual_contextual', 'contextual', $claveAnual);
            $emitidos[] = [
                'cumpleanero_id' => $rid,
                'remitente_id' => $remitente,
                'mensaje_id' => $msgId,
            ];
            DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
                'mensaje' => $r['mensaje'] ?? null,
                'origen_evento' => 'ritual_contextual_cumpleanos',
            ], $logger, 'MensajitoContextualEngine');
        }

        return $emitidos;
    }

    /**
     * Celestine felicita (micro-efecto emocional, sin pareja).
     *
     * @return array<string, mixed>
     */
    public static function participarCumple(array &$partida, string $mensajeId): array
    {
        $ctx = self::contextoDe($partida, $mensajeId);
        if ($ctx === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $cumpleId = (string) ($ctx['cumpleanero_id'] ?? '');
        if ($cumpleId !== '' && isset($partida['residentes'][$cumpleId])) {
            EstadoEmocional::ensureResidente($partida['residentes'][$cumpleId], $partida['reloj'] ?? null);
            $reloj = $partida['reloj'] ?? [];
            $partida['residentes'][$cumpleId]['runtime']['estado_emocional'] = EstadoEmocional::estructura(
                EstadoEmocional::ALEGRE,
                1,
                'cumple_felicitud',
                EstadoEmocional::marcaReloj($reloj),
                EstadoEmocional::hastaDesdeDuracion($reloj, 12),
                ['fuente' => 'f10_cumpleanos'],
                12
            );
            $partida['residentes'][$cumpleId]['runtime']['animo'] = EstadoEmocional::ALEGRE;
        }
        self::cerrarHilo($partida, $mensajeId, ['accion' => 'participar_cumple', 'cumpleanero_id' => $cumpleId]);
        return [
            'ok' => true,
            'mensaje_ui' => 'Le daré la enhorabuena.',
            'detallito_hook' => ['pendiente' => true, 'motivo' => 'cumpleanos_felicitar'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function organizarCumple(array &$partida, string $mensajeId): array
    {
        $ctx = self::contextoDe($partida, $mensajeId);
        if ($ctx === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $cumpleId = (string) ($ctx['cumpleanero_id'] ?? '');
        if ($cumpleId === '') {
            return ['ok' => false, 'error' => 'sin_cumpleanero'];
        }
        self::cerrarHilo($partida, $mensajeId, ['accion' => 'organizar_cumple', 'cumpleanero_id' => $cumpleId]);
        return [
            'ok' => true,
            'mensaje_ui' => 'Vamos a montarle algo.',
            'preset_organizar' => [
                'modo' => 'individual',
                'participantes' => [$cumpleId],
                'tipo' => 'individual',
                'intencion' => 'fiesta',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ignorarContextual(array &$partida, string $mensajeId): array
    {
        $ctx = self::contextoDe($partida, $mensajeId);
        if ($ctx === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        self::cerrarHilo($partida, $mensajeId, ['accion' => 'ignorar_contextual']);
        return ['ok' => true, 'mensaje_ui' => 'Vale, esta vez paso.'];
    }

    private static function esResidenteActivo(array $partida, string $rid): bool
    {
        $r = $partida['residentes'][$rid] ?? null;
        if (!is_array($r)) {
            return false;
        }
        $pres = (string) ($r['presencia'] ?? 'residente');
        return $pres === 'residente' || $pres === 'nuevo';
    }

    private static function elegirRemitente(array $partida, string $cumpleaneroId): ?string
    {
        $candidatos = [];
        foreach ($partida['relaciones'][$cumpleaneroId] ?? [] as $otro => $val) {
            if (!is_string($otro) || $otro === $cumpleaneroId || !is_array($val)) {
                continue;
            }
            if (!self::esResidenteActivo($partida, $otro)) {
                continue;
            }
            $social = (float) ($val['social'] ?? 0);
            if ($social >= 20) {
                $candidatos[] = $otro;
            }
        }
        if ($candidatos !== []) {
            return $candidatos[array_rand($candidatos)];
        }
        if (self::esResidenteActivo($partida, $cumpleaneroId)) {
            return $cumpleaneroId;
        }
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function contextoDe(array $partida, string $mensajeId): ?array
    {
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m) || (string) ($m['id'] ?? '') !== $mensajeId) {
                continue;
            }
            $datos = is_array($m['datos_familia'] ?? null) ? $m['datos_familia'] : [];
            return $datos;
        }
        return null;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private static function cerrarHilo(array &$partida, string $mensajeId, array $meta): void
    {
        foreach ($partida['buzon'] as &$m) {
            if (!is_array($m) || (string) ($m['id'] ?? '') !== $mensajeId) {
                continue;
            }
            $m['respuesta_celestine'] = $meta;
            $m['hilo_estado'] = 'respondido';
            $m['seguimiento_pendiente'] = false;
            break;
        }
        unset($m);
    }
}
