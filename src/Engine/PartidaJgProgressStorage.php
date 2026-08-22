<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Persistencia de partida completa en jg_progress (mismo contrato que /juegos/api/progress-*.php).
 */
final class PartidaJgProgressStorage
{
    private string $root;
    private \PDO $pdo;

    public function __construct(string $projectRoot, \PDO $pdo)
    {
        $this->root = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $this->pdo = $pdo;
        self::ensureSchema($this->pdo);
    }

    public static function fromProject(string $projectRoot): self
    {
        return new self($projectRoot, IntocablesDatabase::pdo());
    }

    public function guardar(int $userId, array $partida): void
    {
        $id = $partida['meta']['partida_id'] ?? null;
        if (!$id) {
            throw new \InvalidArgumentException('partida sin partida_id');
        }
        $partida['meta']['updated_at'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM);

        $json = json_encode($partida, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('json_encode_fallido');
        }
        PartidaPersistenceConfig::assertEncodedPayloadSize($json);

        $schemaVersion = (int) ($partida['meta']['schema_version'] ?? SchemaMigrator::CURRENT_VERSION);
        $clientUpdated = self::formatClientUpdatedAt((string) ($partida['meta']['updated_at'] ?? ''));
        $summary = self::buildSummary($partida);

        $sql = $this->isMysql()
            ? 'INSERT INTO jg_progress
                (user_id, game_slug, slot, schema_version, summary, payload, client_updated_at)
               VALUES (?, ?, ?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE
                schema_version = VALUES(schema_version),
                summary = VALUES(summary),
                payload = VALUES(payload),
                client_updated_at = VALUES(client_updated_at)'
            : 'INSERT OR REPLACE INTO jg_progress
                (user_id, game_slug, slot, schema_version, summary, payload, client_updated_at)
               VALUES (?, ?, ?, ?, ?, ?, ?)';

        $st = $this->pdo->prepare($sql);
        $st->execute([
            $userId,
            PartidaPersistenceConfig::GAME_SLUG,
            PartidaPersistenceConfig::SLOT,
            $schemaVersion,
            $summary,
            $json,
            $clientUpdated,
        ]);
    }

    public function cargar(int $userId, string $partidaId): array
    {
        $st = $this->pdo->prepare(
            'SELECT payload FROM jg_progress
             WHERE user_id = ? AND game_slug = ? AND slot = ?
             LIMIT 1'
        );
        $st->execute([$userId, PartidaPersistenceConfig::GAME_SLUG, PartidaPersistenceConfig::SLOT]);
        $row = $st->fetch();
        if (!$row) {
            throw new \RuntimeException('partida_no_encontrada');
        }
        $partida = json_decode((string) $row['payload'], true);
        if (!is_array($partida)) {
            throw new \RuntimeException('save_corrupto');
        }
        PartidaPersistenceConfig::assertPartidaIdMatches($partida, $partidaId);
        return $partida;
    }

    public function existe(int $userId, string $partidaId): bool
    {
        try {
            $this->cargar($userId, $partidaId);
            return true;
        } catch (\RuntimeException $e) {
            if (in_array($e->getMessage(), ['partida_no_encontrada', 'partida_no_autorizada', 'save_corrupto'], true)) {
                return false;
            }
            throw $e;
        }
    }

    public function eliminar(int $userId): bool
    {
        $st = $this->pdo->prepare(
            'DELETE FROM jg_progress WHERE user_id = ? AND game_slug = ? AND slot = ?'
        );
        $st->execute([$userId, PartidaPersistenceConfig::GAME_SLUG, PartidaPersistenceConfig::SLOT]);
        return $st->rowCount() > 0;
    }

    /** @return list<array{partida_id: string, updated_at: string|null, reloj: mixed}> */
    public function listar(int $userId): array
    {
        $st = $this->pdo->prepare(
            'SELECT payload, client_updated_at FROM jg_progress
             WHERE user_id = ? AND game_slug = ? AND slot = ?
             LIMIT 1'
        );
        $st->execute([$userId, PartidaPersistenceConfig::GAME_SLUG, PartidaPersistenceConfig::SLOT]);
        $row = $st->fetch();
        if (!$row) {
            return [];
        }
        $partida = json_decode((string) $row['payload'], true);
        if (!is_array($partida)) {
            return [];
        }
        return [[
            'partida_id' => (string) ($partida['meta']['partida_id'] ?? ''),
            'updated_at' => $partida['meta']['updated_at'] ?? ($row['client_updated_at'] ?? null),
            'reloj' => $partida['reloj'] ?? null,
        ]];
    }

    public static function ensureSchema(\PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS jg_progress (
                    user_id INTEGER NOT NULL,
                    game_slug TEXT NOT NULL,
                    slot INTEGER NOT NULL DEFAULT 0,
                    schema_version INTEGER NOT NULL DEFAULT 1,
                    summary TEXT,
                    payload TEXT NOT NULL,
                    client_updated_at TEXT NOT NULL,
                    server_updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (user_id, game_slug, slot)
                )'
            );
            $done = true;
            return;
        }
        $juegosApi = dirname(__DIR__, 2) . '/../api/bootstrap.php';
        if (is_file($juegosApi)) {
            require_once $juegosApi;
            if (function_exists('jg_api_ensure_schema')) {
                jg_api_ensure_schema($pdo);
                $done = true;
                return;
            }
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `jg_progress` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id` INT UNSIGNED NOT NULL,
              `game_slug` VARCHAR(64) NOT NULL,
              `slot` TINYINT UNSIGNED NOT NULL DEFAULT 0,
              `schema_version` INT NOT NULL DEFAULT 1,
              `summary` VARCHAR(255) DEFAULT NULL,
              `payload` LONGTEXT NOT NULL,
              `client_updated_at` DATETIME NOT NULL,
              `server_updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_jg_user_game_slot` (`user_id`, `game_slug`, `slot`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $done = true;
    }

    private function isMysql(): bool
    {
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    private static function formatClientUpdatedAt(string $value): string
    {
        $ts = strtotime($value);
        if ($ts === false) {
            $ts = time();
        }
        return gmdate('Y-m-d H:i:s', $ts) . '.000';
    }

    /** @param array<string, mixed> $partida */
    private static function buildSummary(array $partida): string
    {
        $id = (string) ($partida['meta']['partida_id'] ?? '');
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 0);
        $summary = trim($id . ' · día ' . $dia);
        return mb_substr($summary, 0, 255);
    }
}
