<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Estado interno del residente. Concepto canónico: estado_emocional.
 * runtime.animo es alias legacy de estado_emocional.id — no es barra Sims ni el PNG.
 * Sin fórmulas: solo estructura, origen y caducidad opcional.
 */
final class EstadoEmocional
{
    public const NEUTRO = 'neutro';
    public const ALEGRE = 'alegre';
    public const TRISTE = 'triste';
    public const ENFADADO = 'enfadado';
    public const V1 = [self::NEUTRO, self::ALEGRE, self::TRISTE, self::ENFADADO];

    public static function canonId(string $id): string
    {
        if ($id === 'neutral') {
            return self::NEUTRO;
        }
        return $id !== '' ? $id : self::NEUTRO;
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function modificadores(string $id, array $cal = []): array
    {
        $id = self::canonId($id);
        $mods = CalibracionConfig::get($cal, 'emociones_v1.modificadores.' . $id, null);
        if (!is_array($mods)) {
            $mods = [
                'aceptar_planes' => 0,
                'experiencia_encuentro' => 0,
                'iniciativa_social' => 0,
                'iniciativa_romantica' => 0,
                'riesgo_conflicto' => 0,
                'peticiones' => 0,
            ];
        }
        return [
            'id' => $id,
            'aceptar_planes' => $mods['aceptar_planes'] ?? 0,
            'experiencia_encuentro' => $mods['experiencia_encuentro'] ?? 0,
            'iniciativa_social' => $mods['iniciativa_social'] ?? 0,
            'iniciativa_romantica' => $mods['iniciativa_romantica'] ?? 0,
            'riesgo_conflicto' => $mods['riesgo_conflicto'] ?? 0,
            'peticiones' => $mods['peticiones'] ?? 0,
            'no_modifica_romance_automatico' => true,
        ];
    }

    public static function estructura(
        string $id = self::NEUTRO,
        $intensidad = null,
        string $origen = 'inicial',
        ?array $desde = null,
        ?array $hasta = null,
        array $contexto = [],
        ?int $duracionHoras = null
    ): array {
        return [
            'id' => $id !== '' ? self::canonId($id) : self::NEUTRO,
            'intensidad' => $intensidad,
            'origen' => $origen !== '' ? $origen : 'inicial',
            'contexto' => $contexto,
            'desde' => $desde,
            'hasta' => $hasta,
            'duracion_horas' => $duracionHoras,
        ];
    }

    public static function expresionEstructura(
        string $id = ExpresionVisual::NEUTRAL,
        ?string $overrideDev = null,
        bool $fallback = false,
        string $motivo = 'inicial',
        ?string $solicitada = null
    ): array {
        return [
            'id' => $id,
            'override_dev' => $overrideDev,
            'fallback' => $fallback,
            'motivo' => $motivo,
            'solicitada' => $solicitada,
        ];
    }

    public static function ensureResidente(array &$residente, ?array $reloj = null): void
    {
        $residente['runtime'] ??= [];
        $rt = &$residente['runtime'];

        if (!isset($rt['estado_emocional']) || !is_array($rt['estado_emocional'])) {
            $animo = is_string($rt['animo'] ?? null) && $rt['animo'] !== ''
                ? (string) $rt['animo']
                : self::NEUTRO;
            $desde = self::marcaReloj($reloj);
            $rt['estado_emocional'] = self::estructura($animo, null, 'inicial', $desde, null);
        } else {
            $e = $rt['estado_emocional'];
            $intensidad = $e['intensidad'] ?? null;
            if (is_numeric($intensidad)) {
                $intensidad = (float) $intensidad;
            } else {
                $intensidad = null;
            }
            $rt['estado_emocional'] = self::estructura(
                (string) ($e['id'] ?? self::NEUTRO),
                $intensidad,
                (string) ($e['origen'] ?? 'inicial'),
                is_array($e['desde'] ?? null) ? $e['desde'] : null,
                is_array($e['hasta'] ?? null) ? $e['hasta'] : null,
                is_array($e['contexto'] ?? null) ? $e['contexto'] : [],
                isset($e['duracion_horas']) && is_int($e['duracion_horas']) ? $e['duracion_horas'] : null
            );
        }

        if (!isset($rt['expresion_visual']) || !is_array($rt['expresion_visual'])) {
            $rt['expresion_visual'] = self::expresionEstructura();
        } else {
            $x = $rt['expresion_visual'];
            $rt['expresion_visual'] = self::expresionEstructura(
                (string) ($x['id'] ?? ExpresionVisual::NEUTRAL),
                isset($x['override_dev']) && is_string($x['override_dev']) ? $x['override_dev'] : null,
                (bool) ($x['fallback'] ?? false),
                (string) ($x['motivo'] ?? 'inicial'),
                isset($x['solicitada']) && is_string($x['solicitada']) ? $x['solicitada'] : null
            );
        }

        $rt['animo'] = $rt['estado_emocional']['id']; // alias legacy; no usar como barra ni como PNG
    }

    public static function marcaReloj(?array $reloj): array
    {
        return [
            'dia' => (int) ($reloj['dia_pueblo'] ?? 1),
            'hora' => (int) ($reloj['hora_actual'] ?? 8),
        ];
    }

    public static function hastaDesdeDuracion(array $reloj, int $duracionHoras): array
    {
        $abs = self::horaAbsoluta($reloj) + max(0, $duracionHoras);
        return [
            'dia' => intdiv($abs, 24) === 0 ? 1 : intdiv($abs, 24),
            'hora' => $abs % 24,
        ];
    }

    public static function horaAbsoluta(array $reloj): int
    {
        return ((int) ($reloj['dia_pueblo'] ?? 1)) * 24 + (int) ($reloj['hora_actual'] ?? 0);
    }

    public static function vencido(?array $hasta, array $reloj): bool
    {
        if ($hasta === null) {
            return false;
        }
        $hd = (int) ($hasta['dia'] ?? 0);
        $hh = (int) ($hasta['hora'] ?? 0);
        return self::horaAbsoluta($reloj) >= ($hd * 24 + $hh);
    }
}
