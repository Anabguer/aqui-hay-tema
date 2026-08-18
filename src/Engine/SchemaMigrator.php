<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class SchemaMigrator
{
    public const CURRENT_VERSION = 2;

    public static function migrate(array $partida): array
    {
        $version = (int) ($partida['meta']['schema_version'] ?? 1);
        while ($version < self::CURRENT_VERSION) {
            if ($version === 1) {
                $partida = self::v1ToV2($partida);
            } else {
                throw new \RuntimeException("Migración desconocida desde v{$version}");
            }
            $version = (int) $partida['meta']['schema_version'];
        }
        SchemaFields::ensure($partida);
        return $partida;
    }

    private static function v1ToV2(array $partida): array
    {
        $seed = (string) ($partida['meta']['seed'] ?? PartidaSchema::generarId());
        $partida['rng'] = [
            'seed' => $seed,
            'state' => crc32($seed) & 0x7FFFFFFF ?: 1,
        ];

        $encuentros = [];
        foreach ($partida['citas'] ?? [] as $cita) {
            $encId = $cita['id'] ?? 'enc_unknown';
            if (str_starts_with($encId, 'cita_')) {
                $encId = 'enc_' . substr($encId, 5);
            }
            $encuentros[] = [
                'id' => $encId,
                'tipo' => $cita['tipo'] ?? 'romantico',
                'intencion' => 'celeste_organizado',
                'participantes' => $cita['participantes'] ?? [],
                'lugar' => $cita['lugar'] ?? null,
                'hora' => $cita['hora'] ?? null,
                'dia' => $cita['dia'] ?? null,
                'estado' => $cita['estado'] ?? 'programado',
                'actividad' => $cita['actividad'] ?? null,
                'resultado' => null,
                '_placeholder_resultado' => true,
                'reserva_agenda' => ['tipo' => 'encuentro', 'origen' => 'celeste'],
                '_migrado_desde' => 'cita',
            ];
        }
        $partida['encuentros'] = $encuentros;
        unset($partida['citas']);

        $celeste = $partida['celeste'] ?? [];
        $partida['celeste'] = array_merge($celeste, [
            'intervenciones_organizadas_usadas_hoy' => $celeste['encuentros_usados_hoy'] ?? 0,
            'intervenciones_organizadas_max_dia' => null,
            '_config_limite_diario' => [
                '_placeholder' => true,
                'nota' => 'Cifra pendiente de balance. No canonizar.',
                'valor_dev' => $celeste['encuentros_max_dia'] ?? null,
            ],
        ]);
        unset(
            $partida['celeste']['encuentros_usados_hoy'],
            $partida['celeste']['encuentros_max_dia']
        );

        $partida['event_log'] = $partida['event_log'] ?? [];
        $partida['economia'] = [
            'dinero' => ['balance' => $celeste['dinero'] ?? null, 'historial' => []],
            'fama' => ['balance' => $celeste['fama'] ?? null, 'historial' => []],
            '_placeholder' => true,
        ];
        $partida['npc_autonomo'] = [
            '_placeholder' => true,
            'planes_pendientes' => [],
        ];
        $partida['buzon_config'] = ['max_dia' => null, '_placeholder' => true];
        $partida['diario_config'] = ['max_dia' => null, '_placeholder' => true];

        $partida['meta']['schema_version'] = 2;
        return $partida;
    }
}
