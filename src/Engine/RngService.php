<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** RNG centralizado reproducible (LCG). Todo azar del simulador debe pasar por aquí. */
final class RngService
{
    private const MOD = 2147483647;
    private const MULT = 48271;

    private int $state;

    public function __construct(string $seed, ?int $state = null)
    {
        if ($state !== null) {
            $this->state = $state % self::MOD;
            if ($this->state <= 0) {
                $this->state += self::MOD - 1;
            }
            return;
        }
        $hash = crc32($seed);
        $this->state = ($hash & 0x7FFFFFFF) ?: 1;
    }

    public static function fromPartida(array $partida): self
    {
        $rng = $partida['rng'] ?? [];
        $seed = (string) ($rng['seed'] ?? $partida['meta']['seed'] ?? 'default');
        $state = isset($rng['state']) ? (int) $rng['state'] : null;
        return new self($seed, $state);
    }

    public function persistToPartida(array &$partida): void
    {
        $partida['rng'] = [
            'seed' => $partida['rng']['seed'] ?? $partida['meta']['seed'] ?? 'default',
            'state' => $this->state,
        ];
    }

    public function getState(): int
    {
        return $this->state;
    }

    public function nextFloat(): float
    {
        return $this->nextInt(0, self::MOD - 1) / (self::MOD - 1);
    }

    public function nextInt(int $min, int $max): int
    {
        if ($max < $min) {
            throw new \InvalidArgumentException('max < min');
        }
        $range = $max - $min + 1;
        $this->step();
        return $min + (int) floor(($this->state / self::MOD) * $range);
    }

    public function next(): int
    {
        $this->step();
        return $this->state;
    }

    private function step(): void
    {
        $this->state = (int) (($this->state * self::MULT) % self::MOD);
    }
}
