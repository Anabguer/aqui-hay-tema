<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PartidaDevService
{
    public function __construct(
        private PartidaRepository $repo,
        private GameLogger $logger
    ) {
    }

    public function resetEncuentros(array &$partida): array
    {
        $antes = count($partida['encuentros'] ?? []);
        $partida['encuentros'] = [];
        $partida['celeste']['intervenciones_organizadas_usadas_hoy'] = 0;
        AuditTrail::record($partida, 'dev_reset_encuentros', [], 'PartidaDevService', 'resetEncuentros', $antes, 0);
        return ['ok' => true, 'eliminados' => $antes];
    }

    public function resetRelaciones(array &$partida): array
    {
        $partida['relaciones_sociales'] = [];
        $partida['relaciones_romanticas'] = [];
        AuditTrail::record($partida, 'dev_reset_relaciones', [], 'PartidaDevService', 'resetRelaciones');
        return ['ok' => true];
    }

    public function resetBuzonDiario(array &$partida): array
    {
        $partida['buzon'] = [];
        $partida['diario'] = [];
        AuditTrail::record($partida, 'dev_reset_buzon_diario', [], 'PartidaDevService', 'resetBuzonDiario');
        return ['ok' => true];
    }

    public function eliminarPlaceholder(array &$partida, string $residenteId): array
    {
        if (!str_starts_with($residenteId, 'per_placeholder_dev_')) {
            return GameError::respuesta(GameError::VALIDACION_FALLIDA, ['motivo' => 'solo_placeholders']);
        }
        unset($partida['residentes'][$residenteId]);
        foreach ($partida['bloque_a']['viviendas'] as &$v) {
            if ($v['ocupante_id'] === $residenteId) {
                $v['ocupante_id'] = null;
                $v['estado'] = 'libre';
            }
        }
        unset($v);
        return ['ok' => true];
    }

    public function forzarResolverEncuentro(array &$partida, string $encuentroId, ?GameLogger $logger = null): array
    {
        foreach ($partida['encuentros'] as &$enc) {
            if ($enc['id'] !== $encuentroId) {
                continue;
            }
            $enc['estado'] = 'terminado';
            $enc['resultado'] = EncuentroResolver::resolver($partida, $enc, $logger);
            EncuentroResolver::aplicarResultado($partida, $enc, $enc['resultado'], $logger);
            return ['ok' => true, 'encuentro' => $enc];
        }
        return GameError::respuesta(GameError::VALIDACION_FALLIDA, ['encuentro_id' => $encuentroId]);
    }
}
