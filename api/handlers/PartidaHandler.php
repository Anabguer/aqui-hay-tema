<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\labActiva;
use function AquiHayTema\Api\savePartida;
use function AquiHayTema\Api\withLabAudit;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ContentValidationException;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\LabAudit;
use AquiHayTema\Engine\PartidaValidator;

final class PartidaHandler
{
    public static function nueva(ApiContext $ctx, array $body): array
    {
        try {
            $partida = $ctx->service->nuevaPartida(
                $body['config_id'] ?? 'debug_v0',
                isset($body['seed']) ? (string) $body['seed'] : null
            );
        } catch (ContentValidationException $e) {
            return [
                'ok' => false,
                'error' => 'content_validation_failed',
                'errores' => $e->errores,
                'mensaje_ui' => 'El catálogo o una ficha no es válida.',
            ];
        }
        FeatureConfig::mergeIntoPartida($partida, $ctx->root);
        LabAudit::reset();
        if (labActiva($body)) {
            LabAudit::eventoNuevaPartida($partida, new Catalog($ctx->root));
        }
        return withLabAudit(['ok' => true, 'partida' => $ctx->service->estadoResumido($partida), 'partida_id' => $partida['meta']['partida_id']]);
    }

    public static function listar(ApiContext $ctx, array $body): array
    {
        return ['ok' => true, 'partidas' => $ctx->service->listarPartidas()];
    }

    public static function estado(ApiContext $ctx, array $body, array $partida): array
    {
        return ['ok' => true, 'estado' => $ctx->service->estadoResumido($partida)];
    }

    public static function guardar(ApiContext $ctx, array $body, array $partida): array
    {
        savePartida($ctx, $partida);
        return ['ok' => true, 'guardado' => true];
    }

    public static function cargar(ApiContext $ctx, array $body): array
    {
        $id = $body['partida_id'] ?? null;
        if (!$id) {
            return ['ok' => false, 'error' => 'partida_id_requerido'];
        }
        $partida = $ctx->service->cargar((string) $id);
        return ['ok' => true, 'partida_id' => $id, 'estado' => $ctx->service->estadoResumido($partida)];
    }

    public static function reiniciar(ApiContext $ctx, array $body, array $partida): array
    {
        $id = $partida['meta']['partida_id'];
        $nueva = $ctx->service->reiniciarPartida($id, $body['config_id'] ?? 'debug_v0', $body['seed'] ?? null);
        LabAudit::reset();
        if (labActiva($body)) {
            LabAudit::eventoNuevaPartida($nueva, new Catalog($ctx->root));
        }
        return withLabAudit([
            'ok' => true,
            'partida_id' => $id,
            'nota' => 'Reiniciar conserva partida_id; partida.nueva crea id nuevo',
            'estado' => $ctx->service->estadoResumido($nueva),
        ]);
    }

    public static function inspeccionar(ApiContext $ctx, array $body, array $partida): array
    {
        LabAudit::reset();
        if (labActiva($body)) {
            $catalog = new Catalog($ctx->root);
            LabAudit::push('REL', '[AHT DEBUG REL]', [
                'evento' => 'MATRIZ_COMPLETA',
                'matriz' => LabAudit::matrizRelacionalPublica($partida, $catalog),
            ]);
        }
        return withLabAudit(['ok' => true, 'partida' => $partida]);
    }

    public static function validar(ApiContext $ctx, array $body, array $partida): array
    {
        $errores = PartidaValidator::validar($partida);
        return ['ok' => empty($errores), 'errores' => $errores];
    }
}
