<?php
declare(strict_types=1);

namespace AquiHayTema\Api;

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\GameError;
use AquiHayTema\Engine\GameLogger;
use AquiHayTema\Engine\PartidaDevService;
use AquiHayTema\Engine\PartidaRepository;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\SnapshotService;

final class ApiContext
{
    public readonly PartidaService $service;
    public readonly PartidaRepository $repo;
    public readonly PartidaDevService $dev;
    public readonly SnapshotService $snapshots;
    public readonly GameLogger $logger;

    public function __construct(public readonly string $root)
    {
        DomainBootstrap::boot();
        $this->service = new PartidaService($root);
        $this->repo = new PartidaRepository($root);
        $this->logger = new GameLogger($root);
        $this->dev = new PartidaDevService($this->repo, $this->logger);
        $this->snapshots = new SnapshotService($root);
    }
}

function jsonOut(array $data, int $code = 200): never
{
    if (isset($data['_http'])) {
        $code = (int) $data['_http'];
        unset($data['_http']);
    }
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function readBody(): array
{
    $raw = file_get_contents('php://input') ?: '{}';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function requirePartida(ApiContext $ctx, array $body): array
{
    $id = $body['partida_id'] ?? ($_GET['partida_id'] ?? null);
    if (!$id) {
        jsonOut(GameError::respuesta(GameError::VALIDACION_FALLIDA, ['campo' => 'partida_id'], 400));
    }
    try {
        $partida = $ctx->service->cargar((string) $id);
        FeatureConfig::mergeIntoPartida($partida, $ctx->root);
        return $partida;
    } catch (\Throwable $e) {
        $code = str_contains($e->getMessage(), 'corrupto') ? GameError::SAVE_CORRUPTO : GameError::PARTIDA_NO_ENCONTRADA;
        jsonOut(GameError::respuesta($code, ['detalle' => $e->getMessage()], 404));
    }
}

function requireDev(): void
{
    require_once dirname(__DIR__) . '/src/dev_gate.php';
    if (!aht_dev_enabled()) {
        jsonOut(GameError::respuesta(GameError::DEV_DESHABILITADO, [], 403));
    }
}

function savePartida(ApiContext $ctx, array &$partida): void
{
    $ctx->service->guardar($partida);
}
