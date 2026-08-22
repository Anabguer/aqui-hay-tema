<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Copy de llegada al pueblo para El Cotilleo. Sin datos técnicos de población. */
final class CotilleoLlegadaCopy
{
    /** @var list<string> */
    private const VARIANTES = [
        'Tenemos vecino nuevo: %s acaba de instalarse.',
        '%s se ha mudado al pueblo. Ya hay quien vigile el tendedero.',
        'Ha llegado %s. El pueblo gana un residente más.',
        '%s acaba de poner sus cosas en una vivienda. Bienvenido al barrio.',
        'Nuevo rostro en el pueblo: %s.',
    ];

    public static function texto(string $nombre, string $residenteId): string
    {
        if ($nombre === '') {
            return 'Hay un vecino nuevo en el pueblo.';
        }
        $idx = abs(crc32($residenteId !== '' ? $residenteId : $nombre)) % count(self::VARIANTES);
        return sprintf(self::VARIANTES[$idx], $nombre);
    }
}
