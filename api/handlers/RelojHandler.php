<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\labActiva;
use function AquiHayTema\Api\savePartida;
use function AquiHayTema\Api\withLabAudit;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\LabAudit;
use AquiHayTema\Engine\RelojDev;

final class RelojHandler
{
    public static function avanzar(ApiContext $ctx, array $body, array &$partida): array
    {
        $lab = labActiva($body);
        $catalog = $lab ? new Catalog($ctx->root) : null;
        $antesEnc = $lab ? LabAudit::snapshotEstadosEncuentros($partida) : [];
        $antesRes = $lab ? LabAudit::residentesActivos($partida) : [];
        $antesLedger = $lab ? count($partida['vida_pueblo']['ledger'] ?? []) : 0;

        $horas = (int) ($body['horas'] ?? 1);
        $paso = (bool) ($body['paso_a_paso'] ?? false);
        $result = $paso
            ? $ctx->service->avanzarRelojPasoAPaso($partida, $horas)
            : $ctx->service->avanzarReloj($partida, $horas);
        if (($result['ok'] ?? true) === false) {
            return $result;
        }
        if ($lab && $catalog !== null) {
            LabAudit::eventosEncuentrosTerminados($antesEnc, $partida, $catalog);
            LabAudit::eventosNuevosResidentes($antesRes, $partida, $catalog);
            LabAudit::eventoVidaDesdeIndice($partida, $antesLedger);
            LabAudit::eventoMisionesDia($partida, $ctx->root);
        }
        savePartida($ctx, $partida);
        return withLabAudit([
            'ok' => true,
            'reloj' => $result,
            'resumen_avance' => $result['resumen_avance'] ?? ['lineas' => [], 'total' => 0],
            'playtest_guia_evento' => $result['playtest_guia_evento'] ?? null,
            'playtest_guia' => $result['playtest_guia'] ?? null,
            'playtest_diag' => $result['playtest_diag'] ?? null,
        ]);
    }

    public static function proximoEncuentro(ApiContext $ctx, array $body, array &$partida): array
    {
        $r = $ctx->service->irAlProximoEncuentro($partida);
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }

    public static function irA(ApiContext $ctx, array $body, array &$partida): array
    {
        \AquiHayTema\Api\requireDev();
        $r = RelojDev::irA(
            $partida,
            (int) ($body['dia'] ?? $partida['reloj']['dia_pueblo']),
            (int) ($body['hora'] ?? $partida['reloj']['hora_actual']),
            (bool) ($body['permitir_rewind'] ?? false),
            $ctx->logger,
            $ctx->service->emociones(),
            $ctx->root
        );
        if ($r['ok'] ?? false) {
            savePartida($ctx, $partida);
        }
        return $r;
    }
}
