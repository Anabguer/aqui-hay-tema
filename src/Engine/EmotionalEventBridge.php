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
        $cal = CalibracionConfig::load(dirname(__DIR__, 2));
        $dur = (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas', 6);
        $svc = new EmotionalStateService(new VisualPackStore(dirname(__DIR__, 2)), (new Catalog(dirname(__DIR__, 2)))->store(), $logger);
        $n = 0;
        foreach ($actores as $rid) {
            $rid = (string) $rid;
            if ($rid === '' || !isset($partida['residentes'][$rid])) {
                continue;
            }
            $resExp = (string) ($resultado['por_participante'][$rid]['resultado'] ?? 'normal');
            $estado = self::estadoDesdeResultado($resExp);
            if ($estado === null) {
                continue;
            }
            $svc->aplicar($partida, $rid, $estado, 'encuentro', null, null, ['encuentro_id' => $encuentro['id'] ?? null], $dur);
            $n++;
        }

        return ['ok' => true, 'evaluados' => $n, '_placeholder' => false, 'origen_sugerido' => $origen];
    }

    private static function estadoDesdeResultado(string $resultado): ?string
    {
        if ($resultado === 'muy_bien' || $resultado === 'bien') {
            return EstadoEmocional::ALEGRE;
        }
        if ($resultado === 'muy_mal') {
            return EstadoEmocional::TRISTE;
        }
        if ($resultado === 'mal') {
            return EstadoEmocional::ENFADADO;
        }
        return null;
    }
}
