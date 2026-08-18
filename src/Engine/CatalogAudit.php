<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Compara contrato PHP V0 vs catálogos JSON vs fichas reales. No modifica identidad. */
final class CatalogAudit
{
    public static function comparar(string $projectRoot): array
    {
        $store = new CatalogStore($projectRoot);
        $pares = [
            'hobbies' => ContractEnums::HOBBY,
            'rasgos' => ContractEnums::RASGO,
            'estilos_sociales' => ContractEnums::ESTILO_SOCIAL,
            'ocupaciones' => ContractEnums::OCUPACION,
        ];
        $jsonKeys = [
            'hobbies' => 'hobbies',
            'rasgos' => 'rasgos',
            'estilos_sociales' => 'estilos_sociales',
            'ocupaciones' => 'profesiones',
        ];

        $out = [];
        foreach ($pares as $nombre => $contrato) {
            $json = $store->ids($jsonKeys[$nombre]);
            $out[$nombre] = [
                'contrato_v0' => $contrato,
                'catalogo_json' => $json,
                'solo_en_json' => array_values(array_diff($json, $contrato)),
                'solo_en_contrato' => array_values(array_diff($contrato, $json)),
            ];
        }

        $ficha = JsonFile::read(rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/data/personajes/per_i03.json');
        $out['rocio'] = [
            'hobby_principal' => $ficha['vida']['hobby_principal'] ?? null,
            'estilo_social' => $ficha['vida']['estilo_social'] ?? null,
            'rasgos_publicos' => $ficha['vida']['rasgos_publicos'] ?? [],
            'en_contrato_v0' => [
                'hobby' => in_array($ficha['vida']['hobby_principal'] ?? '', ContractEnums::HOBBY, true),
                'estilo' => in_array($ficha['vida']['estilo_social'] ?? '', ContractEnums::ESTILO_SOCIAL, true),
                'rasgos' => array_values(array_filter(
                    $ficha['vida']['rasgos_publicos'] ?? [],
                    static fn($r) => in_array($r, ContractEnums::RASGO, true)
                )),
            ],
            'en_catalogo_json' => [
                'hobby' => in_array($ficha['vida']['hobby_principal'] ?? '', $store->ids('hobbies'), true),
                'estilo' => in_array($ficha['vida']['estilo_social'] ?? '', $store->ids('estilos_sociales'), true),
                'rasgos' => array_values(array_filter(
                    $ficha['vida']['rasgos_publicos'] ?? [],
                    static fn($r) => in_array($r, $store->ids('rasgos'), true)
                )),
            ],
        ];

        return $out;
    }

    /** Estimación de variedad para 100 fichas. No genera personajes. */
    public static function combinatoria(): array
    {
        $c = static fn(int $n, int $k): int => $k <= 0 || $k > $n ? 0 : (int) round(exp(self::lnChoose($n, $k)));

        return [
            'rasgos_triples_v0' => $c(10, 3),
            'rasgos_triples_json_actual' => $c(13, 3),
            'rasgos_triples_v2_propuesto_28' => $c(28, 3),
            'hobbies_principales_v0' => 12,
            'personas_por_hobby_si_100_v0' => round(100 / 12, 1),
            'estilos_v0' => 6,
            'personas_por_estilo_si_100' => round(100 / 6, 1),
            'voces_v0' => 5,
            'personas_por_voz_si_100' => 20,
            'nota' => 'Triples únicos no bastan: el checklist de clon usa voz+estilo+≥2 rasgos+edad.',
        ];
    }

    private static function lnChoose(int $n, int $k): float
    {
        $s = 0.0;
        for ($i = 1; $i <= $k; $i++) {
            $s += log($n - $k + $i) - log($i);
        }
        return $s;
    }
}
