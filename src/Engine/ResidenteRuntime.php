<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class ResidenteRuntime
{
    public static function crearDesdeCatalogo(array $catalogo, string $presencia = 'residente'): array
    {
        $id = $catalogo['id'];
        return [
            'catalog_id' => $id,
            'presencia' => $presencia,
            'vivienda_id' => null,
            'flag_nuevo' => $presencia === 'nuevo',
            'estado_en_partida' => $presencia === 'residente' ? 'residente' : 'no_presentado',
            'identidad_publica' => [
                'nombre' => $catalogo['identidad']['nombre'] ?? 'PLACEHOLDER_SIN_NOMBRE',
                'slot_catalogo' => $catalogo['slot'] ?? null,
            ],
            'runtime' => [
                'ocupacion' => $catalogo['vida']['ocupacion'] ?? null,
                'compromisos_recurrentes' => $catalogo['vida']['compromisos_recurrentes'] ?? [],
            ],
            '_placeholder' => false,
        ];
    }

    /** Residente de prueba explícito — NO es personaje canon. */
    public static function crearPlaceholderDev(int $numero = 1): array
    {
        $id = 'per_placeholder_dev_' . str_pad((string) $numero, 2, '0', STR_PAD_LEFT);
        return [
            'catalog_id' => $id,
            'presencia' => 'residente',
            'vivienda_id' => null,
            'flag_nuevo' => true,
            'estado_en_partida' => 'residente',
            'identidad_publica' => [
                'nombre' => "Placeholder Dev {$numero}",
                'slot_catalogo' => null,
            ],
            'runtime' => [
                'ocupacion' => 'autonomo',
                'compromisos_recurrentes' => [],
            ],
            '_placeholder' => true,
            '_placeholder_nota' => 'Residente sintético solo para pruebas de motor. No modifica fichas canon.',
        ];
    }

    public static function catalogoParaRuntime(array $residente, Catalog $catalog): ?array
    {
        $catalogId = $residente['catalog_id'];
        if (!empty($residente['_placeholder'])) {
            return null;
        }
        try {
            return $catalog->loadPersonaje($catalogId);
        } catch (\RuntimeException $ignored) {
            return null;
        }
    }
}
