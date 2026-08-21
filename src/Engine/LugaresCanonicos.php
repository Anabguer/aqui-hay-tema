<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Los 9 destinos canónicos V3 — siempre operativos en producto. */
final class LugaresCanonicos
{
    /** @var list<string> */
    public const IDS = [
        'lug_cafeteria',
        'lug_biblioteca',
        'lug_gimnasio',
        'lug_restaurante',
        'lug_parque',
        'lug_bar',
        'lug_cine',
        'lug_discoteca',
        'lug_bingo',
    ];

    /** @var array<string, string> id → zona mapa */
    public const ZONA = [
        'lug_cafeteria' => 'cafeteria',
        'lug_biblioteca' => 'biblioteca',
        'lug_gimnasio' => 'gimnasio',
        'lug_restaurante' => 'restaurante',
        'lug_parque' => 'parque',
        'lug_bar' => 'bar',
        'lug_cine' => 'cine',
        'lug_discoteca' => 'discoteca',
        'lug_bingo' => 'bingo',
    ];

    public static function esCanonico(string $id): bool
    {
        return in_array($id, self::IDS, true);
    }

    public static function operativoEnProducto(string $id): bool
    {
        return self::esCanonico($id);
    }

    /** @return list<string> */
    public static function todos(): array
    {
        return self::IDS;
    }
}
