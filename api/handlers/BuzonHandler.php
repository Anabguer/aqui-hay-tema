<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\requireDev;
use function AquiHayTema\Api\savePartida;
use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\PeticionPuebloEngine;

final class BuzonHandler
{
    public static function listar(ApiContext $ctx, array $body, array $partida): array
    {
        $mensajes = BuzonEngine::listar(
            $partida,
            $body['estado'] ?? null,
            $body['clasificacion'] ?? null,
            $body['canal'] ?? null
        );
        $pets = [];
        foreach ($partida['peticiones'] ?? [] as $p) {
            if (is_array($p) && !empty($p['id'])) {
                $pets[(string) $p['id']] = $p;
            }
        }
        foreach ($mensajes as &$m) {
            if (!is_array($m)) {
                continue;
            }
            $pid = (string) ($m['peticion_id'] ?? '');
            if ($pid === '' || !isset($pets[$pid])) {
                continue;
            }
            $v = PeticionPuebloEngine::vistaItem($partida, $pets[$pid]);
            $m['plazo_humano'] = $v['plazo_humano'] ?? '';
            $m['estado_pueblo'] = $v['estado'] ?? null;
            if (($m['texto'] ?? '') === '' && ($v['texto'] ?? '') !== '') {
                $m['texto'] = (string) $v['texto'];
            }
        }
        unset($m);
        return [
            'ok' => true,
            'mensajes' => $mensajes,
        ];
    }

    public static function leer(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = BuzonEngine::marcarLeido($partida, (string) ($body['mensaje_id'] ?? ''));
        if ($r['ok'] ?? false) {
            $r['tutorial'] = \AquiHayTema\Engine\TutorialBucle::registrarConRoot(
                $partida,
                \AquiHayTema\Engine\TutorialBucle::HECHO_BUZON,
                $ctx->root,
                $ctx->logger
            );
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function crearDev(ApiContext $ctx, array $body, array &$partida): array
    {
        requireDev();
        $r = BuzonEngine::crear($partida, is_array($body['mensaje'] ?? null) ? $body['mensaje'] : [
            'texto' => '[DEV PLACEHOLDER] Mensaje de prueba',
            'de_persona' => $body['de_persona'] ?? 'per_i03',
            'tipo' => $body['tipo'] ?? 'peticion',
        ]);
        savePartida($ctx, $partida);
        return $r;
    }
}
