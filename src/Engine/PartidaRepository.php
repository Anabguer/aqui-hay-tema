<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PartidaRepository
{
    private string $dir;

    public function __construct(string $projectRoot)
    {
        $this->dir = rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/data/partidas';
        if (!is_dir($this->dir) && !mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            throw new \RuntimeException("No se pudo crear {$this->dir}");
        }
    }

    public function pathFor(string $partidaId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $partidaId) ?? $partidaId;
        return "{$this->dir}/{$safe}.json";
    }

    public function guardar(array $partida): void
    {
        $id = $partida['meta']['partida_id'] ?? null;
        if (!$id) {
            throw new \InvalidArgumentException('partida sin partida_id');
        }
        $partida['meta']['updated_at'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM);
        JsonFile::write($this->pathFor($id), $partida);
    }

    public function cargar(string $partidaId): array
    {
        return JsonFile::read($this->pathFor($partidaId));
    }

    public function existe(string $partidaId): bool
    {
        return is_file($this->pathFor($partidaId));
    }

    /** @return list<array{partida_id: string, updated_at: string|null}> */
    public function listar(): array
    {
        $out = [];
        foreach (glob("{$this->dir}/*.json") ?: [] as $file) {
            try {
                $data = JsonFile::read($file);
                $out[] = [
                    'partida_id' => $data['meta']['partida_id'] ?? basename($file, '.json'),
                    'updated_at' => $data['meta']['updated_at'] ?? null,
                    'reloj' => $data['reloj'] ?? null,
                ];
            } catch (\Throwable) {
                continue;
            }
        }
        usort($out, static fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
        return $out;
    }
}
