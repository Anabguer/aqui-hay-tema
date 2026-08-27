<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Seguimiento de consejos de Celestine (F9, §22.2).
 *
 * "Una intervencion de Celestine no muere al pulsar una respuesta —
 * todo consejo, encargo o evento organizado debe tener cierre posterior."
 *
 * Cuando Celestine da un consejo (ConsejoEngine), se registra con
 * sigue_consejo=null. Despues de N dias (configurable), el motor verifica
 * si el NPC hizo algo coherente con el consejo y genera un Mensajito F9
 * de seguimiento (reaccion/cierre).
 */
final class SeguimientoConsejoEngine
{
    /**
     * Registra un consejo como pendiente de seguimiento.
     *
     * @param array<string, mixed> $partida
     */
    public static function registrar(array &$partida, string $residenteId, string $consejoId, string $tema = 'romance'): void
    {
        $partida['seguimientos_consejo_pendientes'] ??= [];
        $partida['seguimientos_consejo_pendientes'][] = [
            'residente_id' => $residenteId,
            'consejo_id' => $consejoId,
            'tema' => $tema,
            'dia_consejo' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora_consejo' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            'resuelto' => false,
        ];
    }

    /**
     * Evalua si hay seguimientos pendientes que deban cerrarse hoy.
     *
     * @param array<string, mixed> $cal
     * @return list<array{residente_id: string, consejo_id: string, texto: string}>
     */
    public static function evaluarPendientes(array &$partida, array $cal, ?GameLogger $logger = null): array
    {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return [];
        }
        $diasEspera = (int) CalibracionConfig::get($cal, 'mensajitos.seguimiento_consejo_dias', 3);
        $diaActual = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $resultados = [];

        foreach ($partida['seguimientos_consejo_pendientes'] ?? [] as $idx => $s) {
            if (!empty($s['resuelto'])) {
                continue;
            }
            $diaConsejo = (int) ($s['dia_consejo'] ?? 0);
            if ($diaActual - $diaConsejo < $diasEspera) {
                continue;
            }

            $rid = (string) ($s['residente_id'] ?? '');
            $consejoId = (string) ($s['consejo_id'] ?? '');
            $texto = self::generarSeguimiento($partida, $rid, $consejoId, $s['tema'] ?? 'romance');

            if ($texto !== '') {
                $hiloOrigen = self::buscarHiloPorConsejo($partida, $rid, $consejoId);
                $r = BuzonEngine::crear($partida, [
                    'clasificacion' => BuzonEngine::OPORTUNIDAD,
                    'tipo' => 'seguimiento_consejo',
                    'canal' => BuzonEngine::CANAL_BUZON,
                    'de_persona' => $rid,
                    'actores' => [$rid],
                    'texto' => $texto,
                    'acciones' => [],
                    'familia_mensajito' => 'f_seguimiento',
                    'seguimiento_pendiente' => false,
                    'consejo_seguimiento' => $consejoId,
                    'hilo_id' => $hiloOrigen['hilo_id'] ?? null,
                    'mensaje_origen_id' => $hiloOrigen['mensaje_id'] ?? null,
                    'origen' => [
                        'evento_id' => $consejoId,
                        'tipo_evento' => 'seguimiento_consejo',
                        'es_narrativo' => true,
                        'informacion_revelada' => [],
                        '_placeholder' => false,
                    ],
                    '_placeholder_contenido' => false,
                ]);

                if (($r['ok'] ?? false)) {
                    $resultados[] = [
                        'residente_id' => $rid,
                        'consejo_id' => $consejoId,
                        'texto' => $texto,
                    ];
                    DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
                        'mensaje' => $r['mensaje'] ?? null,
                        'origen_evento' => 'seguimiento_consejo',
                    ], $logger, 'SeguimientoConsejoEngine');
                }
            }

            $partida['seguimientos_consejo_pendientes'][$idx]['resuelto'] = true;
        }

        return $resultados;
    }

    /**
     * Genera el texto F9 de seguimiento.
     */
    private static function generarSeguimiento(array &$partida, string $rid, string $consejoId, string $tema): string
    {
        $vars = ['consejo_id' => $consejoId, 'texto' => $tema];
        $seed = 'seguimiento|' . $rid . '|' . $consejoId;
        return MensajitoVoz::linea($partida, 'seguimiento_consejo', $vars, $seed, $rid);
    }

    /**
     * @return array{hilo_id: ?string, mensaje_id: ?string}
     */
    private static function buscarHiloPorConsejo(array $partida, string $rid, string $consejoId): array
    {
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m) || ($m['de_persona'] ?? '') !== $rid) {
                continue;
            }
            $resp = is_array($m['respuesta_celestine'] ?? null) ? $m['respuesta_celestine'] : [];
            if ((string) ($resp['consejo_id'] ?? '') !== $consejoId) {
                continue;
            }
            return [
                'hilo_id' => (string) ($m['hilo_id'] ?? $m['id'] ?? ''),
                'mensaje_id' => (string) ($m['id'] ?? ''),
            ];
        }
        return ['hilo_id' => null, 'mensaje_id' => null];
    }
}