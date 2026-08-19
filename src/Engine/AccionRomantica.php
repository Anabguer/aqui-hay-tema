<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Acciones románticas autónomas. Prerequisitos por familia.
 * No aplican deltas de barra salvo que la calibración lo pida (hoy: null).
 */
final class AccionRomantica
{
    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function evaluar(
        array $partida,
        string $accionId,
        string $desde,
        string $hacia,
        CatalogStore $store,
        array $cal
    ): array {
        $item = $store->item('acontecimientos', $accionId);
        if ($item === null) {
            return ['ok' => false, 'error' => 'accion_desconocida', 'elegible' => false];
        }
        if (ParentescoVeto::bloqueaRomance($partida, $desde, $hacia, $cal)
            && in_array('no_parentesco_veto', $item['condiciones'] ?? [], true)) {
            return [
                'ok' => true,
                'elegible' => false,
                'motivo' => 'parentesco_veto',
                'quimica_no_atraviesa' => true,
            ];
        }
        $ok = AcontecimientoElegibilidad::cumple($partida, $item, [$desde, $hacia], $cal);
        return [
            'ok' => true,
            'elegible' => $ok['ok'],
            'fallos' => $ok['fallos'],
            'prerequisito_nivel' => $item['prerequisito_nivel'] ?? null,
            'peso' => $item['peso'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function ejecutar(
        array &$partida,
        string $accionId,
        string $desde,
        string $hacia,
        CatalogStore $store,
        array $cal,
        bool $forzar = false
    ): array {
        $ev = self::evaluar($partida, $accionId, $desde, $hacia, $store, $cal);
        if (!($ev['elegible'] ?? false) && !$forzar) {
            return array_merge(['ok' => false, 'error' => 'no_elegible'], $ev);
        }
        if ($accionId === 'flechazo') {
            $prob = CalibracionConfig::get($cal, 'flechazo.probabilidad', null);
            if ($prob === null && !$forzar) {
                return ['ok' => false, 'error' => 'probabilidad_no_calibrada', 'elegible' => $ev['elegible'] ?? false];
            }
            RelacionBitacora::registrar($partida, RelacionBitacora::FLECHAZO, [$desde, $hacia], $desde . '>' . $hacia);
            RelacionEngine::upsertRomance($partida, $desde, $hacia, []);
            $rel = RelacionEngine::obtenerEntre($partida, $desde, $hacia)['romance'];
            $rel['flechazos'] ??= [];
            $rel['flechazos'][] = [
                'desde' => $desde,
                'hacia' => $hacia,
                'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            ];
            RelacionEngine::persistirRomance($partida, $rel);
            $delta = CalibracionConfig::get($cal, 'flechazo.delta_romance', null);
            if (is_numeric($delta)) {
                $actual = RelacionEngine::romanceHacia($partida, $desde, $hacia);
                $nuevo = RelacionBandas::clampRomance(($actual ?? 0) + (int) $delta);
                RelacionEngine::setRomanceHacia($partida, $desde, $hacia, $nuevo);
            }
            SenalRomantica::avisarSiAplica($partida, $desde, $hacia, $cal);
            return ['ok' => true, 'accion' => $accionId, 'delta_romance' => is_numeric($delta) ? (int) $delta : null, 'unilateral' => true, 'no_crea_pareja' => true];
        }
        if ($accionId === 'mandar_flores' || $accionId === 'mandar_mensaje') {
            RelacionBitacora::registrar(
                $partida,
                $accionId === 'mandar_flores' ? RelacionBitacora::REGALO : RelacionBitacora::PLAN_SIGNIFICATIVO,
                [$desde, $hacia],
                $desde . '>' . $hacia
            );
            return ['ok' => true, 'accion' => $accionId, 'delta_romance' => null];
        }
        return ['ok' => true, 'accion' => $accionId, 'delta_romance' => null];
    }
}
