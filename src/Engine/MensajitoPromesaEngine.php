<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * F14 — Promesa / testigo (§22).
 *
 * Tras una confidencia (F6), el vecino puede pedir que Celestine le recuerde
 * el tema si vuelve a caer en el mismo patrón. La promesa vive en partida;
 * al repetirse la causa real, dispara un Mensajito F14 enlazado al hilo original.
 */
final class MensajitoPromesaEngine
{
    public static function ensure(array &$partida): void
    {
        $partida['mensajitos_promesas'] ??= [];
    }

    /**
     * Registra promesa vigente tras escuchar una confidencia.
     *
     * @param array<string, mixed> $datos datos_familia del mensaje F6
     */
    public static function registrarDesdeConfidencia(
        array &$partida,
        string $residenteId,
        array $datos,
        string $mensajeId,
        string $hiloId
    ): void {
        self::ensure($partida);
        $clave = self::claveDe($residenteId, $datos);
        foreach ($partida['mensajitos_promesas'] as $p) {
            if (!is_array($p) || !empty($p['cerrada'])) {
                continue;
            }
            if (($p['residente_id'] ?? '') === $residenteId && ($p['clave'] ?? '') === $clave) {
                return;
            }
        }
        $partida['mensajitos_promesas'][] = [
            'residente_id' => $residenteId,
            'clave' => $clave,
            'tema' => (string) ($datos['subtipo'] ?? $datos['emocion'] ?? 'personal'),
            'dia_registro' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'mensaje_origen_id' => $mensajeId,
            'hilo_id' => $hiloId,
            'activa' => true,
            'cerrada' => false,
        ];
        MemoriaEventos::registrar($partida, 'promesa_testigo', [$residenteId], null, 'f_promesa', 'registro');
    }

    /**
     * Si hay promesa activa y la causa se repite, devuelve datos para F14.
     *
     * @param array<string, mixed> $datosCausa datos del candidato F6 que se repetiría
     * @return array<string, mixed>|null
     */
    public static function datosRecuerdoSiAplica(array $partida, string $residenteId, array $datosCausa): ?array
    {
        self::ensure($partida);
        $clave = self::claveDe($residenteId, $datosCausa);
        foreach ($partida['mensajitos_promesas'] as $p) {
            if (!is_array($p) || empty($p['activa']) || !empty($p['cerrada'])) {
                continue;
            }
            if (($p['residente_id'] ?? '') !== $residenteId || ($p['clave'] ?? '') !== $clave) {
                continue;
            }
            $diaReg = (int) ($p['dia_registro'] ?? 0);
            $diaNow = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            if ($diaNow <= $diaReg) {
                return null;
            }
            return [
                'clave' => $clave,
                'tema' => (string) ($p['tema'] ?? 'personal'),
                'promesa_id' => $clave,
                'mensaje_origen_id' => (string) ($p['mensaje_origen_id'] ?? ''),
                'hilo_origen_id' => (string) ($p['hilo_id'] ?? ''),
            ];
        }
        return null;
    }

    public static function cerrarPromesa(array &$partida, string $residenteId, string $clave): void
    {
        self::ensure($partida);
        foreach ($partida['mensajitos_promesas'] as &$p) {
            if (!is_array($p)) {
                continue;
            }
            if (($p['residente_id'] ?? '') === $residenteId && ($p['clave'] ?? '') === $clave) {
                $p['activa'] = false;
                $p['cerrada'] = true;
                $p['dia_cierre'] = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            }
        }
        unset($p);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function claveDe(string $residenteId, array $datos): string
    {
        if (!empty($datos['clave']) && is_string($datos['clave'])) {
            return (string) $datos['clave'];
        }
        $sub = (string) ($datos['subtipo'] ?? $datos['emocion'] ?? 'general');
        $otro = (string) ($datos['otro_id'] ?? '');
        if ($otro !== '') {
            return 'confidencia|' . $sub . '|' . $otro . '|' . $residenteId;
        }
        return 'confidencia|' . $sub . '|' . $residenteId;
    }
}
