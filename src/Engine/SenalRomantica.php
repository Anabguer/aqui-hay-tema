<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Gate único de oportunidad romántica para Celestine.
 * Señal = flechazo O romance direccional ≥ tilín (calibración). Unilateral.
 * No crea pareja. No es orientación.
 */
final class SenalRomantica
{
    /**
     * @param array<string, mixed> $cal
     */
    public static function umbralTilin(array $cal): int
    {
        $cortes = CalibracionConfig::get($cal, 'romance.cortes', []);
        if (is_array($cortes) && isset($cortes['tilin']) && is_numeric($cortes['tilin'])) {
            return (int) $cortes['tilin'];
        }
        return 8;
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function tieneFlechazo(array $partida, string $desde, string $hacia): bool
    {
        $rel = RelacionEngine::obtenerEntre($partida, $desde, $hacia)['romance'] ?? null;
        if (!is_array($rel)) {
            return false;
        }
        foreach ($rel['flechazos'] ?? [] as $f) {
            if (!is_array($f)) {
                continue;
            }
            if ((string) ($f['desde'] ?? '') === $desde && (string) ($f['hacia'] ?? '') === $hacia) {
                return true;
            }
        }
        return RelacionBitacora::entre($partida, $desde, $hacia, RelacionBitacora::FLECHAZO) !== []
            && self::direccionEnBitacora($partida, $desde, $hacia);
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function desdeHacia(array $partida, string $desde, string $hacia, array $cal = []): array
    {
        $valor = RelacionEngine::romanceHacia($partida, $desde, $hacia);
        $n = $valor === null ? 0 : (int) $valor;
        $flechazo = self::tieneFlechazo($partida, $desde, $hacia);
        $ok = $flechazo || $n >= self::umbralTilin($cal);
        return [
            'ok' => $ok,
            'unilateral' => true,
            'flechazo' => $flechazo,
            'banda' => RelacionBandas::romance($n, $cal),
            'motivo' => $flechazo ? 'flechazo' : ($ok ? 'tilin' : 'sin_senal'),
        ];
    }

    /**
     * R1 · ¿Alguno del par está en pareja (o crisis) con una TERCERA persona?
     * Gate transversal de exclusividad para iniciativas y señales nuevas.
     */
    public static function enParejaConTercero(array $partida, string $a, string $b): bool
    {
        if ($a === '' || $b === '' || $a === $b) {
            return false;
        }
        $pa = ParejaEngine::parejaActivaDe($partida, $a);
        if ($pa !== null && $pa !== $b) {
            return true;
        }
        $pb = ParejaEngine::parejaActivaDe($partida, $b);
        return $pb !== null && $pb !== $a;
    }

    /**
     * ¿Celestine puede proponer Primera cita a este par?
     *
     * @param array<string, mixed> $cal
     */
    public static function desbloqueaPrimeraCita(array $partida, string $a, string $b, array $cal = []): bool
    {
        if ($a === '' || $b === '' || $a === $b) {
            return false;
        }
        if (!RelacionEngine::seConocen($partida, $a, $b)) {
            return false;
        }
        $est = ParejaEngine::estado($partida, $a, $b);
        if ($est === ParejaEngine::PAREJA || $est === ParejaEngine::CRISIS) {
            return false;
        }
        // R7: exes no re-entran en citas románticas por la puerta de primera cita.
        if ($est === ParejaEngine::EX) {
            return false;
        }
        // R1: nadie inicia nada romántico nuevo si está emparejado con un tercero.
        if (self::enParejaConTercero($partida, $a, $b)) {
            return false;
        }
        $el = RomanceElegibilidad::par($partida, $a, $b, $cal);
        if (empty($el['ok'])) {
            return false;
        }
        $ab = self::desdeHacia($partida, $a, $b, $cal);
        $ba = self::desdeHacia($partida, $b, $a, $cal);
        return !empty($ab['ok']) || !empty($ba['ok']);
    }

    public static function yaHuboPrimeraCita(array $partida, string $a, string $b): bool
    {
        return RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::PRIMERA_CITA);
    }

    /**
     * Quién siente algo por quién (primera dirección que cumple).
     *
     * @param array<string, mixed> $cal
     * @return array{desde:string,hacia:string,motivo:string}|null
     */
    public static function direccionVisible(array $partida, string $a, string $b, array $cal = []): ?array
    {
        $ab = self::desdeHacia($partida, $a, $b, $cal);
        if (!empty($ab['ok'])) {
            return ['desde' => $a, 'hacia' => $b, 'motivo' => (string) $ab['motivo']];
        }
        $ba = self::desdeHacia($partida, $b, $a, $cal);
        if (!empty($ba['ok'])) {
            return ['desde' => $b, 'hacia' => $a, 'motivo' => (string) $ba['motivo']];
        }
        return null;
    }

    /**
     * Aviso a Celestine la primera vez que ESTA dirección tiene señal.
     * Unilateral: A→B no cubre B→A.
     *
     * @param array<string, mixed> $cal
     */
    public static function avisarSiAplica(array &$partida, string $desde, string $hacia, array $cal = []): void
    {
        if ($desde === '' || $hacia === '' || $desde === $hacia) {
            return;
        }
        $el = RomanceElegibilidad::par($partida, $desde, $hacia, $cal);
        if (empty($el['ok'])) {
            return;
        }
        $est = ParejaEngine::estado($partida, $desde, $hacia);
        if ($est === ParejaEngine::PAREJA || $est === ParejaEngine::CRISIS) {
            return;
        }
        // R1: sin avisos de señal hacia/desde alguien emparejado con un tercero.
        if (self::enParejaConTercero($partida, $desde, $hacia)) {
            return;
        }
        $senal = self::desdeHacia($partida, $desde, $hacia, $cal);
        if (empty($senal['ok'])) {
            return;
        }
        RelacionEngine::upsertRomance($partida, $desde, $hacia, []);
        $rel = RelacionEngine::obtenerEntre($partida, $desde, $hacia)['romance'];
        $rel['avisos_senal'] ??= [];
        $key = $desde . '>' . $hacia;
        if (!empty($rel['avisos_senal'][$key])) {
            RelacionEngine::persistirRomance($partida, $rel);
            return;
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $rel['avisos_senal'][$key] = [
            'dia' => $dia,
            'hora' => $hora,
            'motivo' => $senal['motivo'],
        ];
        RelacionEngine::persistirRomance($partida, $rel);
        SimFunnelProbe::on($partida, 'senal', [
            'ev' => 'emitida',
            '_k' => 'senal_' . (string) $senal['motivo'],
            'desde' => $desde,
            'hacia' => $hacia,
            'motivo' => $senal['motivo'],
        ]);

        $nomDe = IdentidadPublica::nombre($partida, $desde);
        $nomA = IdentidadPublica::nombre($partida, $hacia);
        $motivo = (string) $senal['motivo'];
        $texto = CopySenalRomantica::texto($nomDe, $nomA, $motivo);
        DomainBootstrap::boot();
        DomainEventDispatcher::emit($partida, DomainEvents::SENAL_ROMANTICA, [
            'desde' => $desde,
            'hacia' => $hacia,
            'motivo' => $motivo,
            'texto' => $texto,
            'ts_juego' => ['dia' => $dia, 'hora' => $hora],
            'actores' => [$desde, $hacia],
        ], null, 'SenalRomantica::avisarSiAplica', [$desde, $hacia]);
    }

    private static function direccionEnBitacora(array $partida, string $desde, string $hacia): bool
    {
        foreach (RelacionBitacora::entre($partida, $desde, $hacia, RelacionBitacora::FLECHAZO) as $h) {
            $d = (string) ($h['direccion'] ?? '');
            if ($d === $desde . '>' . $hacia) {
                return true;
            }
        }
        return false;
    }
}
