<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Códigos de error estables para motor/API/UI. */
final class GameError
{
    public const AGENDA_SLOT_OCUPADO = 'AGENDA_SLOT_OCUPADO';
    public const LUGAR_NO_OPERATIVO = 'LUGAR_NO_OPERATIVO';
    public const LUGAR_CERRADO = 'LUGAR_CERRADO';
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
    public const SAVE_DEMASIADO_GRANDE = 'SAVE_DEMASIADO_GRANDE';
    public const ENCUENTRO_RECHAZADO_INDISPONIBILIDAD = 'ENCUENTRO_RECHAZADO_INDISPONIBILIDAD';
    public const ENCUENTRO_RECHAZADO_VOLUNTAD = 'ENCUENTRO_RECHAZADO_VOLUNTAD';
    public const ENCUENTRO_RECHAZADO_COOLDOWN = 'ENCUENTRO_RECHAZADO_COOLDOWN';
    public const PROPUESTA_PENDIENTE = 'PROPUESTA_PENDIENTE';
    public const PROPUESTA_NO_ENCONTRADA = 'PROPUESTA_NO_ENCONTRADA';
    public const PETICION_NO_ENCONTRADA = 'PETICION_NO_ENCONTRADA';
    public const FASE_TRANSICION_INVALIDA = 'FASE_TRANSICION_INVALIDA';
    public const TIPO_ENCUENTRO_NO_DISPONIBLE = 'TIPO_ENCUENTRO_NO_DISPONIBLE';
    public const MISMA_PERSONA = 'MISMA_PERSONA';
    public const PARTICIPANTES_EXCESO = 'PARTICIPANTES_EXCESO';
    public const HORA_PASADA = 'HORA_PASADA';
    public const INTERVENCION_NO_DISPONIBLE = 'INTERVENCION_NO_DISPONIBLE';
    public const INTERVENCION_YA_USADA = 'INTERVENCION_YA_USADA';
    public const INTERVENCION_ACCION_INVALIDA = 'INTERVENCION_ACCION_INVALIDA';
    public const AFORO_COMPLETO = 'AFORO_COMPLETO';
    public const PARTICIPANTE_YA_APUNTADO = 'PARTICIPANTE_YA_APUNTADO';
    public const EVENTO_PUEBLO_NO_ENCONTRADO = 'EVENTO_PUEBLO_NO_ENCONTRADO';
    public const EVENTO_PUEBLO_CERRADO = 'EVENTO_PUEBLO_CERRADO';

    /** Mensajes UI placeholder (no narrativa final). */
    public static function mensajeUi(string $codigo): string
    {
        switch ($codigo) {
            case self::AGENDA_SLOT_OCUPADO:
                return 'No pueden quedar a esa hora.';
            case self::LUGAR_NO_OPERATIVO:
                return 'Ese lugar no está operativo.';
            case self::LUGAR_CERRADO:
                return 'Ese lugar está cerrado a esa hora.';
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
            case self::SAVE_DEMASIADO_GRANDE:
                return 'La partida ocupa demasiado espacio para guardarse.';
            case self::ENCUENTRO_RECHAZADO_INDISPONIBILIDAD:
                return 'No puede ir a esa hora.';
            case self::ENCUENTRO_RECHAZADO_VOLUNTAD:
                return 'No quiere ir.';
            case self::ENCUENTRO_RECHAZADO_COOLDOWN:
                return 'Todavía no quiere hablar de eso.';
            case self::PROPUESTA_PENDIENTE:
                return 'Todavía no han decidido.';
            case self::PROPUESTA_NO_ENCONTRADA:
                return 'No hay ninguna propuesta con ese id.';
            case self::PETICION_NO_ENCONTRADA:
                return 'No hay ninguna petición con ese id.';
            case self::FASE_TRANSICION_INVALIDA:
                return 'Esa fase de relación no es un paso válido.';
            case self::TIPO_ENCUENTRO_NO_DISPONIBLE:
                return 'Ese tipo de encuentro no está disponible para esta relación todavía.';
            case self::HORA_PASADA:
                return 'Esa hora ya ha pasado.';
            case self::MISMA_PERSONA:
                return 'Elige a dos personas distintas.';
            case self::PARTICIPANTES_EXCESO:
                return 'Puedes organizar planes con hasta 2 vecinos.';
            case self::INTERVENCION_NO_DISPONIBLE:
                return 'Ahora no puedes intervenir en este encuentro.';
            case self::INTERVENCION_YA_USADA:
                return 'Ya has intervenido en este encuentro.';
            case self::INTERVENCION_ACCION_INVALIDA:
                return 'Esa acción no está disponible ahora.';
            case self::AFORO_COMPLETO:
                return 'El evento ya está completo.';
            case self::PARTICIPANTE_YA_APUNTADO:
                return 'Ese vecino ya está apuntado al evento.';
            case self::EVENTO_PUEBLO_NO_ENCONTRADO:
                return 'No se ha encontrado ese evento del pueblo.';
            case self::EVENTO_PUEBLO_CERRADO:
                return 'Ese evento del pueblo ya no admite apuntados.';
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
