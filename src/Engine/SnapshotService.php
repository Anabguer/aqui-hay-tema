<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Snapshots DEV para QA — restauración explícita (permite rewind vía snapshot). */
final class SnapshotService
{
    private string $dir;

    public function __construct(string $projectRoot)
    {
        $this->dir = rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/data/snapshots';
        if (!is_dir($this->dir) && !mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            throw new \RuntimeException("No se pudo crear {$this->dir}");
        }
    }

    private function pathFor(string $partidaId, string $name): string
    {
        $pid = preg_replace('/[^a-zA-Z0-9_\-]/', '', $partidaId);
        $n = preg_replace('/[^a-zA-Z0-9_\-]/', '', $name);
        $subdir = "{$this->dir}/{$pid}";
        if (!is_dir($subdir)) {
            mkdir($subdir, 0775, true);
        }
        return "{$subdir}/{$n}.json";
    }

    public function guardar(array $partida, string $nombre): array
    {
        $meta = [
            'nombre' => $nombre,
            'partida_id' => $partida['meta']['partida_id'] ?? null,
            'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'reloj' => $partida['reloj'] ?? null,
        ];
        $payload = ['_snapshot_meta' => $meta, 'partida' => $partida];
        JsonFile::write($this->pathFor((string) $meta['partida_id'], $nombre), $payload);
        return ['ok' => true, 'snapshot' => $meta];
    }

    public function restaurar(string $partidaId, string $nombre): array
    {
        $data = JsonFile::read($this->pathFor($partidaId, $nombre));
        if (!isset($data['partida']) || !is_array($data['partida'])) {
            return GameError::respuesta(GameError::SAVE_CORRUPTO, ['snapshot' => $nombre]);
        }
        return ['ok' => true, 'partida' => SchemaMigrator::migrate($data['partida']), 'meta' => $data['_snapshot_meta'] ?? []];
    }

    public function listar(string $partidaId): array
    {
        $pid = preg_replace('/[^a-zA-Z0-9_\-]/', '', $partidaId);
        $subdir = "{$this->dir}/{$pid}";
        if (!is_dir($subdir)) {
            return [];
        }
        $out = [];
        foreach (glob("{$subdir}/*.json") ?: [] as $f) {
            try {
                $d = JsonFile::read($f);
                $out[] = $d['_snapshot_meta'] ?? ['nombre' => basename($f, '.json')];
            } catch (\Throwable $ignored) {
                continue;
            }
        }
        return $out;
    }

    public function eliminar(string $partidaId, string $nombre): bool
    {
        $p = $this->pathFor($partidaId, $nombre);
        return is_file($p) && unlink($p);
    }
}
