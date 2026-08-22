<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Detecta coincidencias técnicas (residentes en el mismo lugar y hora).
 * No implica interacción social: solo deja historial y un evento técnico.
 */
final class CoincidenciasEngine
{
    /**
     * @return list<array{key:string,dia:int,hora:int,lugar_id:string,residentes:list<string>}>
     */
    public static function detectarYRegistrar(
        array &$partida,
        string $projectRoot,
        int $dia,
        int $hora,
        ?GameLogger $logger = null
    ): array {
        $partida['historial_coincidencias'] ??= [];

        $existKeys = [];
        foreach ($partida['historial_coincidencias'] as $e) {
            if (is_array($e) && isset($e['key']) && is_string($e['key'])) {
                $existKeys[$e['key']] = true;
            }
        }

        $pres = PresenciaEngine::resolver($partida, $projectRoot, $dia, $hora);
        $new = [];

        foreach ($pres['lugares'] ?? [] as $l) {
            $lugarId = (string) ($l['id'] ?? '');
            $residents = [];
            foreach ($l['residentes_presentes'] ?? [] as $rp) {
                $rid = (string) ($rp['id'] ?? '');
                if ($rid !== '') {
                    $residents[] = $rid;
                }
            }
            $residents = array_values(array_unique($residents));
            if (count($residents) < 2) {
                continue;
            }
            sort($residents);
            $key = 'coin_' . $dia . '_' . $hora . '_' . $lugarId . '_' . md5(implode('|', $residents));
            if (isset($existKeys[$key])) {
                continue;
            }
            $existKeys[$key] = true;
            $entry = [
                'key' => $key,
                'dia' => $dia,
                'hora' => $hora,
                'lugar_id' => $lugarId,
                'residentes' => $residents,
                '_placeholder' => true,
                'ts' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            ];
            $partida['historial_coincidencias'][] = $entry;
            $new[] = $entry;

            DomainEventDispatcher::emit($partida, DomainEvents::COINCIDENCIA_RESIDENTES, [
                'dia' => $dia,
                'hora' => $hora,
                'lugar_id' => $lugarId,
                'residentes' => $residents,
                'actores' => $residents,
            ], $logger, 'CoincidenciasEngine::detectarYRegistrar', $residents);

            CoincidenciasInteraccionBridge::intentarTrasCoincidencia(
                $partida,
                $entry,
                $projectRoot,
                $logger
            );
        }
        PersistenciaCaps::recortarLista(
            $partida,
            'historial_coincidencias',
            PersistenciaCaps::cap($partida, 'historial_coincidencias_cap', 500),
            'historial_coincidencias_archivo'
        );

        return $new;
    }

    /**
     * Recorre horas del intervalo (tras avanzar el reloj, antes de sincronizar encuentros)
     * y registra coincidencias. Solo mira horas con encuentro o plan NPC, más la hora destino.
     *
     * @return list<array>
     */
    public static function detectarEnIntervalo(
        array &$partida,
        string $projectRoot,
        array $relojAntes,
        int $horasAvanzadas,
        ?GameLogger $logger = null
    ): array {
        if ($horasAvanzadas <= 0) {
            return [];
        }
        $all = [];
        foreach (self::horasRelevantes($partida, $relojAntes, $horasAvanzadas) as [$dia, $hora]) {
            foreach (self::detectarYRegistrar($partida, $projectRoot, $dia, $hora, $logger) as $e) {
                $all[] = $e;
            }
        }
        return $all;
    }

    /**
     * @return list<array{0:int,1:int}>
     */
    public static function horasRelevantes(array $partida, array $relojAntes, int $horasAvanzadas): array
    {
        $dia = (int) ($relojAntes['dia_pueblo'] ?? 1);
        $hora = (int) ($relojAntes['hora_actual'] ?? 0);
        $intervalo = [];
        for ($i = 1; $i <= $horasAvanzadas; $i++) {
            $hora++;
            if ($hora >= 24) {
                $hora = 0;
                $dia++;
            }
            $intervalo[$dia . ':' . $hora] = [$dia, $hora];
        }

        $keep = [];
        foreach (array_merge(
            is_array($partida['encuentros'] ?? null) ? $partida['encuentros'] : [],
            is_array($partida['npc_autonomo']['planes_pendientes'] ?? null) ? $partida['npc_autonomo']['planes_pendientes'] : []
        ) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $k = (int) ($item['dia'] ?? 0) . ':' . (int) ($item['hora'] ?? -1);
            if (isset($intervalo[$k])) {
                $keep[$k] = $intervalo[$k];
            }
        }

        $lastKey = array_key_last($intervalo);
        if ($lastKey !== null) {
            $keep[$lastKey] = $intervalo[$lastKey];
        }

        return array_values($keep);
    }
}

