<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Estado de necesidades personales de un residente.
 * 4 necesidades canónicas: social, diversion, actividad, calma.
 * Almacena en runtime.necesidades.
 * Sin fórmulas duras: decay y recuperación base, modulados por contexto.
 */
final class NecesidadEstado
{
    public const SOCIAL = 'social';
    public const DIVERSION = 'diversion';
    public const ACTIVIDAD = 'actividad';
    public const CALMA = 'calma';
    public const TODAS = [self::SOCIAL, self::DIVERSION, self::ACTIVIDAD, self::CALMA];

    public const BANDA_BIEN = 'bien';
    public const BANDA_LE_VENDRIA_BIEN = 'le_vendria_bien';
    public const BANDA_LO_NECESITA = 'lo_necesita';
    public const BANDA_EN_ROJO = 'en_rojo';

    private const BANDAS = [
        self::BANDA_BIEN           => ['min' => 75, 'max' => 100],
        self::BANDA_LE_VENDRIA_BIEN => ['min' => 50, 'max' => 74],
        self::BANDA_LO_NECESITA    => ['min' => 25, 'max' => 49],
        self::BANDA_EN_ROJO        => ['min' => 0,  'max' => 24],
    ];

    private const DECAY_BASE = 2.5;
    private const RECUPERACION_BASE = 3.0;
    private const INITIAL_VALUE = 85;
    private const MIN_VALUE = 0;
    private const MAX_VALUE = 100;

    /**
     * Asegura que runtime.necesidades exista para un residente.
     * Si no existe, inicializa con valores por defecto (banda bien).
     *
     * @param array<string, mixed> &$residente
     * @param array<string, mixed>|null $reloj
     */
    public static function ensureResidente(array &$residente, ?array $reloj = null): void
    {
        $residente['runtime'] ??= [];
        $rt = &$residente['runtime'];

        if (!isset($rt['necesidades']) || !is_array($rt['necesidades'])) {
            $rt['necesidades'] = self::estructuraInicial($reloj);
        } else {
            // Normalizar: asegurar que las 4 existen
            foreach (self::TODAS as $nec) {
                if (!isset($rt['necesidades'][$nec])) {
                    $rt['necesidades'][$nec] = self::crearNecesidad(self::INITIAL_VALUE, $reloj);
                } else {
                    $rt['necesidades'][$nec] = self::normalizar($rt['necesidades'][$nec], $reloj);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $reloj
     * @return array<string, array<string, mixed>>
     */
    public static function estructuraInicial(?array $reloj = null): array
    {
        $necesidades = [];
        foreach (self::TODAS as $nec) {
            $necesidades[$nec] = self::crearNecesidad(self::INITIAL_VALUE, $reloj);
        }
        return $necesidades;
    }

    /**
     * @param array<string, mixed>|null $reloj
     * @return array<string, mixed>
     */
    public static function crearNecesidad(int $valor, ?array $reloj = null): array
    {
        return [
            'valor' => max(self::MIN_VALUE, min(self::MAX_VALUE, $valor)),
            'banda' => self::calcularBanda($valor),
            'ultima_actualizacion' => $reloj !== null ? self::marcaReloj($reloj) : null,
            'ultima_recuperacion' => null,
        ];
    }

    /**
     * @param array<string, mixed> $necesidad
     * @param array<string, mixed>|null $reloj
     * @return array<string, mixed>
     */
    public static function normalizar(array $necesidad, ?array $reloj = null): array
    {
        $valor = (int) ($necesidad['valor'] ?? self::INITIAL_VALUE);
        $valor = max(self::MIN_VALUE, min(self::MAX_VALUE, $valor));
        return [
            'valor' => $valor,
            'banda' => self::calcularBanda($valor),
            'ultima_actualizacion' => $necesidad['ultima_actualizacion'] ?? ($reloj !== null ? self::marcaReloj($reloj) : null),
            'ultima_recuperacion' => $necesidad['ultima_recuperacion'] ?? null,
        ];
    }

    /**
     * Calcula la banda para un valor dado.
     */
    public static function calcularBanda(int|float $valor): string
    {
        foreach (self::BANDAS as $banda => $rango) {
            if ($valor >= $rango['min'] && $valor <= $rango['max']) {
                return $banda;
            }
        }
        return self::BANDA_EN_ROJO;
    }

    /**
     * Aplica decay horario a todas las necesidades de un residente.
     * Retorna cuántas cambiaron de banda.
     *
     * @param array<string, mixed> &$residente
     * @param array<string, mixed> $cal
     * @return int número de necesidades que cambiaron de banda
     */
    public static function aplicarDecay(array &$residente, array $cal): int
    {
        self::ensureResidente($residente);
        $rt = &$residente['runtime'];
        $cambios = 0;

        foreach (self::TODAS as $nec) {
            $antes = $rt['necesidades'][$nec]['banda'];
            $decay = self::decayParaNecesidad($nec, $cal);
            $rt['necesidades'][$nec]['valor'] = max(
                self::MIN_VALUE,
                $rt['necesidades'][$nec]['valor'] - $decay
            );
            $rt['necesidades'][$nec]['banda'] = self::calcularBanda($rt['necesidades'][$nec]['valor']);
            if ($rt['necesidades'][$nec]['banda'] !== $antes) {
                $cambios++;
            }
        }

        return $cambios;
    }

    /**
     * Aplica recuperación por estar en un lugar adecuado.
     *
     * @param array<string, mixed> &$residente
     * @param array<string, mixed> $lugarNecesidades ej: {"social":"principal","calma":"secundaria"}
     * @param bool $estaAcompanado
     * @param bool $hobbyMatch
     * @param array<string, mixed> $cal
     * @return int número de necesidades que cambiaron de banda
     */
    public static function aplicarRecuperacion(
        array &$residente,
        array $lugarNecesidades,
        bool $estaAcompanado,
        bool $hobbyMatch,
        array $cal
    ): int {
        self::ensureResidente($residente);
        $rt = &$residente['runtime'];
        $cambios = 0;

        foreach (self::TODAS as $nec) {
            $antes = $rt['necesidades'][$nec]['banda'];
            $recuperacion = self::recuperacionParaNecesidad($nec, $lugarNecesidades, $estaAcompanado, $hobbyMatch, $cal);
            if ($recuperacion > 0) {
                $rt['necesidades'][$nec]['valor'] = min(
                    self::MAX_VALUE,
                    $rt['necesidades'][$nec]['valor'] + $recuperacion
                );
                $rt['necesidades'][$nec]['banda'] = self::calcularBanda($rt['necesidades'][$nec]['valor']);
                $rt['necesidades'][$nec]['ultima_recuperacion'] = $rt['necesidades'][$nec]['ultima_actualizacion'] ?? null;
            }
            if ($rt['necesidades'][$nec]['banda'] !== $antes) {
                $cambios++;
            }
        }

        return $cambios;
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function decayParaNecesidad(string $necesidad, array $cal): float
    {
        $base = (float) CalibracionConfig::get($cal, "necesidades.decay.{$necesidad}", self::DECAY_BASE);
        return max(0.0, $base);
    }

    /**
     * Calcula la recuperación de una necesidad según el lugar y contexto.
     *
     * @param array<string, mixed> $lugarNecesidades
     * @param array<string, mixed> $cal
     */
    private static function recuperacionParaNecesidad(
        string $necesidad,
        array $lugarNecesidades,
        bool $estaAcompanado,
        bool $hobbyMatch,
        array $cal
    ): float {
        $base = (float) CalibracionConfig::get($cal, "necesidades.recuperacion.{$necesidad}", self::RECUPERACION_BASE);

        // Determinar intensidad del lugar para esta necesidad
        $intensidadLugar = 0.0;
        $rol = $lugarNecesidades[$necesidad] ?? null;
        if ($rol === 'principal') {
            $intensidadLugar = 1.0;
        } elseif ($rol === 'secundaria') {
            $intensidadLugar = 0.5;
        }

        if ($intensidadLugar <= 0.0) {
            return 0.0;
        }

        // Modificadores
        $modCompania = $estaAcompanado ? 1.2 : 1.0;
        $modHobby = $hobbyMatch ? 1.3 : 1.0;

        return $base * $intensidadLugar * $modCompania * $modHobby;
    }

    /**
     * Obtiene el estado actual de todas las necesidades de un residente.
     *
     * @param array<string, mixed> $residente
     * @return array<string, array{valor: int, banda: string}>
     */
    public static function obtener(array $residente): array
    {
        self::ensureResidente($residente);
        $necesidades = $residente['runtime']['necesidades'];
        $resultado = [];
        foreach (self::TODAS as $nec) {
            $resultado[$nec] = [
                'valor' => (int) ($necesidades[$nec]['valor'] ?? self::INITIAL_VALUE),
                'banda' => $necesidades[$nec]['banda'] ?? self::BANDA_BIEN,
            ];
        }
        return $resultado;
    }

    /**
     * Obtiene una necesidad específica.
     *
     * @param array<string, mixed> $residente
     * @return array{valor: int, banda: string}
     */
    public static function obtenerUna(array $residente, string $necesidad): array
    {
        self::ensureResidente($residente);
        $necesidades = $residente['runtime']['necesidades'];
        return [
            'valor' => (int) ($necesidades[$necesidad]['valor'] ?? self::INITIAL_VALUE),
            'banda' => $necesidades[$necesidad]['banda'] ?? self::BANDA_BIEN,
        ];
    }

    /**
     * Lista necesidades que están en banda "lo_necesita" o "en_rojo".
     *
     * @param array<string, mixed> $residente
     * @return list<string>
     */
    public static function necesidadesBajas(array $residente): array
    {
        $necesidades = self::obtener($residente);
        $bajas = [];
        foreach ($necesidades as $id => $n) {
            if ($n['banda'] === self::BANDA_LO_NECESITA || $n['banda'] === self::BANDA_EN_ROJO) {
                $bajas[] = $id;
            }
        }
        return $bajas;
    }

    /**
     * Actualiza la marca de tiempo de una necesidad.
     *
     * @param array<string, mixed> &$residente
     */
    public static function marcarActualizacion(array &$residente, array $reloj): void
    {
        self::ensureResidente($residente);
        $marca = self::marcaReloj($reloj);
        foreach (self::TODAS as $nec) {
            $residente['runtime']['necesidades'][$nec]['ultima_actualizacion'] = $marca;
        }
    }

    /**
     * @param array<string, mixed> $reloj
     * @return array{dia: int, hora: int}
     */
    public static function marcaReloj(array $reloj): array
    {
        return [
            'dia' => (int) ($reloj['dia_pueblo'] ?? 1),
            'hora' => (int) ($reloj['hora_actual'] ?? 0),
        ];
    }

    /**
     * Genera copy narrativo de una necesidad baja para la ficha.
     */
    public static function copyNecesidad(string $necesidad, string $banda): string
    {
        $copys = [
            self::SOCIAL => [
                self::BANDA_LE_VENDRIA_BIEN => 'Le vendría bien socializar',
                self::BANDA_LO_NECESITA => 'Necesita socializar',
                self::BANDA_EN_ROJO => 'Lleva tiempo sin hablar con nadie',
            ],
            self::DIVERSION => [
                self::BANDA_LE_VENDRIA_BIEN => 'Le vendría bien divertirse',
                self::BANDA_LO_NECESITA => 'Necesita diversión',
                self::BANDA_EN_ROJO => 'Lleva tiempo sin hacer nada que le guste',
            ],
            self::ACTIVIDAD => [
                self::BANDA_LE_VENDRIA_BIEN => 'Le vendría bien salir a hacer algo',
                self::BANDA_LO_NECESITA => 'Necesita actividad',
                self::BANDA_EN_ROJO => 'Lleva tiempo sin moverse',
            ],
            self::CALMA => [
                self::BANDA_LE_VENDRIA_BIEN => 'Le vendría bien un respiro',
                self::BANDA_LO_NECESITA => 'Necesita calma',
                self::BANDA_EN_ROJO => 'Lleva días agobiado',
            ],
        ];
        return $copys[$necesidad][$banda] ?? '';
    }
}
