<?php
declare(strict_types=1);

namespace AquiHayTema\Api\Handlers;

use AquiHayTema\Api\ApiContext;
use function AquiHayTema\Api\labActiva;
use function AquiHayTema\Api\savePartida;
use function AquiHayTema\Api\withLabAudit;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\LabAudit;
use AquiHayTema\Engine\Reloj;
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
            try {
                LabAudit::eventosEncuentrosTerminados($antesEnc, $partida, $catalog);
                LabAudit::eventosNuevosResidentes($antesRes, $partida, $catalog);
                LabAudit::eventoVidaDesdeIndice($partida, $antesLedger);
                LabAudit::eventoMisionesDia($partida, $ctx->root);
            } catch (\Throwable $auditErr) {
                LabAudit::push('AUDIT', '[AHT DEBUG ERROR]', [
                    'mensaje' => $auditErr->getMessage(),
                    'accion' => 'reloj.avanzar',
                ]);
            }
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

    /**
     * Sincronización ligera: procesa tiempo real pendiente (catch-up) sin avance manual.
     * Devuelve el reloj canónico actualizado + flag de cambios visibles.
     * Ideal para heartbeat periódico.
     *
     * NOTA: requirePartida() en la ruta ya ejecuta cargar() → CatchUpEngine::ejecutarAlCargar()
     * antes de llegar aquí. Solo reportamos el estado resultante.
     */
    public static function sincronizar(ApiContext $ctx, array $body, array &$partida): array
    {
        $cu = $partida['reloj']['catch_up_pendiente'] ?? [];
        $horasProcesadas = (int) ($cu['horas_juego_avanzadas'] ?? 0);
        $ejecutado = !empty($cu['ejecutado']);
        $hayCambiosVisibles = $ejecutado && $horasProcesadas > 0;
        savePartida($ctx, $partida);
        return withLabAudit([
            'ok' => true,
            'reloj' => $partida['reloj'],
            'reloj_vista' => Reloj::vista($partida['reloj']),
            'reloj_texto' => Reloj::formatear($partida['reloj']),
            'hay_cambios_visibles' => $hayCambiosVisibles,
            'catch_up' => [
                'ejecutado' => $ejecutado,
                'horas_procesadas' => $horasProcesadas,
                'segundos_pendientes' => (int) ($cu['segundos_pendientes'] ?? 0),
                'hora_antes' => isset($cu['hora_antes']) ? (int) $cu['hora_antes'] : null,
                'hora_despues' => isset($cu['hora_despues']) ? (int) $cu['hora_despues'] : null,
                'dia_antes' => isset($cu['dia_antes']) ? (int) $cu['dia_antes'] : null,
                'dia_despues' => isset($cu['dia_despues']) ? (int) $cu['dia_despues'] : null,
                'encuentros_offline' => (int) ($cu['encuentros_offline'] ?? 0),
                'eventos_offline' => (int) ($cu['eventos_offline'] ?? 0),
                'salidas_offline' => (int) ($cu['salidas_offline'] ?? 0),
            ],
        ]);
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
