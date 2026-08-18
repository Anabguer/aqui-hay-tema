<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * estado_emocional + pack → expression_id. Canon: fallback neutral; sin fórmulas de mapeo.
 * Personalidad y contexto se aceptan y NO se aplican.
 * No genera PNG. Si falta el asset o la versión de identidad no coincide → neutral.
 */
final class ExpressionResolver
{
    /**
     * @param array{
     *   estado_emocional_id?: string,
     *   intensidad?: mixed,
     *   personalidad?: array,
     *   contexto?: array,
     *   override_dev?: ?string,
     *   expresion_solicitada?: ?string,
     *   pack?: ?array,
     *   pack_id?: ?string
     * } $in
     */
    public static function resolver(array $in, VisualPackStore $store, CatalogStore $catalog): array
    {
        $estado = (string) ($in['estado_emocional_id'] ?? EstadoEmocional::NEUTRO);
        if ($estado === 'neutral') {
            $estado = EstadoEmocional::NEUTRO;
        }
        $pack = $in['pack'] ?? null;
        if (!is_array($pack) && !empty($in['pack_id'])) {
            $pack = $store->pack((string) $in['pack_id']);
        }

        $solicitada = self::primeraNoVacia([
            $in['override_dev'] ?? null,
            $in['expresion_solicitada'] ?? null,
            is_array($in['contexto'] ?? null) ? ($in['contexto']['expresion_solicitada'] ?? null) : null,
        ]);

        $motivoSolicitud = !empty($in['override_dev']) ? 'override_dev' : 'solicitada';

        if ($solicitada !== null) {
            return self::conFallback($solicitada, $estado, $pack, $store, $motivoSolicitud);
        }

        $candidato = self::mapear($estado, $pack, $catalog);
        $motivo = $candidato === ExpresionVisual::NEUTRAL ? 'sin_mapeo' : 'mapeo_catalogo';
        return self::conFallback($candidato, $estado, $pack, $store, $motivo);
    }

    private static function mapear(string $estado, ?array $pack, CatalogStore $catalog): string
    {
        $packMap = is_array($pack['mapeo_estado_a_expresion'] ?? null) ? $pack['mapeo_estado_a_expresion'] : [];
        unset($packMap['_placeholder'], $packMap['_comentario']);
        if (isset($packMap[$estado]) && is_string($packMap[$estado]) && $packMap[$estado] !== '') {
            return $packMap[$estado];
        }

        $global = $catalog->read('mapeo_estado_expresion_placeholder.json');
        $mapeo = is_array($global['mapeo'] ?? null) ? $global['mapeo'] : [];
        if (isset($mapeo[$estado]) && is_string($mapeo[$estado]) && $mapeo[$estado] !== '') {
            return $mapeo[$estado];
        }

        return ExpresionVisual::NEUTRAL;
    }

    private static function conFallback(
        string $solicitada,
        string $estado,
        ?array $pack,
        VisualPackStore $store,
        string $motivoBase
    ): array {
        if (self::usable($store, $pack, $solicitada)) {
            return self::salida($solicitada, $estado, false, $motivoBase, $solicitada, $pack, $store);
        }

        $neutralOk = self::usable($store, $pack, ExpresionVisual::NEUTRAL);
        $motivo = $pack === null ? 'sin_pack' : 'asset_faltante';
        return self::salida(
            ExpresionVisual::NEUTRAL,
            $estado,
            true,
            $motivo,
            $solicitada,
            $pack,
            $store,
            !$neutralOk
        );
    }

    private static function usable(VisualPackStore $store, ?array $pack, string $expressionId): bool
    {
        if ($expressionId === '' || !ExpresionVisual::idFormatoValido($expressionId)) {
            return false;
        }
        if (!is_array($pack)) {
            return false;
        }
        return $store->disponible($pack, $expressionId);
    }

    private static function primeraNoVacia(array $vals): ?string
    {
        foreach ($vals as $v) {
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }
        return null;
    }

    private static function salida(
        string $expressionId,
        string $estado,
        bool $fallback,
        string $motivo,
        string $solicitada,
        ?array $pack,
        VisualPackStore $store,
        bool $placeholderTecnico = false
    ): array {
        $asset = is_array($pack) ? $store->asset($pack, $expressionId) : null;
        if ($asset === null) {
            $asset = $store->placeholderTecnico($expressionId);
            $placeholderTecnico = true;
        }
        return [
            'expression_id' => $expressionId,
            'solicitada' => $solicitada,
            'estado_emocional_id' => $estado,
            'fallback' => $fallback,
            'motivo' => $motivo,
            'personalidad_aplicada' => false,
            'contexto_aplicado' => false,
            '_placeholder_mapeo' => $motivo === 'mapeo_catalogo',
            'placeholder_tecnico' => $placeholderTecnico,
            'asset' => $asset,
            'visual_identity_version' => is_array($pack) ? (int) ($pack['visual_identity_version'] ?? 0) : 0,
            'pack_id' => is_array($pack) ? ($pack['pack_id'] ?? $pack['id'] ?? null) : null,
        ];
    }
}
