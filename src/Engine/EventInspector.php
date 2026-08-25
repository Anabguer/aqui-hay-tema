<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class EventInspector
{
    public static function timeline(array $partida, array $filtros = []): array
    {
        $entries = [];

        foreach ($partida['audit_trail'] ?? [] as $e) {
            $entries[] = self::normalize($e, 'audit_trail');
        }
        foreach ($partida['domain_events'] ?? [] as $e) {
            $entries[] = self::normalizeDomain($e);
        }
        foreach ($partida['npc_autonomo']['historial_eventos'] ?? [] as $e) {
            if (is_array($e)) {
                $entries[] = self::normalizeNpc($e);
            }
        }
        foreach (array_slice($partida['event_log'] ?? [], -200) as $e) {
            $entries[] = self::normalizeLog($e);
        }

        usort($entries, static function ($a, $b) {
            $ka = ($a['ts_juego']['dia'] ?? 0) * 24 + ($a['ts_juego']['hora'] ?? 0);
            $kb = ($b['ts_juego']['dia'] ?? 0) * 24 + ($b['ts_juego']['hora'] ?? 0);
            return $ka <=> $kb;
        });

        if ($filtroRes = $filtros['residente_id'] ?? null) {
            $entries = array_values(array_filter($entries, static fn($e) => in_array($filtroRes, $e['actores'] ?? [], true)));
        }
        if ($filtroTipo = $filtros['tipo'] ?? null) {
            $entries = array_values(array_filter($entries, static fn($e) => ($e['tipo'] ?? '') === $filtroTipo));
        }
        if ($filtroEnc = $filtros['encuentro_id'] ?? null) {
            $entries = array_values(array_filter($entries, static fn($e) => str_contains(json_encode($e['detalle'] ?? ''), $filtroEnc)));
        }
        if ($filtroCorr = $filtros['correlacion_id'] ?? null) {
            $cid = (string) $filtroCorr;
            $entries = array_values(array_filter($entries, static fn($e) => (string) ($e['correlacion_id'] ?? '') === $cid));
        }

        $limit = (int) ($filtros['limit'] ?? 100);
        if (count($entries) > $limit) {
            $entries = array_slice($entries, -$limit);
        }

        return ['ok' => true, 'total' => count($entries), 'eventos' => $entries];
    }

    public static function correlacionados(array $partida, string $correlacionId, int $limit = 100): array
    {
        $tl = self::timeline($partida, ['correlacion_id' => $correlacionId, 'limit' => $limit]);
        return [
            'ok' => true,
            'correlacion_id' => $correlacionId,
            'total' => $tl['total'] ?? 0,
            'eventos' => $tl['eventos'] ?? [],
        ];
    }

    private static function normalize(array $e, string $fuente): array
    {
        return [
            'fuente' => $fuente,
            'tipo' => $e['tipo'] ?? '',
            'ts_juego' => $e['ts_juego'] ?? null,
            'actores' => $e['actores'] ?? [],
            'origen' => $e['origen'] ?? null,
            'regla' => $e['regla'] ?? null,
            'correlacion_id' => $e['correlacion_id'] ?? null,
            'detalle' => ['antes' => $e['antes'] ?? null, 'despues' => $e['despues'] ?? null],
        ];
    }

    private static function normalizeDomain(array $e): array
    {
        return [
            'fuente' => 'domain_events',
            'tipo' => $e['evento'] ?? '',
            'ts_juego' => self::tsDe($e),
            'actores' => $e['payload']['actores'] ?? [],
            'correlacion_id' => $e['correlacion_id'] ?? null,
            'detalle' => $e,
        ];
    }

    private static function normalizeLog(array $e): array
    {
        return [
            'fuente' => 'event_log',
            'tipo' => $e['tipo'] ?? $e['evento'] ?? 'log',
            'ts_juego' => self::tsDe($e),
            'actores' => $e['payload']['actores'] ?? ($e['actores'] ?? []),
            'correlacion_id' => $e['correlacion_id'] ?? null,
            'detalle' => $e,
        ];
    }

    /**
     * Extrae ts_juego de la fila o de sus campos planos (dia/hora, dia_pueblo/hora_actual).
     */
    private static function tsDe(array $e): ?array
    {
        if (isset($e['ts_juego']) && is_array($e['ts_juego'])) {
            return $e['ts_juego'];
        }
        $dia = $e['dia'] ?? $e['dia_pueblo'] ?? null;
        $hora = $e['hora'] ?? $e['hora_actual'] ?? null;
        if ($dia === null || $hora === null) {
            return null;
        }
        return ['dia' => (int) $dia, 'hora' => (int) $hora];
    }

    private static function normalizeNpc(array $e): array
    {
        $rid = isset($e['residente_id']) && is_string($e['residente_id']) ? $e['residente_id'] : null;
        return [
            'fuente' => 'npc_autonomo.historial_eventos',
            'tipo' => $e['tipo'] ?? 'npc_autonomo',
            'ts_juego' => $e['ts_juego'] ?? [
                'dia' => $e['dia'] ?? null,
                'hora' => $e['hora'] ?? null,
            ],
            'actores' => $rid !== null ? [$rid] : [],
            'correlacion_id' => $e['correlacion_id'] ?? null,
            'detalle' => $e,
        ];
    }
}
