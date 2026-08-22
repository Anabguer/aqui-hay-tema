<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Registra qué personajes del pool de 200 ya aparecieron en esta partida (residente, candidato, etc.).
 * Un personaje que ya apareció no vuelve al pool de candidatos aunque haya abandonado el pueblo.
 */
final class HistorialPersonajesPartida
{
    public const CAMPO = 'ya_aparecieron';

    /**
     * @param array<string, mixed> $partida
     */
    public static function ensure(array &$partida): void
    {
        CandidatoLlegadaEngine::ensure($partida);
        if (!isset($partida['llegadas'][self::CAMPO]) || !is_array($partida['llegadas'][self::CAMPO])) {
            $partida['llegadas'][self::CAMPO] = [];
        }
        self::reconciliar($partida);
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function marcar(array &$partida, string $catalogId): void
    {
        if ($catalogId === '' || !PoolJugableCanon::esIdCanonico($catalogId)) {
            return;
        }
        self::ensure($partida);
        $partida['llegadas'][self::CAMPO][$catalogId] = true;
    }

    public static function yaAparecio(array $partida, string $catalogId): bool
    {
        if ($catalogId === '') {
            return false;
        }
        $map = $partida['llegadas'][self::CAMPO] ?? null;
        if (!is_array($map)) {
            return self::inferirAparecidoLegacy($partida, $catalogId);
        }
        if (!empty($map[$catalogId])) {
            return true;
        }
        return self::inferirAparecidoLegacy($partida, $catalogId);
    }

    /**
     * @return list<string>
     */
    public static function idsAparecidos(array $partida): array
    {
        self::ensure($partida);
        return array_keys(array_filter(
            is_array($partida['llegadas'][self::CAMPO] ?? null) ? $partida['llegadas'][self::CAMPO] : [],
            static fn($v) => (bool) $v
        ));
    }

    /**
     * @param array<string, mixed> $partida
     */
    private static function reconciliar(array &$partida): void
    {
        $map = &$partida['llegadas'][self::CAMPO];
        foreach (array_keys($partida['residentes'] ?? []) as $rid) {
            if (is_string($rid) && PoolJugableCanon::esIdCanonico($rid)) {
                $map[$rid] = true;
            }
        }
        foreach ($partida['llegadas']['historial'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cid = (string) ($row['catalog_id'] ?? '');
            if (PoolJugableCanon::esIdCanonico($cid)) {
                $map[$cid] = true;
            }
        }
        foreach ($partida['llegadas']['excluidos'] ?? [] as $cid) {
            if (is_string($cid) && PoolJugableCanon::esIdCanonico($cid)) {
                $map[$cid] = true;
            }
        }
        foreach ($partida['llegadas']['tutorial_hechas'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cid = (string) ($row['catalog_id'] ?? '');
            if (PoolJugableCanon::esIdCanonico($cid)) {
                $map[$cid] = true;
            }
        }
        $cand = $partida['llegadas']['candidato_activo'] ?? null;
        if (is_array($cand)) {
            $cid = (string) ($cand['catalog_id'] ?? '');
            if (PoolJugableCanon::esIdCanonico($cid)) {
                $map[$cid] = true;
            }
        }
        $ec = $partida['llegadas']['en_camino'] ?? null;
        if (is_array($ec)) {
            $cid = (string) ($ec['catalog_id'] ?? '');
            if (PoolJugableCanon::esIdCanonico($cid)) {
                $map[$cid] = true;
            }
        }
    }

    private static function inferirAparecidoLegacy(array $partida, string $catalogId): bool
    {
        if (!PoolJugableCanon::esIdCanonico($catalogId)) {
            return false;
        }
        if (isset($partida['residentes'][$catalogId])) {
            return true;
        }
        foreach ($partida['llegadas']['historial'] ?? [] as $row) {
            if (is_array($row) && ($row['catalog_id'] ?? '') === $catalogId) {
                return true;
            }
        }
        return false;
    }
}
