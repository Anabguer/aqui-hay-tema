<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Vista de ficha para play: sin IDs técnicos ni hobbies duplicados. */
final class FichaPlayVista
{
    /**
     * @param array<string, mixed> $ficha
     * @return array<string, mixed>
     */
    public static function de(array $ficha, CatalogStore $store): array
    {
        $campos = is_array($ficha['discovery']['campos'] ?? null) ? $ficha['discovery']['campos'] : [];
        $hobbies = [];
        $hobbyIds = [];
        $rasgos = [];
        $rasgoIds = [];
        foreach (['vida.hobby_principal', 'vida.hobbies_secundarios'] as $k) {
            $row = $campos[$k] ?? null;
            if (!is_array($row) || ($row['visible_jugador'] ?? true) === false) {
                continue;
            }
            $val = $row['valor'] ?? null;
            $ids = is_array($val) ? $val : ($val !== null && $val !== '' ? [$val] : []);
            foreach ($ids as $id) {
                if (!is_string($id) || $id === '') {
                    continue;
                }
                if (!in_array($id, $hobbyIds, true)) {
                    $hobbyIds[] = $id;
                    $hobbies[] = EtiquetaFicha::hobby($id, $store);
                }
            }
        }

        $rowR = $campos['vida.rasgos_publicos'] ?? null;
        if (is_array($rowR) && ($rowR['visible_jugador'] ?? true) !== false) {
            $val = $rowR['valor'] ?? [];
            if (is_array($val)) {
                foreach ($val as $id) {
                    if (is_string($id) && $id !== '') {
                        if (!in_array($id, $rasgoIds, true)) {
                            $rasgoIds[] = $id;
                            $rasgos[] = EtiquetaFicha::rasgo($id, $store);
                        }
                    }
                }
            }
        }

        $ocId = $ficha['trabajo']['ocupacion'] ?? null;
        $genero = $ficha['identidad']['genero'] ?? null;
        $trabajoVista = is_array($ficha['trabajo']['vista'] ?? null)
            ? $ficha['trabajo']['vista']
            : TrabajoHorario::paraFicha(
                [
                    'ocupacion' => $ficha['trabajo']['ocupacion'] ?? null,
                    'trabajo_dias' => $ficha['trabajo']['dias'] ?? null,
                    'trabajo_hora_inicio' => $ficha['trabajo']['hora_inicio'] ?? null,
                    'trabajo_hora_fin' => $ficha['trabajo']['hora_fin'] ?? null,
                ],
                is_string($genero) ? $genero : null,
                $store
            );
        $ocupacion = null;
        if (!($trabajoVista['desempleado'] ?? false)) {
            $ocupacion = is_string($trabajoVista['linea_principal'] ?? null) && $trabajoVista['linea_principal'] !== ''
                ? $trabajoVista['linea_principal']
                : (is_string($ocId) && $ocId !== '' ? EtiquetaFicha::ocupacion($ocId, $store) : null);
        }

        $pistas = [];
        $nombre = (string) ($ficha['identidad']['nombre'] ?? '');
        foreach ($ficha['descubrimientos'] ?? [] as $d) {
            if (!is_array($d)) {
                continue;
            }
            $campo = (string) ($d['campo'] ?? '');
            if ($campo === '' || (strpos($campo, 'gusto_') !== 0 && strpos($campo, 'rechazo_') !== 0)) {
                continue;
            }
            $txt = CopyDescubrimiento::texto($nombre, $campo, $d['valor'] ?? null, $store);
            if (is_string($txt) && $txt !== '') {
                $pistas[] = $txt;
            }
        }

        $rasgosSlots = self::tresSlotsConId($rasgoIds, $rasgos);
        $hobbiesSlots = self::tresSlotsHobby($hobbyIds, $hobbies, $store);
        $gustaGente = self::dosSlotsGente($ficha, $store, 'gusto');
        $noGustaGente = self::dosSlotsGente($ficha, $store, 'rechazo');

        return [
            'nombre' => $ficha['identidad']['nombre'] ?? '',
            'edad' => $ficha['identidad']['edad'] ?? null,
            'genero' => $ficha['identidad']['genero'] ?? null,
            'ocupacion' => $ocupacion,
            'trabajo' => $trabajoVista,
            'gusta' => $hobbies,
            'hobbies_slots' => $hobbiesSlots,
            'manera_de_ser' => $rasgos,
            'rasgos_slots' => $rasgosSlots,
            'gusta_en_gente' => $gustaGente,
            'no_gusta_en_gente' => $noGustaGente,
            'pistas' => $pistas,
            'aprecio_celeste' => is_array($ficha['aprecio_celeste'] ?? null) ? $ficha['aprecio_celeste'] : null,
            'estado_animo' => $ficha['estado_emocional']['id'] ?? 'neutro',
            'pista_estado' => EmocionalNarrativa::pistaFicha(
                is_array($ficha['estado_emocional'] ?? null) ? $ficha['estado_emocional'] : []
            ),
        ];
    }

    /**
     * @param list<string> $ids
     * @param list<string> $textos
     * @return list<array{descubierto: bool, id: string|null, texto: string|null}>
     */
    private static function tresSlotsConId(array $ids, array $textos): array
    {
        $slots = [];
        for ($i = 0; $i < 3; $i++) {
            $id = $ids[$i] ?? null;
            $txt = $textos[$i] ?? null;
            $slots[] = [
                'descubierto' => is_string($id) && $id !== '',
                'id' => is_string($id) && $id !== '' ? $id : null,
                'texto' => is_string($txt) && $txt !== '' ? $txt : null,
            ];
        }
        return $slots;
    }

    /**
     * @param list<string> $ids
     * @param list<string> $textos
     * @return list<array{descubierto: bool, id: string|null, texto: string|null, pista: string|null}>
     */
    private static function tresSlotsHobby(array $ids, array $textos, CatalogStore $store): array
    {
        $slots = [];
        for ($i = 0; $i < 3; $i++) {
            $id = $ids[$i] ?? null;
            $txt = $textos[$i] ?? null;
            $descubierto = is_string($id) && $id !== '';
            $slots[] = [
                'descubierto' => $descubierto,
                'id' => $descubierto ? $id : null,
                'texto' => is_string($txt) && $txt !== '' ? $txt : null,
                'pista' => $descubierto ? HobbyAccionable::pista($id, $store) : null,
            ];
        }
        return $slots;
    }

    /**
     * @param list<string> $descubiertos
     * @return list<array{descubierto: bool, texto: string|null}>
     */
    private static function tresSlotsTexto(array $descubiertos): array
    {
        $slots = [];
        for ($i = 0; $i < 3; $i++) {
            $txt = $descubiertos[$i] ?? null;
            $slots[] = [
                'descubierto' => is_string($txt) && $txt !== '',
                'texto' => is_string($txt) && $txt !== '' ? $txt : null,
            ];
        }
        return $slots;
    }

    /**
     * Preferencias de personalidad en la gente (solo gusto/rechazo_personalidad descubiertos).
     *
     * @return list<array{descubierto: bool, texto: string|null}>
     */
    private static function dosSlotsGente(array $ficha, CatalogStore $store, string $tipo): array
    {
        $prefix = $tipo === 'gusto' ? 'gusto_personalidad:' : 'rechazo_personalidad:';
        $prefKey = $tipo === 'gusto' ? 'personalidad_pos' : 'personalidad_neg';

        $discovered = [];
        foreach ($ficha['descubrimientos'] ?? [] as $d) {
            if (!is_array($d)) {
                continue;
            }
            if (($d['estado'] ?? DiscoveryEngine::DESCUBIERTO) !== DiscoveryEngine::DESCUBIERTO) {
                continue;
            }
            $campo = (string) ($d['campo'] ?? '');
            if (!str_starts_with($campo, $prefix)) {
                continue;
            }
            $id = CopyDescubrimiento::idDeCampo($campo);
            if ($id === '') {
                continue;
            }
            $discovered[$id] = EtiquetaFicha::rasgo($id, $store);
        }

        $perfil = is_array($ficha['perfil_partida'] ?? null) ? $ficha['perfil_partida'] : [];
        $prefs = is_array($perfil['preferencias'][$prefKey] ?? null)
            ? array_values($perfil['preferencias'][$prefKey]) : [];
        $prefs = array_slice($prefs, 0, 2);

        if ($prefs === []) {
            $labels = array_values($discovered);
            $slots = [];
            for ($i = 0; $i < 2; $i++) {
                $txt = $labels[$i] ?? null;
                $slots[] = [
                    'descubierto' => is_string($txt) && $txt !== '',
                    'texto' => is_string($txt) && $txt !== '' ? $txt : null,
                ];
            }
            return $slots;
        }

        $slots = [];
        for ($i = 0; $i < 2; $i++) {
            $pid = $prefs[$i] ?? null;
            if (!is_string($pid) || $pid === '') {
                $slots[] = ['descubierto' => false, 'texto' => null];
                continue;
            }
            if (isset($discovered[$pid])) {
                $slots[] = [
                    'descubierto' => true,
                    'texto' => $discovered[$pid],
                ];
            } else {
                $slots[] = ['descubierto' => false, 'texto' => null];
            }
        }
        return $slots;
    }
}
