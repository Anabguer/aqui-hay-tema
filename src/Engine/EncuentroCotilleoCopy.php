<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Copy compacto de resultado de encuentro para El Cotilleo.
 * Una sola entrada: escena + tono + hitos + descubrimientos. Sin scores ocultos.
 */
final class EncuentroCotilleoCopy
{
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

        $partes = [];
        $escena = self::escena($quien, $base, $enc);
        if ($escena !== '') {
            $partes[] = $escena;
        }
        foreach (self::hitosRelacion($partida, $participantes, $quien) as $hito) {
            $partes[] = $hito;
        }
        $tono = self::tonoExperiencia($res, $participantes);
        if ($tono !== '') {
            $partes[] = $tono;
        }

        $encVista = array_merge($enc, ['resultado' => $res, 'estado' => 'terminado']);
        $vista = EncuentroResultadoVista::de($partida, $encVista, $catalog, $root);
        $resultado = is_array($vista['resultado'] ?? null) ? $vista['resultado'] : [];

        if ($tono === '') {
            foreach (['social', 'romance', 'conflicto'] as $canal) {
                $row = is_array($resultado[$canal] ?? null) ? $resultado[$canal] : [];
                if (!empty($row['hay']) && (string) ($row['texto'] ?? '') !== '') {
                    $partes[] = (string) $row['texto'];
                }
            }
        } elseif (!empty($resultado['conflicto']['hay']) && (string) ($resultado['conflicto']['texto'] ?? '') !== '') {
            $partes[] = (string) $resultado['conflicto']['texto'];
        }

        foreach ($resultado['descubrimientos'] ?? [] as $d) {
            if (!is_array($d)) {
                continue;
            }
            $txt = trim((string) ($d['texto'] ?? ''));
            if ($txt !== '') {
                $partes[] = $txt;
            }
        }

        $partes = array_values(array_unique(array_filter($partes, static function ($p) {
            return is_string($p) && $p !== '';
        })));
        if ($partes === []) {
            return null;
        }
        return implode(' ', $partes);
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
            return $nombre . ' ha decidido que hoy necesitaba ' . $lugar . '. No haremos preguntas.';
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
            return $quien . ' han quedado' . $lugarTxt . $momento . '.';
        }
        if ($tipo === 'conflicto') {
            return $quien . ' han coincidido' . $lugarTxt . $momento . '.';
        }
        return $quien . ' han pasado el rato' . $lugarTxt . $momento . '.';
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
     * @param list<string|int> $participantes
     * @return list<string>
     */
    private static function hitosRelacion(array $partida, array $participantes, string $quien): array
    {
        if (count($participantes) < 2) {
            return [];
        }
        $a = (string) $participantes[0];
        $b = (string) $participantes[1];
        $hoy = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $out = [];
        foreach (RelacionBitacora::entre($partida, $a, $b) as $h) {
            if (!is_array($h)) {
                continue;
            }
            $fd = (int) (($h['fecha']['dia'] ?? 0));
            if ($fd !== $hoy) {
                continue;
            }
            $tipo = (string) ($h['tipo'] ?? '');
            if ($tipo === RelacionBitacora::SE_CONOCIERON) {
                $out[] = $quien . ' ya no son completos desconocidos.';
            } elseif ($tipo === RelacionBitacora::PRIMERA_CITA) {
                $out[] = 'Ha habido una primera cita entre ' . $quien . '.';
            }
        }
        return array_values(array_unique($out));
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
            return 'Aquí ha habido bastante buen rollo.';
        }
        if ($allNeg) {
            return 'La cosa ha estado algo fría.';
        }
        $hasPos = (bool) array_intersect($vals, $pos);
        $hasNeg = (bool) array_intersect($vals, $neg);
        if ($hasPos && !$hasNeg) {
            return 'Parece que ahora se conocen un poco mejor.';
        }
        if ($hasNeg && !$hasPos) {
            return 'La cosa ha estado algo tensa.';
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
        if ($n > 2) {
            return 'Aquí ha habido bastante buen rollo.';
        }
        if ($n > 0) {
            return 'Parece que ahora se conocen un poco mejor.';
        }
        if ($n < -2) {
            return 'La cosa ha estado algo fría.';
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
