<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\requireDev;
use function AquiHayTema\Api\requirePartidaLigera;
use function AquiHayTema\Api\savePartidaRapida;
use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\MensajitoAcciones;
use AquiHayTema\Engine\PeticionPuebloEngine;

final class BuzonHandler
{
    public static function listar(ApiContext $ctx, array $body, array $partida): array
    {
        $canal = array_key_exists('canal', $body)
            ? $body['canal']
            : BuzonEngine::CANAL_BUZON;
        $mensajes = BuzonEngine::listar(
            $partida,
            $body['estado'] ?? null,
            $body['clasificacion'] ?? null,
            $canal
        );
        $mensajes = array_values(array_filter(
            $mensajes,
            static fn($m) => is_array($m) && BuzonEngine::tieneContenido($m)
        ));
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
            $pet = $pets[$pid];
            if (($pet['estado'] ?? '') === PeticionPuebloEngine::EST_ABIERTA && empty($m['selector_opciones'])) {
                $preset = PeticionPuebloEngine::presetOrganizarParaUi($partida, $pet);
                if ($preset !== null) {
                    $m['preset_organizar'] = $preset;
                }
            }
        }
        unset($m);
        return [
            'ok' => true,
            'mensajes' => $mensajes,
            'no_leidos' => BuzonEngine::contarNoLeidos($partida),
        ];
    }

    public static function leer(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = BuzonEngine::marcarLeido($partida, (string) ($body['mensaje_id'] ?? ''));
        if ($r['ok'] ?? false) {
            $mid = (string) ($body['mensaje_id'] ?? '');
            \AquiHayTema\Engine\TutorialPrimerosPasos::alLeerMensaje(
                $partida,
                $mid,
                $ctx->service->getCatalog()
            );
            if (($partida['tutorial']['id'] ?? '') !== \AquiHayTema\Engine\TutorialPrimerosPasos::ID) {
                $r['tutorial'] = \AquiHayTema\Engine\TutorialBucle::registrarConRoot(
                    $partida,
                    \AquiHayTema\Engine\TutorialBucle::HECHO_BUZON,
                    $ctx->root,
                    $ctx->logger
                );
            } else {
                $r['tutorial'] = \AquiHayTema\Engine\TutorialPrimerosPasos::vistaPublica($partida);
            }
            savePartidaRapida($ctx, $partida);
            $r['no_leidos'] = BuzonEngine::contarNoLeidos($partida);
        }
        return $r;
    }

    public static function leerTodos(ApiContext $ctx, array $body, array &$partida): array
    {
        $canal = array_key_exists('canal', $body)
            ? $body['canal']
            : BuzonEngine::CANAL_BUZON;
        $r = BuzonEngine::marcarTodosLeidos($partida, $canal);
        if (($r['ok'] ?? false) && ($r['marcados'] ?? 0) > 0) {
            foreach ($r['ids'] ?? [] as $mid) {
                \AquiHayTema\Engine\TutorialPrimerosPasos::alLeerMensaje(
                    $partida,
                    (string) $mid,
                    $ctx->service->getCatalog()
                );
            }
            if (($partida['tutorial']['id'] ?? '') !== \AquiHayTema\Engine\TutorialPrimerosPasos::ID) {
                $r['tutorial'] = \AquiHayTema\Engine\TutorialBucle::registrarConRoot(
                    $partida,
                    \AquiHayTema\Engine\TutorialBucle::HECHO_BUZON,
                    $ctx->root,
                    $ctx->logger
                );
            } else {
                $r['tutorial'] = \AquiHayTema\Engine\TutorialPrimerosPasos::vistaPublica($partida);
            }
            savePartidaRapida($ctx, $partida);
        }
        return $r;
    }

    public static function noLeer(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = BuzonEngine::marcarEstado($partida, (string) ($body['mensaje_id'] ?? ''), 'pendiente');
        if ($r['ok'] ?? false) {
            savePartidaRapida($ctx, $partida);
            $r['no_leidos'] = BuzonEngine::contarNoLeidos($partida);
        }
        return $r;
    }

    public static function resolver(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = MensajitoAcciones::resolver(
            $partida,
            (string) ($body['mensaje_id'] ?? ''),
            (string) ($body['accion'] ?? ''),
            $ctx->root,
            $ctx->logger,
            is_array($body) ? $body : []
        );
        if ($r['ok'] ?? false) {
            savePartidaRapida($ctx, $partida);
        }
        return $r;
    }

    public static function catalogoAcciones(ApiContext $ctx): array
    {
        return [
            'ok' => true,
            'acciones' => MensajitoAcciones::catalogo(),
        ];
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
