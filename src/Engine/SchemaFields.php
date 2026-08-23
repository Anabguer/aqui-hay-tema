<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Campos aditivos de schema sin bump de versión mayor. */
final class SchemaFields
{
    public static function ensure(array &$partida): void
    {
        Reloj::ensure($partida);
        VidaPuebloEngine::ensure($partida);
        MisionDiariaEngine::ensure($partida);
        PeticionPuebloEngine::ensure($partida);
        CapacidadViviendas::ensure($partida);
        CandidatoLlegadaEngine::ensure($partida);
        $partida['audit_trail'] ??= [];
        $partida['descubrimientos'] ??= [];
        $partida['historial_relaciones'] ??= [];
        $partida['domain_events'] ??= [];
        $partida['audit_trail_archivo'] ??= [];
        $partida['domain_events_archivo'] ??= [];

        foreach ($partida['residentes'] ?? [] as $id => $_) {
            EstadoEmocional::ensureResidente($partida['residentes'][$id], $partida['reloj'] ?? null);
        }
        PerfilPartida::reconciliarLugaresPreferentes($partida);

        $partida['historial_coincidencias'] ??= [];
        $partida['npc_autonomo'] ??= ['planes_pendientes' => []];
        $partida['npc_autonomo']['planes_pendientes'] ??= [];
        $partida['npc_autonomo']['historial_eventos'] ??= [];
        $partida['propuestas_encuentro'] ??= [];
        $partida['peticiones'] ??= [];
        $partida['relaciones_conflicto'] ??= [];
        CompatibilidadOculta::ensure($partida);
        QuimicaEngine::ensure($partida);
        MemoriaEventos::ensure($partida);
        RelacionBitacora::ensure($partida);
        $partida['parentesco'] ??= [];
        $partida['inclinaciones_consejo'] ??= [];
        $partida['conocimiento_npc'] ??= [];
        $partida['propuestas_cooldown'] ??= [];
        $partida['rechazos_propuesta'] ??= [];
        $partida['huecos_vida'] ??= [];
        CotilleoAutonomoCadencia::ensure($partida);
        RelacionGrafo::asegurarTodos($partida);

        foreach ($partida['relaciones_sociales'] ?? [] as $i => $rel) {
            if (is_array($rel)) {
                RelacionEngine::ensureSocialCampos($partida['relaciones_sociales'][$i]);
            }
        }
        foreach ($partida['relaciones_romanticas'] ?? [] as $i => $rel) {
            if (is_array($rel)) {
                RelacionEngine::ensureRomanceCampos($partida['relaciones_romanticas'][$i]);
            }
        }
        foreach ($partida['relaciones_conflicto'] ?? [] as $i => $rel) {
            if (is_array($rel)) {
                RelacionFase::ensure($partida['relaciones_conflicto'][$i]);
            }
        }
    }
}
