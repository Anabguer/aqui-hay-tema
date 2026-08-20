<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Generador de fichas sobre catálogos candidatos. No escribe P001–P200.
 */
final class GeneradorFichaCandidata
{
    public const AFICIONES_CANON = 3;
    public const RASGOS_CANON = 3;

    /**
     * @param array<string, mixed> $pack
     * @param array<string, mixed> $opts modo: produccion|showcase
     * @return array<string, mixed>
     */
    public static function una(array $pack, RngService $rng, array &$maniasUsadas, array $opts = []): array
    {
        $modo = (string) ($opts['modo'] ?? 'produccion');
        $indice = (int) ($opts['indice'] ?? 0);
        $maxIntentos = 24;
        $mejor = null;
        for ($intento = 0; $intento < $maxIntentos; $intento++) {
            $ficha = self::intentar($pack, $rng, $maniasUsadas, $modo, $indice, $intento);
            if (self::cardinalidadOk($ficha)) {
                return $ficha;
            }
            $mejor = $ficha;
        }
        if ($mejor === null) {
            throw new \RuntimeException('generador candidatos: sin ficha');
        }
        return self::forzarCardinalidad($pack, $mejor, $rng);
    }

    /**
     * @param array<string, mixed> $ficha
     */
    public static function cardinalidadOk(array $ficha): bool
    {
        $af = $ficha['aficiones'] ?? [];
        $ra = $ficha['rasgos'] ?? [];
        return is_array($af) && is_array($ra)
            && count($af) === self::AFICIONES_CANON
            && count(array_unique($af)) === self::AFICIONES_CANON
            && count($ra) === self::RASGOS_CANON
            && count(array_unique($ra)) === self::RASGOS_CANON;
    }

    /**
     * 0 = coherente, 1 = una tensión, 2 = varias.
     * Producción: mayoría 0, algunas 1, pocas 2.
     */
    public static function presupuestoContradiccion(RngService $rng): int
    {
        $x = $rng->nextFloat();
        if ($x < 0.70) {
            return 0;
        }
        if ($x < 0.95) {
            return 1;
        }
        return 2;
    }

    /**
     * @param array<string, mixed> $ficha
     * @return list<string>
     */
    public static function tensiones(array $ficha): array
    {
        $af = $ficha['aficiones'] ?? [];
        $ras = $ficha['rasgos'] ?? [];
        $rech = $ficha['rechazos'] ?? [];
        $soc = $ficha['social'] ?? [];
        $afe = $ficha['afecto'] ?? [];
        $hasAf = static function (string $id) use ($af): bool {
            return in_array($id, $af, true);
        };
        $hasRas = static function (string $id) use ($ras): bool {
            return in_array($id, $ras, true);
        };
        $hasRech = static function (string $tipo, string $id) use ($rech): bool {
            foreach ($rech as $r) {
                if (!is_array($r)) {
                    continue;
                }
                if (($r['tipo'] ?? '') === $tipo && ($r['id'] ?? '') === $id) {
                    return true;
                }
            }
            return false;
        };
        $out = [];
        if ($hasRas('timido') && ($hasAf('karaoke') || $hasAf('fiesta') || $hasAf('baile'))) {
            $out[] = 'timidez_escenario';
        }
        if ($hasRas('sociable') && ($hasRech('destino', 'lug_discoteca') || (($soc['umbral_ruido'] ?? '') === 'bajo' && ($soc['energia_social'] ?? '') === 'alta'))) {
            $out[] = 'sociable_sin_discoteca';
        }
        if (($hasAf('correr') || $hasAf('deporte_equipo') || $hasAf('bici')) && $hasRech('destino', 'lug_gimnasio') && !$hasAf('gimnasio')) {
            $out[] = 'deporte_sin_gimnasio';
        }
        if ($hasAf('leer') && $hasRech('contexto', 'ctx_biblioteca_habitat')) {
            $out[] = 'leer_sin_habitat';
        }
        if ($hasAf('cine_sala') && ($hasRech('gusto', 'cine_terror') || $hasRech('contexto', 'ctx_sala_a_oscuras'))) {
            $out[] = 'cine_con_veto';
        }
        if ($hasAf('escuchar_musica') && $hasRech('actividad', 'act_bailar') && !$hasAf('baile')) {
            $out[] = 'musica_sin_baile';
        }
        if ($hasRas('calido') && ($afe['espacio_personal'] ?? '') === 'amplio') {
            $out[] = 'calidez_con_espacio';
        }
        if ($hasAf('leer') && ($hasAf('fiesta') || $hasAf('copas'))) {
            $out[] = 'lectura_y_noche';
        }
        if ($hasAf('spa') && $hasRech('contexto', 'ctx_que_te_toquen')) {
            $out[] = 'spa_sin_manos';
        }
        if ($hasAf('copas') && $hasRech('contexto', 'ctx_alcohol')) {
            $out[] = 'bar_sin_alcohol';
        }
        if ($hasRas('competitivo') && $hasRech('contexto', 'ctx_perder_en_publico')) {
            $out[] = 'competir_sin_publico';
        }
        if (($hasAf('pasear') || $hasAf('picnic') || $hasAf('plantas') || $hasAf('perros')) && $hasRech('contexto', 'ctx_parque_lleno')) {
            $out[] = 'parque_sin_feria';
        }
        if ($hasAf('ver_en_casa') && $hasRech('contexto', 'ctx_sala_a_oscuras')) {
            $out[] = 'cine_en_casa';
        }
        if (($soc['energia_social'] ?? '') === 'alta' && ($soc['umbral_ruido'] ?? '') === 'bajo') {
            $out[] = 'energia_sin_jaleo';
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $pack
     * @param list<string> $maniasUsadas
     * @return array<string, mixed>
     */
    private static function intentar(
        array $pack,
        RngService $rng,
        array &$maniasUsadas,
        string $modo,
        int $indice,
        int $intento
    ): array {
        $aficiones = CatalogosCandidatos::items($pack, 'aficiones');
        $gustos = CatalogosCandidatos::items($pack, 'gustos');
        $rechazosCat = CatalogosCandidatos::items($pack, 'rechazos');
        $rasgos = CatalogosCandidatos::items($pack, 'rasgos');
        $manias = CatalogosCandidatos::items($pack, 'manias');
        $idsAf = self::ids($aficiones);

        $afic = self::pickAficiones($aficiones, $rng, self::AFICIONES_CANON);
        $gust = self::pickGustos($gustos, $afic, $aficiones, $rng, $rng->nextInt(1, 3));
        $rech = self::pickRechazos($rechazosCat, $afic, $gust, $rng);
        $ras = self::pickRasgos($rasgos, $rng, self::RASGOS_CANON);
        $social = self::pickEjes($pack['social']['ejes'] ?? [], $rng);
        $afecto = self::pickEjes($pack['afecto']['ejes'] ?? [], $rng);
        $mania = self::pickMania(self::ids($manias), $maniasUsadas, $rng);

        $twistNombre = null;
        if ($modo === 'showcase' && $intento === 0) {
            $tw = self::aplicarTwist($indice, $afic, $gust, $rech, $ras, $social, $afecto, $idsAf, $rasgos);
            $afic = $tw['aficiones'];
            $gust = $tw['gustos'];
            $rech = $tw['rechazos'];
            $ras = $tw['rasgos'];
            $social = $tw['social'];
            $afecto = $tw['afecto'];
            $twistNombre = $tw['nombre'];
        } elseif ($modo === 'produccion') {
            $presupuesto = self::presupuestoContradiccion($rng);
            $aj = self::ajustarContradicciones($pack, $afic, $gust, $rech, $ras, $social, $afecto, $presupuesto, $rng);
            $afic = $aj['aficiones'];
            $gust = $aj['gustos'];
            $rech = $aj['rechazos'];
            $ras = $aj['rasgos'];
            $social = $aj['social'];
            $afecto = $aj['afecto'];
        }

        $ficha = [
            'aficiones' => array_values(array_unique($afic)),
            'gustos' => array_values(array_unique($gust)),
            'rechazos' => $rech,
            'rasgos' => array_values(array_unique($ras)),
            'social' => $social,
            'afecto' => $afecto,
            'mania' => $mania,
            'twist' => $twistNombre,
        ];
        $ficha['tensiones'] = self::tensiones($ficha);
        $ficha['presupuesto_contradiccion'] = count($ficha['tensiones']);
        return $ficha;
    }

    /**
     * @param array<string, mixed> $pack
     * @param array<string, mixed> $ficha
     * @return array<string, mixed>
     */
    private static function forzarCardinalidad(array $pack, array $ficha, RngService $rng): array
    {
        $aficItems = CatalogosCandidatos::items($pack, 'aficiones');
        $rasItems = CatalogosCandidatos::items($pack, 'rasgos');
        $afic = array_values(array_unique($ficha['aficiones'] ?? []));
        $ras = array_values(array_unique($ficha['rasgos'] ?? []));
        $idsAf = self::ids($aficItems);
        $idsRa = self::ids($rasItems);
        $guard = 0;
        while (count($afic) < self::AFICIONES_CANON && $guard++ < 80) {
            $cand = $idsAf[$rng->nextInt(0, count($idsAf) - 1)];
            if (!in_array($cand, $afic, true)) {
                $afic[] = $cand;
            }
        }
        $ejeDe = [];
        foreach ($rasItems as $r) {
            $ejeDe[(string) $r['id']] = (string) ($r['eje'] ?? '');
        }
        $ejesUsados = [];
        foreach ($ras as $id) {
            if (isset($ejeDe[$id])) {
                $ejesUsados[$ejeDe[$id]] = true;
            }
        }
        $guard = 0;
        while (count($ras) < self::RASGOS_CANON && $guard++ < 80) {
            $cand = $idsRa[$rng->nextInt(0, count($idsRa) - 1)];
            $e = $ejeDe[$cand] ?? '';
            if (in_array($cand, $ras, true)) {
                continue;
            }
            if ($e !== '' && isset($ejesUsados[$e])) {
                continue;
            }
            $ras[] = $cand;
            if ($e !== '') {
                $ejesUsados[$e] = true;
            }
        }
        $ficha['aficiones'] = array_slice($afic, 0, self::AFICIONES_CANON);
        $ficha['rasgos'] = array_slice($ras, 0, self::RASGOS_CANON);
        $ficha['tensiones'] = self::tensiones($ficha);
        return $ficha;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    public static function ids(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            $out[] = (string) $it['id'];
        }
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    public static function pickAficiones(array $items, RngService $rng, int $n): array
    {
        $byFam = [];
        foreach ($items as $it) {
            $byFam[(string) ($it['familia'] ?? '?')][] = (string) $it['id'];
        }
        $fams = array_keys($byFam);
        $out = [];
        $famCount = [];
        $guard = 0;
        while (count($out) < $n && $guard++ < 120) {
            $fam = $fams[$rng->nextInt(0, count($fams) - 1)];
            $cap = ($guard > 60) ? 3 : 2;
            if ((int) ($famCount[$fam] ?? 0) >= $cap) {
                continue;
            }
            $pool = array_values(array_diff($byFam[$fam], $out));
            if ($pool === []) {
                continue;
            }
            $id = $pool[$rng->nextInt(0, count($pool) - 1)];
            $out[] = $id;
            $famCount[$fam] = (int) ($famCount[$fam] ?? 0) + 1;
        }
        if (count($out) < $n) {
            $todos = self::ids($items);
            $guard = 0;
            while (count($out) < $n && $guard++ < 80) {
                $id = $todos[$rng->nextInt(0, count($todos) - 1)];
                if (!in_array($id, $out, true)) {
                    $out[] = $id;
                }
            }
        }
        return array_slice(array_values(array_unique($out)), 0, $n);
    }

    /**
     * @param list<array<string, mixed>> $gustos
     * @param list<string> $afic
     * @param list<array<string, mixed>> $aficiones
     * @return list<string>
     */
    public static function pickGustos(array $gustos, array $afic, array $aficiones, RngService $rng, int $n): array
    {
        $famDe = [];
        foreach ($aficiones as $a) {
            $famDe[(string) $a['id']] = (string) ($a['familia'] ?? '');
        }
        $fams = [];
        foreach ($afic as $id) {
            if (isset($famDe[$id])) {
                $fams[] = $famDe[$id];
            }
        }
        $rel = [];
        $otros = [];
        foreach ($gustos as $g) {
            $gid = (string) $g['id'];
            $gf = (string) ($g['familia'] ?? '');
            $afin = is_array($g['afinidad_aficiones'] ?? null) ? $g['afinidad_aficiones'] : [];
            $hit = in_array($gf, $fams, true);
            foreach ($afin as $a) {
                if (in_array((string) $a, $afic, true)) {
                    $hit = true;
                }
            }
            if ($hit) {
                $rel[] = $gid;
            } else {
                $otros[] = $gid;
            }
        }
        $out = [];
        if ($rel !== []) {
            $out[] = $rel[$rng->nextInt(0, count($rel) - 1)];
        }
        $pool = array_values(array_diff($otros !== [] ? $otros : self::ids($gustos), $out));
        while (count($out) < $n && $pool !== []) {
            $pick = $pool[$rng->nextInt(0, count($pool) - 1)];
            $out[] = $pick;
            $pool = array_values(array_diff($pool, [$pick]));
        }
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $cat
     * @param list<string> $afic
     * @param list<string> $gust
     * @return list<array{tipo:string,id:string}>
     */
    public static function pickRechazos(array $cat, array $afic, array $gust, RngService $rng): array
    {
        $n = $rng->nextInt(1, 2);
        $out = [];
        $guard = 0;
        $dest = array_keys(CatalogosCandidatos::DESTINOS);
        while (count($out) < $n && $guard++ < 40) {
            $modo = $rng->nextInt(0, 2);
            if ($modo === 0 && $dest !== []) {
                $id = $dest[$rng->nextInt(0, count($dest) - 1)];
                $row = ['tipo' => 'destino', 'id' => $id];
            } else {
                $it = $cat[$rng->nextInt(0, count($cat) - 1)];
                $cid = (string) $it['id'];
                $contra = is_array($it['contra'] ?? null) ? $it['contra'] : [];
                $choqueDuro = in_array($cid, $afic, true);
                $choque = $choqueDuro;
                foreach ($contra as $c) {
                    if (in_array((string) $c, $afic, true) || in_array((string) $c, $gust, true)) {
                        $choque = true;
                    }
                }
                if ($choqueDuro) {
                    continue;
                }
                if ($choque) {
                    continue;
                }
                $row = ['tipo' => (string) ($it['tipo'] ?? 'contexto'), 'id' => $cid];
            }
            $key = $row['tipo'] . ':' . $row['id'];
            $dup = false;
            foreach ($out as $o) {
                if ($o['tipo'] . ':' . $o['id'] === $key) {
                    $dup = true;
                }
            }
            if ($dup) {
                continue;
            }
            if ($row['tipo'] === 'aficion' && in_array($row['id'], $afic, true)) {
                continue;
            }
            if ($row['tipo'] === 'gusto' && in_array($row['id'], $gust, true)) {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rasgos
     * @return list<string>
     */
    public static function pickRasgos(array $rasgos, RngService $rng, int $n): array
    {
        $byEje = [];
        foreach ($rasgos as $r) {
            $byEje[(string) ($r['eje'] ?? '?')][] = (string) $r['id'];
        }
        $ejes = array_keys($byEje);
        $out = [];
        $usada = [];
        $guard = 0;
        while (count($out) < $n && $guard++ < 80) {
            $e = $ejes[$rng->nextInt(0, count($ejes) - 1)];
            if (isset($usada[$e])) {
                continue;
            }
            $pool = $byEje[$e];
            $out[] = $pool[$rng->nextInt(0, count($pool) - 1)];
            $usada[$e] = true;
        }
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $ejes
     * @return array<string, string>
     */
    public static function pickEjes(array $ejes, RngService $rng): array
    {
        $out = [];
        foreach ($ejes as $e) {
            if (!is_array($e)) {
                continue;
            }
            $vals = is_array($e['valores'] ?? null) ? $e['valores'] : [];
            if ($vals === []) {
                continue;
            }
            $v = $vals[$rng->nextInt(0, count($vals) - 1)];
            $out[(string) $e['id']] = (string) ($v['id'] ?? '');
        }
        return $out;
    }

    /**
     * @param list<string> $ids
     * @param list<string> $usadas
     */
    public static function pickMania(array $ids, array &$usadas, RngService $rng): ?string
    {
        if ($ids === []) {
            return null;
        }
        if ($rng->nextFloat() > 0.72) {
            return null;
        }
        $libres = array_values(array_diff($ids, $usadas));
        if ($libres === []) {
            $libres = $ids;
        }
        $id = (string) $libres[$rng->nextInt(0, count($libres) - 1)];
        $usadas[] = $id;
        return $id;
    }

    /**
     * @param list<string> $afic
     * @param list<string> $gust
     * @param list<array{tipo:string,id:string}> $rech
     * @param list<string> $ras
     * @param array<string, string> $social
     * @param array<string, string> $afecto
     * @param list<string> $idsAf
     * @param list<array<string, mixed>> $rasgos
     * @return array<string, mixed>
     */
    private static function aplicarTwist(
        int $indice,
        array $afic,
        array $gust,
        array $rech,
        array $ras,
        array $social,
        array $afecto,
        array $idsAf,
        array $rasgos
    ): array {
        $nombre = null;
        $putAf = static function (array $afic, string $id) use ($idsAf): array {
            if (!in_array($id, $idsAf, true)) {
                return $afic;
            }
            $afic = array_values(array_diff($afic, [$id]));
            array_unshift($afic, $id);
            return array_slice($afic, 0, self::AFICIONES_CANON);
        };
        $putRas = static function (array $ras, string $id) use ($rasgos): array {
            $ejeDe = [];
            foreach ($rasgos as $r) {
                $ejeDe[(string) $r['id']] = (string) ($r['eje'] ?? '');
            }
            $ejeNew = $ejeDe[$id] ?? '';
            $out = [$id];
            foreach ($ras as $old) {
                if ($old === $id) {
                    continue;
                }
                if ($ejeNew !== '' && ($ejeDe[$old] ?? '') === $ejeNew) {
                    continue;
                }
                $out[] = $old;
                if (count($out) >= self::RASGOS_CANON) {
                    break;
                }
            }
            return $out;
        };
        $putRech = static function (array $rech, string $tipo, string $id): array {
            $rech[0] = ['tipo' => $tipo, 'id' => $id];
            $uniq = [];
            $out = [];
            foreach ($rech as $r) {
                $k = $r['tipo'] . ':' . $r['id'];
                if (isset($uniq[$k])) {
                    continue;
                }
                $uniq[$k] = true;
                $out[] = $r;
            }
            return $out;
        };
        $fillAf = static function (array $afic) use ($idsAf): array {
            $i = 0;
            while (count($afic) < self::AFICIONES_CANON && $i < count($idsAf)) {
                $id = $idsAf[$i++];
                if (!in_array($id, $afic, true)) {
                    $afic[] = $id;
                }
            }
            return array_slice($afic, 0, self::AFICIONES_CANON);
        };

        switch ($indice) {
            case 0:
                $nombre = 'timidez + karaoke';
                $ras = $putRas($ras, 'timido');
                $afic = $putAf($afic, 'karaoke');
                break;
            case 1:
                $nombre = 'sociabilidad + odia discotecas';
                $ras = $putRas($ras, 'sociable');
                $social['energia_social'] = 'alta';
                $social['umbral_ruido'] = 'bajo';
                $rech = $putRech($rech, 'destino', 'lug_discoteca');
                break;
            case 2:
                $nombre = 'deporte + odia gimnasio';
                $afic = $putAf($afic, 'correr');
                $rech = $putRech($rech, 'destino', 'lug_gimnasio');
                $afic = array_values(array_diff($afic, ['gimnasio']));
                break;
            case 3:
                $nombre = 'leer sin vivir en la biblioteca';
                $afic = $putAf($afic, 'leer');
                $rech = $putRech($rech, 'contexto', 'ctx_biblioteca_habitat');
                break;
            case 4:
                $nombre = 'cine de sala + odia terror';
                $afic = $putAf($afic, 'cine_sala');
                $gust = array_values(array_diff($gust, ['cine_terror']));
                if (!in_array('cine_comedia', $gust, true)) {
                    $gust[] = 'cine_comedia';
                }
                $rech = $putRech($rech, 'gusto', 'cine_terror');
                break;
            case 5:
                $nombre = 'música + odia bailar';
                $afic = $putAf($afic, 'escuchar_musica');
                $afic = array_values(array_diff($afic, ['baile']));
                $rech = $putRech($rech, 'actividad', 'act_bailar');
                break;
            case 6:
                $nombre = 'competitividad + lleva fatal perder';
                $ras = $putRas($ras, 'competitivo');
                $rech = $putRech($rech, 'contexto', 'ctx_perder_en_publico');
                break;
            case 7:
                $nombre = 'calidez + mucho espacio';
                $ras = $putRas($ras, 'calido');
                $afecto['espacio_personal'] = 'amplio';
                $afecto['ritmo_vinculo'] = 'lento';
                break;
            case 8:
                $nombre = 'leer + fiesta';
                $afic = $putAf($afic, 'leer');
                if (!in_array('fiesta', $afic, true) && !in_array('copas', $afic, true)) {
                    $afic[] = 'fiesta';
                }
                $social['energia_social'] = 'alta';
                $social['umbral_ruido'] = 'alto';
                break;
            case 9:
                $nombre = 'juegos + plantas';
                $afic = $putAf($afic, 'videojuegos_cozy');
                if (!in_array('plantas', $afic, true)) {
                    $afic[] = 'plantas';
                }
                break;
        }
        $afic = $fillAf($afic);

        return [
            'nombre' => $nombre,
            'aficiones' => array_slice(array_values(array_unique($afic)), 0, self::AFICIONES_CANON),
            'gustos' => $gust,
            'rechazos' => $rech,
            'rasgos' => $ras,
            'social' => $social,
            'afecto' => $afecto,
        ];
    }

    /**
     * @param array<string, mixed> $pack
     * @param list<string> $afic
     * @param list<string> $gust
     * @param list<array{tipo:string,id:string}> $rech
     * @param list<string> $ras
     * @param array<string, string> $social
     * @param array<string, string> $afecto
     * @return array<string, mixed>
     */
    private static function ajustarContradicciones(
        array $pack,
        array $afic,
        array $gust,
        array $rech,
        array $ras,
        array $social,
        array $afecto,
        int $presupuesto,
        RngService $rng
    ): array {
        $ficha = [
            'aficiones' => $afic,
            'gustos' => $gust,
            'rechazos' => $rech,
            'rasgos' => $ras,
            'social' => $social,
            'afecto' => $afecto,
        ];
        $tens = self::tensiones($ficha);
        while (count($tens) > $presupuesto && $rech !== []) {
            array_pop($rech);
            $ficha['rechazos'] = $rech;
            $tens = self::tensiones($ficha);
        }
        $guard = 0;
        while (count($tens) < $presupuesto && $guard++ < 3) {
            $iny = self::inyectarTension($afic, $rech, $ras, $social, $afecto, $rng);
            $rech = $iny['rechazos'];
            $ras = $iny['rasgos'];
            $social = $iny['social'];
            $afecto = $iny['afecto'];
            $ficha['rechazos'] = $rech;
            $ficha['rasgos'] = $ras;
            $ficha['social'] = $social;
            $ficha['afecto'] = $afecto;
            $tens = self::tensiones($ficha);
        }
        return [
            'aficiones' => $afic,
            'gustos' => $gust,
            'rechazos' => $rech,
            'rasgos' => $ras,
            'social' => $social,
            'afecto' => $afecto,
        ];
    }

    /**
     * @param list<string> $afic
     * @param list<array{tipo:string,id:string}> $rech
     * @param list<string> $ras
     * @param array<string, string> $social
     * @param array<string, string> $afecto
     * @return array<string, mixed>
     */
    private static function inyectarTension(
        array $afic,
        array $rech,
        array $ras,
        array $social,
        array $afecto,
        RngService $rng
    ): array {
        $ops = [];
        if (in_array('leer', $afic, true)) {
            $ops[] = 'leer_biblio';
        }
        if (in_array('escuchar_musica', $afic, true) && !in_array('baile', $afic, true)) {
            $ops[] = 'musica_baile';
        }
        if (in_array('cine_sala', $afic, true) || in_array('ver_en_casa', $afic, true)) {
            $ops[] = 'cine_terror';
        }
        if (in_array('ver_en_casa', $afic, true)) {
            $ops[] = 'sala_oscuras';
        }
        if (in_array('correr', $afic, true) || in_array('bici', $afic, true) || in_array('deporte_equipo', $afic, true)) {
            $ops[] = 'gym';
        }
        if (in_array('sociable', $ras, true)) {
            $ops[] = 'disco';
        }
        if (in_array('calido', $ras, true)) {
            $ops[] = 'espacio';
        }
        if (in_array('spa', $afic, true)) {
            $ops[] = 'spa_manos';
        }
        if (in_array('copas', $afic, true)) {
            $ops[] = 'alcohol';
        }
        if (in_array('competitivo', $ras, true)) {
            $ops[] = 'perder';
        }
        if (in_array('pasear', $afic, true) || in_array('picnic', $afic, true) || in_array('plantas', $afic, true) || in_array('perros', $afic, true)) {
            $ops[] = 'parque_lleno';
        }
        if (in_array('karaoke', $afic, true) || in_array('fiesta', $afic, true) || in_array('baile', $afic, true)) {
            $ops[] = 'timidez';
        }
        if (in_array('leer', $afic, true) && (in_array('fiesta', $afic, true) || in_array('copas', $afic, true))) {
            $ops[] = 'lectura_noche';
        }
        if ($ops === []) {
            $ops[] = 'energia_jaleo';
        }
        $pick = $ops[$rng->nextInt(0, count($ops) - 1)];
        $add = static function (array $rech, string $tipo, string $id): array {
            foreach ($rech as $r) {
                if (($r['tipo'] ?? '') === $tipo && ($r['id'] ?? '') === $id) {
                    return $rech;
                }
            }
            $rech[] = ['tipo' => $tipo, 'id' => $id];
            return $rech;
        };
        if ($pick === 'leer_biblio') {
            $rech = $add($rech, 'contexto', 'ctx_biblioteca_habitat');
        } elseif ($pick === 'musica_baile') {
            $rech = $add($rech, 'actividad', 'act_bailar');
        } elseif ($pick === 'cine_terror') {
            $rech = $add($rech, 'gusto', 'cine_terror');
        } elseif ($pick === 'sala_oscuras') {
            $rech = $add($rech, 'contexto', 'ctx_sala_a_oscuras');
        } elseif ($pick === 'gym') {
            $rech = $add($rech, 'destino', 'lug_gimnasio');
        } elseif ($pick === 'disco') {
            $rech = $add($rech, 'destino', 'lug_discoteca');
            $social['umbral_ruido'] = 'bajo';
        } elseif ($pick === 'espacio') {
            $afecto['espacio_personal'] = 'amplio';
        } elseif ($pick === 'spa_manos') {
            $rech = $add($rech, 'contexto', 'ctx_que_te_toquen');
        } elseif ($pick === 'alcohol') {
            $rech = $add($rech, 'contexto', 'ctx_alcohol');
        } elseif ($pick === 'perder') {
            $rech = $add($rech, 'contexto', 'ctx_perder_en_publico');
        } elseif ($pick === 'parque_lleno') {
            $rech = $add($rech, 'contexto', 'ctx_parque_lleno');
        } elseif ($pick === 'timidez') {
            $sinSocial = [];
            foreach ($ras as $id) {
                if (!in_array($id, ['directo', 'timido', 'reservado', 'observador', 'sociable', 'cotilla', 'discreto'], true)) {
                    $sinSocial[] = $id;
                }
            }
            $ras = array_slice(array_values(array_unique(array_merge(['timido'], $sinSocial))), 0, 3);
        } elseif ($pick === 'energia_jaleo' || $pick === 'lectura_noche') {
            $social['energia_social'] = 'alta';
            $social['umbral_ruido'] = 'bajo';
            $rech = $add($rech, 'destino', 'lug_discoteca');
        }
        return [
            'rechazos' => $rech,
            'rasgos' => $ras,
            'social' => $social,
            'afecto' => $afecto,
        ];
    }
}
