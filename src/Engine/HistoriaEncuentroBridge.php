<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente: resultados de encuentro → Historia del Pueblo.
 * Registra hitos que dependen del resultado del encuentro:
 *   hito_03 (UY) — primer hito_romantico en bitácora (flechazo/coqueteo/beso)
 *   hito_06 (cita bien) — primera_cita con resultado positivo
 *   hito_07 (cita mal) — primera_cita con resultado negativo
 *   hito_21 (chiringuito) — primer encuentro en bar/café
 */
final class HistoriaEncuentroBridge
{
    public static function register(): void
    {
        EventBus::on(DomainEvents::ENCUENTRO_TERMINADO, static function (array &$partida, array $envelope, ?GameLogger $logger): array {
            return self::handle($partida, $envelope);
        });
    }

    public static function handle(array &$partida, array $envelope): array
    {
        $payload = is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
        $encuentro = is_array($payload['encuentro'] ?? null) ? $payload['encuentro'] : [];
        $resultado = is_array($payload['resultado'] ?? null) ? $payload['resultado'] : [];
        $participantes = is_array($encuentro['participantes'] ?? null) ? $encuentro['participantes'] : [];

        if ($participantes === [] || empty($encuentro['id'])) {
            return ['ok' => true, 'skipped' => 'sin_participantes'];
        }

        $registrados = [];

        // hito_06 / hito_07: primera cita con resultado bueno/malo
        $tipoEnc = PropuestaNivel::aliasTipo((string) ($encuentro['tipo'] ?? ''));
        if ($tipoEnc === PropuestaNivel::PRIMERA_CITA) {
            $r = self::evaluarResultadoCita($resultado, $participantes);
            if ($r !== null) {
                $reg = HistoriaPuebloEngine::registrar(
                    $partida,
                    $r,
                    array_slice($participantes, 0, 2),
                    ['origen' => 'encuentro_resultado', 'encuentro_id' => $encuentro['id']]
                );
                if ($reg !== null && !($reg['ya_existia'] ?? false)) {
                    $registrados[] = $r;
                }
            }
        }

        // hito_21: primer encuentro en bar/café
        $lugarId = (string) ($encuentro['lugar'] ?? '');
        if ($lugarId !== '' && self::esLugarBar($lugarId)) {
            $reg = self::registrarPrimeraVezEnBar($partida, $participantes, $lugarId, $encuentro['id'] ?? null);
            if ($reg !== null && !($reg['ya_existia'] ?? false)) {
                $registrados[] = 'hito_21';
            }
        }

        return ['ok' => true, 'registrados' => $registrados];
    }

    private static function evaluarResultadoCita(array $resultado, array $participantes): ?string
    {
        $porPart = $resultado['por_participante'] ?? [];
        if ($porPart === []) {
            return null;
        }

        $buenos = 0;
        $malos = 0;
        $total = 0;
        foreach ($participantes as $pid) {
            $pid = (string) $pid;
            $res = (string) ($porPart[$pid]['resultado'] ?? 'normal');
            $total++;
            if ($res === 'muy_bien' || $res === 'bien') {
                $buenos++;
            } elseif ($res === 'mal' || $res === 'muy_mal') {
                $malos++;
            }
        }

        if ($total === 0) {
            return null;
        }

        // Primera cita: si al menos 1 fue bien → hito_06; si todos malos → hito_07
        if ($buenos > 0) {
            return 'hito_06';
        }
        if ($malos === $total) {
            return 'hito_07';
        }
        return null;
    }

    private static function esLugarBar(string $lugarId): bool
    {
        // El chiringuito / bar es el lugar social por excelencia
        return str_contains($lugarId, 'chiringuito') || str_contains($lugarId, 'bar');
    }

    private static function registrarPrimeraVezEnBar(array &$partida, array $participantes, string $lugarId, ?string $encuentroId): ?array
    {
        if (count($participantes) < 2) {
            return null;
        }

        // Comprobar si ya existe un hito_21 para este par en el bar
        $par = array_slice($participantes, 0, 2);
        $clave = HistoriaPuebloEngine::clave('hito_21', $par);
        if (HistoriaPuebloEngine::existe($partida, $clave)) {
            return ['ya_existia' => true];
        }

        return HistoriaPuebloEngine::registrar(
            $partida,
            'hito_21',
            $par,
            ['origen' => 'encuentro_bar', 'lugar' => $lugarId, 'encuentro_id' => $encuentroId]
        );
    }
}
