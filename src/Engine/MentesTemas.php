<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * MENTES iteración 2: temas elegibles, afinidad y copy.
 * El jugador elige; el motor evalúa sin regalar la respuesta correcta.
 */
final class MentesTemas
{
    /** @var array<string, string> */
    private const EMOJI = [
        'correr' => '🏃',
        'leer' => '📚',
        'cine' => '🎬',
        'bingo' => '🎱',
        'musica' => '🎵',
        'deporte' => '⚽',
        'cocina' => '🍳',
        'baile' => '💃',
        'cafe_social' => '☕',
        'copas' => '🍸',
        'senderismo' => '🥾',
        'manualidades' => '🧵',
        'videojuegos' => '🎮',
        'pasear' => '🚶',
        'escribir' => '✍️',
    ];

    /** @var array<string, string> */
    private const FRASE = [
        'correr' => 'Salir a correr',
        'leer' => 'Libros y lectura',
        'cine' => 'Cine',
        'bingo' => 'Bingo',
        'musica' => 'Música',
        'deporte' => 'Deporte',
        'cocina' => 'Cocina',
        'baile' => 'Baile',
        'cafe_social' => 'Tomar un café',
        'copas' => 'Unas copas',
        'senderismo' => 'Senderismo',
        'manualidades' => 'Manualidades',
        'videojuegos' => 'Videojuegos',
        'pasear' => 'Dar un paseo',
        'escribir' => 'Escribir',
    ];

    /**
     * @param array<string, mixed> $enc
     * @return list<array{id: string, etiqueta: string, rompe_hielo_id: string, interlocutor_id: string}>
     */
    public static function temasElegibles(
        array $partida,
        array $enc,
        string $rompeHielo,
        ?Catalog $catalog
    ): array {
        $rompeHielo = (string) $rompeHielo;
        $interlocutor = EncuentroIntervencion::interlocutorDe($enc, $rompeHielo);
        if ($rompeHielo === '' || $interlocutor === '' || $catalog === null) {
            return [];
        }
        $store = $catalog->store();
        $cal = CalibracionConfig::load($catalog->getRoot());
        $generales = CalibracionConfig::get($cal, 'mentes.temas_generales', ['cine', 'leer', 'musica']);
        $max = (int) CalibracionConfig::get($cal, 'mentes.max_temas', 6);
        if ($max < 2) {
            $max = 2;
        }

        /** @var array<string, true> $ids */
        $ids = [];
        foreach ($generales as $gid) {
            if (is_string($gid) && $gid !== '' && self::hobbyExiste($gid, $store)) {
                $ids[$gid] = true;
            }
        }
        foreach ([$rompeHielo, $interlocutor] as $rid) {
            $perfil = PerfilPartida::deOLegacy($partida, $rid, $catalog);
            foreach ($perfil['hobbies'] ?? [] as $hid) {
                if (!is_string($hid) || $hid === '') {
                    continue;
                }
                if (!DiscoveryReveal::jugadorSabeHobby($partida, $rid, $hid)) {
                    continue;
                }
                if (self::hobbyExiste($hid, $store)) {
                    $ids[$hid] = true;
                }
            }
        }
        foreach (ConocimientoNpc::hobbiesConocidos($partida, $rompeHielo, $interlocutor) as $hid) {
            if (DiscoveryReveal::jugadorSabeHobby($partida, $interlocutor, $hid)
                && self::hobbyExiste($hid, $store)) {
                $ids[$hid] = true;
            }
        }

        $orden = array_keys($ids);
        sort($orden, SORT_STRING);
        $out = [];
        foreach ($orden as $hid) {
            $out[] = [
                'id' => $hid,
                'etiqueta' => self::etiquetaTema($hid, $store),
                'rompe_hielo_id' => $rompeHielo,
                'interlocutor_id' => $interlocutor,
            ];
            if (count($out) >= $max) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $enc
     */
    public static function kickerRompeHielo(
        array $partida,
        array $enc,
        string $rompeHielo,
        RngService $rng
    ): string {
        $rompeHielo = (string) $rompeHielo;
        $interlocutor = EncuentroIntervencion::interlocutorDe($enc, $rompeHielo);
        $na = IdentidadPublica::nombre($partida, $rompeHielo);
        $nb = $interlocutor !== '' ? IdentidadPublica::nombre($partida, $interlocutor) : '';
        $plantillas = [
            'A ver, %s… ¿por dónde tiramos?',
            'Venga, %s… a ver si damos en el clavo.',
            '%s, esta es la tuya. ¿Por dónde entramos?',
            '¿Con qué nos la jugamos, %s?',
            'Venga, %s… elige bien.',
        ];
        if ($nb !== '') {
            $plantillas[] = 'A ver si encontramos un tema que le entre a ' . $nb . '…';
            $plantillas[] = $na . ', ¿qué le planteamos a ' . $nb . '?';
        }
        $idx = $rng->nextInt(0, count($plantillas) - 1);
        $tpl = $plantillas[$idx];
        if (strpos($tpl, '%s') !== false) {
            return sprintf($tpl, $na);
        }
        return $tpl;
    }

    public static function evaluarAfinidad(
        array $partida,
        string $interlocutor,
        string $temaId,
        Catalog $catalog
    ): string {
        $temaId = (string) $temaId;
        $interlocutor = (string) $interlocutor;
        if ($temaId === '' || $interlocutor === '') {
            return 'neutro';
        }
        $perfil = PerfilPartida::deOLegacy($partida, $interlocutor, $catalog);
        $prefs = is_array($perfil['preferencias'] ?? null) ? $perfil['preferencias'] : [];
        $neg = is_array($prefs['hobbies_neg'] ?? null) ? $prefs['hobbies_neg'] : [];
        if (in_array($temaId, $neg, true)) {
            return 'aversion';
        }
        $hobbies = is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : [];
        if (in_array($temaId, $hobbies, true)) {
            return 'afin';
        }
        $pos = is_array($prefs['hobbies_pos'] ?? null) ? $prefs['hobbies_pos'] : [];
        if (in_array($temaId, $pos, true)) {
            return 'afin';
        }
        $store = $catalog->store();
        $catT = self::categoriaDe($temaId, $store);
        if ($catT !== '') {
            foreach ($hobbies as $h) {
                if (!is_string($h) || $h === '') {
                    continue;
                }
                if (self::categoriaDe($h, $store) === $catT) {
                    return 'afin';
                }
            }
        }
        return 'neutro';
    }

    public static function etiquetaTema(string $hobbyId, CatalogStore $store): string
    {
        $hobbyId = (string) $hobbyId;
        $emoji = self::EMOJI[$hobbyId] ?? '💬';
        $frase = self::FRASE[$hobbyId] ?? null;
        if ($frase === null) {
            try {
                $frase = EtiquetaFicha::hobby($hobbyId, $store);
            } catch (\Throwable $ignored) {
                $frase = $hobbyId;
            }
        }
        return $emoji . ' ' . $frase;
    }

    public static function copyResultado(
        array $partida,
        string $rompeHielo,
        string $interlocutor,
        string $labelTema,
        string $afinidad,
        string $tono,
        RngService $rng
    ): string {
        $na = IdentidadPublica::nombre($partida, $rompeHielo);
        $nb = IdentidadPublica::nombre($partida, $interlocutor);
        $bancos = self::bancosCopy($na, $nb, $labelTema);
        $clave = $afinidad . '_' . $tono;
        if (!isset($bancos[$clave]) || $bancos[$clave] === []) {
            $clave = 'neutro_neutral';
        }
        $opts = $bancos[$clave];
        return $opts[$rng->nextInt(0, count($opts) - 1)];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function bancosCopy(string $na, string $nb, string $label): array
    {
        $t = $label;
        return [
            'afin_bien' => [
                $na . ' tira por ' . $t . ' y ' . $nb . ' entra enseguida al trapo. La charla coge ritmo.',
                $na . ' saca ' . $t . ' y a ' . $nb . ' le encaja. Conectan al momento.',
                'Buena elección: ' . $na . ' habla de ' . $t . ' y ' . $nb . ' se anima.',
            ],
            'afin_neutral' => [
                $na . ' plantea ' . $t . ' y ' . $nb . ' le sigue la corriente con interés.',
                $na . ' saca ' . $t . ' y a ' . $nb . ' le entra. La conversación avanza.',
            ],
            'afin_mal' => [
                $na . ' insiste con ' . $t . ', pero hoy a ' . $nb . ' no le acaba de cuajar.',
            ],
            'neutro_bien' => [
                $na . ' prueba con ' . $t . ' y ' . $nb . ' le sigue la conversación sin problema.',
                $na . ' saca ' . $t . ' y la charla fluye, aunque sin chispa especial.',
            ],
            'neutro_neutral' => [
                $na . ' prueba hablando de ' . $t . '. ' . $nb . ' le sigue la conversación, aunque tampoco parece su tema favorito.',
                $na . ' menciona ' . $t . ' y ' . $nb . ' responde con educación. Nada del otro mundo.',
                $na . ' plantea ' . $t . ' y la charla sigue, sin más sobresaltos.',
            ],
            'neutro_mal' => [
                $na . ' saca ' . $t . ' y la conversación se queda un poco plana.',
            ],
            'aversion_bien' => [
                $na . ' toca ' . $t . ' y ' . $nb . ' lo tolera mejor de lo esperado.',
            ],
            'aversion_neutral' => [
                $na . ' saca ' . $t . ' y ' . $nb . ' no parece muy convencido.',
            ],
            'aversion_mal' => [
                $na . ' saca el tema de ' . $t . ' y a ' . $nb . ' no parece hacerle demasiada gracia. La charla se enfría un poco.',
                $na . ' tira por ' . $t . ' y ' . $nb . ' se cierra un poco. Mal momento.',
            ],
        ];
    }

    private static function hobbyExiste(string $id, CatalogStore $store): bool
    {
        return $store->hobby($id) !== null || $store->item('hobbies', $id) !== null;
    }

    private static function categoriaDe(string $hobbyId, CatalogStore $store): string
    {
        $item = $store->hobby($hobbyId);
        if (!is_array($item)) {
            return '';
        }
        return (string) ($item['categoria'] ?? '');
    }
}
