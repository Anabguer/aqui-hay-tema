<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Seguimiento de consejos de Celestine (F9, §22.2).
 *
 * Verifica el HECHO REAL ocurrido tras la intervención antes de generar copy.
 */
final class SeguimientoConsejoEngine
{
    /**
     * Registra un consejo como pendiente de seguimiento.
     *
     * @param array<string, mixed> $partida
     */
    public static function registrar(
        array &$partida,
        string $residenteId,
        string $consejoId,
        string $tema = 'romance',
        ?string $objetivoId = null,
        ?string $mensajeOrigenId = null
    ): void {
        $partida['seguimientos_consejo_pendientes'] ??= [];
        $partida['seguimientos_consejo_pendientes'][] = [
            'residente_id' => $residenteId,
            'consejo_id' => $consejoId,
            'tema' => $tema,
            'objetivo_id' => $objetivoId,
            'mensaje_origen_id' => $mensajeOrigenId,
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
            if (!is_array($s) || !empty($s['resuelto'])) {
                continue;
            }
            $diaConsejo = (int) ($s['dia_consejo'] ?? 0);
            if ($diaActual - $diaConsejo < $diasEspera) {
                continue;
            }

            $rid = (string) ($s['residente_id'] ?? '');
            $consejoId = (string) ($s['consejo_id'] ?? '');
            $hecho = self::evaluarHechoReal($partida, $rid, $consejoId, $s);
            $texto = self::generarSeguimiento($partida, $rid, $consejoId, $s, $hecho);

            if ($texto !== '') {
                $hiloOrigen = self::buscarHiloOrigen($partida, $rid, $consejoId, $s);
                $eventoId = 'seg_' . $rid . '_' . $consejoId . '_' . $diaConsejo;
                if (CanalDeduplicador::yaPublicado($partida, $eventoId, BuzonEngine::CANAL_BUZON)) {
                    $partida['seguimientos_consejo_pendientes'][$idx]['resuelto'] = true;
                    continue;
                }
                $r = CanalDeduplicador::crearSiAplica($partida, [
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
                    'resultado_hecho' => $hecho['codigo'] ?? 'desconocido',
                    'hilo_id' => $hiloOrigen['hilo_id'] ?? null,
                    'mensaje_origen_id' => $hiloOrigen['mensaje_id'] ?? null,
                    'origen' => [
                        'evento_id' => $eventoId,
                        'tipo_evento' => 'seguimiento_consejo',
                        'es_narrativo' => true,
                        'informacion_revelada' => ['resultado' => $hecho['codigo'] ?? ''],
                        '_placeholder' => false,
                    ],
                    '_placeholder_contenido' => false,
                ]);

                if ($r !== null && ($r['ok'] ?? false)) {
                    $resultados[] = [
                        'residente_id' => $rid,
                        'consejo_id' => $consejoId,
                        'texto' => $texto,
                        'resultado' => $hecho['codigo'] ?? '',
                    ];
                    if (isset($hiloOrigen['hilo_id']) && $hiloOrigen['hilo_id'] !== null) {
                        $partida['mensajitos_hilos'][(string) $hiloOrigen['hilo_id']]['estado'] = 'cerrado_seguimiento';
                    }
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
     * Comprueba qué ocurrió realmente tras el consejo.
     *
     * @param array<string, mixed> $seg
     * @return array{codigo: string, detalle: string}
     */
    public static function evaluarHechoReal(array $partida, string $rid, string $consejoId, array $seg): array
    {
        $objetivo = (string) ($seg['objetivo_id'] ?? '');
        $diaConsejo = (int) ($seg['dia_consejo'] ?? 0);

        if ($consejoId === 'no_es_el_momento') {
            return ['codigo' => 'calma', 'detalle' => 'fui con calma'];
        }

        if ($objetivo === '' || !in_array($consejoId, ['lanzate', 'queda_mas'], true)) {
            $activo = self::huboActividadSocial($partida, $rid, $diaConsejo);
            if ($activo) {
                return ['codigo' => 'actividad', 'detalle' => 'hubo movimiento'];
            }
            return ['codigo' => 'nada', 'detalle' => 'sin novedad'];
        }

        $nombreOtro = IdentidadPublica::nombre($partida, $objetivo);

        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc)) {
                continue;
            }
            $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
            if (!in_array($rid, $parts, true) || !in_array($objetivo, $parts, true)) {
                continue;
            }
            $diaEnc = (int) ($enc['dia'] ?? 0);
            if ($diaEnc < $diaConsejo) {
                continue;
            }
            $est = (string) ($enc['estado'] ?? '');
            if ($est === 'terminado') {
                $res = (string) ($enc['experiencia_por_participante'][$rid]['resultado'] ?? $enc['resultado'] ?? 'normal');
                if (in_array($res, ['malo', 'mal', 'pesimo', 'horrible'], true)) {
                    return ['codigo' => 'mal', 'detalle' => 'con ' . $nombreOtro . ' no fue bien'];
                }
                if (in_array($res, ['bien', 'genial', 'excelente'], true)) {
                    return ['codigo' => 'bien', 'detalle' => 'con ' . $nombreOtro . ' salió bien'];
                }
                return ['codigo' => 'neutral', 'detalle' => 'con ' . $nombreOtro . ' quedamos'];
            }
            if (in_array($est, ['programado', 'en_curso'], true)) {
                return ['codigo' => 'pendiente', 'detalle' => 'tengo algo montado con ' . $nombreOtro];
            }
        }

        foreach ($partida['propuestas_encuentro'] ?? [] as $prop) {
            if (!is_array($prop)) {
                continue;
            }
            $parts = is_array($prop['participantes'] ?? null) ? $prop['participantes'] : [];
            if (!in_array($rid, $parts, true) || !in_array($objetivo, $parts, true)) {
                continue;
            }
            $diaP = (int) ($prop['dia'] ?? 0);
            if ($diaP < $diaConsejo) {
                continue;
            }
            if (($prop['estado'] ?? '') === 'rechazada') {
                return ['codigo' => 'rechazo', 'detalle' => $nombreOtro . ' no quiso'];
            }
        }

        foreach (MemoriaEventos::recientes($partida, [$rid, $objetivo], 8) as $ev) {
            $diaEv = (int) ($ev['dia'] ?? 0);
            if ($diaEv < $diaConsejo) {
                continue;
            }
            $fam = (string) ($ev['familia'] ?? '');
            if (in_array($fam, ['encuentro', 'romance_accion', 'iniciativa_social'], true)) {
                $exp = (string) ($ev['resultado_experiencia'] ?? '');
                if (in_array($exp, ['malo', 'mal'], true)) {
                    return ['codigo' => 'mal', 'detalle' => 'con ' . $nombreOtro . ' no fue como esperaba'];
                }
                return ['codigo' => 'bien', 'detalle' => 'con ' . $nombreOtro . ' hubo algo'];
            }
        }

        return ['codigo' => 'nada', 'detalle' => 'al final no pasó nada con ' . $nombreOtro];
    }

    /**
     * @param array<string, mixed> $seg
     * @param array{codigo: string, detalle: string} $hecho
     */
    private static function generarSeguimiento(
        array &$partida,
        string $rid,
        string $consejoId,
        array $seg,
        array $hecho
    ): string {
        $vars = [
            'consejo_id' => $consejoId,
            'texto' => $hecho['detalle'],
            'resultado' => $hecho['codigo'],
        ];
        $seed = 'seguimiento|' . $rid . '|' . $consejoId . '|' . ($hecho['codigo'] ?? '');
        return MensajitoVoz::linea($partida, 'seguimiento_consejo', $vars, $seed, $rid);
    }

    /**
     * @param array<string, mixed> $seg
     * @return array{hilo_id: ?string, mensaje_id: ?string}
     */
    private static function buscarHiloOrigen(array $partida, string $rid, string $consejoId, array $seg): array
    {
        $mid = (string) ($seg['mensaje_origen_id'] ?? '');
        if ($mid !== '') {
            foreach ($partida['buzon'] ?? [] as $m) {
                if (is_array($m) && (string) ($m['id'] ?? '') === $mid) {
                    return [
                        'hilo_id' => (string) ($m['hilo_id'] ?? $mid),
                        'mensaje_id' => $mid,
                    ];
                }
            }
        }
        return self::buscarHiloPorConsejo($partida, $rid, $consejoId);
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

    private static function huboActividadSocial(array $partida, string $rid, int $desdeDia): bool
    {
        foreach (MemoriaEventos::recientes($partida, [$rid], 6) as $ev) {
            if ((int) ($ev['dia'] ?? 0) >= $desdeDia) {
                return true;
            }
        }
        return false;
    }
}
