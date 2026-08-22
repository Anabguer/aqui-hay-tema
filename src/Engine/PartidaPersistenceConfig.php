<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Configuración de persistencia AHT ↔ Intocables jg_progress. */
final class PartidaPersistenceConfig
{
    public const GAME_SLUG = 'aqui-hay-tema';
    public const SLOT = 0;
    /** Límite de producto para payload JSON de partida (8 MiB). */
    public const MAX_PAYLOAD_BYTES = 8388608;

    /** auto | file | sql */
    public static function mode(): string
    {
        $m = strtolower(trim((string) (getenv('AHT_PERSISTENCE') ?: 'auto')));
        return in_array($m, ['auto', 'file', 'sql'], true) ? $m : 'auto';
    }

    public static function shouldUseSql(?int $userId): bool
    {
        if ($userId === null || $userId <= 0) {
            return false;
        }
        $mode = self::mode();
        if ($mode === 'file') {
            return false;
        }
        if ($mode === 'sql') {
            return IntocablesDatabase::isAvailable();
        }
        return IntocablesDatabase::isAvailable();
    }

    public static function assertEncodedPayloadSize(string $json): void
    {
        $bytes = strlen($json);
        if ($bytes > self::MAX_PAYLOAD_BYTES) {
            throw new \RuntimeException('save_demasiado_grande:' . $bytes);
        }
    }

    public static function assertPartidaIdMatches(array $partida, string $partidaId): void
    {
        $storedId = (string) ($partida['meta']['partida_id'] ?? '');
        $requested = preg_replace('/[^a-zA-Z0-9_\-]/', '', $partidaId) ?? $partidaId;
        if ($storedId === '' || $storedId !== $requested) {
            throw new \RuntimeException('partida_no_autorizada');
        }
    }
}
