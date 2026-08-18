<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Campos aditivos de schema sin bump de versión mayor. */
final class SchemaFields
{
    public static function ensure(array &$partida): void
    {
        $partida['audit_trail'] ??= [];
        $partida['descubrimientos'] ??= [];
        $partida['historial_relaciones'] ??= [];
        $partida['domain_events'] ??= [];
        $partida['audit_trail_archivo'] ??= [];
        $partida['domain_events_archivo'] ??= [];

        foreach ($partida['residentes'] ?? [] as $id => $_) {
            EstadoEmocional::ensureResidente($partida['residentes'][$id], $partida['reloj'] ?? null);
        }

        $partida['historial_coincidencias'] ??= [];
        $partida['npc_autonomo'] ??= ['planes_pendientes' => []];
        $partida['npc_autonomo']['planes_pendientes'] ??= [];
    }
}
