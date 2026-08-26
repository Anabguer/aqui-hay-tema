<?php
declare(strict_types=1);

namespace AquiHayTema\Dev;

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\GameLogger;
use AquiHayTema\Engine\PartidaDevService;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\SnapshotService;
use ReflectionClass;

/** ApiContext sin sesión Intocables/DB — solo harness dev visual. */
final class VisualApiContextFactory
{
    public static function create(string $root): ApiContext
    {
        DomainBootstrap::boot();
        $ref = new ReflectionClass(ApiContext::class);
        /** @var ApiContext $ctx */
        $ctx = $ref->newInstanceWithoutConstructor();
        $ctx->root = $root;
        $ctx->service = new PartidaService($root);
        $ctx->repo = $ctx->service->getRepository();
        $ctx->logger = new GameLogger($root);
        $ctx->dev = new PartidaDevService($ctx->repo, $ctx->logger);
        $ctx->snapshots = new SnapshotService($root);
        $ctx->partidaCargadaSincronizada = false;
        return $ctx;
    }
}
