<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class Reloj
{
    private const DIAS_SEMANA = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    public static function diaSemana(int $diaPueblo): string
    {
        $idx = ($diaPueblo - 1) % 7;
        return self::DIAS_SEMANA[$idx];
    }

    public static function avanzarHoras(array &$partida, int $horas): void
    {
        if ($horas < 0) {
            throw new \InvalidArgumentException('horas debe ser >= 0');
        }
        $reloj = &$partida['reloj'];
        $total = (int) $reloj['hora_actual'] + $horas;
        while ($total >= 24) {
            $total -= 24;
            $reloj['dia_pueblo'] = (int) $reloj['dia_pueblo'] + 1;
            $reloj['dia_en_temporada'] = (int) $reloj['dia_en_temporada'] + 1;
            $partida['celeste']['encuentros_usados_hoy'] = 0;
        }
        $reloj['hora_actual'] = $total;
        $reloj['ultima_sesion_iso'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM);
    }

    public static function avanzarDias(array &$partida, int $dias): void
    {
        self::avanzarHoras($partida, $dias * 24);
    }

    public static function formatear(array $reloj): string
    {
        $dia = (int) $reloj['dia_pueblo'];
        $hora = str_pad((string) (int) $reloj['hora_actual'], 2, '0', STR_PAD_LEFT);
        return "Día {$dia} (" . self::diaSemana($dia) . ") · {$hora}:00";
    }

    /** Estructura catch-up sin generar eventos narrativos. */
    public static function calcularCatchUpPendiente(array &$partida): array
    {
        $ultima = $partida['reloj']['ultima_sesion_iso'] ?? null;
        if ($ultima === null) {
            return ['segundos' => 0, 'aplicado' => false];
        }
        try {
            $then = new \DateTimeImmutable($ultima);
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $segundos = max(0, $now->getTimestamp() - $then->getTimestamp());
        } catch (\Exception) {
            return ['segundos' => 0, 'aplicado' => false];
        }

        $partida['reloj']['catch_up_pendiente']['segundos_pendientes'] = $segundos;
        $partida['reloj']['catch_up_pendiente']['eventos_pendientes'] = [];
        $partida['reloj']['ultima_sesion_iso'] = $now->format(DATE_ATOM);

        return [
            'segundos' => $segundos,
            'aplicado' => true,
            'nota' => '_placeholder: tiempo registrado; eventos offline no implementados.',
        ];
    }
}
