<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Plantillas estructurales A — fuente: PLANTILLAS_AGENDA_BORRADOR.md */
final class AgendaTemplates
{
    /** @return list<array{dias: list<string>, hora_inicio: int, hora_fin: int, tipo: string}> */
    public static function bloquesTrabajo(string $ocupacion): array
    {
        switch ($ocupacion) {
            case 'oficina':
            case 'docente':
                return [
                    ['dias' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'], 'hora_inicio' => 9, 'hora_fin' => 17, 'tipo' => 'trabajo'],
                ];
            case 'camarero':
                return [
                    ['dias' => ['martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'], 'hora_inicio' => 16, 'hora_fin' => 24, 'tipo' => 'trabajo'],
                ];
            case 'sanitario':
                return [
                    ['dias' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'], 'hora_inicio' => 7, 'hora_fin' => 15, 'tipo' => 'trabajo'],
                ];
            case 'comercio':
                return [
                    ['dias' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'], 'hora_inicio' => 10, 'hora_fin' => 14, 'tipo' => 'trabajo'],
                    ['dias' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'], 'hora_inicio' => 17, 'hora_fin' => 21, 'tipo' => 'trabajo'],
                ];
            case 'estudiante_adulto':
                return [
                    ['dias' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'], 'hora_inicio' => 10, 'hora_fin' => 14, 'tipo' => 'trabajo'],
                    ['dias' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'], 'hora_inicio' => 17, 'hora_fin' => 19, 'tipo' => 'estudio'],
                ];
            case 'autonomo':
                return [
                    ['dias' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'], 'hora_inicio' => 10, 'hora_fin' => 18, 'tipo' => 'trabajo_blando'],
                ];
            case 'jubilado':
                return [];
            default:
                return [
                    ['dias' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'], 'hora_inicio' => 9, 'hora_fin' => 17, 'tipo' => 'trabajo_generico'],
                ];
        }
    }

    /** @return array{hora_inicio: int, hora_fin: int} */
    public static function ventanaSueno(string $ocupacion): array
    {
        switch ($ocupacion) {
            case 'camarero':
                return ['hora_inicio' => 1, 'hora_fin' => 9];
            case 'estudiante_adulto':
            case 'autonomo':
                return ['hora_inicio' => 0, 'hora_fin' => 8];
            default:
                return ['hora_inicio' => 23, 'hora_fin' => 7];
        }
    }

    private static function horaEnRango(int $hora, int $inicio, int $fin): bool
    {
        if ($inicio < $fin) {
            return $hora >= $inicio && $hora < $fin;
        }
        return $hora >= $inicio || $hora < $fin;
    }

    private static function diaEnLista(string $diaSemana, array $dias): bool
    {
        return in_array($diaSemana, $dias, true);
    }
}
