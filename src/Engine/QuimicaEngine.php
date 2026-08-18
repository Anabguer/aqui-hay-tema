<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Química = facilidad inexplicable para conectar. NO es atracción romántica.
 * Persistida, estable, reproducible. V1 simétrica en valor; almacén direccional.
 */
final class QuimicaEngine
{
    public static function ensure(array &$partida): void
    {
        $partida['quimica'] ??= [
            'pares' => [],
            'simetrica_v1' => true,
        ];
        $partida['quimica']['pares'] ??= [];
    }

    public static function parId(string $a, string $b): string
    {
        return $a < $b ? "qui_{$a}_{$b}" : "qui_{$b}_{$a}";
    }

    /**
     * @return array<string, mixed>
     */
    public static function obtener(array $partida, string $a, string $b): ?array
    {
        self::ensure($partida);
        $id = self::parId($a, $b);
        $row = $partida['quimica']['pares'][$id] ?? null;
        return is_array($row) ? $row : null;
    }

    /**
     * No rerolea si ya existe.
     *
     * @return array<string, mixed>
     */
    public static function asegurarPar(
        array &$partida,
        string $a,
        string $b,
        RngService $rng,
        array $cal,
        ?GameLogger $logger = null
    ): array {
        self::ensure($partida);
        $id = self::parId($a, $b);
        if (isset($partida['quimica']['pares'][$id])) {
            return $partida['quimica']['pares'][$id];
        }
        $min = (int) CalibracionConfig::get($cal, 'quimica.min', 0);
        $max = (int) CalibracionConfig::get($cal, 'quimica.max', 100);
        $valor = $rng->nextInt($min, $max);
        $simetrica = (bool) CalibracionConfig::get($cal, 'quimica.simetrica_v1', true);
        $ab = $valor;
        $ba = $simetrica ? $valor : $rng->nextInt($min, $max);
        $row = [
            'id' => $id,
            'persona_a' => $a < $b ? $a : $b,
            'persona_b' => $a < $b ? $b : $a,
            'a_hacia_b' => $a < $b ? $ab : $ba,
            'b_hacia_a' => $a < $b ? $ba : $ab,
            'simetrica' => $simetrica,
            'visible_jugador' => false,
            '_provisional' => true,
        ];
        $partida['quimica']['pares'][$id] = $row;
        $rng->persistToPartida($partida);
        DomainEventDispatcher::emit($partida, DomainEvents::QUIMICA_GENERADA, [
            'par' => $row,
            'actores' => [$a, $b],
        ], $logger, 'QuimicaEngine::asegurarPar', [$a, $b]);
        return $row;
    }

    public static function alIncorporar(
        array &$partida,
        string $nuevoId,
        Catalog $catalog,
        ?GameLogger $logger = null
    ): int {
        $cal = CalibracionConfig::load($catalog->getRoot());
        $rng = RngService::fromPartida($partida);
        $n = 0;
        foreach ($partida['residentes'] ?? [] as $otroId => $_) {
            if ((string) $otroId === $nuevoId) {
                continue;
            }
            $antes = self::obtener($partida, $nuevoId, (string) $otroId);
            self::asegurarPar($partida, $nuevoId, (string) $otroId, $rng, $cal, $logger);
            CompatibilidadOculta::asegurarDireccional($partida, $nuevoId, (string) $otroId, $catalog);
            if ($antes === null) {
                $n++;
            }
        }
        $rng->persistToPartida($partida);
        return $n;
    }

    public static function valorHacia(array $partida, string $desde, string $hacia): ?int
    {
        $row = self::obtener($partida, $desde, $hacia);
        if ($row === null) {
            return null;
        }
        $a = (string) ($row['persona_a'] ?? '');
        $b = (string) ($row['persona_b'] ?? '');
        if ($desde === $a && $hacia === $b) {
            return isset($row['a_hacia_b']) ? (int) $row['a_hacia_b'] : null;
        }
        if ($desde === $b && $hacia === $a) {
            return isset($row['b_hacia_a']) ? (int) $row['b_hacia_a'] : null;
        }
        return null;
    }
}
