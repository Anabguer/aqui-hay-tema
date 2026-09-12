<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Copy en primera persona para pistas sociales/románticas del Diario.
 * Cada familia tiene un pool de frases variadas.
 * NO muestra números ni mecánica interna.
 */
final class CopyPistasSociales
{
    /** @var array<string, list<string>> */
    private const DIARIO = [
        'flechazo' => [
            'No sé qué me ha pasado con %s, pero me he quedado pensando en esa persona.',
            'Algo ha cambiado con %s. No puedo dejar de pensar en ello.',
            'Con %s todo se siente diferente. Y no sé explicar por qué.',
            'Desde que vi a %s algo se me quedó grabado.',
            '%s me ha llegado de una forma que no esperaba.',
        ],
        'quimica_alta' => [
            'Con %s todo fluye demasiado fácil.',
            'No sé qué tiene %s, pero conecto como con nadie.',
            'Cada vez que hablo con %s me llevo una sorpresa.',
            'Con %s las conversaciones salen solas.',
            'Hay algo con %s que no puedo explicar.',
        ],
        'quimica_baja' => [
            'No termino de conectar con %s.',
            'Con %s las conversaciones nunca fluyen.',
            'Algo no cuadra entre %s y yo.',
            'Con %s cuesta encontrar el hilo.',
            'No sé qué pasa, pero con %s no conecto.',
        ],
        'atraccion_asimetrica' => [
            'Creo que me gusta más de lo que yo le gusto.',
            'Pienso bastante en %s, pero no estoy seguro de que sea mutuo.',
            'Las miradas de %s no dicen lo que las mías.',
            'Creo que %s me cae bien, pero yo le caigo mejor.',
            'Noto que %s me busca, pero yo busco a %s.',
        ],
        'conflicto_personal' => [
            'Últimamente %s me saca de quicio.',
            'Con %s todo se convierte en un problema.',
            'No estamos pasando por buen momento %s y yo.',
            'Con %s las cosas se tensan rápido.',
            'Algo va mal entre %s y yo últimamente.',
        ],
        'calentamiento_social' => [
            'Cada vez me cae mejor %s.',
            'Con %s cada vez me siento más cómodo.',
            'Algo ha cambiado con %s últimamente. Para bien.',
            'Con %s todo es más fácil ahora.',
            'Cada vez conecto más con %s.',
        ],
        'enfriamiento_social' => [
            'Antes conectábamos más con %s.',
            'Algo se ha enfriado entre %s y yo.',
            'Con %s ya no es como antes.',
            'Se ha perdido algo entre %s y yo.',
            'Con %s las cosas ya no fluyen como antes.',
        ],
        'estabilidad_pareja_baja' => [
            'Con %s las cosas no están del todo bien.',
            'Noto que algo va mal entre %s y yo.',
            'Preocupan las cosas con %s.',
            'Con %s me siento un poco inseguro.',
            'Las cosas con %s no van como antes.',
        ],
        'primer_rechazo_relevante' => [
            'Me he llevado un pequeño chasco con %s.',
            'Algo se ha roto entre %s y yo. Fue un no que dolió.',
            'Pensaba que había conexión con %s, pero no era así.',
            'Con %s algo se cortó. No lo esperaba.',
            'La cosa con %s no fue como yo creía.',
        ],
        'rechazo_repetido' => [
            'Empiezo a pensar que debería dejar de insistir con %s.',
            'Con %s las cosas no fluyen. Cada vez que intento algo, lo pillo mal.',
            'Parece que %s y yo no estamos en la misma sintonía.',
            'Ya van varias con %s. Algo no funciona.',
            'Creo que es hora de aceptar que con %s no hay roce.',
        ],
    ];

    /** @var array<string, list<string>> */
    private const TITULOS = [
        'flechazo' => ['Me ha pillado', 'No me lo esperaba', 'Con %s todo cambia'],
        'quimica_alta' => ['Algo conecta', 'Una conexión rara', 'Con %s todo es fácil'],
        'quimica_baja' => ['No conecto', 'Algo no cuadra', 'Le cuesta conectar'],
        'atraccion_asimetrica' => ['No es mutuo', 'Creo que es unilateral', 'Solo lo siento yo'],
        'primer_rechazo_relevante' => ['Un chasco', 'Me duele', 'Con %s se rompió algo'],
        'rechazo_repetido' => ['Insisto de más', 'Es hora de parar', 'Con %s no funciona'],
        'conflicto_personal' => ['Tensión', 'No vamos bien', 'Problemas con %s'],
        'calentamiento_social' => ['Mejorando', 'Algo cambia', 'Con %s todo mejor'],
        'enfriamiento_social' => ['Se enfría', 'Ya no es lo mismo', 'Algo se pierde'],
        'estabilidad_pareja_baja' => ['Preocupación', 'No estamos bien', 'Cosas con %s'],
    ];

    public static function texto(string $familia, string $nombreOtro): string
    {
        $pool = self::DIARIO[$familia] ?? [];
        if ($pool === []) {
            return '';
        }
        $idx = abs(crc32($nombreOtro . '>' . $familia . '|pistas')) % count($pool);
        return sprintf($pool[$idx], $nombreOtro, $nombreOtro);
    }

    public static function titulo(string $familia, string $nombreOtro): string
    {
        $pool = self::TITULOS[$familia] ?? [];
        if ($pool === []) {
            return 'Algo entre nosotros';
        }
        $idx = abs(crc32($nombreOtro . '>' . $familia . '|titulo')) % count($pool);
        return sprintf($pool[$idx], $nombreOtro);
    }
}
