<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Cadencia y anti-saturación de Mensajitos (§22.5 del Plan Maestro).
 *
 * Controla:
 * - Presupuesto diario global escalando con √n (referencia 3-5/día ~16 residentes).
 * - Cooldown por vecino para espontáneos/opiniones (~36-48 h).
 * - Prioridad: importante > petición > opinión/dilema > espontáneo.
 * - Cola al día siguiente si no hay hueco (no pérdida).
 * - Compactación de mensajes antiguos resueltos (conserva hilos con seguimiento vivo).
 *
 * Provisional: cifras configurables desde calibración, no canon.
 */
final class MensajitosCadenciaEngine
{
    public const ESTADO_COLA = 'en_cola';

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function config(array $cal): array
    {
        return [
            'presupuesto_base' => (int) CalibracionConfig::get($cal, 'mensajitos.presupuesto_base', 3),
            'presupuesto_escala' => (float) CalibracionConfig::get($cal, 'mensajitos.presupuesto_escala', 0.5),
            'cooldown_vecino_horas' => (int) CalibracionConfig::get($cal, 'mensajitos.cooldown_vecino_horas', 36),
            'compactar_resueltos_dias' => (int) CalibracionConfig::get($cal, 'mensajitos.compactar_resueltos_dias', 7),
            'max_cola' => (int) CalibracionConfig::get($cal, 'mensajitos.max_cola', 10),
        ];
    }

    /**
     * Presupuesto diario según población actual.
     *
     * @param array<string, mixed> $cal
     */
    public static function presupuestoDiario(int $nResidentes, array $cal): int
    {
        $cfg = self::config($cal);
        $base = $cfg['presupuesto_base'];
        $escala = $cfg['presupuesto_escala'];
        $extra = (int) ceil(sqrt(max($nResidentes, 1)) * $escala);
        return $base + $extra;
    }

    /**
     * Cuántos mensajitos espontáneos se han creado hoy.
     */
    public static function creadosHoy(array $partida): int
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $n = 0;
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            if (!BuzonEngine::tieneContenido($m)) {
                continue;
            }
            $canal = (string) ($m['canal'] ?? BuzonEngine::CANAL_BUZON);
            if ($canal !== BuzonEngine::CANAL_BUZON) {
                continue;
            }
            $diaMsg = (int) ($m['dia'] ?? 0);
            if ($diaMsg !== $dia) {
                continue;
            }
            $tipo = (string) ($m['tipo'] ?? '');
            // Solo contar espontáneos, no peticiones automáticas ni feedback
            if (in_array($tipo, ['peticion', 'peticion_resultado', 'respuesta_plan', 'candidato_oferta', 'candidato_en_camino', 'candidato_llegado', 'marcha_intencion', 'marcha_se_queda'], true)) {
                continue;
            }
            $n++;
        }
        return $n;
    }

    /**
     * ¿Tiene este vecino cooldown activo para espontáneos?
     *
     * @param array<string, mixed> $cal
     */
    public static function enCooldownVecino(array $partida, string $residenteId, array $cal): bool
    {
        $cfg = self::config($cal);
        $horas = $cfg['cooldown_vecino_horas'];
        if ($horas <= 0) {
            return false;
        }
        $now = ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24 + (int) ($partida['reloj']['hora_actual'] ?? 0);
        foreach ($partida['mensajitos_historial'] ?? [] as $h) {
            if (!is_array($h)) {
                continue;
            }
            if (($h['residente_id'] ?? '') !== $residenteId) {
                continue;
            }
            if (($h['tipo'] ?? '') === 'espontaneo' || ($h['familia'] ?? '') !== '') {
                $t = ((int) ($h['dia'] ?? 0)) * 24 + (int) ($h['hora'] ?? 0);
                if ($now - $t < $horas) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Registra un mensaje en el historial de cadencia.
     */
    public static function registrar(array &$partida, string $residenteId, string $familia, string $tipo = 'espontaneo', string $clave = ''): void
    {
        $partida['mensajitos_historial'] ??= [];
        $entry = [
            'residente_id' => $residenteId,
            'familia' => $familia,
            'tipo' => $tipo,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
        ];
        if ($clave !== '') {
            $entry['clave'] = $clave;
        }
        $partida['mensajitos_historial'][] = $entry;
    }

    /**
     * Prioridad numérica de una familia (menor = mayor prioridad).
     */
    public static function prioridad(string $familia): int
    {
        switch ($familia) {
            case 'importante':
            case 'respuesta_plan':
            case 'peticion_resultado':
                return 0;
            case 'peticion':
            case 'f_peticion':
            case 'f_presentacion':
            case 'candidato_oferta':
            case 'marcha_intencion':
            case 'f_ritual_contextual':
            case 'f_mediacion':
                return 1;
            case 'f_duda_permanencia':
                return 0;
            case 'f_opinion':
            case 'f_dilema':
            case 'f_alerta_vecinal':
            case 'f_colectivo':
                return 2;
            case 'f_confidencia':
            case 'f_seguimiento':
            case 'f_promesa':
            case 'f_curiosidad_celestine':
                return 3;
            case 'espontaneo':
                return 4;
            default:
                return 3;
        }
    }

    /**
     * ¿Hay hueco en el presupuesto diario?
     *
     * @param array<string, mixed> $cal
     */
    public static function hayPresupuesto(array $partida, array $cal): bool
    {
        $nRes = count(PeticionPuebloEngine::residentes($partida));
        $presupuesto = self::presupuestoDiario($nRes, $cal);
        $creados = self::creadosHoy($partida);
        return $creados < $presupuesto;
    }

    /**
     * Compacta mensajes resueltos antiguos, conservando solo los que tienen
     * seguimiento activo. Provoca poda de guardado (§22.5).
     *
     * @param array<string, mixed> $cal
     * @return int Número de mensajes compactados
     */
    public static function compactarResueltos(array &$partida, array $cal): int
    {
        $cfg = self::config($cal);
        $dias = $cfg['compactar_resueltos_dias'];
        if ($dias <= 0) {
            return 0;
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $umbral = $dia - $dias;
        $compactados = 0;
        foreach ($partida['buzon'] ?? [] as &$m) {
            if (!is_array($m)) {
                continue;
            }
            $estado = (string) ($m['estado'] ?? '');
            if ($estado !== 'resuelto' && $estado !== 'en_espera') {
                continue;
            }
            $diaMsg = (int) ($m['dia'] ?? 0);
            if ($diaMsg > $umbral) {
                continue;
            }
            // Conservar si tiene seguimiento activo
            if (!empty($m['seguimiento_pendiente'])) {
                continue;
            }
            if (!empty($m['acciones']) && is_array($m['acciones']) && $m['acciones'] !== []) {
                continue;
            }
            // Podar: marcar como compactado (el frontend lo oculta)
            $m['_compactado'] = true;
            $compactados++;
        }
        unset($m);
        return $compactados;
    }
}
