<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Códigos de error estables para motor/API/UI. */
final class GameError
{
    public const AGENDA_SLOT_OCUPADO = 'AGENDA_SLOT_OCUPADO';
    public const LUGAR_NO_OPERATIVO = 'LUGAR_NO_OPERATIVO';
    public const PARTICIPANTE_INEXISTENTE = 'PARTICIPANTE_INEXISTENTE';
    public const RESIDENTE_NO_ACTIVO = 'RESIDENTE_NO_ACTIVO';
    public const DOBLE_RESERVA = 'DOBLE_RESERVA';
    public const LIMITE_INTERVENCIONES = 'LIMITE_INTERVENCIONES';
    public const TRANSICION_INVALIDA = 'TRANSICION_INVALIDA';
    public const PARTIDA_NO_ENCONTRADA = 'PARTIDA_NO_ENCONTRADA';
    public const BLOQUE_LLENO = 'BLOQUE_LLENO';
    public const DEV_DESHABILITADO = 'DEV_DESHABILITADO';
    public const RELOJ_NO_REWIND = 'RELOJ_NO_REWIND';
    public const SIN_PROXIMO_ENCUENTRO = 'SIN_PROXIMO_ENCUENTRO';
    public const VALIDACION_FALLIDA = 'VALIDACION_FALLIDA';
    public const SAVE_CORRUPTO = 'SAVE_CORRUPTO';
    public const ENCUENTRO_RECHAZADO_INDISPONIBILIDAD = 'ENCUENTRO_RECHAZADO_INDISPONIBILIDAD';
    public const ENCUENTRO_RECHAZADO_VOLUNTAD = 'ENCUENTRO_RECHAZADO_VOLUNTAD';
    public const PROPUESTA_PENDIENTE = 'PROPUESTA_PENDIENTE';
    public const PROPUESTA_NO_ENCONTRADA = 'PROPUESTA_NO_ENCONTRADA';
    public const PETICION_NO_ENCONTRADA = 'PETICION_NO_ENCONTRADA';
    public const FASE_TRANSICION_INVALIDA = 'FASE_TRANSICION_INVALIDA';

    /** Mensajes UI placeholder (no narrativa final). */
    public static function mensajeUi(string $codigo): string
    {
        switch ($codigo) {
            case self::AGENDA_SLOT_OCUPADO:
                return 'No pueden quedar a esa hora.';
            case self::LUGAR_NO_OPERATIVO:
                return 'Ese lugar no está operativo.';
            case self::PARTICIPANTE_INEXISTENTE:
                return 'Uno de los participantes no existe.';
            case self::RESIDENTE_NO_ACTIVO:
                return 'Ese residente no está activo.';
            case self::DOBLE_RESERVA:
                return 'Ya hay algo programado a esa hora.';
            case self::LIMITE_INTERVENCIONES:
                return 'Has alcanzado el límite de intervenciones de hoy.';
            case self::TRANSICION_INVALIDA:
                return 'Esa transición de estado no es válida.';
            case self::PARTIDA_NO_ENCONTRADA:
                return 'Partida no encontrada.';
            case self::BLOQUE_LLENO:
                return 'No hay viviendas libres en el Bloque A.';
            case self::DEV_DESHABILITADO:
                return 'Herramientas de desarrollo deshabilitadas.';
            case self::RELOJ_NO_REWIND:
                return 'No se puede retroceder el reloj en partida normal.';
            case self::SIN_PROXIMO_ENCUENTRO:
                return 'No hay ningún encuentro programado más adelante.';
            case self::VALIDACION_FALLIDA:
                return 'Datos no válidos.';
            case self::SAVE_CORRUPTO:
                return 'El archivo de partida está dañado.';
            case self::ENCUENTRO_RECHAZADO_INDISPONIBILIDAD:
                return 'No puede ir a esa hora.';
            case self::ENCUENTRO_RECHAZADO_VOLUNTAD:
                return 'No quiere ir.';
            case self::PROPUESTA_PENDIENTE:
                return 'Todavía no han decidido.';
            case self::PROPUESTA_NO_ENCONTRADA:
                return 'No hay ninguna propuesta con ese id.';
            case self::PETICION_NO_ENCONTRADA:
                return 'No hay ninguna petición con ese id.';
            case self::FASE_TRANSICION_INVALIDA:
                return 'Esa fase de relación no es un paso válido.';
            default:
                return 'Ha ocurrido un error.';
        }
    }

    public static function respuesta(string $codigo, array $contexto = [], int $http = 400): array
    {
        return [
            'ok' => false,
            'error' => $codigo,
            'mensaje_ui' => self::mensajeUi($codigo),
            'contexto' => $contexto,
            '_http' => $http,
        ];
    }
}
