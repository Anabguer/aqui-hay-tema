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
        if (in_array($accionId, ['flechazo', 'mandar_flores', 'mandar_mensaje'], true)) {
            $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
            foreach ([$desde, $hacia] as $rid) {
                if (EncuentroEngine::residenteOcupadoEnHorario($partida, $rid, $dia, $hora)) {
                    return [
                        'ok' => false,
                        'error' => 'ocupado_encuentro',
                        'residente_id' => $rid,
                        'elegible' => $ev['elegible'] ?? false,
                    ];
                }
            }
        }
        if ($accionId === 'flechazo') {
            $prob = CalibracionConfig::get($cal, 'flechazo.probabilidad', null);
            if ($prob === null && !$forzar) {
                return ['ok' => false, 'error' => 'probabilidad_no_calibrada', 'elegible' => $ev['elegible'] ?? false];
            }
            // El flechazo es un hito único por pareja (importancia "hito"):
            // si ya ocurrió entre los dos, repetir el azar no repite el hito
            // ni su entrada de diario ni su delta de romance.
            if (RelacionBitacora::tienenHito($partida, $desde, $hacia, RelacionBitacora::FLECHAZO)) {
                // FASE 1: el flechazo ya existe; el paso canonico es intentar la primera cita autonoma.
                IniciativaRomantica::intentarPrimeraCita($partida, $desde, $hacia, $cal);
                return [
                    'ok' => true,
                    'accion' => $accionId,
                    'flechazo_ya_registrado' => true,
                    'delta_romance' => null,
                    'unilateral' => true,
                    'no_crea_pareja' => true,
                ];
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
