<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente narrativo: hitos relacionales reales → Cotilleo (una vez por hito).
 * Flechazo e interés romántico respetan dirección A→B.
 */
final class RelacionNarrativaBridge
{
    public static function ensure(array &$partida): void
    {
        $partida['narrativa_hitos_publicados'] ??= [];
    }

    /**
     * @param list<string> $participantes
     * @return array<string, mixed>|null
     */
    public static function alHito(
        array &$partida,
        string $tipo,
        array $participantes,
        ?GameLogger $logger = null
    ): ?array {
        $ids = array_values(array_filter($participantes, static fn($id) => is_string($id) && $id !== ''));
        if ($ids === []) {
            return null;
        }
        sort($ids);
        $clave = $tipo . ':' . implode('|', $ids);
        self::ensure($partida);
        if (!empty($partida['narrativa_hitos_publicados'][$clave])) {
            return null;
        }

        $copy = self::copyDeHito($partida, $tipo, $ids);
        if ($copy === null || ($copy['texto'] ?? '') === '') {
            return null;
        }

        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $msg = [
            'clasificacion' => BuzonEngine::COTILLEO,
            'tipo' => 'cotilleo_hito',
            'texto' => (string) $copy['texto'],
            'cotilleo_meta' => CotilleoCategoria::meta(
                (string) ($copy['categoria'] ?? CotilleoCategoria::RELACION),
                (bool) ($copy['destacado'] ?? false)
            ),
            'actores' => $ids,
            'hito_tipo' => $tipo,
            'hito_clave' => $clave,
            'dia' => $dia,
            'origen' => [
                'evento_id' => $clave,
                'tipo_evento' => 'relacion_hito',
                'es_narrativo' => true,
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ];
        $r = BuzonEngine::crear($partida, $msg);
        DiarioNarrativaBridge::mirrorCotilleoBuzon($partida, $r);
        if ($r['ok'] ?? false) {
            $partida['narrativa_hitos_publicados'][$clave] = $dia;
            DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
                'mensaje' => $r['mensaje'] ?? null,
                'origen_evento' => 'relacion_hito',
                'hito' => $tipo,
            ], $logger, 'RelacionNarrativaBridge');
            return $r['mensaje'] ?? null;
        }
        return null;
    }

    /**
     * @param list<string> $ids
     * @return array{texto: string, categoria: string, destacado: bool}|null
     */
    private static function copyDeHito(array $partida, string $tipo, array $ids): ?array
    {
        $cal = CalibracionConfig::load(dirname(__DIR__, 2));
        $seed = $tipo . '|' . implode('|', $ids);

        if ($tipo === RelacionBitacora::FLECHAZO && count($ids) >= 2) {
            $dir = self::direccionFlechazo($partida, $ids[0], $ids[1]);
            if ($dir !== null) {
                $desde = IdentidadPublica::nombre($partida, $dir['desde']);
                $hacia = IdentidadPublica::nombre($partida, $dir['hacia']);
                $texto = CopyCotilleoFamilias::linea(
                    'flechazo_unilateral',
                    ['desde' => $desde, 'hacia' => $hacia, 'quien' => $desde . ' y ' . $hacia],
                    $seed . '|' . $dir['desde'] . '>' . $dir['hacia']
                );
                if ($texto === '') {
                    $texto = $desde . ' últimamente se fija bastante en ' . $hacia . '…';
                }
                return [
                    'texto' => $texto,
                    'categoria' => CotilleoCategoria::ROMANCE,
                    'destacado' => true,
                ];
            }
        }

        if (in_array($tipo, [RelacionBitacora::FLECHAZO, RelacionBitacora::HITO_ROMANTICO, RelacionBitacora::DECLARACION], true)
            && count($ids) >= 2) {
            $a = $ids[0];
            $b = $ids[1];
            $ab = SenalRomantica::desdeHacia($partida, $a, $b, $cal);
            $ba = SenalRomantica::desdeHacia($partida, $b, $a, $cal);
            if (!empty($ab['ok']) && !empty($ba['ok'])) {
                $quien = self::yNombres([
                    IdentidadPublica::nombre($partida, $a),
                    IdentidadPublica::nombre($partida, $b),
                ]);
                $texto = CopyCotilleoFamilias::linea('romance_mutuo', ['quien' => $quien], $seed);
                if ($texto === '') {
                    $texto = 'Las miradas entre ' . $quien . ' ya van en las dos direcciones.';
                }
                return [
                    'texto' => $texto,
                    'categoria' => CotilleoCategoria::ROMANCE,
                    'destacado' => true,
                ];
            }
        }

        $nombres = [];
        foreach ($ids as $id) {
            $nombres[] = IdentidadPublica::nombre($partida, $id);
        }
        $quien = self::yNombres($nombres);

        switch ($tipo) {
            case RelacionBitacora::SE_CONOCIERON:
                return [
                    'texto' => CopyCotilleoFamilias::linea('se_conocieron', ['quien' => $quien], $seed),
                    'categoria' => CotilleoCategoria::RELACION,
                    'destacado' => false,
                ];
            case RelacionBitacora::PRIMERA_CITA:
            case RelacionBitacora::PLAN_SIGNIFICATIVO:
                return [
                    'texto' => CopyCotilleoFamilias::linea('amistad_social', ['quien' => $quien], $seed),
                    'categoria' => CotilleoCategoria::RELACION,
                    'destacado' => false,
                ];
            case RelacionBitacora::REGALO:
                return [
                    'texto' => CopyCotilleoFamilias::linea('romance', ['quien' => $quien], $seed),
                    'categoria' => CotilleoCategoria::ROMANCE,
                    'destacado' => true,
                ];
            case RelacionBitacora::RECHAZO_IMPORTANTE:
                return [
                    'texto' => CopyCotilleoFamilias::linea('drama', ['quien' => $quien], $seed),
                    'categoria' => CotilleoCategoria::DRAMA,
                    'destacado' => true,
                ];
            case RelacionBitacora::FLECHAZO:
            case RelacionBitacora::HITO_ROMANTICO:
            case RelacionBitacora::DECLARACION:
                return [
                    'texto' => CopyCotilleoFamilias::linea('romance', ['quien' => $quien], $seed),
                    'categoria' => CotilleoCategoria::ROMANCE,
                    'destacado' => true,
                ];
            case RelacionBitacora::INICIO_PAREJA:
            case RelacionBitacora::VUELTA:
                return [
                    'texto' => CopyCotilleoFamilias::linea('pareja', ['quien' => $quien], $seed),
                    'categoria' => CotilleoCategoria::ROMANCE,
                    'destacado' => true,
                ];
            case RelacionBitacora::DISCUSION_FUERTE:
            case RelacionBitacora::CRISIS:
                return [
                    'texto' => CopyCotilleoFamilias::linea('drama', ['quien' => $quien], $seed),
                    'categoria' => CotilleoCategoria::DRAMA,
                    'destacado' => true,
                ];
            case RelacionBitacora::RUPTURA:
                return [
                    'texto' => CopyCotilleoFamilias::linea('ruptura', ['quien' => $quien], $seed),
                    'categoria' => CotilleoCategoria::DRAMA,
                    'destacado' => true,
                ];
            default:
                return null;
        }
    }

    /**
     * @return array{desde: string, hacia: string}|null
     */
    private static function direccionFlechazo(array $partida, string $a, string $b): ?array
    {
        foreach (RelacionBitacora::entre($partida, $a, $b, RelacionBitacora::FLECHAZO) as $h) {
            $d = (string) ($h['direccion'] ?? '');
            if (str_contains($d, '>')) {
                [$desde, $hacia] = explode('>', $d, 2);
                if ($desde !== '' && $hacia !== '') {
                    return ['desde' => $desde, 'hacia' => $hacia];
                }
            }
        }
        $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'] ?? null;
        if (is_array($rel)) {
            foreach ($rel['flechazos'] ?? [] as $f) {
                if (!is_array($f)) {
                    continue;
                }
                $desde = (string) ($f['desde'] ?? '');
                $hacia = (string) ($f['hacia'] ?? '');
                if ($desde !== '' && $hacia !== '') {
                    return ['desde' => $desde, 'hacia' => $hacia];
                }
            }
        }
        return null;
    }

    /**
     * @param list<string> $nombres
     */
    private static function yNombres(array $nombres): string
    {
        $nombres = array_values(array_filter($nombres, static fn($n) => is_string($n) && $n !== ''));
        $n = count($nombres);
        if ($n === 0) {
            return 'Alguien';
        }
        if ($n === 1) {
            return $nombres[0];
        }
        if ($n === 2) {
            return $nombres[0] . ' y ' . $nombres[1];
        }
        $last = array_pop($nombres);
        return implode(', ', $nombres) . ' y ' . $last;
    }
}
