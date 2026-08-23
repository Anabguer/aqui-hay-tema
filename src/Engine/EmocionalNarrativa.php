<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Copy y cotilleo derivados del origen emocional real del motor.
 * Sin números internos ni secretos; solo lo observable o deducible.
 */
final class EmocionalNarrativa
{
    public static function esSignificativo(string $estadoId): bool
    {
        $id = EstadoEmocional::canonId($estadoId);
        return in_array($id, [EstadoEmocional::TRISTE, EstadoEmocional::ENFADADO, EstadoEmocional::ALEGRE], true);
    }

    /**
     * Pista breve en ficha (sin exponer reglas internas).
     *
     * @param array<string, mixed> $estado
     */
    public static function pistaFicha(array $estado): ?string
    {
        if (!self::esSignificativo((string) ($estado['id'] ?? ''))) {
            return null;
        }
        $origen = (string) ($estado['origen'] ?? '');
        $ctx = is_array($estado['contexto'] ?? null) ? $estado['contexto'] : [];

        switch ($origen) {
            case 'perder_trabajo':
                return 'Acaba de perder el trabajo.';
            case 'encontrar_trabajo':
                return 'Acaba de encontrar trabajo.';
            case 'rechazo_repetido':
                return 'Le han rechazado planes repetidas veces.';
            case 'encuentro':
            case 'encuentro_intervencion':
                $res = (string) ($ctx['resultado_experiencia'] ?? '');
                if ($res === 'muy_mal') {
                    return 'Le ha sentado muy mal un encuentro reciente.';
                }
                if ($res === 'mal') {
                    return 'Ha salido malhumorada de un encuentro reciente.';
                }
                if (($estado['id'] ?? '') === EstadoEmocional::ALEGRE) {
                    return 'Ha tenido un encuentro que le ha animado.';
                }
                return 'Su estado cambió tras un encuentro reciente.';
            case 'hobby_recuperacion':
            case 'encuentro_y_hobby':
                return 'Un rato con su hobby le ha sentado bien.';
            default:
                return null;
        }
    }

    /**
     * Texto social para El Cotilleo / buzón.
     */
    public static function cotilleoParaOrigen(
        array $partida,
        string $residenteId,
        string $origen,
        array $contexto = []
    ): ?string {
        $nombre = IdentidadPublica::nombre($partida, $residenteId);
        if ($nombre === '') {
            return null;
        }
        $oA = self::oA($partida, $residenteId);

        switch ($origen) {
            case 'perder_trabajo':
                return 'Parece que a ' . $nombre . ' le han soltado del trabajo. Está hecha polv' . $oA . '.';
            case 'encontrar_trabajo':
                return $nombre . ' ha encontrado trabajo. Se le nota más animad' . $oA . '.';
            case 'rechazo_repetido':
                $quien = (string) ($contexto['hacia'] ?? $contexto['quien'] ?? '');
                $nomQ = $quien !== '' ? IdentidadPublica::nombre($partida, $quien) : 'alguien';
                if ($nomQ === '') {
                    $nomQ = 'alguien';
                }
                return $nombre . ' está desanimad' . $oA . ': ' . $nomQ . ' le ha dicho que no demasiadas veces.';
            case 'encuentro':
            case 'encuentro_intervencion':
                $res = (string) ($contexto['resultado_experiencia'] ?? '');
                if ($res === 'muy_mal') {
                    return 'A ' . $nombre . ' le ha sentado fatal un encuentro. Se le nota en la cara.';
                }
                if ($res === 'mal') {
                    return $nombre . ' ha salido de un encuentro con el ánimo por los suelos.';
                }
                return null;
            default:
                return null;
        }
    }

    /**
     * Publica cotilleo en buzón si hay copy y el flag buzón está activo.
     *
     * @param array<string, mixed> $metaExtra
     */
    public static function publicarCotilleo(
        array &$partida,
        string $residenteId,
        string $origen,
        array $contexto = [],
        ?GameLogger $logger = null,
        array $metaExtra = []
    ): ?array {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return null;
        }
        $texto = self::cotilleoParaOrigen($partida, $residenteId, $origen, $contexto);
        if ($texto === null || $texto === '') {
            return null;
        }
        $tipo = (string) ($metaExtra['tipo'] ?? 'estado_emocional');
        $categoria = (string) ($metaExtra['categoria'] ?? CotilleoCategoria::DRAMA);
        $destacado = (bool) ($metaExtra['destacado'] ?? true);

        return BuzonEngine::crear($partida, [
            'clasificacion' => BuzonEngine::COTILLEO,
            'tipo' => $tipo,
            'texto' => $texto,
            'cotilleo_meta' => CotilleoCategoria::meta($categoria, $destacado),
            'de_persona' => $residenteId,
            'actores' => [$residenteId],
            'importancia' => 'relevante',
            'origen' => [
                'evento_id' => $metaExtra['evento_id'] ?? $origen,
                'tipo_evento' => 'estado_emocional',
                'es_narrativo' => false,
                'informacion_revelada' => [
                    'origen_emocional' => $origen,
                    'contexto' => $contexto,
                ],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);
    }

    private static function oA(array $partida, string $rid): string
    {
        $g = (string) ($partida['residentes'][$rid]['identidad_publica']['genero'] ?? '');
        return $g === 'mujer' ? 'a' : 'o';
    }
}
