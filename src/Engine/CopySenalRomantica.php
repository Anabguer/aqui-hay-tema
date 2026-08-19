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
        '%s lleva un rato demasiado pendiente de %s. Aquí hay tema.',
        '%s acaba de mirar a %s de ESA manera. Tú no has visto nada. Bueno, sí.',
        'Algo se le ha encendido a %s con %s. Celestine, toma nota.',
    ];

    /**
     * @var list<string>
     */
    private const TILIN = [
        '%s dice que %s le cae “normal”. Lleva veinte minutos hablando de esa persona.',
        '%s está empezando a mirar a %s como quien no quiere la cosa. Quiere la cosa.',
        'A %s se le escapa una sonrisa tonta cuando sale %s. No es alergia.',
    ];

    public static function texto(string $quien, string $hacia, string $motivo): string
    {
        $pool = $motivo === 'flechazo' ? self::FLECHAZO : self::TILIN;
        $idx = abs(crc32($quien . '>' . $hacia . '>' . $motivo)) % count($pool);
        return sprintf($pool[$idx], $quien, $hacia);
    }
}
