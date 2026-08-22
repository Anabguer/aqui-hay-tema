<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Cotilleo de señal romántica. Sin números. Quién siente algo por quién. */
final class CopySenalRomantica
{
    /**
     * @var list<string>
     */
    private const FLECHAZO = [
        '%s acaba de mirar a %s de ESA manera. Tú no has visto nada. Bueno, sí.',
        'Algo se le ha encendido a %s con %s. Celestine, toma nota.',
        'A %s se le ha ido la pinza un poco con %s. Aquí hay tema.',
    ];

    /**
     * @var list<string>
     */
    private const TILIN = [
        '%s lleva un rato demasiado pendiente de %s. Aquí hay tema.',
        '%s dice que %s le cae “normal”. Lleva veinte minutos hablando de esa persona.',
        '%s está empezando a mirar a %s como quien no quiere la cosa. Quiere la cosa.',
        'A %s se le escapa una sonrisa tonta cuando sale %s. No es alergia.',
    ];

    /**
     * @var list<string>
     */
    private const FLECHAZO_HIST = [
        'Algo se le encendió a %s con %s más temprano.',
        '%s miró a %s de ESA manera hace un rato. Tú no viste nada. Bueno, sí.',
        'A %s ya le ha empezado a interesar %s. Aquí hay tema.',
    ];

    /**
     * @var list<string>
     */
    private const TILIN_HIST = [
        'Desde hace un rato a %s le da vueltas %s.',
        'Por lo visto a %s le ha empezado a interesar %s.',
        '%s lleva un rato demasiado pendiente de %s. Aquí hay tema.',
    ];

    public static function texto(string $quien, string $hacia, string $motivo): string
    {
        return self::textoDe($quien, $hacia, $motivo, false);
    }

    /**
     * Copy al mostrar un aviso ya emitido: enmarca en pasado si ocurrió antes que el reloj actual.
     *
     * @param array<string, mixed> $reloj
     * @param array<string, mixed>|null $tsJuego
     */
    public static function textoParaVista(
        string $quien,
        string $hacia,
        string $motivo,
        array $reloj,
        ?array $tsJuego = null
    ): string {
        $diaNow = (int) ($reloj['dia_pueblo'] ?? 1);
        $horaNow = (int) ($reloj['hora_actual'] ?? 0);
        $ts = is_array($tsJuego) ? $tsJuego : [];
        $diaTs = (int) ($ts['dia'] ?? $diaNow);
        $horaTs = (int) ($ts['hora'] ?? $horaNow);
        $historico = $diaTs < $diaNow || ($diaTs === $diaNow && $horaTs < $horaNow);

        return self::textoDe($quien, $hacia, $motivo, $historico);
    }

    /**
     * Reenmarca el copy guardado según el reloj actual (cotilleo / Aquí hay tema).
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $msg
     */
    public static function textoDeMensaje(array $partida, array $msg): string
    {
        $texto = trim((string) ($msg['texto'] ?? ''));
        $origen = is_array($msg['origen'] ?? null) ? $msg['origen'] : [];
        $rev = is_array($origen['informacion_revelada'] ?? null) ? $origen['informacion_revelada'] : [];
        $desde = (string) ($rev['desde'] ?? $msg['de_persona'] ?? '');
        $hacia = (string) ($rev['hacia'] ?? '');
        if ($desde === '' || $hacia === '') {
            return $texto;
        }
        $motivo = (string) ($rev['motivo'] ?? 'tilin');
        $ts = is_array($msg['ts_juego'] ?? null) ? $msg['ts_juego'] : null;
        if ($ts === null && is_array($rev['ts_juego'] ?? null)) {
            $ts = $rev['ts_juego'];
        }
        $nomDe = IdentidadPublica::nombre($partida, $desde);
        $nomA = IdentidadPublica::nombre($partida, $hacia);

        return self::textoParaVista($nomDe, $nomA, $motivo !== '' ? $motivo : 'tilin', $partida['reloj'] ?? [], $ts);
    }

    private static function textoDe(string $quien, string $hacia, string $motivo, bool $historico): string
    {
        switch ($motivo) {
            case 'flechazo':
                $pool = $historico ? self::FLECHAZO_HIST : self::FLECHAZO;
                break;
            default:
                $pool = $historico ? self::TILIN_HIST : self::TILIN;
                break;
        }
        $idx = abs(crc32($quien . '>' . $hacia . '>' . $motivo . ($historico ? '|hist' : ''))) % count($pool);

        return sprintf($pool[$idx], $quien, $hacia);
    }
}
