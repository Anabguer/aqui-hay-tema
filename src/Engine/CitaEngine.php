<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** @deprecated Use EncuentroEngine. Wrapper retrocompatible para API cita.* */
final class CitaEngine
{
    public static function programar(
        array &$partida,
        string $residenteA,
        string $residenteB,
        int $dia,
        int $hora,
        ?string $lugarId = null,
        ?string $actividad = null
    ): array {
        $r = EncuentroEngine::programar(
            $partida,
            [$residenteA, $residenteB],
            $dia,
            $hora,
            'romantico',
            $lugarId,
            $actividad
        );
        if ($r['ok'] ?? false) {
            $r['cita'] = $r['encuentro'];
        }
        return $r;
    }

    public static function cambiarEstado(array &$partida, string $citaId, string $nuevoEstado): array
    {
        $id = str_starts_with($citaId, 'cita_') ? 'enc_' . substr($citaId, 5) : $citaId;
        $r = EncuentroEngine::cambiarEstado($partida, $id, $nuevoEstado);
        if ($r['ok'] ?? false) {
            $r['cita'] = $r['encuentro'];
        }
        return $r;
    }

    public static function cancelar(array &$partida, string $citaId): array
    {
        return self::cambiarEstado($partida, $citaId, 'cancelado');
    }

    public static function listarActivas(array $partida): array
    {
        return EncuentroEngine::listarActivos($partida);
    }
}
