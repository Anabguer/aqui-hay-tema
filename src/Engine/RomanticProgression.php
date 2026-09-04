<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Clasificador narrativo de progresión romántica.
 * Estado puro: lee datos existentes y devuelve señales narrativas.
 * No almacena estado nuevo. No toca balance niumbrales.
 */
final class RomanticProgression
{
    public const SENAL_NINGUNA = 'ninguna';
    public const SENAL_SE_FIJA = 'se_fija';
    public const SENAL_INTERES_CRECIENTE = 'interes_creciente';
    public const SENAL_MUTUO = 'mutuo';
    public const SENAL_CITA = 'cita';
    public const SENAL_PRE_PAREJA = 'pre_pareja';

    /**
     * Evalúa la señal narrativa romántica para la dirección A→B.
     *
     * @return array{
     *   senal: string,
     *   dirigida_por: string|null,
     *   mutuo: bool,
     *   copia: string,
     *   intensidad: int
     * }
     */
    public static function evaluarDireccion(
        array $partida,
        string $desde,
        string $hacia,
        array $cal = []
    ): array {
        $vDE = (int) (RelacionEngine::romanceHacia($partida, $desde, $hacia) ?? 0);
        $vHA = (int) (RelacionEngine::romanceHacia($partida, $hacia, $desde) ?? 0);
        $flechazosDE = self::tieneFlechazo($partida, $desde, $hacia);
        $flechazosHA = self::tieneFlechazo($partida, $hacia, $desde);
        $seConocen = RelacionEngine::seConocen($partida, $desde, $hacia);
        $estadoPareja = ParejaEngine::estado($partida, $desde, $hacia);
        $bitacora = RelacionBitacora::entre($partida, $desde, $hacia);
        $tienePrimeraCita = self::tieneHito($bitacora, RelacionBitacora::PRIMERA_CITA);
        $tieneDeclaracion = self::tieneHito($bitacora, RelacionBitacora::DECLARACION);
        $encuentros = self::contarEncuentros($partida, $desde, $hacia);

        $orientacion = self::resolverOrientacion($vDE, $vHA, $flechazosDE, $flechazosHA);
        $esMutuo = $orientacion === 'mutuo';
        $dirigidoPor = $esMutuo ? null : ($orientacion === 'a_hacia_b' ? $desde : $hacia);

        // Pareja o ex: sin señal narrativa de progresión
        if ($estadoPareja === ParejaEngine::PAREJA || $estadoPareja === ParejaEngine::EX) {
            return self::resultado(self::SENAL_NINGUNA, $dirigidoPor, $esMutuo);
        }

        // Pre-pareja: tuvieron primera cita o declaración
        if ($tienePrimeraCita || $tieneDeclaracion) {
            $maxV = max($vDE, $vHA);
            if ($maxV >= 22 || $flechazosDE || $flechazosHA) {
                return self::resultado(self::SENAL_PRE_PAREJA, $dirigidoPor, $esMutuo, $maxV);
            }
        }

        // Cita: tienen primera cita programada o reciente
        if ($tienePrimeraCita) {
            return self::resultado(self::SENAL_CITA, $dirigidoPor, $esMutuo);
        }

        // Mutuo: ambos tienen señal (romance >= tilin o flechazo)
        if (($vDE >= 8 || $flechazosDE) && ($vHA >= 8 || $flechazosHA)) {
            $maxV = max($vDE, $vHA);
            return self::resultado(self::SENAL_MUTUO, null, true, $maxV);
        }

        // Interés creciente: al menos uno tiene romance >= 4 o flechazo, y hay repeticion
        $algunoInteres = ($vDE >= 4 || $flechazosDE) || ($vHA >= 4 || $flechazosHA);
        if ($algunoInteres && $encuentros >= 2) {
            $maxV = max($vDE, $vHA);
            return self::resultado(self::SENAL_INTERES_CRECIENTE, $dirigidoPor, $esMutuo, $maxV);
        }

        // Se fija: al menos uno tiene romance >= 2 o flechazo
        $algunoNotable = ($vDE >= 2 || $flechazosDE) || ($vHA >= 2 || $flechazosHA);
        if ($algunoNotable && $seConocen) {
            $maxV = max($vDE, $vHA);
            return self::resultado(self::SENAL_SE_FIJA, $dirigidoPor, $esMutuo, $maxV);
        }

        return self::resultado(self::SENAL_NINGUNA, $dirigidoPor, $esMutuo);
    }

    /**
     * Evalúa todas las señales románticas activas en la partida.
     *
     * @return list<array{desde: string, hacia: string, senal: string, mutuo: bool, copia: string, intensidad: int}>
     */
    public static function todasLasSenales(array $partida, array $cal = []): array
    {
        $residentes = CapacidadViviendas::residentesActivos($partida);
        $senales = [];
        $procesadas = [];

        for ($i = 0; $i < count($residentes); $i++) {
            for ($j = $i + 1; $j < count($residentes); $j++) {
                $a = $residentes[$i];
                $b = $residentes[$j];
                $clave = $a . '|' . $b;
                if (isset($procesadas[$clave])) {
                    continue;
                }
                $procesadas[$clave] = true;

                $res = self::evaluarDireccion($partida, $a, $b, $cal);
                if ($res['senal'] !== self::SENAL_NINGUNA) {
                    $senales[] = [
                        'desde' => $a,
                        'hacia' => $b,
                        'senal' => $res['senal'],
                        'mutuo' => $res['mutuo'],
                        'copia' => $res['copia'],
                        'intensidad' => $res['intensidad'],
                    ];
                }
            }
        }

        usort($senales, fn($a, $b) => $b['intensidad'] <=> $a['intensidad']);

        return $senales;
    }

    /**
     * Intensidad narrativa 0-100 para ordering.
     */
    private static function intensidadNarrativa(int $romanceMax, string $senal): int
    {
        switch ($senal) {
            case self::SENAL_SE_FIJA:
                $base = 10;
                break;
            case self::SENAL_INTERES_CRECIENTE:
                $base = 30;
                break;
            case self::SENAL_MUTUO:
                $base = 55;
                break;
            case self::SENAL_CITA:
                $base = 70;
                break;
            case self::SENAL_PRE_PAREJA:
                $base = 85;
                break;
            default:
                $base = 0;
                break;
        }

        return min(100, $base + (int) ($romanceMax * 0.3));
    }

    private static function resolverOrientacion(int $vDE, int $vHA, bool $flechDE, bool $flechHA): string
    {
        $senalDE = $vDE >= 8 || $flechDE;
        $senalHA = $vHA >= 8 || $flechHA;

        if ($senalDE && $senalHA) {
            return 'mutuo';
        }
        if ($senalDE) {
            return 'a_hacia_b';
        }
        if ($senalHA) {
            return 'b_hacia_a';
        }

        $notableDE = $vDE >= 2 || $flechDE;
        $notableHA = $vHA >= 2 || $flechHA;

        if ($notableDE && $notableHA) {
            return 'mutuo';
        }
        if ($notableDE) {
            return 'a_hacia_b';
        }
        if ($notableHA) {
            return 'b_hacia_a';
        }

        return 'ninguno';
    }

    private static function tieneFlechazo(array $partida, string $desde, string $hacia): bool
    {
        foreach (RelacionBitacora::entre($partida, $desde, $hacia) as $h) {
            if (($h['tipo'] ?? '') === RelacionBitacora::FLECHAZO) {
                $d = (string) ($h['direccion'] ?? '');
                if (str_starts_with($d, $desde . '>')) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function tieneHito(array $bitacora, string $tipo): bool
    {
        foreach ($bitacora as $h) {
            if (($h['tipo'] ?? '') === $tipo) {
                return true;
            }
        }
        return false;
    }

    private static function contarEncuentros(array $partida, string $a, string $b): int
    {
        $count = 0;
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (($enc['estado'] ?? '') !== 'terminado') {
                continue;
            }
            $acts = $enc['actores'] ?? [];
            if (in_array($a, $acts, true) && in_array($b, $acts, true)) {
                $count++;
            }
        }
        return $count;
    }

    private static function resultado(string $senal, ?string $dirigidoPor, bool $mutuo, int $romanceMax = 0): array
    {
        return [
            'senal' => $senal,
            'dirigida_por' => $dirigidoPor,
            'mutuo' => $mutuo,
            'copia' => '', // Caller uses CopyRomanticProgression
            'intensidad' => self::intensidadNarrativa($romanceMax, $senal),
        ];
    }
}
