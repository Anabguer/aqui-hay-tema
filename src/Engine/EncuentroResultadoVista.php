<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * DTO público de un encuentro para play. Se calcula al leer; no guarda narrativa.
 * No incluye RNG, pesos, dealbreakers ni campos ocultos por Discovery.
 */
final class EncuentroResultadoVista
{
    public const CAMPOS_LABEL = [
        'identidad.nombre' => 'Nombre',
        'identidad.edad' => 'Edad',
        'vida.ocupacion' => 'Ocupación',
        'vida.hobby_principal' => 'Hobby principal',
        'vida.hobbies_secundarios' => 'Hobby secundario',
        'vida.rasgos_publicos' => 'Rasgos visibles',
        'vida.rasgos_ocultos' => 'Rasgos ocultos',
    ];

    /**
     * @param array<string, mixed>|null $visConfig
     * @return array<string, mixed>
     */
    public static function de(
        array $partida,
        array $enc,
        ?Catalog $catalog = null,
        ?string $projectRoot = null,
        ?array $visConfig = null
    ): array {
        $base = ResumenDia::vistaEncuentro($partida, $enc, $catalog);
        $terminado = ($enc['estado'] ?? '') === 'terminado';
        $res = is_array($enc['resultado'] ?? null) ? $enc['resultado'] : [];

        $social = self::canalSocial($res);
        $romance = self::canalRomance($res);
        $conflicto = self::canalConflicto($res, $enc);
        $descubrimientos = $terminado
            ? self::descubrimientosPublicos($partida, $enc, $res, $catalog, $projectRoot, $visConfig)
            : [];
        $emociones = $terminado ? self::emocionesPublicas($partida, $res) : [];
        $consecuencias = $terminado ? self::consecuenciasPublicas($res) : [];

        $lineas = [];
        if ($terminado) {
            if (!empty($social['hay']) && (string) ($social['texto'] ?? '') !== '') {
                $lineas[] = $social['texto'];
            }
            if (!empty($romance['hay']) && (string) ($romance['texto'] ?? '') !== '') {
                $lineas[] = $romance['texto'];
            }
            if ($conflicto['hay'] && (string) ($conflicto['texto'] ?? '') !== '') {
                $lineas[] = $conflicto['texto'];
            }
            foreach ($descubrimientos as $d) {
                $lineas[] = $d['texto'];
            }
            foreach ($emociones as $em) {
                $lineas[] = $em['texto'];
            }
            foreach ($consecuencias as $c) {
                $lineas[] = $c['texto'];
            }
        }

        $resultado = [
            'social' => $social,
            'romance' => $romance,
            'conflicto' => $conflicto,
            'descubrimientos' => $descubrimientos,
            'emociones' => $emociones,
            'consecuencias' => $consecuencias,
            'lineas' => $lineas,
        ];
        if (FeatureConfig::isEnabled($partida, 'debug_tools_enabled')) {
            $resultado['debug'] = [
                'social_delta' => $social['delta'] ?? 0,
                'romance_delta' => $romance['delta'] ?? 0,
                'conflicto' => $conflicto['valor'] ?? null,
            ];
        }

        return [
            'id' => $base['id'],
            'tipo' => $base['tipo'],
            'estado' => $base['estado'],
            'dia' => $base['dia'],
            'hora' => $base['hora'],
            'es_hoy' => $base['es_hoy'] ?? false,
            'fecha_corta' => $base['fecha_corta'] ?? null,
            'dia_semana_ui' => $base['dia_semana_ui'] ?? null,
            'lugar' => $base['lugar'],
            'lugar_nombre' => $base['lugar_nombre'],
            'participantes' => $base['participantes'],
            'participantes_nombres' => $base['participantes_nombres'],
            'resultado' => $resultado,
        ];
    }

    /** @param array<string, mixed> $res */
    private static function canalSocial(array $res): array
    {
        $ds = is_array($res['delta_social'] ?? null) ? $res['delta_social'] : [];
        if ($ds === [] || (($ds['aplicado'] ?? true) === false && !array_key_exists('intensidad', $ds) && !isset($ds['tipo']) && !isset($ds['a_hacia_b']))) {
            return ['hay' => false, 'delta' => 0, 'tipo' => null, 'texto' => ''];
        }
        $n = array_key_exists('intensidad', $ds) ? (int) $ds['intensidad'] : 0;
        if ($n === 0 && (isset($ds['a_hacia_b']) || isset($ds['b_hacia_a']))) {
            $n = (int) round(((int) ($ds['a_hacia_b'] ?? 0) + (int) ($ds['b_hacia_a'] ?? 0)) / 2);
        }
        $tipo = isset($ds['tipo']) ? (string) $ds['tipo'] : null;
        if ($tipo === 'reales') {
            $tipo = 'conocidos';
        }
        $texto = '';
        if ($n > 0) {
            $texto = 'Se han llevado mejor.';
        } elseif ($n < 0) {
            $texto = 'Se han llevado peor.';
        }
        $hay = $n !== 0;
        return ['hay' => $hay, 'delta' => $n, 'tipo' => $tipo, 'texto' => $texto];
    }

    /** @param array<string, mixed> $res */
    private static function canalRomance(array $res): array
    {
        $dr = is_array($res['delta_romance'] ?? null) ? $res['delta_romance'] : [];
        if ($dr === [] || (($dr['aplicado'] ?? true) === false)) {
            return ['hay' => false, 'delta' => 0, 'texto' => ''];
        }
        $n = 0;
        if (array_key_exists('vinculo', $dr) && $dr['vinculo'] !== null) {
            $n = (int) $dr['vinculo'];
        }
        if ($n === 0) {
            $a = (int) ($dr['atraccion_a_hacia_b'] ?? 0);
            $b = (int) ($dr['atraccion_b_hacia_a'] ?? 0);
            $n = $a !== 0 ? $a : $b;
        }
        $texto = '';
        if ($n > 0) {
            $texto = 'Ha habido un destello romántico.';
        } elseif ($n < 0) {
            $texto = 'El ambiente romántico se ha enfriado.';
        }
        return ['hay' => $n !== 0, 'delta' => $n, 'texto' => $texto];
    }

    /** @param array<string, mixed> $res */
    private static function canalConflicto(array $res, array $enc): array
    {
        $ds = is_array($res['delta_social'] ?? null) ? $res['delta_social'] : [];
        $dr = is_array($res['delta_romance'] ?? null) ? $res['delta_romance'] : [];
        $conf = $res['conflicto'] ?? null;
        $romConf = $dr['conflicto'] ?? null;
        $hay = ($enc['tipo'] ?? '') === 'conflicto'
            || ($ds['tipo'] ?? '') === 'roce'
            || (($ds['se_soportan'] ?? true) === false)
            || ($conf !== null && $conf !== false && $conf !== '')
            || ($romConf !== null && $romConf !== false && $romConf !== 0 && $romConf !== '0');
        if (!$hay) {
            return ['hay' => false, 'valor' => null, 'texto' => null];
        }
        $valor = $conf ?? $romConf ?? true;
        $texto = 'Ha habido un roce.';
        return ['hay' => true, 'valor' => $valor, 'texto' => $texto];
    }

    /**
     * @param array<string, mixed> $res
     * @param array<string, mixed>|null $visConfig
     * @return list<array{residente: string, residente_nombre: string, campo: string, etiqueta: string, valor: mixed, texto: string}>
     */
    private static function descubrimientosPublicos(
        array $partida,
        array $enc,
        array $res,
        ?Catalog $catalog,
        ?string $projectRoot,
        ?array $visConfig
    ): array {
        $config = $visConfig;
        if ($config === null && is_string($projectRoot) && $projectRoot !== '') {
            $config = DiscoveryVisibilityPolicy::load($projectRoot);
        }
        if ($config === null) {
            $config = ['default' => DiscoveryVisibilityPolicy::SIN_POLITICA, 'por_categoria' => []];
        }

        $items = is_array($res['descubrimientos'] ?? null) ? $res['descubrimientos'] : [];
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rid = (string) ($item['residente'] ?? $item['residente_id'] ?? $item['de'] ?? '');
            $campo = (string) ($item['campo'] ?? '');
            if ($rid === '' || $campo === '') {
                continue;
            }
            $nombre = IdentidadPublica::nombre($partida, $rid);
            $valorReal = $item['valor'] ?? self::valorCatalogo($partida, $rid, $campo, $catalog);
            if (($valorReal === null || $valorReal === true || $valorReal === '') && strpos($campo, ':') !== false) {
                $valorReal = CopyDescubrimiento::idDeCampo($campo);
            }
            $proy = DiscoveryProjection::proyectar(
                $partida,
                $rid,
                [$campo => $valorReal],
                $config
            );
            $row = $proy[$campo] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $valor = $row['valor'] ?? null;
            $visible = $row['visible_jugador'] ?? null;
            if ($visible === false && $valor !== DiscoveryVisibilityResolver::PARCIAL_PLACEHOLDER) {
                continue;
            }
            if ($valor === DiscoveryVisibilityResolver::PARCIAL_PLACEHOLDER) {
                $valorTxt = 'parcial';
            } else {
                $valorTxt = self::valorTexto($valor);
            }
            if ($valorTxt === '' && strpos($campo, ':') === false) {
                continue;
            }
            $etiqueta = CopyDescubrimiento::texto($nombre, $campo, $valor, $catalog !== null ? $catalog->store() : new CatalogStore(dirname(__DIR__, 2)));
            if ($etiqueta === null) {
                $etiquetaCampo = self::CAMPOS_LABEL[$campo] ?? null;
                if ($etiquetaCampo === null && (strpos($campo, ':') !== false || strpos($campo, '_') !== false)) {
                    continue;
                }
                $etiqueta = $etiquetaCampo ?? $campo;
                $texto = $valor === DiscoveryVisibilityResolver::PARCIAL_PLACEHOLDER
                    ? "Has descubierto: {$etiqueta} (parcial)."
                    : "Has descubierto: {$etiqueta} — {$valorTxt}.";
            } else {
                $texto = $etiqueta;
                $etiqueta = 'Pista';
            }
            $out[] = [
                'residente' => $rid,
                'residente_nombre' => $nombre,
                'campo' => $campo,
                'etiqueta' => $etiqueta,
                'valor' => $valor === DiscoveryVisibilityResolver::PARCIAL_PLACEHOLDER ? null : $valor,
                'texto' => $texto,
            ];
        }
        return $out;
    }

    /** @param array<string, mixed> $res */
    private static function emocionesPublicas(array $partida, array $res): array
    {
        $list = $res['emociones'] ?? $res['estados_emocionales'] ?? [];
        if (!is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $em) {
            if (!is_array($em)) {
                continue;
            }
            $estado = (string) ($em['estado'] ?? $em['id'] ?? '');
            if ($estado === '') {
                continue;
            }
            $rid = (string) ($em['residente'] ?? $em['residente_id'] ?? '');
            $nombre = $rid !== '' ? IdentidadPublica::nombre($partida, $rid) : 'Alguien';
            $out[] = [
                'residente' => $rid !== '' ? $rid : null,
                'estado' => $estado,
                'texto' => $nombre . ' ha cambiado de humor.',
            ];
        }
        return $out;
    }

    /** @param array<string, mixed> $res */
    private static function consecuenciasPublicas(array $res): array
    {
        $list = is_array($res['eventos_derivados'] ?? null) ? $res['eventos_derivados'] : [];
        $out = [];
        foreach ($list as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if (!empty($ev['_interno']) || !empty($ev['secreto'])) {
                continue;
            }
            $texto = (string) ($ev['texto_ui'] ?? '');
            if ($texto === '' && isset($ev['tipo']) && is_string($ev['tipo'])) {
                $texto = 'Consecuencia: ' . $ev['tipo'];
            }
            if ($texto === '') {
                continue;
            }
            $out[] = ['texto' => $texto];
        }
        return $out;
    }

    private static function valorCatalogo(array $partida, string $rid, string $campo, ?Catalog $catalog)
    {
        if ($catalog === null) {
            return null;
        }
        $runtime = $partida['residentes'][$rid] ?? null;
        if (!is_array($runtime)) {
            return null;
        }
        try {
            $catalogo = ResidenteRuntime::catalogoParaRuntime($runtime, $catalog);
        } catch (\Throwable $ignored) {
            return null;
        }
        if (!is_array($catalogo)) {
            return null;
        }
        $campos = DiscoveryProjection::deCatalogo($catalogo, $runtime);
        return $campos[$campo] ?? null;
    }

    private static function valorTexto($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if (is_bool($valor)) {
            return $valor ? 'sí' : 'no';
        }
        if (is_array($valor)) {
            $parts = [];
            foreach ($valor as $v) {
                if (is_scalar($v) && (string) $v !== '') {
                    $parts[] = (string) $v;
                }
            }
            return implode(', ', $parts);
        }
        if (is_scalar($valor)) {
            return (string) $valor;
        }
        return '';
    }
}
