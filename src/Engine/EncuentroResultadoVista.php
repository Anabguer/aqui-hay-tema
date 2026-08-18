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
            $lineas[] = $social['texto'];
            $lineas[] = $romance['texto'];
            if ($conflicto['hay']) {
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

        return [
            'id' => $base['id'],
            'tipo' => $base['tipo'],
            'estado' => $base['estado'],
            'dia' => $base['dia'],
            'hora' => $base['hora'],
            'es_hoy' => $base['es_hoy'] ?? false,
            'lugar' => $base['lugar'],
            'lugar_nombre' => $base['lugar_nombre'],
            'participantes' => $base['participantes'],
            'participantes_nombres' => $base['participantes_nombres'],
            'resultado' => [
                'social' => $social,
                'romance' => $romance,
                'conflicto' => $conflicto,
                'descubrimientos' => $descubrimientos,
                'emociones' => $emociones,
                'consecuencias' => $consecuencias,
                'lineas' => $lineas,
            ],
        ];
    }

    /** @param array<string, mixed> $res */
    private static function canalSocial(array $res): array
    {
        $ds = is_array($res['delta_social'] ?? null) ? $res['delta_social'] : [];
        if ($ds === [] || (($ds['aplicado'] ?? true) === false && !array_key_exists('intensidad', $ds) && !isset($ds['tipo']))) {
            return ['hay' => false, 'delta' => 0, 'tipo' => null, 'texto' => 'Relación social: 0'];
        }
        $n = array_key_exists('intensidad', $ds) ? (int) $ds['intensidad'] : 0;
        $tipo = isset($ds['tipo']) ? (string) $ds['tipo'] : null;
        $texto = 'Relación social: ' . self::signo($n);
        if ($tipo !== null && $tipo !== '' && $tipo !== 'roce') {
            $texto .= ' (' . $tipo . ')';
        }
        return ['hay' => $n !== 0 || ($tipo !== null && $tipo !== ''), 'delta' => $n, 'tipo' => $tipo, 'texto' => $texto];
    }

    /** @param array<string, mixed> $res */
    private static function canalRomance(array $res): array
    {
        $dr = is_array($res['delta_romance'] ?? null) ? $res['delta_romance'] : [];
        if ($dr === [] || (($dr['aplicado'] ?? true) === false)) {
            return ['hay' => false, 'delta' => 0, 'texto' => 'Romance: 0'];
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
        return ['hay' => $n !== 0, 'delta' => $n, 'texto' => 'Romance: ' . self::signo($n)];
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
        $texto = is_numeric($valor)
            ? 'Conflicto/roce: ' . (string) $valor
            : 'Conflicto/roce: sí';
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
            $rid = (string) ($item['residente'] ?? $item['residente_id'] ?? '');
            $campo = (string) ($item['campo'] ?? '');
            if ($rid === '' || $campo === '') {
                continue;
            }
            $valorReal = $item['valor'] ?? self::valorCatalogo($partida, $rid, $campo, $catalog);
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
            if ($valorTxt === '') {
                continue;
            }
            $etiqueta = self::CAMPOS_LABEL[$campo] ?? $campo;
            $nombre = IdentidadPublica::nombre($partida, $rid);
            $texto = $valor === DiscoveryVisibilityResolver::PARCIAL_PLACEHOLDER
                ? "Has descubierto: {$etiqueta} (parcial)."
                : "Has descubierto: {$etiqueta} — {$valorTxt}.";
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
                'texto' => "Estado emocional: {$estado} ({$nombre}).",
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

    private static function signo(int $n): string
    {
        return $n > 0 ? '+' . $n : (string) $n;
    }
}
