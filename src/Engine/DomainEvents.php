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

    /** @deprecated usar ENCUENTRO_TERMINADO */
    public const ENCOUNTER_FINISHED_LEGACY = 'encounter_finished';
}
