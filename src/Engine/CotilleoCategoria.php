<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Clasificación pública de entradas de El Cotilleo (desde tipo/origen, no desde el texto). */
final class CotilleoCategoria
{
    public const PUEBLO = 'pueblo';
    public const ENCUENTRO = 'encuentro';
    public const DESCUBRIMIENTO = 'descubrimiento';
    public const ROMANCE = 'romance';
    public const DRAMA = 'drama';
    public const RELACION = 'relacion';
    public const COINCIDENCIAS = 'coincidencias';

    /** @var array<string, string> */
    private const ETIQUETAS = [
        self::PUEBLO => 'Llegada',
        self::ENCUENTRO => 'Encuentro',
        self::DESCUBRIMIENTO => 'Pista',
        self::ROMANCE => 'Romance',
        self::DRAMA => 'Drama',
        self::RELACION => 'Relación',
        self::COINCIDENCIAS => 'Coincidencias',
    ];

    /** @var array<string, string> */
    private const ICONOS = [
        self::PUEBLO => '⌂',
        self::ENCUENTRO => '☕',
        self::DESCUBRIMIENTO => '◉',
        self::ROMANCE => '♥',
        self::DRAMA => '⚡',
        self::RELACION => '↔',
        self::COINCIDENCIAS => '◎',
    ];

    /**
     * @param array<string, mixed> $entrada
     * @return array{id: string, etiqueta: string, icono: string, destacado: bool}
     */
    public static function de(array $entrada): array
    {
        $meta = is_array($entrada['cotilleo_meta'] ?? null) ? $entrada['cotilleo_meta'] : [];
        if (isset($meta['categoria']) && is_string($meta['categoria']) && $meta['categoria'] !== '') {
            return self::fila($meta['categoria'], (bool) ($meta['destacado'] ?? false));
        }

        $tipo = (string) ($entrada['tipo'] ?? 'cotilleo');
        $destacado = false;
        $id = self::ENCUENTRO;
        switch ($tipo) {
            case 'llegada_pueblo':
                $id = self::PUEBLO;
                $destacado = true;
                break;
            case 'cotilleo_autonomo':
                $id = self::PUEBLO;
                break;
            case 'cotilleo_patron':
                $id = self::COINCIDENCIAS;
                break;
            case 'discusion':
                $id = self::DRAMA;
                $destacado = true;
                break;
            case 'senal_romantica':
                $id = self::ROMANCE;
                $destacado = true;
                break;
            default:
                $id = self::ENCUENTRO;
                break;
        }

        return self::fila($id, $destacado);
    }

    /** @return array{categoria: string, destacado: bool} */
    public static function meta(string $categoria, bool $destacado = false): array
    {
        return ['categoria' => $categoria, 'destacado' => $destacado];
    }

    /**
     * @return array{id: string, etiqueta: string, icono: string, destacado: bool}
     */
    private static function fila(string $id, bool $destacado): array
    {
        return [
            'id' => $id,
            'etiqueta' => self::ETIQUETAS[$id] ?? 'Cotilleo',
            'icono' => self::ICONOS[$id] ?? '✦',
            'destacado' => $destacado,
        ];
    }
}
