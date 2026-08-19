<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Nombres estables de eventos de dominio. */
final class DomainEvents
{
    public const PARTIDA_CREADA = 'partida_creada';
    public const RESIDENTE_INCORPORADO = 'residente_incorporado';
    public const ENCUENTRO_PROGRAMADO = 'encuentro_programado';
    public const ENCUENTRO_INICIADO = 'encuentro_iniciado';
    public const ENCUENTRO_TERMINADO = 'encuentro_terminado';
    public const ENCUENTRO_CANCELADO = 'encuentro_cancelado';
    public const RELACION_MODIFICADA = 'relacion_modificada';
    public const TIEMPO_AVANZADO = 'tiempo_avanzado';
    public const ESTADO_EMOCIONAL_CAMBIADO = 'estado_emocional_cambiado';
    public const EXPRESION_VISUAL_RESUELTA = 'expresion_visual_resuelta';
    public const DESCUBRIMIENTO_REGISTRADO = 'descubrimiento_registrado';
    public const BUZON_MENSAJE = 'buzon_mensaje';
    public const DIARIO_ENTRADA = 'diario_entrada';
    public const EVENTO_EDIFICIO = 'evento_edificio';
    public const NPC_AUTONOMO_PLAN = 'npc_autonomo_plan';
    public const DISCUSION = 'discusion';
    public const PROPUESTA_ENCUENTRO = 'propuesta_encuentro';
    public const PETICION_CREADA = 'peticion_creada';
    public const PETICION_CADUCADA = 'peticion_caducada';
    public const CATCH_UP_PLANIFICADO = 'catch_up_planificado';
    public const PERFIL_PARTIDA_GENERADO = 'perfil_partida_generado';
    public const QUIMICA_GENERADA = 'quimica_generada';
    public const PAREJA_HITO = 'pareja_hito';
    public const ACONTECIMIENTO_DIARIO = 'acontecimiento_diario';
    public const SENAL_ROMANTICA = 'senal_romantica';

    /** Coincidencia técnica de residentes en el mismo lugar/hora (sin interacción garantizada). */
    public const COINCIDENCIA_RESIDENTES = 'coincidencia_residentes';

    /** @deprecated usar ENCUENTRO_TERMINADO */
    public const ENCOUNTER_FINISHED_LEGACY = 'encounter_finished';
}
