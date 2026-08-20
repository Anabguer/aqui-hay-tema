<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Lab de catálogos candidatos. No toca P001–P200 ni producción.
 */
final class SimuladorCatalogosPersonalidad
{
    private const NOMBRES = [
        'Nuria', 'Iker', 'Belén', 'Tomás', 'Fátima', 'Gorka', 'Inés', 'Pablo',
        'Vera', 'Héctor', 'Lidia', 'Omar', 'Teresa', 'Quique', 'Bruno', 'Elena',
        'Iván', 'Pilar', 'Mauro', 'Claudia', 'Andrés', 'Noa', 'Félix', 'Amaia',
        'Rosa', 'Jaime', 'Nerea', 'Unai', 'Lola', 'Sergio',
    ];
    private const APELLIDOS = [
        'Valseca', 'Larrabe', 'Cifuentes', 'Baroja', 'Benjumea', 'Otxoa', 'Salcedo',
        'Trillo', 'Zamora', 'Ariza', 'Peñate', 'Hinojosa', 'Requejo', 'Aldama',
        'Corral', 'Urrutia', 'Sampedro', 'Ballester', 'Quiroga', 'Noguera',
        'Ferrer', 'Olmo', 'Pascual', 'Rivas', 'Cano', 'Vidal', 'Soler', 'Prieto',
        'Navas', 'Ortega',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function ejecutar(string $projectRoot, int $nMuestras = 30, string $seed = 'lab-catalogos-30'): array
    {
        $pack = CatalogosCandidatos::cargar($projectRoot);
        $rng = new RngService($seed);
        $muestras = [];
        $maniasUsadas = [];
        for ($i = 0; $i < $nMuestras; $i++) {
            $muestras[] = self::unaPersona($pack, $rng, $i, $maniasUsadas, $nMuestras);
        }
        $audit = self::auditar($pack, $muestras);
        return [
            '_provisional' => true,
            '_canon' => false,
            '_nota' => 'Muestra de auditoría V2. NO son P001–P200. NO se escriben fichas canónicas.',
            'conteos' => $audit['conteos'],
            'auditoria' => $audit,
            'muestras' => $muestras,
            'fichas_humanas' => array_map([self::class, 'fichaHumana'], $muestras),
        ];
    }

    /**
     * @param array<string, mixed> $pack
     * @param list<string> $maniasUsadas
     * @return array<string, mixed>
     */
    public static function unaPersona(array $pack, RngService $rng, int $indice, array &$maniasUsadas, int $nMuestras = 30): array
    {
        $modo = ($indice < 10) ? 'showcase' : 'produccion';
        $gen = GeneradorFichaCandidata::una($pack, $rng, $maniasUsadas, [
            'modo' => $modo,
            'indice' => $indice,
        ]);
        $nombre = self::NOMBRES[$indice % count(self::NOMBRES)];
        $ape = self::APELLIDOS[$indice % count(self::APELLIDOS)];
        $edad = 22 + (($indice * 7 + $rng->nextInt(0, 5)) % 47);
        $afic = $gen['aficiones'];
        $ras = $gen['rasgos'];

        return [
            'id_muestra' => 'mues_' . str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT),
            'nombre' => $nombre . ' ' . $ape,
            'edad' => $edad,
            'twist' => $gen['twist'] ?? null,
            'modo_generacion' => $modo,
            'aficiones' => $afic,
            'gustos' => $gen['gustos'],
            'rechazos' => $gen['rechazos'],
            'rasgos' => $ras,
            'social' => $gen['social'],
            'afecto' => $gen['afecto'],
            'mania' => $gen['mania'],
            'tensiones' => $gen['tensiones'] ?? [],
            'visible_al_llegar' => [
                'aficion' => $afic[0] ?? null,
                'rasgo' => $ras[0] ?? null,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    private static function ids(array $items): array
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
    private static function pickAficiones(array $items, RngService $rng, int $n): array
    {
        $byFam = [];
        foreach ($items as $it) {
            $byFam[(string) ($it['familia'] ?? '?')][] = (string) $it['id'];
        }
        $fams = array_keys($byFam);
        $out = [];
        $famCount = [];
        $guard = 0;
        while (count($out) < $n && $guard++ < 40) {
            $fam = $fams[$rng->nextInt(0, count($fams) - 1)];
            if ((int) ($famCount[$fam] ?? 0) >= 2) {
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
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $gustos
     * @param list<string> $afic
     * @param list<array<string, mixed>> $aficiones
     * @return list<string>
     */
    private static function pickGustos(array $gustos, array $afic, array $aficiones, RngService $rng, int $n): array
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
    private static function pickRechazos(array $cat, array $afic, array $gust, RngService $rng): array
    {
        $n = $rng->nextInt(1, 2);
        $out = [];
        $guard = 0;
        while (count($out) < $n && $guard++ < 30) {
            $modo = $rng->nextInt(0, 2);
            if ($modo === 0) {
                $dest = array_keys(CatalogosCandidatos::DESTINOS);
                $id = $dest[$rng->nextInt(0, count($dest) - 1)];
                $row = ['tipo' => 'destino', 'id' => $id];
            } elseif ($modo === 1 && $gust !== []) {
                $id = $gust[0];
                // rechazo de un gusto de su familia pero no el que tiene
                $row = ['tipo' => 'gusto', 'id' => $id];
                // skip: would contradict same gusto
                continue;
            } else {
                $it = $cat[$rng->nextInt(0, count($cat) - 1)];
                $cid = (string) $it['id'];
                $contra = is_array($it['contra'] ?? null) ? $it['contra'] : [];
                $choque = false;
                foreach ($contra as $c) {
                    if (in_array((string) $c, $afic, true) || in_array((string) $c, $gust, true)) {
                        $choque = true;
                    }
                }
                if ($choque && $rng->nextInt(0, 2) !== 0) {
                    // 1/3: permitir choque suave (música vs bailar) si contra no es la afición misma
                    $misma = in_array($cid, $afic, true);
                    if ($misma) {
                        continue;
                    }
                } elseif ($choque) {
                    continue;
                }
                $row = ['tipo' => (string) ($it['tipo'] ?? 'contexto'), 'id' => $cid];
            }
            $key = $row['tipo'] . ':' . $row['id'];
            foreach ($out as $o) {
                if ($o['tipo'] . ':' . $o['id'] === $key) {
                    continue 2;
                }
            }
            if ($row['tipo'] === 'aficion' && in_array($row['id'], $afic, true)) {
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
    private static function pickRasgos(array $rasgos, RngService $rng, int $n): array
    {
        $byEje = [];
        foreach ($rasgos as $r) {
            $byEje[(string) ($r['eje'] ?? '?')][] = (string) $r['id'];
        }
        $ejes = array_keys($byEje);
        $out = [];
        $usada = [];
        $guard = 0;
        while (count($out) < $n && $guard++ < 20) {
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
    private static function pickEjes(array $ejes, RngService $rng): array
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
     * @param list<string> $afic
     * @param list<string> $gust
     * @param list<array{tipo:string,id:string}> $rech
     * @param list<string> $ras
     * @param array<string, string> $social
     * @param array<string, string> $afecto
     * @param list<string> $idsAf
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
        array $idsAf
    ): array {
        $nombre = null;
        $putAf = static function (array $afic, string $id) use ($idsAf): array {
            if (!in_array($id, $idsAf, true)) {
                return $afic;
            }
            $afic = array_values(array_diff($afic, [$id]));
            array_unshift($afic, $id);
            if (count($afic) > 3) {
                $afic = array_slice($afic, 0, 3);
            }
            return $afic;
        };
        $putRas = static function (array $ras, string $id): array {
            $ras[0] = $id;
            return array_values(array_unique($ras));
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

        switch ($indice) {
            case 0:
                $nombre = 'tímida + karaoke';
                $ras = $putRas($ras, 'timido');
                $afic = $putAf($afic, 'karaoke');
                break;
            case 1:
                $nombre = 'sociable + odia discotecas';
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
                $nombre = 'lectora que no quiere vivir en la biblioteca';
                $afic = $putAf($afic, 'leer');
                $rech = $putRech($rech, 'contexto', 'ctx_biblioteca_habitat');
                break;
            case 4:
                $nombre = 'cine + odia terror';
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
                $nombre = 'competitiva + lleva fatal perder';
                $ras = $putRas($ras, 'competitivo');
                $rech = $putRech($rech, 'contexto', 'ctx_perder_en_publico');
                break;
            case 7:
                $nombre = 'cálida + mucho espacio';
                $ras = $putRas($ras, 'calido');
                $afecto['espacio_personal'] = 'amplio';
                $afecto['ritmo_vinculo'] = 'lento';
                break;
            case 8:
                $nombre = 'lectora + fiestera';
                $afic = $putAf($afic, 'leer');
                if (!in_array('fiesta', $afic, true) && !in_array('copas', $afic, true)) {
                    $afic[] = 'fiesta';
                    if (count($afic) > 3) {
                        array_splice($afic, 2, 1);
                    }
                }
                $social['energia_social'] = 'alta';
                $social['umbral_ruido'] = 'alto';
                break;
            case 9:
                $nombre = 'juegos + naturaleza';
                $afic = $putAf($afic, 'videojuegos_cozy');
                if (!in_array('plantas', $afic, true) && !in_array('pasear', $afic, true)) {
                    $afic[] = 'plantas';
                    if (count($afic) > 3) {
                        array_splice($afic, 2, 1);
                    }
                }
                break;
        }

        return [
            'nombre' => $nombre,
            'aficiones' => $afic,
            'gustos' => $gust,
            'rechazos' => $rech,
            'rasgos' => $ras,
            'social' => $social,
            'afecto' => $afecto,
        ];
    }

    /**
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    public static function fichaHumana(array $p): array
    {
        return [
            'id_muestra' => $p['id_muestra'],
            'quien' => $p['nombre'] . ', ' . $p['edad'] . ' años',
            'si_parece' => $p['twist'],
            'al_llegar_celestine_sabe' => [
                'aficion' => $p['visible_al_llegar']['aficion'],
                'rasgo' => $p['visible_al_llegar']['rasgo'],
            ],
            'hace' => $p['aficiones'],
            'le_tira' => $p['gustos'],
            'no_traga' => $p['rechazos'],
            'caracter' => $p['rasgos'],
            'tensiones' => $p['tensiones'] ?? [],
            'con_la_gente' => $p['social'],
            'en_lo_afectivo' => $p['afecto'],
            'mania' => $p['mania'],
        ];
    }

    /**
     * @param array<string, mixed> $pack
     * @param list<array<string, mixed>> $muestras
     * @return array<string, mixed>
     */
    public static function auditar(array $pack, array $muestras): array
    {
        $af = CatalogosCandidatos::items($pack, 'aficiones');
        $gu = CatalogosCandidatos::items($pack, 'gustos');
        $re = CatalogosCandidatos::items($pack, 'rechazos');
        $ra = CatalogosCandidatos::items($pack, 'rasgos');
        $ma = CatalogosCandidatos::items($pack, 'manias');

        $cobAf = [];
        $cobRe = [];
        foreach (array_keys(CatalogosCandidatos::DESTINOS) as $d) {
            $cobAf[$d] = [];
            $cobRe[$d] = [];
        }
        foreach ($af as $it) {
            foreach ($it['lugar_ids'] ?? [] as $l) {
                $l = (string) $l;
                if (isset($cobAf[$l])) {
                    $cobAf[$l][] = (string) $it['id'];
                }
            }
        }
        foreach ($gu as $it) {
            foreach ($it['lugar_ids'] ?? [] as $l) {
                $l = (string) $l;
                if (isset($cobAf[$l]) && !in_array((string) $it['id'], $cobAf[$l], true)) {
                    $cobAf[$l][] = 'g:' . $it['id'];
                }
            }
        }
        foreach ($re as $it) {
            foreach ($it['lugar_ids'] ?? [] as $l) {
                $l = (string) $l;
                if (isset($cobRe[$l])) {
                    $cobRe[$l][] = (string) $it['id'];
                }
            }
        }

        $huecosAf = [];
        $huecosRe = [];
        foreach (CatalogosCandidatos::DESTINOS as $id => $nom) {
            if (count($cobAf[$id]) < 2) {
                $huecosAf[$id] = $nom;
            }
            if ($cobRe[$id] === []) {
                $huecosRe[$id] = $nom;
            }
        }

        $sinUso = [];
        foreach (array_merge($af, $gu, $re, $ra, $ma) as $it) {
            $usos = $it['usos'] ?? [];
            if (!is_array($usos) || $usos === []) {
                $sinUso[] = $it['id'] ?? '?';
            }
        }

        $dupEt = [];
        $seen = [];
        foreach (array_merge($af, $gu, $ra, $ma) as $it) {
            $e = self::min((string) ($it['etiqueta'] ?? ''));
            if ($e === '') {
                continue;
            }
            if (isset($seen[$e])) {
                $dupEt[] = [$seen[$e], $it['id']];
            } else {
                $seen[$e] = $it['id'];
            }
        }

        $pares = [];
        for ($i = 0; $i < count($muestras); $i++) {
            for ($j = $i + 1; $j < count($muestras); $j++) {
                $sim = self::similitud($muestras[$i], $muestras[$j]);
                $pares[] = [
                    'a' => $muestras[$i]['id_muestra'],
                    'b' => $muestras[$j]['id_muestra'],
                    'nombres' => [$muestras[$i]['nombre'], $muestras[$j]['nombre']],
                    'similitud' => $sim,
                ];
            }
        }
        usort($pares, static function ($x, $y) {
            return $y['similitud'] <=> $x['similitud'];
        });
        $clones = array_values(array_filter($pares, static function ($p) {
            return $p['similitud'] >= 0.55;
        }));

        $familias = [];
        foreach ($af as $it) {
            $f = (string) ($it['familia'] ?? '?');
            $familias[$f] = (int) ($familias[$f] ?? 0) + 1;
        }

        $n33 = 0;
        $maniasCount = [];
        $frasesCount = [];
        $tensionesHist = ['0' => 0, '1' => 0, '2plus' => 0];
        $ejesSoc = [];
        $ejesAfe = [];
        $famMuestra = [];
        foreach ($muestras as $p) {
            if (GeneradorFichaCandidata::cardinalidadOk($p)) {
                $n33++;
            }
            $mid = (string) ($p['mania'] ?? '');
            if ($mid !== '') {
                $maniasCount[$mid] = (int) ($maniasCount[$mid] ?? 0) + 1;
            }
            $nt = count($p['tensiones'] ?? []);
            if ($nt <= 0) {
                $tensionesHist['0']++;
            } elseif ($nt === 1) {
                $tensionesHist['1']++;
            } else {
                $tensionesHist['2plus']++;
            }
            foreach ($p['social'] ?? [] as $k => $v) {
                $key = $k . ':' . $v;
                $ejesSoc[$key] = (int) ($ejesSoc[$key] ?? 0) + 1;
            }
            foreach ($p['afecto'] ?? [] as $k => $v) {
                $key = $k . ':' . $v;
                $ejesAfe[$key] = (int) ($ejesAfe[$key] ?? 0) + 1;
            }
            foreach ($p['aficiones'] ?? [] as $aid) {
                $it = CatalogosCandidatos::porId($pack, 'aficiones', (string) $aid);
                $f = (string) ($it['familia'] ?? '?');
                $famMuestra[$f] = (int) ($famMuestra[$f] ?? 0) + 1;
            }
            foreach (['aficiones' => $p['aficiones'] ?? [], 'rasgos' => $p['rasgos'] ?? [], 'gustos' => $p['gustos'] ?? []] as $cat => $ids) {
                foreach ($ids as $id) {
                    $it = CatalogosCandidatos::porId($pack, $cat, (string) $id);
                    if ($it === null) {
                        continue;
                    }
                    $slot = abs(crc32($p['id_muestra'] . ':' . $id)) % 8;
                    $fr = CatalogosCandidatos::frase($it, 'libreta', $slot);
                    if ($fr !== '') {
                        $frasesCount[$fr] = (int) ($frasesCount[$fr] ?? 0) + 1;
                    }
                }
            }
            if (!empty($p['mania'])) {
                $it = CatalogosCandidatos::porId($pack, 'manias', (string) $p['mania']);
                if ($it) {
                    $fr = CatalogosCandidatos::frase($it, 'libreta', 0);
                    if ($fr !== '') {
                        $frasesCount[$fr] = (int) ($frasesCount[$fr] ?? 0) + 1;
                    }
                }
            }
        }
        $maniasRep = [];
        foreach ($maniasCount as $id => $c) {
            if ($c > 1) {
                $maniasRep[] = ['id' => $id, 'n' => $c];
            }
        }
        $frasesRep = [];
        foreach ($frasesCount as $fr => $c) {
            if ($c > 1) {
                $frasesRep[] = ['frase' => $fr, 'n' => $c];
            }
        }
        usort($frasesRep, static function ($a, $b) {
            return $b['n'] <=> $a['n'];
        });

        $minDesc = ['aficiones' => 6, 'rasgos' => 6, 'gustos' => 4, 'rechazos' => 4, 'manias' => 2];
        $copyCorta = [];
        foreach (['aficiones' => $af, 'rasgos' => $ra, 'gustos' => $gu, 'rechazos' => $re, 'manias' => $ma] as $cat => $items) {
            $min = $minDesc[$cat];
            foreach ($items as $it) {
                $n = 0;
                foreach ($it['descubrimientos'] ?? [] as $fr) {
                    if (is_string($fr) && $fr !== '') {
                        $n++;
                    }
                }
                if ($n < $min) {
                    $copyCorta[] = $cat . ':' . ($it['id'] ?? '?') . '=' . $n;
                }
            }
        }

        return [
            'conteos' => [
                'aficiones' => count($af),
                'gustos' => count($gu),
                'rechazos_no_destino' => count($re),
                'rasgos' => count($ra),
                'manias' => count($ma),
                'ejes_sociales' => count($pack['social']['ejes'] ?? []),
                'ejes_afecto' => count($pack['afecto']['ejes'] ?? []),
                'destinos' => count(CatalogosCandidatos::DESTINOS),
            ],
            'familias_aficion' => $familias,
            'cobertura_afinidad_por_destino' => $cobAf,
            'cobertura_rechazo_por_destino' => $cobRe,
            'destinos_con_menos_de_2_afinidades' => $huecosAf,
            'destinos_sin_rechazo_especifico' => $huecosRe,
            'items_sin_usos' => $sinUso,
            'etiquetas_duplicadas' => $dupEt,
            'pares_mas_parecidos' => array_slice($pares, 0, 5),
            'avisos_clon' => $clones,
            'capacidad_200' => self::capacidad200(count($af), count($ra), count($gu)),
            'pct_exactamente_3_3' => count($muestras) > 0 ? round(100.0 * $n33 / count($muestras), 1) : 0,
            'manias_repetidas_en_muestra' => $maniasRep,
            'frases_libreta_repetidas' => array_slice($frasesRep, 0, 12),
            'distribucion_contradicciones' => $tensionesHist,
            'cobertura_familias_en_muestra' => $famMuestra,
            'distribucion_ejes_social' => $ejesSoc,
            'distribucion_ejes_afecto' => $ejesAfe,
            'copy_por_debajo_del_minimo' => $copyCorta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function capacidad200(int $af, int $ra, int $gu): array
    {
        $c3 = static function (int $n): int {
            if ($n < 3) {
                return 0;
            }
            return intdiv($n * ($n - 1) * ($n - 2), 6);
        };
        return [
            'combos_3_aficiones' => $c3($af),
            'combos_3_rasgos' => $c3($ra),
            'nota' => 'Con 3 aficiones y 3 rasgos de ejes distintos, 200 residentes no agotan el espacio. El riesgo de clones es el generador estereotipado, no el tamaño.',
        ];
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    public static function similitud(array $a, array $b): float
    {
        $set = static function (array $p): array {
            $s = array_merge($p['aficiones'] ?? [], $p['rasgos'] ?? [], $p['gustos'] ?? []);
            foreach ($p['rechazos'] ?? [] as $r) {
                if (is_array($r)) {
                    $s[] = ($r['tipo'] ?? '') . ':' . ($r['id'] ?? '');
                }
            }
            foreach ($p['social'] ?? [] as $k => $v) {
                $s[] = 's:' . $k . ':' . $v;
            }
            return array_values(array_unique($s));
        };
        $sa = $set($a);
        $sb = $set($b);
        $inter = array_intersect($sa, $sb);
        $uni = array_unique(array_merge($sa, $sb));
        if ($uni === []) {
            return 0.0;
        }
        return round(count($inter) / count($uni), 3);
    }

    private static function min(string $s): string
    {
        if (function_exists('mb_strtolower')) {
            return \mb_strtolower($s, 'UTF-8');
        }
        return strtolower($s);
    }

    /**
     * @param array<string, mixed> $lab
     */
    public static function markdownMuestras(array $lab, array $pack): string
    {
        $out = "# 30 residentes de muestra (auditoría V2, no canon)\n\n";
        $out .= "No son P001–P200. Seed `lab-catalogos-30`. Celestine, al llegar, solo ve 1 afición y 1 rasgo.\n";
        $out .= "Contrato: exactamente 3 aficiones + 3 rasgos. Etiquetas de rasgo en forma invariable (Timidez, no Tímido).\n";
        $out .= "Las 10 primeras fichas son *showcase* de tensiones conocidas; el resto usa presupuesto de contradicción de producción.\n\n";
        foreach ($lab['muestras'] as $p) {
            $out .= self::bloqueHumano($p, $pack) . "\n";
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $p
     * @param array<string, mixed> $pack
     */
    public static function bloqueHumano(array $p, array $pack): string
    {
        $eti = static function (string $cat, string $id) use ($pack): string {
            $it = CatalogosCandidatos::porId($pack, $cat, $id);
            return $it ? (string) ($it['etiqueta'] ?? $id) : $id;
        };
        $frase = static function (string $cat, string $id, string $mues) use ($pack): string {
            $it = CatalogosCandidatos::porId($pack, $cat, $id);
            if (!$it) {
                return $id;
            }
            $slot = abs(crc32($mues . ':' . $id)) % 8;
            return CatalogosCandidatos::frase($it, 'libreta', $slot);
        };
        $rechTxt = [];
        foreach ($p['rechazos'] as $r) {
            $tipo = $r['tipo'];
            $id = $r['id'];
            if ($tipo === 'destino') {
                $rechTxt[] = CatalogosCandidatos::DESTINOS[$id] ?? $id;
            } elseif ($tipo === 'gusto') {
                $rechTxt[] = $eti('gustos', $id);
            } else {
                $it = CatalogosCandidatos::porId($pack, 'rechazos', $id);
                $rechTxt[] = $it ? (string) ($it['etiqueta'] ?? $id) : $id;
            }
        }
        $hace = [];
        foreach ($p['aficiones'] as $id) {
            $hace[] = $eti('aficiones', $id);
        }
        $tira = [];
        foreach ($p['gustos'] as $id) {
            $tira[] = $eti('gustos', $id);
        }
        $car = [];
        foreach ($p['rasgos'] as $id) {
            $car[] = $eti('rasgos', $id);
        }
        $soc = [];
        foreach ($p['social'] as $k => $v) {
            $slot = abs(crc32($p['id_muestra'] . ':s:' . $k)) % 8;
            $soc[] = CatalogosCandidatos::fraseEje($pack, 'social', (string) $k, (string) $v, $slot);
        }
        $af = [];
        foreach ($p['afecto'] as $k => $v) {
            $slot = abs(crc32($p['id_muestra'] . ':a:' . $k)) % 8;
            $af[] = CatalogosCandidatos::fraseEje($pack, 'afecto', (string) $k, (string) $v, $slot);
        }
        $visA = $p['visible_al_llegar']['aficion'] ?? '';
        $visR = $p['visible_al_llegar']['rasgo'] ?? '';
        $mania = $p['mania'] ? $eti('manias', (string) $p['mania']) : '—';
        $twist = $p['twist'] ? " _(" . $p['twist'] . ")_" : '';

        $md = '## ' . $p['nombre'] . ', ' . $p['edad'] . $twist . "\n";
        $md .= '**Al llegar, Celestine solo tiene:** ' . $frase('aficiones', (string) $visA, (string) $p['id_muestra']);
        $md .= ' / ' . $frase('rasgos', (string) $visR, (string) $p['id_muestra']) . "\n\n";
        $md .= '- **Hace:** ' . implode(', ', $hace) . "\n";
        $md .= '- **Le tira:** ' . implode(', ', $tira) . "\n";
        $md .= '- **No traga:** ' . implode(', ', $rechTxt) . "\n";
        $md .= '- **De carácter:** ' . implode(', ', $car) . "\n";
        $md .= '- **Con la gente:** ' . implode('; ', $soc) . "\n";
        $md .= '- **En lo afectivo:** ' . implode('; ', $af) . "\n";
        $md .= '- **Manía:** ' . $mania . "\n";
        if (!empty($p['tensiones'])) {
            $md .= '- **Tensión (lab):** ' . implode(', ', $p['tensiones']) . "\n";
        }
        if ($visA !== '' && count($p['aficiones']) > 1) {
            $oculta = $eti('aficiones', (string) $p['aficiones'][1]);
            $md .= '- **Aún no sabríamos, por ejemplo:** que también ' . self::min($oculta) . ".\n";
        }
        return $md;
    }
}
