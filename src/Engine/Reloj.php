<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class Reloj
{
    public const ZONA = 'Europe/Madrid';

    /** Lunes 17 ago 2026 08:00. Tests CLI congelan aquí para no romper agendas laborales. */
    public const TEST_AHORA = '2026-08-17 08:00:00';

    /** Contrato de inicio: toda partida nueva empieza el día 1 a las 09:00 (reloj de juego). */
    public const HORA_INICIO_PARTIDA = 9;

    private const DIAS_SEMANA = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    private const DIAS_UI = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];
    private const MESES = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    /** @var \DateTimeImmutable|null */
    private static $ahoraFijo = null;

    public static function zona(): \DateTimeZone
    {
        return new \DateTimeZone(self::ZONA);
    }

    public static function fijarAhora(?\DateTimeImmutable $dt): void
    {
        self::$ahoraFijo = $dt;
    }

    public static function ahoraLocal(): \DateTimeImmutable
    {
        if (self::$ahoraFijo instanceof \DateTimeImmutable) {
            return self::$ahoraFijo;
        }
        if (self::esContextoTest()) {
            return new \DateTimeImmutable(self::TEST_AHORA, self::zona());
        }
        return new \DateTimeImmutable('now', self::zona());
    }

    public static function esContextoTest(): bool
    {
        if (getenv('AHT_RELOJ_REAL') === '1') {
            return false;
        }
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        return $script !== '' && preg_match('#(?:^|/)tests/#', $script) === 1;
    }

    /**
     * Nueva partida: fecha ancla opcional desde el dispositivo; reloj de juego fijo día 1 @ 09:00.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed>|null $horaLocalCliente {fecha: Y-m-d, hora: 0-23} — solo fecha se usa
     */
    public static function aplicarAlCrear(array &$partida, ?array $horaLocalCliente = null): void
    {
        $ancla = self::resolverFechaAnclaCreacion($horaLocalCliente);
        $partida['reloj']['zona'] = self::ZONA;
        $partida['reloj']['fecha_ancla'] = $ancla['fecha'];
        $partida['reloj']['dia_pueblo'] = 1;
        $partida['reloj']['dia_en_temporada'] = 1;
        $partida['reloj']['hora_actual'] = self::HORA_INICIO_PARTIDA;
        $partida['reloj']['minuto_actual'] = 0;
        $partida['reloj']['ultima_sesion_iso'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM);
    }

    /**
     * Valida hora local del cliente para creación. Null = inválido/ausente.
     *
     * @param mixed $dato
     * @return array{fecha: string, hora: int}|null
     */
    public static function normalizarHoraLocalCliente($dato): ?array
    {
        if (!is_array($dato)) {
            return null;
        }
        $fecha = $dato['fecha'] ?? null;
        if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$dt instanceof \DateTimeImmutable || $dt->format('Y-m-d') !== $fecha) {
            return null;
        }
        if (!array_key_exists('hora', $dato)) {
            return null;
        }
        $horaRaw = $dato['hora'];
        if (is_string($horaRaw) && $horaRaw !== '' && ctype_digit($horaRaw)) {
            $horaRaw = (int) $horaRaw;
        }
        if (!is_int($horaRaw)) {
            return null;
        }
        if ($horaRaw < 0 || $horaRaw > 23) {
            return null;
        }
        return ['fecha' => $fecha, 'hora' => $horaRaw];
    }

    /**
     * @param array<string, mixed>|null $horaLocalCliente
     * @return array{fecha: string, origen: string}
     */
    public static function resolverFechaAnclaCreacion(?array $horaLocalCliente = null): array
    {
        $cliente = self::normalizarHoraLocalCliente($horaLocalCliente);
        if ($cliente !== null) {
            return ['fecha' => $cliente['fecha'], 'origen' => 'cliente'];
        }
        $now = self::ahoraLocal();
        return [
            'fecha' => $now->format('Y-m-d'),
            'origen' => 'fallback',
        ];
    }

    /**
     * @param array<string, mixed>|null $horaLocalCliente
     * @return array{fecha: string, hora: int, origen: string}
     */
    public static function resolverHoraInicialCreacion(?array $horaLocalCliente = null): array
    {
        $ancla = self::resolverFechaAnclaCreacion($horaLocalCliente);
        return array_merge($ancla, ['hora' => self::HORA_INICIO_PARTIDA]);
    }

    /**
     * Saves antiguos: ancla de calendario para que el día actual caiga en la fecha de hoy.
     *
     * @param array<string, mixed> $partida
     */
    public static function ensure(array &$partida): void
    {
        if (!isset($partida['reloj']) || !is_array($partida['reloj'])) {
            $partida['reloj'] = [];
        }
        $reloj = &$partida['reloj'];
        $reloj['zona'] = is_string($reloj['zona'] ?? null) && $reloj['zona'] !== '' ? $reloj['zona'] : self::ZONA;
        if (!isset($reloj['minuto_actual']) || !is_int($reloj['minuto_actual'])) {
            $reloj['minuto_actual'] = (int) ($reloj['minuto_actual'] ?? 0);
        }
        if ($reloj['minuto_actual'] < 0) {
            $reloj['minuto_actual'] = 0;
        }
        if ($reloj['minuto_actual'] > 59) {
            $reloj['minuto_actual'] = 59;
        }
        if (empty($reloj['fecha_ancla']) || !is_string($reloj['fecha_ancla'])) {
            $dia = max(1, (int) ($reloj['dia_pueblo'] ?? 1));
            $hoy = self::ahoraLocal()->setTime(0, 0, 0);
            $ancla = $hoy->modify('-' . ($dia - 1) . ' days');
            $reloj['fecha_ancla'] = $ancla->format('Y-m-d');
        }
    }

    public static function fechaDeDia(array $reloj, int $diaPueblo): \DateTimeImmutable
    {
        $ancla = is_string($reloj['fecha_ancla'] ?? null) ? $reloj['fecha_ancla'] : '';
        $zonaNombre = is_string($reloj['zona'] ?? null) && $reloj['zona'] !== '' ? $reloj['zona'] : self::ZONA;
        try {
            $tz = new \DateTimeZone($zonaNombre);
        } catch (\Exception $ignored) {
            $tz = self::zona();
        }
        if ($ancla !== '') {
            $base = \DateTimeImmutable::createFromFormat('Y-m-d', $ancla, $tz);
            if ($base instanceof \DateTimeImmutable) {
                $base = $base->setTime(0, 0, 0);
                $delta = $diaPueblo - 1;
                if ($delta !== 0) {
                    $base = $base->modify(($delta > 0 ? '+' : '') . $delta . ' days');
                }
                return $base;
            }
        }
        return (new \DateTimeImmutable('2026-08-17', $tz))->modify('+' . ($diaPueblo - 1) . ' days')->setTime(0, 0, 0);
    }

    public static function instante(array $reloj): \DateTimeImmutable
    {
        $dia = (int) ($reloj['dia_pueblo'] ?? 1);
        $hora = (int) ($reloj['hora_actual'] ?? 0);
        $min = (int) ($reloj['minuto_actual'] ?? 0);
        return self::fechaDeDia($reloj, $dia)->setTime($hora, $min, 0);
    }

    /**
     * Id técnico de agenda (sin tilde: miercoles) para plantillas laborales.
     */
    public static function diaSemana(int $diaPueblo, ?array $reloj = null): string
    {
        if ($reloj !== null && !empty($reloj['fecha_ancla'])) {
            $n = (int) self::fechaDeDia($reloj, $diaPueblo)->format('N');
            return self::DIAS_SEMANA[$n - 1] ?? self::DIAS_SEMANA[0];
        }
        $idx = ($diaPueblo - 1) % 7;
        if ($idx < 0) {
            $idx += 7;
        }
        return self::DIAS_SEMANA[$idx];
    }

    public static function diaSemanaUi(int $diaPueblo, array $reloj): string
    {
        $n = (int) self::fechaDeDia($reloj, $diaPueblo)->format('N');
        return self::DIAS_UI[$n] ?? 'Lunes';
    }

    public static function formatear(array $reloj): string
    {
        $dt = self::instante($reloj);
        $n = (int) $dt->format('N');
        $mes = (int) $dt->format('n');
        $diaUi = self::DIAS_UI[$n] ?? 'Lunes';
        $mesUi = self::MESES[$mes] ?? 'enero';
        return $diaUi . ', ' . ((int) $dt->format('j')) . ' de ' . $mesUi . ' de ' . $dt->format('Y')
            . ' · ' . $dt->format('H:i');
    }

    /** Fecha/hora humanas de un día de pueblo. Sin IDs técnicos tipo D2. */
    public static function formatearDiaHora(array $reloj, int $diaPueblo, int $hora, int $minuto = 0): string
    {
        $fake = $reloj;
        $fake['dia_pueblo'] = max(1, $diaPueblo);
        $fake['hora_actual'] = max(0, min(23, $hora));
        $fake['minuto_actual'] = max(0, min(59, $minuto));
        return self::formatear($fake);
    }

    public static function fechaIso(array $reloj, int $diaPueblo): string
    {
        return self::fechaDeDia($reloj, $diaPueblo)->format('Y-m-d');
    }

    public static function fechaCorta(array $reloj, int $diaPueblo): string
    {
        return self::fechaDeDia($reloj, $diaPueblo)->format('d/m');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function selectorDias(array $reloj, int $maxDias = 7): array
    {
        $hoy = (int) ($reloj['dia_pueblo'] ?? 1);
        $out = [];
        for ($i = 0; $i < $maxDias; $i++) {
            $dia = $hoy + $i;
            $dt = self::fechaDeDia($reloj, $dia);
            $n = (int) $dt->format('N');
            $etiqueta = $i === 0
                ? ('Hoy ' . $dt->format('d/m'))
                : ((self::DIAS_UI[$n] ?? '') . ' ' . $dt->format('d/m'));
            $out[] = [
                'dia_pueblo' => $dia,
                'fecha_iso' => $dt->format('Y-m-d'),
                'fecha_corta' => $dt->format('d/m'),
                'dia_semana' => self::DIAS_SEMANA[$n - 1] ?? 'lunes',
                'dia_semana_ui' => self::DIAS_UI[$n] ?? 'Lunes',
                'es_hoy' => $i === 0,
                'etiqueta' => $etiqueta,
            ];
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function vista(array $reloj): array
    {
        $dt = self::instante($reloj);
        return [
            'texto' => self::formatear($reloj),
            'fecha_iso' => $dt->format('Y-m-d'),
            'fecha_corta' => $dt->format('d/m'),
            'dia_semana' => self::diaSemana((int) ($reloj['dia_pueblo'] ?? 1), $reloj),
            'dia_semana_ui' => self::diaSemanaUi((int) ($reloj['dia_pueblo'] ?? 1), $reloj),
            'dia_pueblo' => (int) ($reloj['dia_pueblo'] ?? 1),
            'hora' => (int) ($reloj['hora_actual'] ?? 0),
            'minuto' => (int) ($reloj['minuto_actual'] ?? 0),
            'zona' => (string) ($reloj['zona'] ?? self::ZONA),
            'fecha_ancla' => (string) ($reloj['fecha_ancla'] ?? ''),
            'proximos_dias' => self::selectorDias($reloj, 7),
        ];
    }

    /** Slot estrictamente posterior al reloj actual (misma hora = pasado). */
    public static function esFuturo(array $reloj, int $dia, int $hora): bool
    {
        $nowD = (int) ($reloj['dia_pueblo'] ?? 1);
        $nowH = (int) ($reloj['hora_actual'] ?? 0);
        return ($dia * 24 + $hora) > ($nowD * 24 + $nowH);
    }

    public static function avanzarHoras(array &$partida, int $horas): void
    {
        if ($horas < 0) {
            throw new \InvalidArgumentException('horas debe ser >= 0');
        }
        self::ensure($partida);
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

    /** Estructura catch-up sin ejecutar (legacy / flag apagado). */
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
        } catch (\Exception $ignored) {
            return ['segundos' => 0, 'aplicado' => false];
        }

        return CatchUpEngine::marcarPlanSinEjecutar($partida, $segundos, $now);
    }
}
