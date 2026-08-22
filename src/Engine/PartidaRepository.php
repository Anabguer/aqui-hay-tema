<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PartidaRepository
{
    private string $dir;
    private string $root;
    private ?int $userId = null;
    private ?PartidaJgProgressStorage $jgStorage = null;

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $this->dir = $this->root . '/data/partidas';
        if (!is_dir($this->dir) && !mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            throw new \RuntimeException("No se pudo crear {$this->dir}");
        }
    }

    public function setUserContext(?int $userId): void
    {
        $this->userId = ($userId !== null && $userId > 0) ? $userId : null;
        $this->jgStorage = null;
    }

    public function persistenceBackend(): string
    {
        return $this->useSql() ? 'sql' : 'file';
    }

    public function pathFor(string $partidaId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $partidaId) ?? $partidaId;
        return "{$this->dir}/{$safe}.json";
    }

    public function guardar(array $partida): void
    {
        if ($this->useSql()) {
            $this->jg()->guardar((int) $this->userId, $partida);
            return;
        }
        $this->guardarArchivo($partida, true);
    }

    public function guardarRapido(array $partida): void
    {
        if ($this->useSql()) {
            $this->jg()->guardar((int) $this->userId, $partida);
            return;
        }
        $this->guardarArchivo($partida, false);
    }

    public function cargar(string $partidaId): array
    {
        if ($this->useSql()) {
            $partida = $this->jg()->cargar((int) $this->userId, $partidaId);
            return $this->postCargar($partida);
        }
        return $this->cargarArchivo($partidaId);
    }

    public function eliminar(string $partidaId): bool
    {
        if ($this->useSql()) {
            if (!$this->jg()->existe((int) $this->userId, $partidaId)) {
                return false;
            }
            return $this->jg()->eliminar((int) $this->userId);
        }
        $path = $this->pathFor($partidaId);
        $ok = is_file($path) && @unlink($path);
        @unlink($path . '.bak');
        @unlink($path . '.tmp');
        return $ok;
    }

    public function existe(string $partidaId): bool
    {
        if ($this->useSql()) {
            return $this->jg()->existe((int) $this->userId, $partidaId);
        }
        return is_file($this->pathFor($partidaId));
    }

    /** @return list<array{partida_id: string, updated_at: string|null, reloj: mixed}> */
    public function listar(): array
    {
        if ($this->useSql()) {
            return $this->jg()->listar((int) $this->userId);
        }
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

    private function useSql(): bool
    {
        return PartidaPersistenceConfig::shouldUseSql($this->userId);
    }

    private function jg(): PartidaJgProgressStorage
    {
        if ($this->jgStorage === null) {
            $this->jgStorage = PartidaJgProgressStorage::fromProject($this->root);
        }
        return $this->jgStorage;
    }

    private function guardarArchivo(array $partida, bool $conValidacion): void
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

        if ($conValidacion) {
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
                }
            }
        }
        if (!@rename($tmp, $path)) {
            JsonFile::write($path, $partida);
            @unlink($tmp);
        }
    }

    private function cargarArchivo(string $partidaId): array
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
                return $this->postCargar($partida);
            }
            throw new \RuntimeException('save_corrupto: ' . $e->getMessage(), 0, $e);
        }
        return $this->postCargar($partida);
    }

    /** @param array<string, mixed> $partida */
    private function postCargar(array $partida): array
    {
        $partida = SchemaMigrator::migrate($partida);
        self::sanitizarTextosPartida($partida);
        return $partida;
    }

    /** @param array<string, mixed> $partida */
    private static function sanitizarTextosPartida(array &$partida): void
    {
        foreach ($partida['residentes'] ?? [] as &$res) {
            if (!is_array($res)) {
                continue;
            }
            if (!isset($res['identidad_publica']) || !is_array($res['identidad_publica'])) {
                $res['identidad_publica'] = [];
            }
            $n = $res['identidad_publica']['nombre'] ?? null;
            if (is_string($n)) {
                $res['identidad_publica']['nombre'] = Utf8Text::repair($n);
            }
        }
        unset($res);
    }
}
