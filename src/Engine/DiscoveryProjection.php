<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Proyección de ficha según políticas de visibilidad.
 * No asigna secretos: si default = sin_politica, el valor sale intacto.
 */
final class DiscoveryProjection
{
    /** Campos que el motor sabe proyectar sin decidir el contenido creativo. */
    public const CAMPOS_CONTRATO = [
        'identidad.nombre',
        'identidad.edad',
        'vida.ocupacion',
        'vida.hobby_principal',
        'vida.hobbies_secundarios',
        'vida.rasgos_publicos',
        'vida.rasgos_ocultos',
    ];

    public static function deCatalogo(array $catalogo, array $runtime): array
    {
        return [
            'identidad.nombre' => $catalogo['identidad']['nombre'] ?? ($runtime['identidad_publica']['nombre'] ?? null),
            'identidad.edad' => $catalogo['identidad']['edad'] ?? null,
            'vida.ocupacion' => $catalogo['vida']['ocupacion'] ?? ($runtime['runtime']['ocupacion'] ?? null),
            'vida.hobby_principal' => $catalogo['vida']['hobby_principal'] ?? null,
            'vida.hobbies_secundarios' => $catalogo['vida']['hobbies_secundarios'] ?? [],
            'vida.rasgos_publicos' => $catalogo['vida']['rasgos_publicos'] ?? [],
            'vida.rasgos_ocultos' => $catalogo['vida']['rasgos_ocultos'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $campos campo => valor_real
     * @return array<string, array>
     */
    public static function proyectar(
        array $partida,
        string $residenteId,
        array $campos,
        array $config,
        array $eventosAlcanzados = []
    ): array {
        $resolver = new DiscoveryVisibilityResolver($config, $partida);
        $out = [];
        foreach ($campos as $campo => $valor) {
            $out[(string) $campo] = $resolver->sanitizarValor([
                'residente_id' => $residenteId,
                'campo' => (string) $campo,
                'valor_real' => $valor,
                'eventos_alcanzados' => $eventosAlcanzados,
            ]);
        }
        return $out;
    }

    /** @param array<string, array> $proyeccion */
    public static function valorSiVisible(array $proyeccion, string $campo, $fallback = null)
    {
        $row = $proyeccion[$campo] ?? null;
        if (!is_array($row)) {
            return $fallback;
        }
        if (($row['visible_jugador'] ?? null) === false) {
            return $row['valor'] ?? $fallback;
        }
        return $row['valor'] ?? $fallback;
    }
}
