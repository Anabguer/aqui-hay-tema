<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Genera el residente de UNA partida sobre una identidad visual.
 * Reproducible por seed. No reescribe la ficha de catálogo.
 */
final class GeneradorResidente
{
    /**
     * @param array<string, mixed>|null $catalogo
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function generar(
        RngService $rng,
        CatalogStore $store,
        array $cal,
        ?array $catalogo = null,
        ?array $indicadoresForzados = null
    ): array {
        $nHob = (int) CalibracionConfig::get($cal, 'generacion.hobbies_por_residente', 3);
        $nRas = (int) CalibracionConfig::get($cal, 'generacion.rasgos_por_residente', 3);
        $nPp = (int) CalibracionConfig::get($cal, 'generacion.prefs_personalidad_pos', 2);
        $nPn = (int) CalibracionConfig::get($cal, 'generacion.prefs_personalidad_neg', 2);
        $nVp = (int) CalibracionConfig::get($cal, 'generacion.prefs_visual_pos', 2);
        $nVn = (int) CalibracionConfig::get($cal, 'generacion.prefs_visual_neg', 2);

        $hobbies = $rng->pickUnique(self::idsGenerables($store, 'hobbies'), $nHob);
        $rasgos = $rng->pickUnique(self::idsGenerables($store, 'rasgos'), $nRas);

        $poolPers = self::idsGenerables($store, 'rasgos');
        $posP = $rng->pickUnique($poolPers, $nPp);
        $negP = $rng->pickUnique(array_values(array_diff($poolPers, $posP)), $nPn);

        $poolVis = self::idsGenerables($store, 'indicadores_visuales');
        if ($poolVis === []) {
            $poolVis = self::idsGenerables($store, 'etiquetas_look');
        }
        $posV = $rng->pickUnique($poolVis, $nVp);
        $negV = $rng->pickUnique(array_values(array_diff($poolVis, $posV)), $nVn);

        $nHp = (int) CalibracionConfig::get($cal, 'generacion.prefs_hobbies_pos', 2);
        $nHn = (int) CalibracionConfig::get($cal, 'generacion.prefs_hobbies_neg', 2);
        $poolHob = self::idsGenerables($store, 'hobbies');
        $posH = $rng->pickUnique($poolHob, $nHp);
        $negPool = array_values(array_diff($poolHob, $posH, $hobbies));
        $negH = $rng->pickUnique($negPool, $nHn);

        $estilos = self::idsGenerables($store, 'estilos_sociales');
        $estilo = $estilos !== [] ? $rng->pickUnique($estilos, 1) : [];

        $visual = $indicadoresForzados;
        if ($visual === null) {
            $visual = is_array($catalogo) ? IndicadoresVisuales::desdeCatalogo($catalogo, $store) : [];
        }
        $edad = is_array($catalogo) && isset($catalogo['identidad']['edad'])
            ? (int) $catalogo['identidad']['edad']
            : null;

        return [
            'fuente' => 'generado',
            '_provisional_catalogos' => true,
            'hobbies' => array_values($hobbies),
            'rasgos' => array_values($rasgos),
            'indicadores_visuales' => array_values($visual),
            'edad' => $edad,
            'estilo_social' => $estilo !== [] ? (string) $estilo[0] : null,
            'preferencias' => [
                'personalidad_pos' => array_values($posP),
                'personalidad_neg' => array_values($negP),
                'visual_pos' => array_values($posV),
                'visual_neg' => array_values($negV),
                'hobbies_pos' => array_values($posH),
                'hobbies_neg' => array_values($negH),
            ],
        ];
    }

    public static function aplicar(array &$partida, string $residenteId, Catalog $catalog, ?GameLogger $logger = null): array
    {
        $res = $partida['residentes'][$residenteId] ?? null;
        if (!is_array($res)) {
            return ['ok' => false, 'error' => 'residente_inexistente'];
        }
        if (isset($res['runtime']['perfil_partida']) && is_array($res['runtime']['perfil_partida'])) {
            return ['ok' => true, 'perfil' => $res['runtime']['perfil_partida'], 'ya_existia' => true];
        }

        $cal = CalibracionConfig::load($catalog->getRoot());
        $rng = RngService::fromPartida($partida);
        $cat = null;
        if (empty($res['_placeholder'])) {
            try {
                $cat = ResidenteRuntime::catalogoParaRuntime($res, $catalog);
            } catch (\Throwable $ignored) {
                $cat = null;
            }
        }
        $perfil = self::generar($rng, $catalog->store(), $cal, is_array($cat) ? $cat : null);
        $rng->persistToPartida($partida);
        $partida['residentes'][$residenteId]['runtime']['perfil_partida'] = $perfil;

        DomainEventDispatcher::emit($partida, DomainEvents::PERFIL_PARTIDA_GENERADO, [
            'residente_id' => $residenteId,
            'actores' => [$residenteId],
        ], $logger, 'GeneradorResidente::aplicar', [$residenteId]);

        return ['ok' => true, 'perfil' => $perfil, 'ya_existia' => false];
    }

    /**
     * @return list<string>
     */
    public static function idsGenerables(CatalogStore $store, string $catalogo): array
    {
        $ids = [];
        foreach ($store->items($catalogo) as $item) {
            if (!empty($item['alias_de']) || !empty($item['_dev_only'])) {
                continue;
            }
            if (isset($item['id'])) {
                $ids[] = (string) $item['id'];
            }
        }
        return array_values(array_unique($ids));
    }
}
