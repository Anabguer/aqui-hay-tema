<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Capacidad V3: pool lógico de 46 slots (catálogo jugable completo).
 * A01–A16, B01–B08 (legacy), C01–C16, D01–D06.
 * Espejo legacy bloque_a/b al guardar; C/D solo en viviendas.slots.
 */
final class CapacidadViviendas
{
    public const CAP_PRODUCTO = 46;
    public const CAP_BLOQUE_A = 16;
    public const CAP_BLOQUE_B = 8;
    public const CAP_BLOQUE_C = 16;
    public const CAP_BLOQUE_D = 6;
    /** @deprecated Usar CAP_BLOQUE_A — alias legacy bloque_a */
    public const CAP_POR_BLOQUE = self::CAP_BLOQUE_A;

    /** @return list<string> */
    public static function slotIdsCanon(): array
    {
        $out = [];
        foreach (['A' => self::CAP_BLOQUE_A, 'B' => self::CAP_BLOQUE_B, 'C' => self::CAP_BLOQUE_C, 'D' => self::CAP_BLOQUE_D] as $bloque => $max) {
            for ($i = 1; $i <= $max; $i++) {
                $out[] = $bloque . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            }
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
        $slots = $partida['viviendas']['slots'] ?? null;
        if (!is_array($slots) || $slots === []) {
            self::ensureLegacyBlocks($partida);
            self::buildPoolFromLegacy($partida);
        } elseif (count($slots) < self::CAP_PRODUCTO) {
            self::expandirPoolAdditivo($partida);
        } elseif (count($slots) > self::CAP_PRODUCTO) {
            self::expandirPoolAdditivo($partida);
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
        $activos = self::residentesActivos($partida);
        if (count($activos) > self::CAP_PRODUCTO) {
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

    /** Abre bloque para lab/gate — capacidad fija en producto (46). */
    public static function abrirBloque(array &$partida, string $bloque): void
    {
        self::ensure($partida);
    }

    /**
     * Migra partidas con pool menor que CAP_PRODUCTO preservando ocupantes en A/B.
     * Añade slots C/D vacíos sin tocar vivienda_id existentes.
     *
     * @param array<string, mixed> $partida
     */
    public static function expandirPoolAdditivo(array &$partida): void
    {
        $byId = [];
        foreach ($partida['viviendas']['slots'] ?? [] as $slot) {
            if (!is_array($slot) || empty($slot['id'])) {
                continue;
            }
            $byId[(string) $slot['id']] = [
                'id' => (string) $slot['id'],
                'ocupante_id' => $slot['ocupante_id'] ?? null,
                'estado' => ($slot['ocupante_id'] ?? null) !== null ? 'ocupado' : ($slot['estado'] ?? 'libre'),
            ];
        }
        $antes = count($byId);
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
        if ($antes > 0 && $antes < self::CAP_PRODUCTO) {
            $partida['viviendas']['migracion']['pool_expandido'] = [
                'desde_slots' => $antes,
                'hacia_slots' => self::CAP_PRODUCTO,
            ];
        }
        self::normalizarViviendaIds($partida);
    }

    /**
     * Construye pool v3 desde bloque_a + bloque_b (primeros 24 slots).
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
        self::expandirPoolAdditivo($partida);
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
        for ($i = 1; $i <= self::CAP_BLOQUE_A; $i++) {
            $aid = 'A' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $partida['bloque_a']['viviendas'][$i - 1] = $mapA[$aid] ?? [
                'id' => $aid,
                'ocupante_id' => null,
                'estado' => 'libre',
            ];
        }
        for ($i = 1; $i <= self::CAP_BLOQUE_B; $i++) {
            $bid = 'B' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $partida['bloque_b']['viviendas'][$i - 1] = $mapB[$bid] ?? [
                'id' => $bid,
                'ocupante_id' => null,
                'estado' => 'libre',
            ];
        }
        $partida['bloque_a']['capacidad'] = self::CAP_BLOQUE_A;
        $partida['bloque_b']['capacidad'] = self::CAP_BLOQUE_B;
    }

    /**
     * @param array<string, mixed> $partida
     */
    private static function ensureLegacyBlocks(array &$partida): void
    {
        $partida['bloque_a'] ??= ['capacidad' => self::CAP_BLOQUE_A, 'viviendas' => []];
        if (!is_array($partida['bloque_a']['viviendas'] ?? null) || $partida['bloque_a']['viviendas'] === []) {
            $partida['bloque_a']['viviendas'] = BloqueA::viviendasVacias();
        }
        $partida['bloque_b'] ??= ['capacidad' => self::CAP_BLOQUE_B, 'viviendas' => self::viviendasVacias('b')];
        if (count($partida['bloque_b']['viviendas'] ?? []) < self::CAP_BLOQUE_B) {
            $partida['bloque_b']['viviendas'] = self::viviendasVacias('b');
        }
    }

    /** @return list<array{id:string,ocupante_id:?string,estado:string}> */
    public static function viviendasVacias(string $bloque): array
    {
        $bloque = strtoupper($bloque);
        if ($bloque === 'B') {
            $max = self::CAP_BLOQUE_B;
        } elseif ($bloque === 'C') {
            $max = self::CAP_BLOQUE_C;
        } elseif ($bloque === 'D') {
            $max = self::CAP_BLOQUE_D;
        } else {
            $max = self::CAP_BLOQUE_A;
        }
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

    public static function liberarResidente(array &$partida, string $residenteId): void
    {
        self::ensure($partida);
        self::liberarSlotsDe($partida, $residenteId);
        if (isset($partida['residentes'][$residenteId])) {
            $vid = (string) ($partida['residentes'][$residenteId]['vivienda_id'] ?? '');
            if ($vid !== '') {
                BloqueA::liberar($partida, $vid);
            }
            $partida['residentes'][$residenteId]['vivienda_id'] = null;
        }
        self::syncLegacyMirror($partida);
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
