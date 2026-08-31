<?php

declare(strict_types=1);



namespace AquiHayTema\Engine;



/**

 * Copy compacto de resultado de encuentro para El Cotilleo.

 * Una entrada breve: escena + como máximo un matiz relevante.

 */

final class EncuentroCotilleoCopy

{

    /**

     * @param array<string, mixed> $enc

     * @param array<string, mixed> $res

     * @return array{texto: string, cotilleo_meta: array{categoria: string, destacado: bool}}|null

     */

    public static function compilar(

        array $partida,

        array $enc,

        array $res,

        ?Catalog $catalog = null,

        ?string $projectRoot = null

    ): ?array {

        $participantes = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];

        if (count($participantes) < 2) {

            return null;

        }



        $root = $projectRoot ?? ($catalog !== null ? $catalog->getRoot() : dirname(__DIR__, 2));

        if ($catalog === null) {

            $catalog = new Catalog($root);

        }

        $base = ResumenDia::vistaEncuentro($partida, $enc, $catalog);

        $quien = self::yNombres($base['participantes_nombres'] ?? []);

        if ($quien === '') {

            return null;

        }



        $escena = self::escena($quien, $base, $enc);

        if ($escena === '') {

            return null;

        }



        $encVista = array_merge($enc, ['resultado' => $res, 'estado' => 'terminado']);

        $vista = EncuentroResultadoVista::de($partida, $encVista, $catalog, $root);

        $resultado = is_array($vista['resultado'] ?? null) ? $vista['resultado'] : [];



        $extra = self::elegirExtra($partida, $participantes, $quien, $res, $resultado, $catalog);

        $texto = $extra !== null && ($extra['texto'] ?? '') !== ''

            ? $escena . ' ' . (string) $extra['texto']

            : $escena;



        $meta = $extra['meta'] ?? CotilleoCategoria::meta(CotilleoCategoria::ENCUENTRO, false);



        return [

            'texto' => $texto,

            'cotilleo_meta' => $meta,

        ];

    }



    /**

     * @param array<string, mixed> $enc

     * @param array<string, mixed> $res

     */

    public static function mensaje(

        array $partida,

        array $enc,

        array $res,

        ?Catalog $catalog = null,

        ?string $projectRoot = null

    ): ?string {

        $comp = self::compilar($partida, $enc, $res, $catalog, $projectRoot);

        return $comp !== null ? (string) ($comp['texto'] ?? '') : null;

    }



    /**

     * Plan individual autónomo terminado (NPC por su cuenta). No rutina ni presencia pasiva.

     *

     * @param array<string, mixed> $enc

     */

    public static function mensajeAutonomo(

        array $partida,

        array $enc,

        ?Catalog $catalog = null,

        ?string $projectRoot = null

    ): ?string {

        $participantes = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];

        if (count($participantes) !== 1) {

            return null;

        }

        $intencion = (string) ($enc['intencion'] ?? '');

        if (($enc['tipo'] ?? '') !== 'individual' || !in_array($intencion, ['autonomo', 'autonomo_relacion'], true)) {

            return null;

        }

        $rid = (string) $participantes[0];

        $nombre = IdentidadPublica::nombre($partida, $rid);

        if ($nombre === '') {

            return null;

        }

        $root = $projectRoot ?? ($catalog !== null ? $catalog->getRoot() : dirname(__DIR__, 2));

        if ($catalog === null) {

            $catalog = new Catalog($root);

        }

        $lugarId = (string) ($enc['lugar'] ?? $enc['lugar_id'] ?? '');

        $lugar = $lugarId !== '' ? EtiquetaFicha::lugar($lugarId, $catalog->store()) : '';

        $lugarTxt = $lugar !== '' ? ' ' . self::prepLugar($lugarId, $lugar) : '';

        $emo = (string) ($partida['residentes'][$rid]['runtime']['estado_emocional']['id'] ?? EstadoEmocional::NEUTRO);

        $emo = EstadoEmocional::canonId($emo);

        $rutinario = CotilleoAutonomoCadencia::esLugarRutinarioPublico($partida, $rid, $lugarId);

        if ($emo === EstadoEmocional::NEUTRO && !$rutinario && $lugar !== '') {

            return $nombre . ' ha decidido que hoy necesitaba ' . $lugar . '. Algo habrá sido.';

        }

        if ($emo === 'triste') {

            return $nombre . ' ha necesitado aire fresco y se ha ido' . $lugarTxt . ' por su cuenta.';

        }

        if ($emo === 'enfadado') {

            return $nombre . ' ha salido' . $lugarTxt . ' a despejarse.';

        }

        if ($emo === 'alegre') {

            return $nombre . ' se ha animado y se ha ido' . $lugarTxt . ' por su cuenta.';

        }

        return $nombre . ' se ha ido' . $lugarTxt . ' por su cuenta.';

    }



    /**

     * @param array<string, mixed> $base

     * @param array<string, mixed> $enc

     */

    private static function escena(string $quien, array $base, array $enc): string

    {

        $lugar = trim((string) ($base['lugar_nombre'] ?? ''));

        if ($lugar === '' || $lugar === '—') {

            $lugar = '';

        }

        $lugarTxt = $lugar !== '' ? ' ' . self::prepLugar((string) ($base['lugar'] ?? ''), $lugar) : '';

        $momento = self::momentoDia((int) ($enc['hora'] ?? $enc['hora_inicio'] ?? 12));

        $tipo = PropuestaNivel::aliasTipo((string) ($enc['tipo'] ?? ''));



        if (PropuestaNivel::esTipoCita($tipo)) {

            return $quien . ' han tenido una cita' . $lugarTxt . $momento . '.';

        }

        if ($tipo === PropuestaNivel::PRESENTAR || $tipo === 'conocerse') {

            if ($lugarTxt !== '' && str_contains($momento, 'tarde')) {

                return $quien . ' han pasado la tarde' . $lugarTxt . '.';

            }

            if ($lugarTxt !== '' && str_contains($momento, 'mañana')) {

                return $quien . ' han pasado la mañana' . $lugarTxt . '.';

            }

            if ($lugarTxt !== '' && str_contains($momento, 'noche')) {

                return $quien . ' han coincidido' . $lugarTxt . ' por la noche.';

            }

            return $quien . ' han quedado' . $lugarTxt . $momento . '.';

        }

        if ($tipo === 'conflicto') {

            return $quien . ' han coincidido' . $lugarTxt . $momento . '.';

        }

        return $quien . ' han pasado el rato' . $lugarTxt . $momento . '.';

    }



    public static function prepLugarPublico(string $lugarId, string $nombre): string

    {

        return self::prepLugar($lugarId, $nombre);

    }



    /** Para estar/coincidir/acabar: «en el Cine», «en el Bar». */

    public static function prepLugarEstancia(string $lugarId, string $nombre): string

    {

        $n = function_exists('mb_strtolower') ? mb_strtolower($nombre, 'UTF-8') : strtolower($nombre);

        if (preg_match('/^(el |la |los |las )/u', $n)) {

            return 'en ' . $nombre;

        }

        if (in_array($lugarId, ['lug_cine', 'lug_gimnasio', 'lug_bar', 'lug_arcade', 'lug_bingo', 'lug_mirador', 'lug_discoteca'], true)) {

            return 'en el ' . $nombre;

        }

        if ($lugarId === 'lug_cafeteria' || $nombre === 'Cafetería') {

            return 'en la Cafetería';

        }

        if ($lugarId === 'lug_biblioteca' || $nombre === 'Biblioteca') {

            return 'en la Biblioteca';

        }

        return 'en ' . $nombre;

    }



    private static function prepLugar(string $lugarId, string $nombre): string

    {

        $n = function_exists('mb_strtolower') ? mb_strtolower($nombre, 'UTF-8') : strtolower($nombre);

        if (preg_match('/^(el |la |los |las |al |a la )/u', $n)) {

            return 'en ' . $nombre;

        }

        if (in_array($lugarId, ['lug_cine', 'lug_gimnasio', 'lug_bar', 'lug_arcade', 'lug_bingo', 'lug_mirador'], true)) {

            return 'al ' . $nombre;

        }

        if ($lugarId === 'lug_cafeteria' || $nombre === 'Cafetería') {

            return 'a la Cafetería';

        }

        return 'en ' . $nombre;

    }



    private static function momentoDia(int $hora): string

    {

        if ($hora >= 6 && $hora < 12) {

            return ' por la mañana';

        }

        if ($hora >= 12 && $hora < 20) {

            return ' por la tarde';

        }

        if ($hora >= 20 || $hora < 6) {

            return ' por la noche';

        }

        return '';

    }



    /**

     * Elige un único matiz: hito > romance > conflicto > descubrimiento > tono.

     *

     * @param list<string|int> $participantes

     * @param array<string, mixed> $res

     * @param array<string, mixed> $resultado

     * @return array{texto: string, meta: array{categoria: string, destacado: bool}}|null

     */

    private static function elegirExtra(

        array $partida,

        array $participantes,

        string $quien,

        array $res,

        array $resultado,

        Catalog $catalog

    ): ?array {

        $hito = self::hitoDelDia($partida, $participantes, $quien);

        if ($hito !== null) {

            return $hito;

        }

        $narr = is_array($resultado['experiencia_narrativa'] ?? null) ? $resultado['experiencia_narrativa'] : null;

        if ($narr !== null && (string) ($narr['texto'] ?? '') !== '') {

            $mal = (int) ($narr['resultado_promedio'] ?? 0) < 0;

            return [

                'texto' => (string) $narr['texto'],

                'meta' => CotilleoCategoria::meta($mal ? CotilleoCategoria::DRAMA : CotilleoCategoria::ENCUENTRO, $mal),

            ];

        }



        $romance = is_array($resultado['romance'] ?? null) ? $resultado['romance'] : [];

        if (!empty($romance['hay']) && (int) ($romance['delta'] ?? 0) > 0) {

            return [

                'texto' => 'Hay un destello romántico en el aire.',

                'meta' => CotilleoCategoria::meta(CotilleoCategoria::ROMANCE, true),

            ];

        }



        $conflicto = is_array($resultado['conflicto'] ?? null) ? $resultado['conflicto'] : [];

        if (!empty($conflicto['hay'])) {

            return [

                'texto' => 'La cosa ha acabado un poco tensa.',

                'meta' => CotilleoCategoria::meta(CotilleoCategoria::DRAMA, true),

            ];

        }



        $desc = self::mejorDescubrimiento($partida, $resultado['descubrimientos'] ?? [], $catalog);

        if ($desc !== null) {

            return $desc;

        }



        $tono = self::tonoExperiencia($res, $participantes);

        if ($tono !== '') {

            return [

                'texto' => $tono,

                'meta' => CotilleoCategoria::meta(CotilleoCategoria::ENCUENTRO, false),

            ];

        }



        $social = is_array($resultado['social'] ?? null) ? $resultado['social'] : [];

        if (!empty($social['hay']) && (string) ($social['texto'] ?? '') !== '') {

            return [

                'texto' => self::suavizarSocial((string) $social['texto']),

                'meta' => CotilleoCategoria::meta(CotilleoCategoria::RELACION, false),

            ];

        }



        return null;

    }



    private static function suavizarSocial(string $texto): string

    {

        if ($texto === 'Se han llevado mejor.') {

            return 'Parece que han hecho buenas migas.';

        }

        if ($texto === 'Se han llevado peor.') {

            return 'No ha terminado del todo bien.';

        }

        return $texto;

    }



    /**

     * @param list<string|int> $participantes

     * @return array{texto: string, meta: array{categoria: string, destacado: bool}}|null

     */

    private static function hitoDelDia(array $partida, array $participantes, string $quien): ?array

    {

        if (count($participantes) < 2) {

            return null;

        }

        $a = (string) $participantes[0];

        $b = (string) $participantes[1];

        $hoy = (int) ($partida['reloj']['dia_pueblo'] ?? 1);

        foreach (RelacionBitacora::entre($partida, $a, $b) as $h) {

            if (!is_array($h)) {

                continue;

            }

            $fd = (int) (($h['fecha']['dia'] ?? 0));

            if ($fd !== $hoy) {

                continue;

            }

            $tipo = (string) ($h['tipo'] ?? '');

            if ($tipo === RelacionBitacora::PRIMERA_CITA) {

                return [

                    'texto' => 'Primera cita entre ' . $quien . '. Esto ya es otra cosa.',

                    'meta' => CotilleoCategoria::meta(CotilleoCategoria::ROMANCE, true),

                ];

            }

            if ($tipo === RelacionBitacora::INICIO_PAREJA || $tipo === RelacionBitacora::DECLARACION) {

                return [

                    'texto' => 'Oficial: ' . $quien . ' ya son pareja.',

                    'meta' => CotilleoCategoria::meta(CotilleoCategoria::ROMANCE, true),

                ];

            }

            if ($tipo === RelacionBitacora::RUPTURA) {

                return [

                    'texto' => 'Se ha roto entre ' . $quien . '. El pueblo suspira.',

                    'meta' => CotilleoCategoria::meta(CotilleoCategoria::DRAMA, true),

                ];

            }

            if ($tipo === RelacionBitacora::CRISIS) {

                return [

                    'texto' => 'Crisis a la vista entre ' . $quien . '.',

                    'meta' => CotilleoCategoria::meta(CotilleoCategoria::DRAMA, true),

                ];

            }

            if ($tipo === RelacionBitacora::RECONCILIACION || $tipo === RelacionBitacora::VUELTA) {

                return [

                    'texto' => 'Parece que ' . $quien . ' vuelven a hablarse.',

                    'meta' => CotilleoCategoria::meta(CotilleoCategoria::RELACION, true),

                ];

            }

            if ($tipo === RelacionBitacora::DISCUSION_FUERTE) {

                return [

                    'texto' => 'Ha habido un buen enfado entre ' . $quien . '.',

                    'meta' => CotilleoCategoria::meta(CotilleoCategoria::DRAMA, true),

                ];

            }

        }

        return null;

    }



    /**

     * @param list<mixed> $descubrimientos

     * @return array{texto: string, meta: array{categoria: string, destacado: bool}}|null

     */

    private static function mejorDescubrimiento(array $partida, array $descubrimientos, Catalog $catalog): ?array

    {

        foreach ($descubrimientos as $d) {

            if (!is_array($d)) {

                continue;

            }

            $rid = (string) ($d['residente'] ?? $d['residente_id'] ?? '');

            $campo = (string) ($d['campo'] ?? '');

            if ($rid === '' || $campo === '') {

                $txt = trim((string) ($d['texto'] ?? ''));

                if ($txt !== '') {

                    return [

                        'texto' => $txt,

                        'meta' => CotilleoCategoria::meta(CotilleoCategoria::DESCUBRIMIENTO, false),

                    ];

                }

                continue;

            }

            $nombre = IdentidadPublica::nombre($partida, $rid);

            $genero = (string) ($partida['residentes'][$rid]['identidad_publica']['genero'] ?? '');

            $valor = $d['valor'] ?? CopyDescubrimiento::idDeCampo($campo);

            $txt = CopyDescubrimiento::textoCotilleo(

                $nombre,

                $campo,

                $valor,

                $catalog->store(),

                $genero !== '' ? $genero : null

            );

            if ($txt !== null && $txt !== '') {

                return [

                    'texto' => $txt,

                    'meta' => CotilleoCategoria::meta(CotilleoCategoria::DESCUBRIMIENTO, false),

                ];

            }

        }

        return null;

    }



    /**

     * @param array<string, mixed> $res

     * @param list<string|int> $participantes

     */

    private static function tonoExperiencia(array $res, array $participantes): string

    {

        $vals = [];

        foreach ($participantes as $pid) {

            $r = (string) ($res['por_participante'][(string) $pid]['resultado'] ?? '');

            if ($r !== '') {

                $vals[] = $r;

            }

        }

        if ($vals === []) {

            return self::tonoDeDeltas($res);

        }



        $pos = ['bien', 'muy_bien'];

        $neg = ['mal', 'muy_mal'];

        $allPos = $vals !== [] && count(array_diff($vals, $pos)) === 0;

        $allNeg = $vals !== [] && count(array_diff($vals, $neg)) === 0;

        if ($allPos) {

            return 'Parece que han hecho buenas migas.';

        }

        if ($allNeg) {

            return 'La cosa ha estado algo fría.';

        }

        $hasPos = (bool) array_intersect($vals, $pos);

        $hasNeg = (bool) array_intersect($vals, $neg);

        if ($hasPos && !$hasNeg) {

            return 'Parece que han hecho buenas migas.';

        }

        if ($hasNeg && !$hasPos) {
            return 'La cosa ha estado algo tensa.';
        }

        if ($hasPos && $hasNeg) {
            return self::tonoAsimetrico($res, $participantes);
        }

        return self::tonoDeDeltas($res);
    }

    /**
     * Resultado mixto: uno pasó bien, otro mal. Copia que refleja la asimetría.
     *
     * @param array<string, mixed> $res
     * @param list<string|int> $participantes
     */
    private static function tonoAsimetrico(array $res, array $participantes): string
    {
        $por = $res['por_participante'] ?? [];
        $pos = ['bien', 'muy_bien'];
        $bien = [];
        $mal = [];
        foreach ($participantes as $pid) {
            $pid = (string) $pid;
            $r = (string) ($por[$pid]['resultado'] ?? '');
            if (in_array($r, $pos, true)) {
                $bien[] = $pid;
            } elseif ($r !== '') {
                $mal[] = $pid;
            }
        }
        if ($bien !== [] && $mal !== []) {
            return 'A uno le ha ido bien, pero el otro no conectó demasiado.';
        }
        return self::tonoDeDeltas($res);
    }



    /** @param array<string, mixed> $res */

    private static function tonoDeDeltas(array $res): string

    {

        $ds = is_array($res['delta_social'] ?? null) ? $res['delta_social'] : [];

        $n = 0;

        if (array_key_exists('intensidad', $ds)) {

            $n = (int) $ds['intensidad'];

        } elseif (isset($ds['a_hacia_b']) || isset($ds['b_hacia_a'])) {

            $n = (int) round(((int) ($ds['a_hacia_b'] ?? 0) + (int) ($ds['b_hacia_a'] ?? 0)) / 2);

        }

        if ($n > 0) {

            return 'Parece que han hecho buenas migas.';

        }

        if ($n < 0) {

            return 'La cosa ha estado algo tensa.';

        }

        return '';

    }



    /**

     * @param list<string> $nombres

     */

    private static function yNombres(array $nombres): string

    {

        $nombres = array_values(array_filter($nombres, static function ($n) {

            return is_string($n) && $n !== '';

        }));

        $n = count($nombres);

        if ($n === 0) {

            return '';

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


