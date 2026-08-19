<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Nivel de intervención de Celestine. El motor decide la banda que nace.
 * Desconocidos → conocerse. Conocidos → quedar. Señal romántica → primera_cita / cita.
 */
final class PropuestaNivel
{
    public const PRESENTAR = 'conocerse';
    public const QUEDAR = 'quedar';
    public const PRIMERA_CITA = 'primera_cita';
    public const CITA = 'cita';
    public const PLAN_PAREJA = 'otro';

    /** @deprecated alias interno de quedar (lab/saves viejos) */
    public const QUEDADA = 'quedar';

    public const MODO_ORGANIZAR = 'organizar';
    public const MODO_PROPONER = 'proponer';

    public static function aliasTipo(string $tipo): string
    {
        if ($tipo === 'amistad') {
            return self::QUEDAR;
        }
        return $tipo;
    }

    public static function etiquetaPlay(string $tipo): string
    {
        $tipo = self::aliasTipo($tipo);
        if ($tipo === self::PRESENTAR) {
            return 'Conocerse';
        }
        if ($tipo === self::QUEDAR) {
            return 'Quedar';
        }
        if ($tipo === self::PRIMERA_CITA) {
            return 'Primera cita';
        }
        if ($tipo === self::CITA) {
            return 'Cita';
        }
        return $tipo;
    }

    public static function tipoPara(array $partida, string $a, string $b): string
    {
        $est = ParejaEngine::estado($partida, $a, $b);
        if ($est === ParejaEngine::PAREJA || $est === ParejaEngine::CRISIS) {
            return self::CITA;
        }
        if (!RelacionEngine::seConocen($partida, $a, $b)) {
            return self::PRESENTAR;
        }
        $cal = CalibracionConfig::load(dirname(__DIR__, 2));
        $tipos = self::tiposPermitidos($partida, $a, $b, $cal);
        if (in_array(self::PRIMERA_CITA, $tipos, true)) {
            return self::PRIMERA_CITA;
        }
        if (in_array(self::CITA, $tipos, true)) {
            return self::CITA;
        }
        return self::QUEDAR;
    }

    /**
     * @param array<string, mixed> $cal
     * @return list<string>
     */
    public static function tiposPermitidos(array $partida, string $a, string $b, array $cal = []): array
    {
        if ($a === '' || $b === '' || $a === $b) {
            return [];
        }
        if (!RelacionEngine::seConocen($partida, $a, $b)) {
            return [self::PRESENTAR];
        }
        $out = [self::QUEDAR];
        $est = ParejaEngine::estado($partida, $a, $b);
        $el = RomanceElegibilidad::par($partida, $a, $b, $cal);
        if (empty($el['ok'])) {
            return $out;
        }
        if ($est === ParejaEngine::PAREJA || $est === ParejaEngine::CRISIS) {
            $out[] = self::CITA;
            return $out;
        }
        if (!SenalRomantica::desbloqueaPrimeraCita($partida, $a, $b, $cal)) {
            return $out;
        }
        if (SenalRomantica::yaHuboPrimeraCita($partida, $a, $b)) {
            $out[] = self::CITA;
        } else {
            $out[] = self::PRIMERA_CITA;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function hintPlay(array $partida, string $a, string $b, array $cal = []): string
    {
        $tipos = self::tiposPermitidos($partida, $a, $b, $cal);
        if ($tipos === [self::PRESENTAR]) {
            return 'Aún no se conocen: solo puedes presentarles.';
        }
        if (in_array(self::PRIMERA_CITA, $tipos, true)) {
            return 'Hay tema. Puedes proponer quedar o una primera cita.';
        }
        if (in_array(self::CITA, $tipos, true)) {
            return 'Puedes proponer quedar o una cita.';
        }
        return 'Ya se conocen: puedes proponer quedar. La amistad, si nace, la decide el pueblo.';
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function permite(array $partida, string $a, string $b, string $tipo, array $cal = []): bool
    {
        $tipo = self::aliasTipo($tipo);
        return in_array($tipo, self::tiposPermitidos($partida, $a, $b, $cal), true);
    }

    public static function esTipoCita(string $tipo): bool
    {
        $tipo = self::aliasTipo($tipo);
        return $tipo === self::PRIMERA_CITA || $tipo === self::CITA || $tipo === 'romantico';
    }

    public static function esTipoQuedar(string $tipo): bool
    {
        $tipo = self::aliasTipo($tipo);
        return $tipo === self::QUEDAR || $tipo === self::PRESENTAR;
    }

    /**
     * Cupo de caras que la UI pide DESPUÉS de elegir tipo (contrato Carlos II).
     * Conocerse = 1. Quedar/cita = 2. Grupales apagados.
     * El motor de propuestas sigue exigiendo 2 IDs para conocerse
     * hasta que el flujo “1 cara + contexto” se cablee.
     */
    public static function cupoUi(string $tipo): int
    {
        $tipo = self::aliasTipo($tipo);
        if ($tipo === self::PRESENTAR || $tipo === 'individual') {
            return 1;
        }
        return 2;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function contratoOrganizar(): array
    {
        return [
            [
                'tipo' => self::PRESENTAR,
                'etiqueta' => self::etiquetaPlay(self::PRESENTAR),
                'cupo' => 1,
                'grupal' => false,
                'participantes_motor_min' => 2,
            ],
            [
                'tipo' => self::QUEDAR,
                'etiqueta' => self::etiquetaPlay(self::QUEDAR),
                'cupo' => 2,
                'grupal' => false,
                'participantes_motor_min' => 2,
            ],
            [
                'tipo' => self::PRIMERA_CITA,
                'etiqueta' => self::etiquetaPlay(self::PRIMERA_CITA),
                'cupo' => 2,
                'grupal' => false,
                'participantes_motor_min' => 2,
            ],
            [
                'tipo' => self::CITA,
                'etiqueta' => self::etiquetaPlay(self::CITA),
                'cupo' => 2,
                'grupal' => false,
                'participantes_motor_min' => 2,
            ],
        ];
    }
}
