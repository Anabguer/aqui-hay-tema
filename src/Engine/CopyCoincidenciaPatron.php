<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Copy de patrones par+lugar. Una sola fuente para Cotilleos y Aquí hay tema.
 *
 * FASE 5: Copy enriquecido con contexto relacional.
 */
final class CopyCoincidenciaPatron
{
    /** @var list<string> */
    private const VARIANTES_DESCONOCIDOS = [];

    /** @var list<string> */
    private const VARIANTES_CONOCIDOS = [
        'Ya no coinciden por casualidad. %s empiezan a buscarse.',
        'Lo de %s ya tiene pinta de costumbre voluntaria.',
    ];

    /** @var list<string> */
    private const VARIANTES_ROMANCE = [
        '%s llevan varios días coincidiendo%s. Hay algo en el ambiente que no engaña.',
        'Otra vez %s%s. Esto ya huele a más que casualidad.',
        '%s vuelven a coincidir%s. El pueblo ya apostaría a que pasa algo.',
    ];

    /** @var list<string> */
    private const VARIANTES_PAREJA = [
        '%s vuelven a coincidir%s. El pueblo ya lo sabe todo.',
        'Otra vez %s%s. Cuando hay pareja, hasta el destino coopera.',
    ];

    /** @var list<string> */
    private const VARIANTES_EX = [
        '%s se han visto otra vez. Han roto, pero algo los sigue juntando.',
        'Otra vez %s. El pueblo no sabe si son ex o si aún queda algo.',
        '%s acaban de cruzarse. Lo que fue, aún pesa.',
    ];

    /** @var list<string> */
    private const VARIANTES_CON_TENSION = [
        '%s han coincidido. El ambiente entre ellos no está del todo bien.',
        'Se han topado %s. La cosa entre ellos aún está caldeada.',
    ];

    /**
     * @param list<string> $ids
     * @return array{
     *   texto: string,
     *   pista: string,
     *   categoria: string,
     *   categoria_etiqueta: string,
     *   categoria_icono: string,
     *   destacado: bool,
     *   cotilleo_meta: array{categoria: string, destacado: bool},
     *   nivel: string,
     *   se_conocen: bool,
     *   contexto_hint: string
     * }
     */
    public static function vista(
        array $partida,
        array $ids,
        string $lugarId,
        ?Catalog $catalog = null,
        ?array $contexto = null
    ): array {
        $ids = array_values(array_unique($ids));
        sort($ids);
        if (count($ids) > 2) {
            $ids = self::reducirAPar($partida, $ids, $lugarId);
        }
        $root = $catalog !== null ? $catalog->getRoot() : dirname(__DIR__, 2);
        if ($catalog === null) {
            $catalog = new Catalog($root);
        }
        $nombres = [];
        foreach ($ids as $id) {
            $nombres[] = IdentidadPublica::nombre($partida, $id);
        }
        $quien = self::yNombres($nombres);
        $lugarNombre = $lugarId !== '' ? EtiquetaFicha::lugar($lugarId, $catalog->store()) : '';
        $lugarTxt = $lugarNombre !== ''
            ? EncuentroCotilleoCopy::prepLugarEstancia($lugarId, $lugarNombre)
            : '';
        $donde = $lugarTxt !== '' ? ' ' . $lugarTxt : '';

        $seConocen = count($ids) >= 2 && RelacionEngine::seConocen($partida, $ids[0], $ids[1]);
        $familiaRel = count($ids) >= 2 ? RelacionBitacora::familiaCopy($partida, $ids[0], $ids[1]) : 'desconocidos';
        $senalRomance = count($ids) >= 2 && self::senalRomanticaReal($partida, $ids[0], $ids[1]);
        $tension = self::tieneTension($partida, $ids);

        $nivel = 'coincidencia';
        $categoria = CotilleoCategoria::COINCIDENCIAS;
        $destacado = false;
        $familiaCopy = 'coincidencia_sospechosa';
        $pista = 'Mira un poco más antes de mover ficha.';
        $contextoHint = '';

        if ($familiaRel === 'ex_reconexion') {
            $nivel = 'romance';
            $categoria = CotilleoCategoria::ROMANCE;
            $destacado = true;
            $familiaCopy = 'ex_reconexion';
            $pista = 'Aunque lo dejaron, algo los sigue juntando. Puede volver a pasar.';
            $contextoHint = 'ex';
        } elseif ($familiaRel === 'pareja') {
            $nivel = 'romance';
            $categoria = CotilleoCategoria::ROMANCE;
            $destacado = true;
            $familiaCopy = 'pareja';
            $pista = 'Aquí hay historia. Si quieres mover algo, este es buen sitio.';
            $contextoHint = 'pareja';
        } elseif ($senalRomance) {
            $nivel = 'romance';
            $categoria = CotilleoCategoria::ROMANCE;
            $destacado = true;
            $familiaCopy = 'romance';
            $pista = 'No es solo casualidad. Podrías proponer que queden de verdad.';
            $contextoHint = 'romance';
        } elseif ($seConocen || $familiaRel === 'conocidos') {
            $nivel = 'relacion';
            $categoria = CotilleoCategoria::RELACION;
            $familiaCopy = 'amistad_social';
            $pista = 'Si quieres que hablen de verdad, organízalo tú.';
            $contextoHint = 'conocidos';
        }

        if ($tension && $familiaCopy !== 'pareja' && $familiaCopy !== 'ex_reconexion') {
            $contextoHint = 'tension';
        }

        $seed = CotilleoNarrativo::clavePar($ids, $lugarId) . '|' . (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $texto = CopyCotilleoFamilias::linea($familiaCopy, ['quien' => $quien, 'donde' => $donde], $seed);
        if ($texto === '') {
            switch ($familiaCopy) {
                case 'ex_reconexion':
                    $pool = self::VARIANTES_EX;
                    break;
                case 'pareja':
                    $pool = self::VARIANTES_PAREJA;
                    break;
                case 'romance':
                    $pool = self::VARIANTES_ROMANCE;
                    break;
                case 'amistad_social':
                    $pool = $tension ? self::VARIANTES_CON_TENSION : self::VARIANTES_CONOCIDOS;
                    break;
                default:
                    $pool = ['%s vuelven a acabar juntos%s. Tanta casualidad empieza a ser sospechosa…'];
                    break;
            }
            if ($pool === []) {
                $texto = $quien . ' vuelven a coincidir' . $donde . '.';
            } else {
                $idx = abs(crc32($seed)) % count($pool);
                $texto = sprintf($pool[$idx], $quien, $donde);
            }
        }
        $cat = CotilleoCategoria::de([
            'tipo' => 'cotilleo_patron',
            'cotilleo_meta' => CotilleoCategoria::meta($categoria, $destacado),
        ]);

        return [
            'texto' => $texto,
            'pista' => $pista,
            'categoria' => $cat['id'],
            'categoria_etiqueta' => $cat['etiqueta'],
            'categoria_icono' => $cat['icono'],
            'destacado' => $destacado,
            'cotilleo_meta' => CotilleoCategoria::meta($categoria, $destacado),
            'nivel' => $nivel,
            'se_conocen' => $seConocen,
            'contexto_hint' => $contextoHint,
        ];
    }

    private static function senalRomanticaReal(array $partida, string $a, string $b): bool
    {
        if (RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::FLECHAZO)
            || RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::PRIMERA_CITA)
            || RelacionBitacora::tienenHito($partida, $a, $b, RelacionBitacora::INICIO_PAREJA)) {
            return true;
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        foreach ($partida['buzon'] ?? [] as $msg) {
            if (!is_array($msg) || (string) ($msg['tipo'] ?? '') !== 'senal_romantica') {
                continue;
            }
            if ((int) ($msg['dia'] ?? 0) !== $dia) {
                continue;
            }
            $actores = is_array($msg['actores'] ?? null) ? $msg['actores'] : [];
            if (in_array($a, $actores, true) && in_array($b, $actores, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * ¿Hay tensión entre el par? (discusión fuerte, rechazo reciente, enfado mutuo)
     *
     * @param list<string> $ids
     */
    private static function tieneTension(array $partida, array $ids): bool
    {
        if (count($ids) < 2) {
            return false;
        }
        $a = $ids[0];
        $b = $ids[1];
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $ventana = 4;
        $tiposTension = [
            RelacionBitacora::DISCUSION_FUERTE,
            RelacionBitacora::RECHAZO_IMPORTANTE,
        ];
        foreach (RelacionBitacora::entre($partida, $a, $b) as $h) {
            if (!is_array($h)) {
                continue;
            }
            $tipo = (string) ($h['tipo'] ?? '');
            if (!in_array($tipo, $tiposTension, true)) {
                continue;
            }
            $fd = (int) (($h['fecha']['dia'] ?? $h['dia'] ?? 0));
            if ($fd >= $dia - $ventana) {
                return true;
            }
        }
        return false;
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

    /**
     * Coincidencias grupales: el copy romántico/social es siempre por PAR.
     *
     * @param list<string> $ids
     * @return list<string>
     */
    private static function reducirAPar(array $partida, array $ids, string $lugarId): array
    {
        $cal = CalibracionConfig::load(dirname(__DIR__, 2));
        $mejor = [$ids[0], $ids[1]];
        $mejorScore = -1;
        $n = count($ids);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $ids[$i];
                $b = $ids[$j];
                $score = 0;
                if (SenalRomantica::desdeHacia($partida, $a, $b, $cal)['ok'] ?? false) {
                    $score += 10;
                }
                if (SenalRomantica::desdeHacia($partida, $b, $a, $cal)['ok'] ?? false) {
                    $score += 10;
                }
                if (RelacionEngine::seConocen($partida, $a, $b)) {
                    $score += 3;
                }
                $soc = RelacionEngine::valorSocialHacia($partida, $a, $b) + RelacionEngine::valorSocialHacia($partida, $b, $a);
                $score += (int) round($soc / 20);
                if ($score > $mejorScore) {
                    $mejorScore = $score;
                    $mejor = [$a, $b];
                }
            }
        }
        sort($mejor);
        return $mejor;
    }
}
