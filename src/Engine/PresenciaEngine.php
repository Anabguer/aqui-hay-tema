<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Presencia visual técnica: quién está en qué lugar según agenda + encuentros. */
final class PresenciaEngine
{
    public static function resolver(array $partida, string $projectRoot, ?int $dia = null, ?int $hora = null): array
    {
        $dia ??= (int) $partida['reloj']['dia_pueblo'];
        $hora ??= (int) $partida['reloj']['hora_actual'];
        $lugares = (new Catalog($projectRoot))->loadLugares();
        $mapa = [];

        foreach ($lugares['items'] as $lug) {
            $id = $lug['id'];
            $operativo = in_array($id, $partida['celeste']['lugares_desbloqueados'] ?? [], true);
            $mapa[$id] = [
                'id' => $id,
                'nombre' => $lug['nombre'],
                'operativo' => $operativo,
                'candado' => !$operativo && ($lug['desbloqueado_inicial'] ?? false) === false,
                'capacidad' => $lug['capacidad'] ?? null,
                'residentes_presentes' => [],
            ];
        }

        foreach ($partida['residentes'] as $rid => $res) {
            if (($res['presencia'] ?? '') !== 'residente') {
                continue;
            }
            $lugar = self::lugarDeResidente($partida, $rid, $dia, $hora);
            if ($lugar !== null && isset($mapa[$lugar])) {
                $mapa[$lugar]['residentes_presentes'][] = [
                    'id' => $rid,
                    'iniciales' => self::iniciales($res),
                    '_placeholder_visual' => true,
                ];
            }
        }

        $catalog = new Catalog($projectRoot);
        $marcas = ResumenDia::marcasPorLugar($partida, $catalog);
        foreach ($mapa as $id => &$lugRow) {
            $m = $marcas[$id] ?? null;
            $lugRow['encuentro_marca'] = is_array($m) ? ($m['marca'] ?? null) : null;
            $lugRow['encuentro'] = is_array($m) ? ($m['encuentro'] ?? null) : null;
        }
        unset($lugRow);

        return [
            'dia' => $dia,
            'hora' => $hora,
            'lugares' => array_values($mapa),
            'bloque_a' => BloqueA::resumen($partida),
        ];
    }

    private static function lugarDeResidente(array $partida, string $rid, int $dia, int $hora): ?string
    {
        foreach (EncuentroEngine::list($partida) as $enc) {
            if (!LugarAtributos::ocupaHora($enc, $dia, $hora)) {
                continue;
            }
            if (!in_array($enc['estado'] ?? '', ['programado', 'en_curso'], true)) {
                continue;
            }
            if (in_array($rid, $enc['participantes'] ?? [], true)) {
                return $enc['lugar'] ?? null;
            }
        }

        // Presencia técnica por planes de NPC autónomo (sin encuentros/interacciones).
        foreach ($partida['npc_autonomo']['planes_pendientes'] ?? [] as $plan) {
            if (!LugarAtributos::ocupaHora($plan, $dia, $hora)) {
                continue;
            }
            $estado = (string) ($plan['estado'] ?? 'programado');
            if (!in_array($estado, ['programado', 'en_curso'], true)) {
                continue;
            }
            if (!in_array($rid, $plan['participantes'] ?? [], true)) {
                continue;
            }
            $lugar = $plan['lugar'] ?? null;
            if (is_string($lugar) && $lugar !== '') {
                return $lugar;
            }
        }
        return null;
    }

    private static function iniciales(array $res): string
    {
        $nombre = $res['identidad_publica']['nombre'] ?? '?';
        $parts = preg_split('/\s+/', trim($nombre)) ?: ['?'];
        $ini = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $char = substr($p, 0, 1);
            $ini .= strtoupper($char);
        }
        return $ini ?: '?';
    }
}
