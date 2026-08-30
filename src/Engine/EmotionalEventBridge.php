<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Enchufes a sistemas que más adelante podrán cambiar estado emocional.
 * Con emotional_state_from_events_enabled=false NO aplica estados ni fórmulas.
 */
final class EmotionalEventBridge
{
    public static function register(): void
    {
        foreach (self::eventos() as $evento) {
            EventBus::on($evento, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
                return self::handle($partida, $envelope, $logger);
            });
        }
    }

    /** @return list<string> */
    public static function eventos(): array
    {
        return [
            DomainEvents::ENCUENTRO_TERMINADO,
            DomainEvents::ENCUENTRO_CANCELADO,
            DomainEvents::RELACION_MODIFICADA,
            DomainEvents::RESIDENTE_INCORPORADO,
            DomainEvents::TIEMPO_AVANZADO,
            DomainEvents::DESCUBRIMIENTO_REGISTRADO,
            DomainEvents::BUZON_MENSAJE,
            DomainEvents::DIARIO_ENTRADA,
            DomainEvents::EVENTO_EDIFICIO,
            DomainEvents::NPC_AUTONOMO_PLAN,
            DomainEvents::DISCUSION,
            DomainEvents::PROPUESTA_ENCUENTRO,
            DomainEvents::PETICION_CREADA,
            DomainEvents::PETICION_CADUCADA,
        ];
    }

    public static function origenSugerido(string $evento): string
    {
        switch ($evento) {
            case DomainEvents::ENCUENTRO_TERMINADO:
                return 'encuentro';
            case DomainEvents::ENCUENTRO_CANCELADO:
                return 'rechazo';
            case DomainEvents::RELACION_MODIFICADA:
                return 'romance';
            case DomainEvents::RESIDENTE_INCORPORADO:
                return 'inicial';
            case DomainEvents::DESCUBRIMIENTO_REGISTRADO:
                return 'descubrimiento';
            case DomainEvents::BUZON_MENSAJE:
            case DomainEvents::DIARIO_ENTRADA:
                return 'mensaje';
            case DomainEvents::EVENTO_EDIFICIO:
                return 'evento_edificio';
            case DomainEvents::NPC_AUTONOMO_PLAN:
                return 'npc_autonomo';
            case DomainEvents::DISCUSION:
                return 'discusion';
            case DomainEvents::PROPUESTA_ENCUENTRO:
                return 'encuentro';
            case DomainEvents::PETICION_CREADA:
            case DomainEvents::PETICION_CADUCADA:
                return 'mensaje';
            case DomainEvents::TIEMPO_AVANZADO:
                return 'expiracion';
            default:
                return 'npc_autonomo';
        }
    }

    private static function handle(array &$partida, array $envelope, ?GameLogger $logger): array
    {
        $evento = (string) ($envelope['evento'] ?? '');
        $origen = self::origenSugerido($evento);
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $envelope = array_merge($envelope, $payload);

        if ($evento === DomainEvents::TIEMPO_AVANZADO) {
            return ['ok' => true, 'skipped' => 'expiracion_la_hace_RelojOperations', 'origen_sugerido' => $origen];
        }

        if (!FeatureConfig::isEnabled($partida, 'emotional_state_from_events_enabled')) {
            return ['ok' => true, 'skipped' => 'feature_disabled', 'origen_sugerido' => $origen];
        }

        if ($evento !== DomainEvents::ENCUENTRO_TERMINADO) {
            return ['ok' => true, 'skipped' => 'evento_sin_regla', 'origen_sugerido' => $origen];
        }

        $resultado = is_array($envelope['resultado'] ?? null) ? $envelope['resultado'] : [];
        $encuentro = is_array($envelope['encuentro'] ?? null) ? $envelope['encuentro'] : [];
        $actores = is_array($encuentro['participantes'] ?? null) ? $encuentro['participantes'] : ($envelope['actores'] ?? []);
        $lugarId = isset($encuentro['lugar']) ? (string) $encuentro['lugar'] : null;
        $cal = CalibracionConfig::load(dirname(__DIR__, 2));
        $dur = (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas', 6);
        $catalog = new Catalog(dirname(__DIR__, 2));
        $svc = new EmotionalStateService(new VisualPackStore(dirname(__DIR__, 2)), $catalog->store(), $logger);
        $n = 0;
        $emocionesRes = [];
        foreach ($actores as $rid) {
            $rid = (string) $rid;
            if ($rid === '' || !isset($partida['residentes'][$rid])) {
                continue;
            }
            EstadoEmocional::ensureResidente($partida['residentes'][$rid], $partida['reloj'] ?? null);
            $antes = $partida['residentes'][$rid]['runtime']['estado_emocional'];
            $estadoAntes = (string) ($antes['id'] ?? EstadoEmocional::NEUTRO);
            $resExp = (string) ($resultado['por_participante'][$rid]['resultado'] ?? 'normal');
            $afin = PlanAfinidad::paraParticipante($partida, $rid, $lugarId, $catalog);
            $hobbyMatch = !empty($afin['relacionado']);
            $eval = EmotionalRecovery::evaluar($estadoAntes, $resExp, $hobbyMatch);
            if ($eval === null) {
                continue;
            }
            $origenAplicar = (string) ($eval['motivo'] === 'hobby_recuperacion' ? 'hobby_recuperacion' : 'encuentro');
            $ctx = [
                'encuentro_id' => $encuentro['id'] ?? null,
                'hobby_match' => $hobbyMatch,
                'resultado_experiencia' => $resExp,
                'estado_antes' => $estadoAntes,
                'motivo' => $eval['motivo'],
            ];
            $svc->aplicar(
                $partida,
                $rid,
                (string) $eval['estado'],
                $origenAplicar,
                null,
                null,
                $ctx,
                $dur
            );
            $despues = $partida['residentes'][$rid]['runtime']['estado_emocional'];
            DiarioNarrativaBridge::desdeEmocion($partida, $rid, $despues);
            $emocionesRes[] = [
                'residente_id' => $rid,
                'estado' => (string) ($despues['id'] ?? ''),
                'antes' => $estadoAntes,
                'hobby_match' => $hobbyMatch,
                'resultado_experiencia' => $resExp,
                'motivo' => $eval['motivo'],
                'encuentro_id' => $encuentro['id'] ?? null,
            ];
            $n++;
        }

        if ($emocionesRes !== [] && isset($encuentro['id'])) {
            self::anotarResultadoEncuentro($partida, (string) $encuentro['id'], $emocionesRes);
        }

        return ['ok' => true, 'evaluados' => $n, '_placeholder' => false, 'origen_sugerido' => $origen, 'emociones' => $emocionesRes];
    }

    /**
     * @param list<array<string, mixed>> $emociones
     */
    private static function anotarResultadoEncuentro(array &$partida, string $encuentroId, array $emociones): void
    {
        foreach ($partida['encuentros'] ?? [] as $i => $enc) {
            if (!is_array($enc) || (string) ($enc['id'] ?? '') !== $encuentroId) {
                continue;
            }
            $res = is_array($enc['resultado'] ?? null) ? $enc['resultado'] : [];
            $res['emociones'] = $emociones;
            $partida['encuentros'][$i]['resultado'] = $res;
            return;
        }
    }
}
