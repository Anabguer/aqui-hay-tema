<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente de progresión romántica visible.
 * Evalúa señales narrativas tras cada tick y publica donde corresponda.
 * No crea estado nuevo. Lee del motor existente.
 */
final class RomanticProgressionBridge
{
    /** @var list<string> */
    private const DIARIO_FAMILIAS = [
        RomanticProgression::SENAL_SE_FIJA,
        RomanticProgression::SENAL_INTERES_CRECIENTE,
        RomanticProgression::SENAL_MUTUO,
    ];

    private static array $publicadas = [];

    public static function reset(): void
    {
        self::$publicadas = [];
    }

    /**
     * Evalúa y publica señales de progresión romántica para todos los pares.
     * Llamar una vez por día (al cerrar día o al avanzar reloj).
     *
     * @return list<array{tipo: string, desde: string, hacia: string, texto: string}>
     */
    public static function tickProgresion(array &$partida, array $cal = []): array
    {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return [];
        }

        $senales = RomanticProgression::todasLasSenales($partida, $cal);
        $publicadas = [];

        foreach ($senales as $s) {
            $resultado = self::procesarSenal($partida, $s, $cal);
            if ($resultado !== null) {
                $publicadas[] = $resultado;
            }
        }

        $publicadas = array_merge($publicadas, self::publicarParejasRecientes($partida));

        return $publicadas;
    }

    /**
     * Publica señales para parejas que se formaron hoy (declaración/inicio_pareja).
     */
    private static function publicarParejasRecientes(array &$partida): array
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $publicadas = [];

        foreach ($partida['relaciones_romanticas'] ?? [] as $par) {
            if (($par['estado_pareja'] ?? 'ninguna') !== ParejaEngine::PAREJA) {
                continue;
            }
            $a = $par['persona_a'] ?? '';
            $b = $par['persona_b'] ?? '';
            if ($a === '' || $b === '') {
                continue;
            }

            $clave = RomanticProgression::SENAL_PRE_PAREJA . ':' . $a . '|' . $b;
            if (isset(self::$publicadas[$clave])) {
                continue;
            }

            $bitacora = RelacionBitacora::entre($partida, $a, $b);
            $declaracionReciente = false;
            foreach ($bitacora as $h) {
                if (($h['tipo'] ?? '') === RelacionBitacora::DECLARACION
                    || ($h['tipo'] ?? '') === RelacionBitacora::INICIO_PAREJA
                ) {
                    $diaHito = (int) ($h['fecha']['dia'] ?? 0);
                    if ($diaHito >= $dia - 1) {
                        $declaracionReciente = true;
                        break;
                    }
                }
            }

            if (!$declaracionReciente) {
                continue;
            }

            $resultado = self::publicarCotilleo($partida, RomanticProgression::SENAL_PRE_PAREJA, $a, $b, true, []);
            if ($resultado !== null) {
                $publicadas[] = $resultado;
            }
            self::$publicadas[$clave] = true;

            self::publicarDiarioPersonal($partida, RomanticProgression::SENAL_MUTUO, $a, $b, []);
            self::publicarDiarioPersonal($partida, RomanticProgression::SENAL_MUTUO, $b, $a, []);
        }

        return $publicadas;
    }

    /**
     * Procesa una señal individual: cotilleo si es público, diario si es personal.
     */
    private static function procesarSenal(
        array &$partida,
        array $senal,
        array $cal
    ): ?array {
        $tipo = $senal['senal'];
        $desde = $senal['desde'];
        $hacia = $senal['hacia'];
        $mutuo = $senal['mutuo'];
        $clave = $tipo . ':' . $desde . '|' . $hacia;

        if (isset(self::$publicadas[$clave])) {
            return null;
        }

        $esPublico = in_array($tipo, [
            RomanticProgression::SENAL_INTERES_CRECIENTE,
            RomanticProgression::SENAL_MUTUO,
            RomanticProgression::SENAL_CITA,
            RomanticProgression::SENAL_PRE_PAREJA,
        ], true);

        $resultado = null;

        if ($esPublico) {
            $resultado = self::publicarCotilleo($partida, $tipo, $desde, $hacia, $mutuo, $cal);
        }

        if ($resultado === null && in_array($tipo, self::DIARIO_FAMILIAS, true)) {
            self::publicarDiarioPersonal($partida, $tipo, $desde, $hacia, $cal);
            if ($mutuo) {
                self::publicarDiarioPersonal($partida, $tipo, $hacia, $desde, $cal);
            }
            $resultado = [
                'tipo' => 'diario_personal',
                'desde' => $desde,
                'hacia' => $hacia,
                'texto' => CopyRomanticProgression::diarioPersonal(
                    $tipo,
                    IdentidadPublica::nombre($partida, $hacia)
                ),
            ];
        }

        if ($resultado !== null) {
            self::$publicadas[$clave] = true;
        }

        return $resultado;
    }

    private static function publicarCotilleo(
        array &$partida,
        string $tipo,
        string $desde,
        string $hacia,
        bool $mutuo,
        array $cal
    ): ?array {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $nomA = IdentidadPublica::nombre($partida, $desde);
        $nomB = IdentidadPublica::nombre($partida, $hacia);

        $texto = CopyRomanticProgression::cotilleo($tipo, $nomA, $nomB);
        if ($texto === '') {
            return null;
        }

        $categoria = $mutuo ? CotilleoCategoria::ROMANCE : CotilleoCategoria::RELACION;
        $destacado = $tipo === RomanticProgression::SENAL_MUTUO
            || $tipo === RomanticProgression::SENAL_CITA
            || $tipo === RomanticProgression::SENAL_PRE_PAREJA;

        $msg = [
            'clasificacion' => BuzonEngine::COTILLEO,
            'tipo' => 'progresion_romantica',
            'texto' => $texto,
            'cotilleo_meta' => CotilleoCategoria::meta($categoria, $destacado),
            'actores' => [$desde, $hacia],
            'hito_tipo' => 'progresion_' . $tipo,
            'hito_clave' => 'progrom:' . $tipo . ':' . $desde . '|' . $hacia,
            'dia' => $dia,
            'origen' => [
                'evento_id' => 'progrom:' . $tipo . ':' . $desde . '|' . $hacia,
                'tipo_evento' => 'progresion_romantica',
                'es_narrativo' => true,
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ];

        $r = BuzonEngine::crear($partida, $msg);
        DiarioNarrativaBridge::mirrorCotilleoBuzon($partida, $r);

        if ($r['ok'] ?? false) {
            DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
                'mensaje' => $r['mensaje'] ?? null,
                'origen_evento' => 'progresion_romantica',
                'hito' => $tipo,
            ], null, 'RomanticProgressionBridge');

            return [
                'tipo' => 'cotilleo',
                'desde' => $desde,
                'hacia' => $hacia,
                'texto' => $texto,
            ];
        }

        return null;
    }

    private static function publicarDiarioPersonal(
        array &$partida,
        string $tipo,
        string $desde,
        string $hacia,
        array $cal
    ): void {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $nomOtro = IdentidadPublica::nombre($partida, $hacia);
        $texto = CopyRomanticProgression::diarioPersonal($tipo, $nomOtro);
        if ($texto === '') {
            return;
        }

        switch ($tipo) {
            case RomanticProgression::SENAL_SE_FIJA:
                $subtipo = 'interes_incipiente';
                break;
            case RomanticProgression::SENAL_INTERES_CRECIENTE:
                $subtipo = 'interes_creciente';
                break;
            case RomanticProgression::SENAL_MUTUO:
                $subtipo = 'interes_mutuo';
                break;
            default:
                $subtipo = 'interes_incipiente';
                break;
        }

        $eventoId = 'progrom_diario:' . $tipo . ':' . $desde . '|' . $hacia;
        if (DiarioEngine::entradaPorEvento($partida, $eventoId) !== null) {
            return;
        }
        DiarioEngine::crear(
            $partida,
            [
                'dia' => $dia,
                'tipo' => 'progresion_romantica',
                'subtipo' => $subtipo,
                'titulo' => self::tituloDiario($tipo),
                'texto' => $texto,
                'actores' => [$desde, $hacia],
                'origen' => [
                    'evento_id' => $eventoId,
                    'tipo_evento' => 'progresion_romantica',
                    'es_narrativo' => true,
                ],
                'consecuencias' => [],
            ]
        );
    }

    private static function tituloDiario(string $tipo): string
    {
        switch ($tipo) {
            case RomanticProgression::SENAL_SE_FIJA:
                return 'Algo empieza';
            case RomanticProgression::SENAL_INTERES_CRECIENTE:
                return 'No puedo evitarlo';
            case RomanticProgression::SENAL_MUTUO:
                return 'Creo que es mutuo';
            case RomanticProgression::SENAL_CITA:
                return 'Una cita';
            case RomanticProgression::SENAL_PRE_PAREJA:
                return 'Esto ya tiene nombre';
            default:
                return 'Algo entre nosotros';
        }
    }
}
