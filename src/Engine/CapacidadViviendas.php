<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Capacidad V3: pool lógico de 24 slots (A01–A16, B01–B08).
 * Espejo legacy bloque_a/b al guardar; bloque_c ignorado en producto.
 */
final class CapacidadViviendas
{
    public const CAP_PRODUCTO = 24;
    public const CAP_POR_BLOQUE = 16;

    /** @return list<string> */
    public static function slotIdsCanon(): array
    {
        $out = [];
        for ($i = 1; $i <= 16; $i++) {
            $out[] = 'A' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        }
        for ($i = 1; $i <= 8; $i++) {
            $out[] = 'B' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        }
        return $out;
    }

    /** @return list<string> */
    public static function bloquesAbiertos(array $partida): array
    {
        return ['a', 'b'];
    }

    public static function capacidadTotal(array $partida): int
    {
        self::ensure($partida);
        return self::CAP_PRODUCTO;
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function ensure(array &$partida): void
    {
        $partida['viviendas'] ??= [];
        if (!is_array($partida['viviendas']['slots'] ?? null)
            || count($partida['viviendas']['slots']) !== self::CAP_PRODUCTO
        ) {
            self::ensureLegacyBlocks($partida);
            self::buildPoolFromLegacy($partida);
        }
        $partida['viviendas']['cap'] = self::CAP_PRODUCTO;
        $partida['celeste']['vivienda_capacidad_max'] = self::CAP_PRODUCTO;
        $partida['celeste']['bloques_abiertos'] = ['a', 'b'];
        self::syncLegacyMirror($partida);
        self::normalizarViviendaIds($partida);
    }

    /** @return list<array{id:string,ocupante_id:?string,estado:string}> */
    public static function slots(array $partida): array
    {
        self::ensure($partida);
        return $partida['viviendas']['slots'];
    }

    /** @return list<array{id:string,ocupante_id:?string,estado:string,bloque:string}> */
    public static function viviendasTodas(array $partida): array
    {
        $out = [];
        foreach (self::slots($partida) as $v) {
            $letra = strtolower($v['id'][0] ?? 'a');
            $v['bloque'] = $letra;
            $out[] = $v;
        }
        return $out;
    }

    public static function huecos(array $partida): int
    {
        self::ensure($partida);
        $activos = self::residentesActivos($partida);
        if (count($activos) >= self::CAP_PRODUCTO) {
            return 0;
        }
        return max(0, self::CAP_PRODUCTO - count($activos));
    }

    public static function ocupadas(array $partida): int
    {
        return count(self::residentesActivos($partida));
    }

    /** @return list<string> */
    public static function residentesActivos(array $partida): array
    {
        $ids = [];
        foreach ($partida['residentes'] ?? [] as $id => $r) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            if (($r['presencia'] ?? 'residente') === 'residente') {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /** @return array{vivienda_id: string|null, error: string|null} */
    public static function asignarAutomatico(array &$partida, string $residenteId): array
    {
        self::ensure($partida);
        if (count(self::residentesActivos($partida)) >= self::CAP_PRODUCTO
            && !isset($partida['residentes'][$residenteId])
        ) {
            return ['vivienda_id' => null, 'error' => 'viviendas_llenas'];
        }
        self::liberarSlotsDe($partida, $residenteId);
        foreach ($partida['viviendas']['slots'] as &$slot) {
            if (($slot['ocupante_id'] ?? null) === null && ($slot['estado'] ?? '') === 'libre') {
                $slot['ocupante_id'] = $residenteId;
                $slot['estado'] = 'ocupado';
                if (isset($partida['residentes'][$residenteId])) {
                    $partida['residentes'][$residenteId]['vivienda_id'] = $slot['id'];
                }
                self::syncLegacyMirror($partida);
                return ['vivienda_id' => $slot['id'], 'error' => null];
            }
        }
        unset($slot);
        return ['vivienda_id' => null, 'error' => 'viviendas_llenas'];
    }

    /** Abre bloque para lab/gate — no expande producto más allá de 24. */
    public static function abrirBloque(array &$partida, string $bloque): void
    {
        self::ensure($partida);
    }

    /**
     * Construye pool v3 desde bloque_a + bloque_b (máx. 24).
     *
     * @param array<string, mixed> $partida
     */
    public static function buildPoolFromLegacy(array &$partida): void
    {
        self::ensureLegacyBlocks($partida);
        $byId = [];
        foreach (['bloque_a', 'bloque_b'] as $key) {
            foreach ($partida[$key]['viviendas'] ?? [] as $v) {
                if (!is_array($v) || empty($v['id'])) {
                    continue;
                }
                $byId[(string) $v['id']] = [
                    'id' => (string) $v['id'],
                    'ocupante_id' => $v['ocupante_id'] ?? null,
                    'estado' => ($v['ocupante_id'] ?? null) !== null ? 'ocupado' : ($v['estado'] ?? 'libre'),
                ];
            }
        }
        $slots = [];
        foreach (self::slotIdsCanon() as $sid) {
            $slots[] = $byId[$sid] ?? [
                'id' => $sid,
                'ocupante_id' => null,
                'estado' => 'libre',
            ];
        }
        $partida['viviendas']['slots'] = $slots;
        $partida['viviendas']['cap'] = self::CAP_PRODUCTO;
        self::reasignarFueraDePool($partida);
        self::normalizarViviendaIds($partida);
    }

    public static function normalizarViviendaIds(array &$partida): void
    {
        if (!is_array($partida['viviendas']['slots'] ?? null)) {
            return;
        }
        $seen = [];
        foreach ($partida['viviendas']['slots'] as &$slot) {
            $occ = $slot['ocupante_id'] ?? null;
            if ($occ === null || $occ === '') {
                continue;
            }
            if (isset($seen[$occ])) {
                $slot['ocupante_id'] = null;
                $slot['estado'] = 'libre';
                continue;
            }
            $seen[$occ] = (string) $slot['id'];
            if (isset($partida['residentes'][$occ])) {
                $partida['residentes'][$occ]['vivienda_id'] = (string) $slot['id'];
            }
        }
        unset($slot);
        self::syncLegacyMirror($partida);
    }

    /**
     * Residentes con vivienda_id fuera del pool → primer slot libre.
     *
     * @param array<string, mixed> $partida
     */
    public static function reasignarFueraDePool(array &$partida): void
    {
        $valid = array_fill_keys(self::slotIdsCanon(), true);
        $partida['viviendas']['migracion']['reasignados'] ??= [];
        foreach ($partida['residentes'] ?? [] as $rid => $res) {
            if (!is_string($rid) || !is_array($res)) {
                continue;
            }
            if (($res['presencia'] ?? 'residente') !== 'residente') {
                continue;
            }
            self::liberarSlotsDe($partida, $rid);
            $vid = (string) ($res['vivienda_id'] ?? '');
            if ($vid !== '' && isset($valid[$vid])) {
                self::ocuparSlot($partida, $vid, $rid);
                $partida['residentes'][$rid]['vivienda_id'] = $vid;
                continue;
            }
            $libre = self::primerSlotLibre($partida);
            if ($libre === null) {
                continue;
            }
            $partida['viviendas']['migracion']['reasignados'][] = [
                'residente_id' => $rid,
                'desde' => $vid !== '' ? $vid : null,
                'hacia' => $libre,
            ];
            self::ocuparSlot($partida, $libre, $rid);
            $partida['residentes'][$rid]['vivienda_id'] = $libre;
        }
        self::syncLegacyMirror($partida);
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function syncLegacyMirror(array &$partida): void
    {
        self::ensureLegacyBlocks($partida);
        $mapA = [];
        $mapB = [];
        foreach ($partida['viviendas']['slots'] ?? [] as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $id = (string) ($slot['id'] ?? '');
            if (str_starts_with($id, 'A')) {
                $mapA[$id] = $slot;
            } elseif (str_starts_with($id, 'B')) {
                $mapB[$id] = $slot;
            }
        }
        for ($i = 1; $i <= self::CAP_POR_BLOQUE; $i++) {
            $aid = 'A' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $partida['bloque_a']['viviendas'][$i - 1] = $mapA[$aid] ?? [
                'id' => $aid,
                'ocupante_id' => null,
                'estado' => 'libre',
            ];
        }
        for ($i = 1; $i <= 8; $i++) {
            $bid = 'B' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $partida['bloque_b']['viviendas'][$i - 1] = $mapB[$bid] ?? [
                'id' => $bid,
                'ocupante_id' => null,
                'estado' => 'libre',
            ];
        }
        $partida['bloque_a']['capacidad'] = self::CAP_POR_BLOQUE;
        $partida['bloque_b']['capacidad'] = 8;
    }

    /**
     * @param array<string, mixed> $partida
     */
    private static function ensureLegacyBlocks(array &$partida): void
    {
        $partida['bloque_a'] ??= ['capacidad' => self::CAP_POR_BLOQUE, 'viviendas' => []];
        if (!is_array($partida['bloque_a']['viviendas'] ?? null) || $partida['bloque_a']['viviendas'] === []) {
            $partida['bloque_a']['viviendas'] = BloqueA::viviendasVacias();
        }
        $partida['bloque_b'] ??= ['capacidad' => 8, 'viviendas' => self::viviendasVacias('b')];
        if (count($partida['bloque_b']['viviendas'] ?? []) < 8) {
            $partida['bloque_b']['viviendas'] = self::viviendasVacias('b');
        }
    }

    /** @return list<array{id:string,ocupante_id:?string,estado:string}> */
    public static function viviendasVacias(string $bloque): array
    {
        $bloque = strtoupper($bloque);
        $max = $bloque === 'B' ? 8 : self::CAP_POR_BLOQUE;
        $out = [];
        for ($i = 1; $i <= $max; $i++) {
            $out[] = [
                'id' => $bloque . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'ocupante_id' => null,
                'estado' => 'libre',
            ];
        }
        return $out;
    }

    private static function primerSlotLibre(array $partida): ?string
    {
        foreach ($partida['viviendas']['slots'] ?? [] as $slot) {
            if (($slot['ocupante_id'] ?? null) === null && ($slot['estado'] ?? '') === 'libre') {
                return (string) $slot['id'];
            }
        }
        return null;
    }

    private static function ocuparSlot(array &$partida, string $slotId, string $residenteId): void
    {
        self::liberarSlotsDe($partida, $residenteId);
        foreach ($partida['viviendas']['slots'] as &$slot) {
            if (($slot['id'] ?? '') === $slotId) {
                if (($slot['ocupante_id'] ?? null) !== null && $slot['ocupante_id'] !== $residenteId) {
                    continue;
                }
                $slot['ocupante_id'] = $residenteId;
                $slot['estado'] = 'ocupado';
                return;
            }
        }
        unset($slot);
    }

    private static function liberarSlotsDe(array &$partida, string $residenteId): void
    {
        foreach ($partida['viviendas']['slots'] ?? [] as &$slot) {
            if (($slot['ocupante_id'] ?? null) === $residenteId) {
                $slot['ocupante_id'] = null;
                $slot['estado'] = 'libre';
            }
        }
        unset($slot);
    }
}
