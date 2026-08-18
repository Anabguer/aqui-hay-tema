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
    }
}
