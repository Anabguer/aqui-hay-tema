<?php
declare(strict_types=1);

namespace AquiHayTema\Api;

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\GameError;
use AquiHayTema\Engine\GameLogger;
use AquiHayTema\Engine\IntocablesSession;
use AquiHayTema\Engine\PartidaDevService;
use AquiHayTema\Engine\PartidaRepository;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\SnapshotService;

final class ApiContext
{
    public PartidaService $service;
    public PartidaRepository $repo;
    public PartidaDevService $dev;
    public SnapshotService $snapshots;
    public GameLogger $logger;
    public string $root;
    public bool $partidaCargadaSincronizada = false;
    private ?\AquiHayTema\Engine\VisualPackStore $visualPackStore = null;

    public function visualPacks(): \AquiHayTema\Engine\VisualPackStore
    {
        if ($this->visualPackStore === null) {
            $this->visualPackStore = new \AquiHayTema\Engine\VisualPackStore($this->root);
        }
        return $this->visualPackStore;
    }

    public function __construct(string $root)
    {
        $this->root = $root;
        DomainBootstrap::boot();
        $this->service = new PartidaService($root);
        $this->service->setUserContext(IntocablesSession::currentUserId($root));
        $this->repo = $this->service->getRepository();
        $this->logger = new GameLogger($root);
        $this->dev = new PartidaDevService($this->repo, $this->logger);
        $this->snapshots = new SnapshotService($root);
    }
}

function jsonOut(array $data, int $code = 200): void
{
    if (isset($data['_http'])) {
        $code = (int) $data['_http'];
        unset($data['_http']);
    }
    http_response_code($code);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function readBody(): array
{
    $raw = file_get_contents('php://input') ?: '{}';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function partidaLoadFail(\Throwable $e): void
{
    $msg = $e->getMessage();
    if (str_starts_with($msg, 'save_demasiado_grande')) {
        jsonOut(GameError::respuesta(GameError::SAVE_DEMASIADO_GRANDE, ['detalle' => $msg], 413));
    }
    if (str_contains($msg, 'partida_no_autorizada')) {
        jsonOut(GameError::respuesta(GameError::PARTIDA_NO_ENCONTRADA, ['detalle' => $msg], 403));
    }
    $code = str_contains($msg, 'corrupto') ? GameError::SAVE_CORRUPTO : GameError::PARTIDA_NO_ENCONTRADA;
    jsonOut(GameError::respuesta($code, ['detalle' => $msg], 404));
}

function requirePartidaRefresh(ApiContext $ctx, array $body): array
{
    $id = $body['partida_id'] ?? ($_GET['partida_id'] ?? null);
    if (!$id) {
        jsonOut(GameError::respuesta(GameError::VALIDACION_FALLIDA, ['campo' => 'partida_id'], 400));
    }
    try {
        $partida = $ctx->service->cargarParaRefresh((string) $id);
        FeatureConfig::mergeIntoPartida($partida, $ctx->root);
        $ctx->partidaCargadaSincronizada = true;
        return $partida;
    } catch (\Throwable $e) {
        partidaLoadFail($e);
    }
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
        $ctx->partidaCargadaSincronizada = true;
        return $partida;
    } catch (\Throwable $e) {
        partidaLoadFail($e);
    }
}

/**
 * Carga ligera: sin sync de encuentros/misiones/peticiones ni guardado en carga.
 *
 * @return array<string, mixed>
 */
function requirePartidaLigera(ApiContext $ctx, array $body): array
{
    $id = $body['partida_id'] ?? ($_GET['partida_id'] ?? null);
    if (!$id) {
        jsonOut(GameError::respuesta(GameError::VALIDACION_FALLIDA, ['campo' => 'partida_id'], 400));
    }
    try {
        $partida = $ctx->service->cargarLigero((string) $id);
        FeatureConfig::mergeIntoPartida($partida, $ctx->root);
        $ctx->partidaCargadaSincronizada = false;
        return $partida;
    } catch (\Throwable $e) {
        partidaLoadFail($e);
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

function savePartidaRapida(ApiContext $ctx, array &$partida): void
{
    $ctx->service->guardarRapido($partida);
}

function labActiva(array $body): bool
{
    return \AquiHayTema\Engine\LabAudit::activa($body);
}

function withLabAudit(array $response): array
{
    return \AquiHayTema\Engine\LabAudit::attach($response);
}
