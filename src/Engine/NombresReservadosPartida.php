<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Nombres ya usados en la partida (sin duplicados entre fichas de catálogo).
 */
final class NombresReservadosPartida
{
    /** @return array<string, string> lowercase_name => catalog_id */
    public static function usados(array $partida, string $root): array
    {
        $usados = [];
        $catalog = new Catalog($root);
        foreach ($partida['residentes'] ?? [] as $id => $res) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            $nombre = '';
            if (is_array($res['identidad_publica'] ?? null)) {
                $nombre = strtolower((string) ($res['identidad_publica']['nombre'] ?? ''));
            }
            if ($nombre === '' && isset($res['identidad']['nombre'])) {
                $nombre = strtolower((string) $res['identidad']['nombre']);
            }
            if ($nombre === '') {
                try {
                    $pj = $catalog->loadPersonaje($id);
                    $nombre = strtolower((string) ($pj['identidad']['nombre'] ?? ''));
                } catch (\Throwable $e) {
                    continue;
                }
            }
            if ($nombre !== '') {
                $usados[$nombre] = $id;
            }
        }
        return $usados;
    }

    public static function idBloqueado(array $usados, string $root, string $id): bool
    {
        $catalog = new Catalog($root);
        try {
            $pj = $catalog->loadPersonaje($id);
        } catch (\Throwable $e) {
            return false;
        }
        $nombre = strtolower((string) ($pj['identidad']['nombre'] ?? ''));
        if ($nombre === '') {
            return false;
        }
        if (!isset($usados[$nombre])) {
            return false;
        }
        return $usados[$nombre] !== $id;
    }

    public static function nombreCatalogo(string $root, string $catalogId): string
    {
        $catalog = new Catalog($root);
        try {
            $pj = $catalog->loadPersonaje($catalogId);
            return (string) ($pj['identidad']['nombre'] ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
