<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Copy banal de rechazo. No es narrativa final. */
final class CopyVoluntad
{
    /** Copy banal de rechazo. No es narrativa final. */
    public const TEXTOS = [
        'lavadora' => 'Tengo que poner la lavadora.',
        'pintar_unias' => 'Me tengo que pintar las uñas.',
        'hoy_no_me_da_la_vida' => 'Hoy no me da la vida.',
        'el_gato_tiene_que_comer' => 'Tengo que dar de comer al gato.',
        'se_me_hace_tarde' => 'Se me hace tarde.',
        'ducha' => 'Me tengo que duchar.',
        'serie' => 'Estoy a mitad de una serie.',
    ];

    /** Variantes ligeras tras cooldown de propuesta (misma voz banal). */
    public const COOLDOWN_IDS = [
        'hoy_no_me_da_la_vida',
        'se_me_hace_tarde',
        'el_gato_tiene_que_comer',
        'lavadora',
        'ducha',
        'serie',
    ];

    public static function texto(?string $copyId): string
    {
        if ($copyId === null || $copyId === '') {
            return self::TEXTOS['hoy_no_me_da_la_vida'];
        }
        return self::TEXTOS[$copyId] ?? self::TEXTOS['hoy_no_me_da_la_vida'];
    }

    public static function rechazoConHablante(string $nombre, ?string $copyId): string
    {
        $frase = rtrim(self::texto($copyId), '.');
        return $nombre . ' ha rechazado la propuesta: "' . $frase . '."';
    }
}
