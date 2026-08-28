<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Capacidad jugable: máximo 16 residentes simultáneos (bloque A, A01–A16).
 *
 * Saves antiguos pueden conservar slots B/C/D (hasta 46 entradas) solo como
 * compatibilidad: no amplían la capacidad funcional ni reciben nuevas asignaciones.
 */
final class CapacidadViviendas
{
    /** Capacidad máxima real del producto (residentes activos simultáneos). */
    public const CAP_PRODUCTO = 16;

    /** Tamaño del pool legacy ampliado (A+B+C+D) en saves históricos — no jugable. */
    public const CAP_POOL_LEGACY_TOTAL = 46;

    public const CAP_BLOQUE_A = 16;
    public const CAP_BLOQUE_B = 8;
    public const CAP_BLOQUE_C = 16;
    public const CAP_BLOQUE_D = 6;

    /** @deprecated Alias de CAP_PRODUCTO — usar CAP_PRODUCTO. */
    public const CAP_POR_BLOQUE = self::CAP_BLOQUE_A;

    public static function capObjetivoPoblacionActiva(): int
    {
        return self::CAP_PRODUCTO;
    }

    /** @return list<string> Slots asignables en producto (solo bloque A). */
    public static function slotIdsJugables(): array
    {
        $out = [];
        for ($i = 1; $i <= self::CAP_BLOQUE_A; $i++) {
            $out[] = 'A' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        }
        return $out;
    }

    /**
     * Pool completo legacy (A+B+C+D) — solo lectura/migración de saves antiguos.
     *
     * @return list<string>
     */
    public static function slotIdsLegacyExtendidos(): array
    {
        $out = [];
        foreach (['A' => self::CAP_BLOQUE_A, 'B' => self::CAP_BLOQUE_B, 'C' => self::CAP_BLOQUE_C, 'D' => self::CAP_BLOQUE_D] as $bloque => $max) {
            for ($i = 1; $i <= $max; $i++) {
                $out[] = $bloque . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            }
        }
        return $out;
    }

    /** @deprecated Usar slotIdsLegacyExtendidos() — alias histórico. */
    public static function slotIdsCanon(): array
    {
        return self::slotIdsLegacyExtendidos();
    }

    /** @return list<string> */
    public static function bloquesAbiertos(array $partida): array
    {
        return ['a'];
    }

    public static function capacidadTotal(array $partida): int
    {
        self::ensure($partida);
        return self::CAP_PRODUCTO;
    }

    public static function tienePoolLegacyAmpliado(array $partida): bool
    {
        $n = count($partida['viviendas']['slots'] ?? []);
        return $n > self::CAP_PRODUCTO;
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
            self::buildPoolJugable($partida);
        } elseif (count($slots) > self::CAP_PRODUCTO) {
            self::preservarPoolLegacy($partida);
        } elseif (count($slots) < self::CAP_PRODUCTO) {
            self::expandirPoolJugable($partida);
        }
        self::aplicarCapCelebrada($partida);
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
        $activos = count(self::residentesActivos($partida));
        if ($activos >= self::CAP_PRODUCTO) {
            return 0;
        }
        return self::CAP_PRODUCTO - $activos;
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
        $yaTieneSlot = false;
        foreach ($partida['viviendas']['slots'] as $slot) {
            if (($slot['ocupante_id'] ?? null) === $residenteId) {
                $yaTieneSlot = true;
                break;
            }
        }
        $n = count($activos);
        $esActivo = in_array($residenteId, $activos, true);
        if ($n > self::CAP_PRODUCTO || ($n >= self::CAP_PRODUCTO && !$esActivo)) {
            return ['vivienda_id' => null, 'error' => 'viviendas_llenas'];
        }
        self::liberarSlotsDe($partida, $residenteId);
        $jugables = array_fill_keys(self::slotIdsJugables(), true);
        foreach ($partida['viviendas']['slots'] as &$slot) {
            $sid = (string) ($slot['id'] ?? '');
            if (!isset($jugables[$sid])) {
                continue;
            }
            if (($slot['ocupante_id'] ?? null) === null && ($slot['estado'] ?? '') === 'libre') {
                $slot['ocupante_id'] = $residenteId;
                $slot['estado'] = 'ocupado';
                if (isset($partida['residentes'][$residenteId])) {
                    $partida['residentes'][$residenteId]['vivienda_id'] = $sid;
                }
                self::syncLegacyMirror($partida);
                return ['vivienda_id' => $sid, 'error' => null];
            }
        }
        unset($slot);
        return ['vivienda_id' => null, 'error' => 'viviendas_llenas'];
    }

    public static function abrirBloque(array &$partida, string $bloque): void
    {
        self::ensure($partida);
    }

    /**
     * Construye pool jugable de 16 slots (bloque A).
     *
     * @param array<string, mixed> $partida
     */
    public static function buildPoolJugable(array &$partida): void
    {
        self::ensureLegacyBlocks($partida);
        $byId = [];
        foreach ($partida['viviendas']['slots'] ?? [] as $slot) {
            if (!is_array($slot) || empty($slot['id'])) {
                continue;
            }
            $byId[(string) $slot['id']] = $slot;
        }
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
        foreach (self::slotIdsJugables() as $sid) {
            $slots[] = self::normalizarSlot($byId[$sid] ?? null, $sid);
        }
        $partida['viviendas']['slots'] = $slots;
        self::reasignarFueraDePoolJugable($partida);
    }

    /**
     * Expande saves con menos de 16 slots hasta el pool jugable A01–A16.
     *
     * @param array<string, mixed> $partida
     */
    public static function expandirPoolJugable(array &$partida): void
    {
        $byId = [];
        foreach ($partida['viviendas']['slots'] ?? [] as $slot) {
            if (!is_array($slot) || empty($slot['id'])) {
                continue;
            }
            $byId[(string) $slot['id']] = self::normalizarSlot($slot, (string) $slot['id']);
        }
        $antes = count($byId);
        $slots = [];
        foreach (self::slotIdsJugables() as $sid) {
            $slots[] = $byId[$sid] ?? self::slotVacio($sid);
        }
        $partida['viviendas']['slots'] = $slots;
        if ($antes > 0 && $antes < self::CAP_PRODUCTO) {
            $partida['viviendas']['migracion']['pool_jugable'] = [
                'desde_slots' => $antes,
                'hacia_slots' => self::CAP_PRODUCTO,
            ];
        }
        self::reasignarFueraDePoolJugable($partida);
    }

    /**
     * Saves con pool >16: conservar ocupantes en B/C/D sin ampliar capacidad funcional.
     *
     * @param array<string, mixed> $partida
     */
    public static function preservarPoolLegacy(array &$partida): void
    {
        $byId = [];
        foreach ($partida['viviendas']['slots'] ?? [] as $slot) {
            if (!is_array($slot) || empty($slot['id'])) {
                continue;
            }
            $byId[(string) $slot['id']] = self::normalizarSlot($slot, (string) $slot['id']);
        }
        $slots = [];
        foreach (self::slotIdsLegacyExtendidos() as $sid) {
            $slots[] = $byId[$sid] ?? self::slotVacio($sid);
        }
        $partida['viviendas']['slots'] = $slots;
        $partida['viviendas']['migracion']['pool_legacy'] = [
            'slots_totales' => count($slots),
            'cap_jugable' => self::CAP_PRODUCTO,
        ];
        $activos = count(self::residentesActivos($partida));
        if ($activos > self::CAP_PRODUCTO) {
            $partida['viviendas']['migracion']['sobrecap_legacy'] = [
                'activos' => $activos,
                'cap_jugable' => self::CAP_PRODUCTO,
            ];
        }
    }

    /**
     * @deprecated Alias de buildPoolJugable — compat tests antiguos.
     *
     * @param array<string, mixed> $partida
     */
    public static function buildPoolFromLegacy(array &$partida): void
    {
        self::buildPoolJugable($partida);
    }

    /**
     * @deprecated Solo migra hacia pool jugable de 16; no expande a 46.
     *
     * @param array<string, mixed> $partida
     */
    public static function expandirPoolAdditivo(array &$partida): void
    {
        if (self::tienePoolLegacyAmpliado($partida)) {
            self::preservarPoolLegacy($partida);
            return;
        }
        self::expandirPoolJugable($partida);
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
     * Residentes con vivienda fuera de A o sin vivienda → primer slot A libre (si hay hueco).
     *
     * @param array<string, mixed> $partida
     */
    public static function reasignarFueraDePoolJugable(array &$partida): void
    {
        $jugables = array_fill_keys(self::slotIdsJugables(), true);
        $partida['viviendas']['migracion']['reasignados'] ??= [];
        foreach ($partida['residentes'] ?? [] as $rid => $res) {
            if (!is_string($rid) || !is_array($res)) {
                continue;
            }
            if (($res['presencia'] ?? 'residente') !== 'residente') {
                continue;
            }
            $vid = (string) ($res['vivienda_id'] ?? '');
            if ($vid !== '' && isset($jugables[$vid])) {
                self::ocuparSlot($partida, $vid, $rid);
                $partida['residentes'][$rid]['vivienda_id'] = $vid;
                continue;
            }
            if (count(self::residentesActivos($partida)) > self::CAP_PRODUCTO) {
                continue;
            }
            self::liberarSlotsDe($partida, $rid);
            $libre = self::primerSlotLibreJugable($partida);
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

    /** @deprecated Usar reasignarFueraDePoolJugable */
    public static function reasignarFueraDePool(array &$partida): void
    {
        self::reasignarFueraDePoolJugable($partida);
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
            $partida['bloque_a']['viviendas'][$i - 1] = $mapA[$aid] ?? self::slotVacio($aid);
        }
        for ($i = 1; $i <= self::CAP_BLOQUE_B; $i++) {
            $bid = 'B' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $partida['bloque_b']['viviendas'][$i - 1] = $mapB[$bid] ?? self::slotVacio($bid);
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
        $partida['bloque_b'] ??= ['capacidad' => self::CAP_BLOQUE_B, 'viviendas' => []];
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
            $out[] = self::slotVacio($bloque . str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }
        return $out;
    }

    private static function primerSlotLibreJugable(array $partida): ?string
    {
        $jugables = array_fill_keys(self::slotIdsJugables(), true);
        foreach ($partida['viviendas']['slots'] ?? [] as $slot) {
            $sid = (string) ($slot['id'] ?? '');
            if (!isset($jugables[$sid])) {
                continue;
            }
            if (($slot['ocupante_id'] ?? null) === null && ($slot['estado'] ?? '') === 'libre') {
                return $sid;
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
        if (!is_array($partida['viviendas']['slots'] ?? null)) {
            return;
        }
        foreach ($partida['viviendas']['slots'] as $i => $slot) {
            if (!is_array($slot)) {
                continue;
            }
            if (($slot['ocupante_id'] ?? null) === $residenteId) {
                $partida['viviendas']['slots'][$i]['ocupante_id'] = null;
                $partida['viviendas']['slots'][$i]['estado'] = 'libre';
            }
        }
    }

    /**
     * @param array<string, mixed>|null $slot
     * @return array{id:string,ocupante_id:?string,estado:string}
     */
    private static function normalizarSlot(?array $slot, string $sid): array
    {
        if ($slot === null) {
            return self::slotVacio($sid);
        }
        return [
            'id' => $sid,
            'ocupante_id' => $slot['ocupante_id'] ?? null,
            'estado' => ($slot['ocupante_id'] ?? null) !== null ? 'ocupado' : ($slot['estado'] ?? 'libre'),
        ];
    }

    /** @return array{id:string,ocupante_id:?string,estado:string} */
    private static function slotVacio(string $sid): array
    {
        return [
            'id' => $sid,
            'ocupante_id' => null,
            'estado' => 'libre',
        ];
    }

    /**
     * @param array<string, mixed> $partida
     */
    private static function aplicarCapCelebrada(array &$partida): void
    {
        $partida['viviendas']['cap'] = self::CAP_PRODUCTO;
        $partida['celeste']['vivienda_capacidad_max'] = self::CAP_PRODUCTO;
        $partida['celeste']['objetivo_poblacion_activa'] = self::CAP_PRODUCTO;
        $partida['celeste']['bloques_abiertos'] = ['a'];
    }
}
