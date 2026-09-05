<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Historia del Pueblo — Vista de presentación para UI.
 * Transforma datos crudos en estructuras listas para el cliente.
 */
final class HistoriaPuebloVista
{
    /**
     * Snapshot completo para el modal — 33 posiciones en orden editorial.
     *
     * @param array<string, mixed> $partida
     * @return array{total_revelados: int, total_hitos: int, hitos: list<array>}
     */
    public static function snapshot(array $partida): array
    {
        $catalogo = HistoriaPuebloEngine::catalogo($partida);
        $hitos = [];

        foreach ($catalogo as $item) {
            if ($item['revelado']) {
                $hitos[] = self::presentarRevelado($partida, $item);
            } else {
                $hitos[] = self::presentarBloqueado($item);
            }
        }

        return [
            'total_revelados' => count(array_filter($catalogo, fn($c) => $c['revelado'])),
            'total_hitos' => count($catalogo),
            'hitos' => $hitos,
        ];
    }

    private static function presentarRevelado(array $partida, array $item): array
    {
        $entrada = $item['entrada'] ?? [];
        $protagonistas = [];

        foreach ($entrada['protagonistas'] ?? [] as $pid) {
            $pid = (string) $pid;
            $residente = $partida['residentes'][$pid] ?? null;
            $protagonistas[] = [
                'id' => $pid,
                'nombre' => $entrada['nombres'][$pid] ?? IdentidadPublica::nombre($partida, $pid),
                'retrato' => self::retratoMini($partida, $pid),
            ];
        }

        return [
            'id' => $item['id'],
            'nombre' => $item['nombre'],
            'revelado' => true,
            'orden' => $item['orden'],
            'dia' => $entrada['dia'] ?? 1,
            'protagonistas' => $protagonistas,
            'imagen_url' => $item['imagen'],
            'texto_narrativo' => HistoriaPuebloEngine::generarTextoNarrativo($item, $entrada),
            'recompensa' => RegalitoRecompensaService::recompensaDeEntradaHistoria($partida, $entrada),
        ];
    }

    private static function presentarBloqueado(array $item): array
    {
        return [
            'id' => $item['id'],
            'nombre' => '???',
            'revelado' => false,
            'orden' => $item['orden'],
            'dia' => null,
            'protagonistas' => [],
            'imagen_url' => null,
        ];
    }

    private static function retratoMini(array $partida, string $pid): ?string
    {
        $residente = $partida['residentes'][$pid] ?? null;
        if ($residente === null) {
            return null;
        }
        try {
            $packs = new VisualPackStore();
            $retrato = RetratoResolver::resolver($residente, $pid, $packs);
            return $retrato['url'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
