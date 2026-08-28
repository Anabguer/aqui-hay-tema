<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * F11 — Mediación / reparación (§22.3 reformulada).
 *
 * Tras un roce real, un vecino le pide a Celestine que ayude a recomponer
 * el trato con alguien (directo o como tercero que conoce a ambos).
 */
final class MensajitoMediacionEngine
{
    public const FAMILIA = 'f_mediacion';

    /**
     * @return array{familia: string, peso: int, datos: array<string, mixed>}|null
     */
    public static function candidato(array $partida, string $rid): ?array
    {
        $conflictos = self::conflictosRecientes($partida);
        if ($conflictos === []) {
            return null;
        }
        shuffle($conflictos);
        foreach ($conflictos as $c) {
            $datos = self::datosDesdeConflicto($partida, $rid, $c);
            if ($datos === null) {
                continue;
            }
            $clave = (string) ($datos['clave'] ?? '');
            if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, self::FAMILIA, $clave)) {
                continue;
            }
            return ['familia' => self::FAMILIA, 'peso' => 2, 'datos' => $datos];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function texto(array &$partida, string $rid, array $datos): string
    {
        $vars = [
            'otro' => (string) ($datos['otro_nombre'] ?? 'alguien'),
            'historial' => (string) ($datos['historial'] ?? ''),
            'texto' => (string) ($datos['subtipo'] ?? 'mediacion'),
        ];
        return MensajitoVoz::linea(
            $partida,
            self::FAMILIA,
            $vars,
            self::FAMILIA . '|' . $rid . '|' . ($datos['otro_id'] ?? '') . '|' . ($datos['subtipo'] ?? ''),
            $rid
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function mediar(array &$partida, string $mensajeId): array
    {
        $mensaje = BuzonEngine::buscar($partida, $mensajeId);
        if ($mensaje === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $datos = is_array($mensaje['datos_familia'] ?? null) ? $mensaje['datos_familia'] : [];
        $rid = (string) ($mensaje['de_persona'] ?? '');
        $otro = (string) ($datos['otro_id'] ?? '');
        if ($rid === '' || $otro === '' || !isset($partida['residentes'][$otro])) {
            return ['ok' => false, 'error' => 'sin_par'];
        }
        MensajitoConsejoEngine::cerrarHiloPublico($partida, $mensajeId, ['accion' => 'mediar']);
        return [
            'ok' => true,
            'preset_organizar' => [
                'modo' => 'pareja',
                'a' => $rid,
                'b' => $otro,
                'tipo' => PropuestaNivel::QUEDAR,
                'intencion' => 'reparacion',
                'participantes' => [$rid, $otro],
                'lugar' => PeticionPuebloEngine::LUGAR_PRESENTACION,
            ],
            'mensaje_ui' => 'Les organizo un quedar para hablarlo con calma.',
        ];
    }

    /**
     * @return list<array{persona_a: string, persona_b: string, dia: int}>
     */
    private static function conflictosRecientes(array $partida): array
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $out = [];
        $seen = [];
        foreach ($partida['bitacora_relaciones'] ?? [] as $h) {
            if (!is_array($h)) {
                continue;
            }
            if ((string) ($h['tipo'] ?? '') !== RelacionBitacora::DISCUSION_FUERTE) {
                continue;
            }
            $hDia = (int) ($h['fecha']['dia'] ?? 0);
            if ($dia - $hDia > 5 || $hDia <= 0) {
                continue;
            }
            $partes = is_array($h['participantes'] ?? null) ? $h['participantes'] : [];
            if (count($partes) < 2) {
                continue;
            }
            $a = (string) $partes[0];
            $b = (string) $partes[1];
            $key = $a < $b ? $a . '|' . $b : $b . '|' . $a;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['persona_a' => $a, 'persona_b' => $b, 'dia' => $hDia];
        }
        return $out;
    }

    /**
     * @param array{persona_a: string, persona_b: string, dia: int} $conflicto
     * @return array<string, mixed>|null
     */
    private static function datosDesdeConflicto(array $partida, string $rid, array $conflicto): ?array
    {
        $a = (string) $conflicto['persona_a'];
        $b = (string) $conflicto['persona_b'];
        if ($rid === $a) {
            return self::packDatos($partida, $rid, $b, 'directo', (int) $conflicto['dia']);
        }
        if ($rid === $b) {
            return self::packDatos($partida, $rid, $a, 'directo', (int) $conflicto['dia']);
        }
        if (!RelacionEngine::seConocen($partida, $rid, $a) || !RelacionEngine::seConocen($partida, $rid, $b)) {
            return null;
        }
        $socA = (float) (RelacionEngine::socialHacia($partida, $rid, $a)['valor'] ?? 0);
        $socB = (float) (RelacionEngine::socialHacia($partida, $rid, $b)['valor'] ?? 0);
        if ($socA < 15 || $socB < 15) {
            return null;
        }
        $otro = $socA >= $socB ? $b : $a;
        return self::packDatos($partida, $rid, $otro, 'tercero', (int) $conflicto['dia'], $a, $b);
    }

    /**
     * @return array<string, mixed>
     */
    private static function packDatos(
        array $partida,
        string $rid,
        string $otroId,
        string $subtipo,
        int $diaConflicto,
        ?string $parA = null,
        ?string $parB = null
    ): array {
        $historial = HistorialPar::contextoNarrativo($partida, $rid, $otroId);
        $clave = self::FAMILIA . '|' . $subtipo . '|' . min($rid, $otroId) . '|' . max($rid, $otroId) . '|' . $diaConflicto;
        $datos = [
            'subtipo' => $subtipo,
            'otro_id' => $otroId,
            'otro_nombre' => IdentidadPublica::nombre($partida, $otroId),
            'historial' => $historial,
            'dia_conflicto' => $diaConflicto,
            'clave' => $clave,
        ];
        if ($parA !== null && $parB !== null) {
            $datos['conflicto_par'] = [$parA, $parB];
        }
        return $datos;
    }
}
