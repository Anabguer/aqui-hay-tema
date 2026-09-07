<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Handles públicos de lugares para Cotilleos (estilo @cuenta).
 * Fuente canónica: derivado de LugaresCanonicos + lugares.json.
 * Sin duplicación — un solo mapping.
 */
final class LugarHandle
{
    /** @var array<string, string> lug_id → @handle */
    private const MAP = [
        'lug_cafeteria'  => '@cafedelpueblo',
        'lug_biblioteca' => '@bibliotecadelpueblo',
        'lug_gimnasio'   => '@gimnasiodelpueblo',
        'lug_restaurante'=> '@restaurantedelpueblo',
        'lug_parque'     => '@parquedelpueblo',
        'lug_bar'        => '@bardelpueblo',
        'lug_cine'       => '@cinedelpueblo',
        'lug_discoteca'  => '@discotecadelpueblo',
        'lug_bingo'      => '@bingodelpueblo',
        'lug_plaza'      => '@plazadelpueblo',
        'lug_arcade'     => '@arcadedelpueblo',
        'lug_tienda_ropa'=> '@tiendadelpueblo',
        'lug_mirador'    => '@miradordelpueblo',
        'lug_casa'       => '@casadelpueblo',
    ];

    private const DEFAULT = '@puebloconfidencial';

    public static function de(?string $lugarId): string
    {
        if ($lugarId === null || $lugarId === '') {
            return self::DEFAULT;
        }
        return self::MAP[$lugarId] ?? self::DEFAULT;
    }
}
