<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PartidaSchema
{
    public const VIVIENDA_IDS = [
        'A01', 'A02', 'A03', 'A04', 'A05', 'A06', 'A07', 'A08',
        'A09', 'A10', 'A11', 'A12', 'A13', 'A14', 'A15', 'A16',
    ];

    public static function fromTemplate(string $projectRoot): array
    {
        $partida = JsonFile::read("{$projectRoot}/data/partida/_plantilla.partida.json");
        $partida['bloque_a']['viviendas'] = BloqueA::viviendasVacias();
        return $partida;
    }

    public static function nueva(string $projectRoot, string $configId, ?string $seed = null): array
    {
        $partida = self::fromTemplate($projectRoot);
        $config = (new Catalog($projectRoot))->loadConfigPrevalidada($configId);

        $partidaId = self::generarId();
        $seedFinal = $seed ?? ($config['seed_sugerida'] ?? $partidaId);

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM);
        $partida['meta']['partida_id'] = $partidaId;
        $partida['meta']['seed'] = $seedFinal;
        $partida['meta']['config_id'] = $configId;
        $partida['meta']['created_at'] = $now;
        $partida['meta']['updated_at'] = $now;
        Reloj::aplicarAlCrear($partida);
        $partida['reloj']['ultima_sesion_iso'] = $now;

        $rng = new RngService($seedFinal);
        $partida['rng'] = [
            'seed' => $seedFinal,
            'state' => $rng->getState(),
        ];

        $partida['celeste']['lugares_desbloqueados'] = LugaresCanonicos::todos();
        if (isset($config['lugares_operativos_dia_1']) && is_array($config['lugares_operativos_dia_1'])) {
            foreach ($config['lugares_operativos_dia_1'] as $lid) {
                if (is_string($lid) && $lid !== '' && !in_array($lid, $partida['celeste']['lugares_desbloqueados'], true)) {
                    $partida['celeste']['lugares_desbloqueados'][] = $lid;
                }
            }
        }
        if (isset($config['bloques_abiertos'])) {
            $partida['celeste']['bloques_abiertos'] = $config['bloques_abiertos'];
        }

        return $partida;
    }

    public static function generarId(): string
    {
        return 'part_' . bin2hex(random_bytes(8));
    }
}
