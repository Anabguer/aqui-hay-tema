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

        $path = $this->pathFor($id);
        $tmp = $path . '.tmp';
        $bak = $path . '.bak';

        JsonFile::write($tmp, $partida);

        try {
            JsonFile::read($tmp);
        } catch (\Throwable $e) {
            @unlink($tmp);
            throw new \RuntimeException('Validación post-escritura fallida: ' . $e->getMessage(), 0, $e);
        }

        if (is_file($path)) {
            try {
                JsonFile::read($path);
                @copy($path, $bak);
            } catch (\Throwable $ignored) {
                // No reemplazar .bak con un save ya corrupto
            }
        }
        if (!@rename($tmp, $path)) {
            JsonFile::write($path, $partida);
            @unlink($tmp);
        }
    }

    public function cargar(string $partidaId): array
    {
        $path = $this->pathFor($partidaId);
        if (!is_file($path)) {
            throw new \RuntimeException('partida_no_encontrada');
        }
        try {
            $partida = JsonFile::read($path);
        } catch (\Throwable $e) {
            if (is_file($path . '.bak')) {
                $partida = JsonFile::read($path . '.bak');
                return SchemaMigrator::migrate($partida);
            }
            throw new \RuntimeException('save_corrupto: ' . $e->getMessage(), 0, $e);
        }
        return SchemaMigrator::migrate($partida);
    }

    public function eliminar(string $partidaId): bool
    {
        $path = $this->pathFor($partidaId);
        $ok = is_file($path) && @unlink($path);
        @unlink($path . '.bak');
        @unlink($path . '.tmp');
        return $ok;
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
            if (str_ends_with($file, '.bak') || str_ends_with($file, '.tmp')) {
                continue;
            }
            try {
                $data = JsonFile::read($file);
                $out[] = [
                    'partida_id' => $data['meta']['partida_id'] ?? basename($file, '.json'),
                    'updated_at' => $data['meta']['updated_at'] ?? null,
                    'reloj' => $data['reloj'] ?? null,
                ];
            } catch (\Throwable $ignored) {
                continue;
            }
        }
        usort($out, static fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
        return $out;
    }
}
