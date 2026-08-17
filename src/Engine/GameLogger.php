<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class GameLogger
{
    private string $logDir;

    public function __construct(string $projectRoot)
    {
        $this->logDir = rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/logs';
        if (!is_dir($this->logDir) && !mkdir($this->logDir, 0775, true) && !is_dir($this->logDir)) {
            throw new \RuntimeException("No se pudo crear {$this->logDir}");
        }
    }

    public function log(array &$partida, string $tipo, array $payload = []): void
    {
        $entry = array_merge([
            'ts' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'partida_id' => $partida['meta']['partida_id'] ?? null,
            'tipo' => $tipo,
            'dia_pueblo' => $partida['reloj']['dia_pueblo'] ?? null,
            'hora_actual' => $partida['reloj']['hora_actual'] ?? null,
        ], $payload);

        if (!isset($partida['event_log']) || !is_array($partida['event_log'])) {
            $partida['event_log'] = [];
        }
        $partida['event_log'][] = $entry;
        if (count($partida['event_log']) > 200) {
            $partida['event_log'] = array_slice($partida['event_log'], -200);
        }

        $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) ($partida['meta']['partida_id'] ?? 'unknown'));
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        @file_put_contents("{$this->logDir}/partida_{$id}.jsonl", $line, FILE_APPEND | LOCK_EX);
    }
}
