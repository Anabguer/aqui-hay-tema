<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Copy para señales narrativas de progresión romántica.
 * Una fuente de verdad: cotilleo, diario, ficha usan la misma clasificación.
 */
final class CopyRomanticProgression
{
    /** @var array<string, list<string>> */
    private const COTILLEO = [
        RomanticProgression::SENAL_SE_FIJA => [
            'Parece que %s le ha pillado el gusto a %s. Nada raro. Todavía.',
            'Últimamente %s aparece mucho por donde está %s. Casualidad, claro.',
            '%s se ha dado cuenta de que %s está en todas sus conversaciones últimamente.',
        ],
        RomanticProgression::SENAL_INTERES_CRECIENTE => [
            'Algo hay entre %s y %s. No sabemos qué, pero el pueblo lo huele.',
            '%s y %s no dejan de coincidir. Ya van demasiadas veces como para ser casual.',
            'Si %s vuelve a buscar a %s, el pueblo entero lo sabe.',
        ],
        RomanticProgression::SENAL_MUTUO => [
            'Las miradas entre %s y %s ya van en las dos direcciones. Aquí hay tema.',
            '%s y %s se están mirando de una manera que no engaña.',
            'Entre %s y %s hay algo que no es solo amistad. Todo el mundo lo ve.',
        ],
        RomanticProgression::SENAL_CITA => [
            'Primera cita entre %s y %s. Esto ya es otra cosa.',
            '%s y %s han quedado fuera del edificio con intención. El pueblo suspira.',
            'Algo se mueve entre %s y %s. Y no es el viento.',
        ],
        RomanticProgression::SENAL_PRE_PAREJA => [
            'Oficial: %s y %s ya son pareja. El secreto mejor guardado del pueblo dura poco.',
            '%s y %s han decidido dar el paso. El pueblo lo celebraba.',
            'Lo de %s y %s por fin tiene nombre. Son pareja.',
        ],
    ];

    /** @var array<string, list<string>> */
    private const DIARIO_PERSONAL = [
        RomanticProgression::SENAL_SE_FIJA => [
            'No sé qué me pasa, pero últimamente no puedo evitar fijarme en %s.',
            'Hoy volví a cruzarme con %s y no pude evitar sonreír.',
            'Me he dado cuenta de que pienso bastante en %s últimamente.',
        ],
        RomanticProgression::SENAL_INTERES_CRECIENTE => [
            'Cada vez que veo a %s se me acelera el corazón un poco.',
            'Últimamente busco excusas para hablar con %s. No sé si es buena señal.',
            'Hoy fue un buen día. Vi a %s y eso me animó.',
        ],
        RomanticProgression::SENAL_MUTUO => [
            'Creo que %s también siente algo por mí. Las miradas no mienten.',
            'Hoy fue especial. %s y yo estuvimos hablando y había algo en el aire.',
            'No puedo dejar de pensar en lo que pasó con %s hoy.',
        ],
    ];

    /** @var list<string> */
    private const FICHA_PISTA_SE_FIJA = [
        'Parece que le empieza a gustar %s.',
        'No para de mirar a %s.',
        'Se le nota cuando está con %s.',
    ];

    /** @var list<string> */
    private const FICHA_PISTA_INTERES = [
        'Tiene algo con %s. Algo que no dice.',
        'Con %s se le nota otra cosa.',
        '%s le quita el sueño últimamente.',
    ];

    /** @var list<string> */
    private const FICHA_PISTA_MUTUO = [
        'Los dos se buscan. No es casualidad.',
        'Hay química entre %s y %s. Y los dos lo saben.',
        'El pueblo entero lo ve menos ellos.',
    ];

    public static function cotilleo(string $senal, string $nombreA, string $nombreB): string
    {
        $pool = self::COTILLEO[$senal] ?? [];
        if ($pool === []) {
            return '';
        }
        $idx = abs(crc32($nombreA . '>' . $nombreB . '>' . $senal)) % count($pool);

        return sprintf($pool[$idx], $nombreA, $nombreB);
    }

    public static function diarioPersonal(string $senal, string $otroNombre): string
    {
        $pool = self::DIARIO_PERSONAL[$senal] ?? [];
        if ($pool === []) {
            return '';
        }
        $idx = abs(crc32($otroNombre . '>' . $senal . '|diario')) % count($pool);

        return sprintf($pool[$idx], $otroNombre);
    }

    public static function fichaPista(string $senal, string $nombreYo, string $nombreOtro): string
    {
        $pool = match ($senal) {
            RomanticProgression::SENAL_SE_FIJA => self::FICHA_PISTA_SE_FIJA,
            RomanticProgression::SENAL_INTERES_CRECIENTE,
            RomanticProgression::SENAL_CITA,
            RomanticProgression::SENAL_PRE_PAREJA => self::FICHA_PISTA_INTERES,
            RomanticProgression::SENAL_MUTUO => self::FICHA_PISTA_MUTUO,
            default => [],
        };
        if ($pool === []) {
            return '';
        }
        $idx = abs(crc32($nombreYo . '>' . $nombreOtro . '|ficha')) % count($pool);

        return sprintf($pool[$idx], $nombreOtro, $nombreYo);
    }

    /**
     * Pista para la ficha: resuelve qué mostrar basándose en la señal.
     */
    public static function pistaFichaRomantica(
        array $partida,
        string $residenteId,
        string $otroId,
        array $cal = []
    ): string {
        $res = RomanticProgression::evaluarDireccion($partida, $residenteId, $otroId, $cal);
        if ($res['senal'] === RomanticProgression::SENAL_NINGUNA) {
            return '';
        }
        $nombreYo = IdentidadPublica::nombre($partida, $residenteId);
        $nombreOtro = IdentidadPublica::nombre($partida, $otroId);

        return self::fichaPista($res['senal'], $nombreYo, $nombreOtro);
    }
}
