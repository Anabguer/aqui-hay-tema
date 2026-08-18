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

    /** Mensajes UI placeholder (no narrativa final). */
    public static function mensajeUi(string $codigo): string
    {
        return match ($codigo) {
            self::AGENDA_SLOT_OCUPADO => 'No pueden quedar a esa hora.',
            self::LUGAR_NO_OPERATIVO => 'Ese lugar no está operativo.',
            self::PARTICIPANTE_INEXISTENTE => 'Uno de los participantes no existe.',
            self::RESIDENTE_NO_ACTIVO => 'Ese residente no está activo.',
            self::DOBLE_RESERVA => 'Ya hay algo programado a esa hora.',
            self::LIMITE_INTERVENCIONES => 'Has alcanzado el límite de intervenciones de hoy.',
            self::TRANSICION_INVALIDA => 'Esa transición de estado no es válida.',
            self::PARTIDA_NO_ENCONTRADA => 'Partida no encontrada.',
            self::BLOQUE_LLENO => 'No hay viviendas libres en el Bloque A.',
            self::DEV_DESHABILITADO => 'Herramientas de desarrollo deshabilitadas.',
            self::RELOJ_NO_REWIND => 'No se puede retroceder el reloj en partida normal.',
            self::SIN_PROXIMO_ENCUENTRO => 'No hay ningún encuentro programado más adelante.',
            self::VALIDACION_FALLIDA => 'Datos no válidos.',
            self::SAVE_CORRUPTO => 'El archivo de partida está dañado.',
            default => 'Ha ocurrido un error.',
        };
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
