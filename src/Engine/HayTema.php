<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Señal narrativa independiente para PLAY: esta persona, en este sitio, merece atención.
 *
 * No es emoción, romance, cita, encuentro próximo ni encuentro en curso.
 * El frontend no la calcula. El motor la deriva de hechos cotilleables reales.
 */
final class HayTema
{
    /** @var list<string> */
    private const TIPOS = ['cotilleo', 'cotilleo_patron', 'discusion', 'senal_romantica'];

    /**
     * Rellena hay_tema y tema_id en la gente ya agrupada por destino.
     *
     * @param array<string, mixed> $partida
     * @param list<array<string, mixed>> $gente
     * @return list<array<string, mixed>>
     */
    public static function aplicar(array $partida, array $gente): array
    {
        $porDest = [];
        foreach ($gente as $i => $p) {
            $did = (string) ($p['destino_id'] ?? '');
            $porDest[$did][] = $i;
        }
        foreach ($gente as $i => $p) {
            $rid = (string) ($p['id'] ?? '');
            $did = (string) ($p['destino_id'] ?? '');
            $ids = [];
            foreach ($porDest[$did] ?? [] as $j) {
                $oid = (string) ($gente[$j]['id'] ?? '');
                if ($oid !== '') {
                    $ids[] = $oid;
                }
            }
            $s = self::de($partida, $rid, $did, $ids);
            $gente[$i]['hay_tema'] = $s['hay_tema'];
            $gente[$i]['tema_id'] = $s['tema_id'];
            $gente[$i]['tema_vista'] = $s['tema_vista'];
        }
        return $gente;
    }

    /**
     * @param array<string, mixed> $partida
     * @param list<string> $idsEnEsteDestino
     * @return array{hay_tema: bool, tema_id: ?string, tema_vista: ?array}
     */
    public static function de(array $partida, string $rid, string $did, array $idsEnEsteDestino = []): array
    {
        if ($rid === '') {
            return ['hay_tema' => false, 'tema_id' => null, 'tema_vista' => null];
        }
        $buzon = self::desdeBuzonHoy($partida, $rid, $did, $idsEnEsteDestino);
        if ($buzon['hay_tema']) {
            return $buzon;
        }
        return self::desdePatron($partida, $rid, $did, $idsEnEsteDestino);
    }

    /**
     * Radar espacial: un tema de lugar solo cuenta si sus PROTAGONISTAS
     * (actores del hecho, dato estructurado del mensaje) están ahora mismo
     * en ese lugar. Quien solo cuenta/oye el cotilleo no lleva sello.
     *
     * @param array<string, mixed> $partida
     * @param list<string> $presentes
     * @return array{hay_tema: bool, tema_id: ?string, tema_vista: ?array}
     */
    private static function desdeBuzonHoy(array $partida, string $rid, string $did, array $presentes = []): array
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $enLugar = array_fill_keys($presentes, true);
        $enLugar[$rid] = true;
        foreach ($partida['buzon'] ?? [] as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            if (!self::esCotilleoHoy($msg, $dia)) {
                continue;
            }
            $tipo = (string) ($msg['tipo'] ?? '');
            if (!in_array($tipo, self::TIPOS, true)) {
                continue;
            }
            $protas = self::actoresDeMensaje($msg);
            if (!in_array($rid, $protas, true)) {
                continue;
            }
            $lugar = self::lugarDeMensaje($msg);
            // Señal romántica sin lugar: estado relacional, no hecho en este sitio ahora.
            if ($tipo === 'senal_romantica' && $lugar === null) {
                continue;
            }
            if ($lugar !== null) {
                if ($lugar !== $did) {
                    continue;
                }
                foreach ($protas as $pid) {
                    if (!isset($enLugar[$pid])) {
                        continue 2;
                    }
                }
            }
            $tid = (string) ($msg['id'] ?? '');
            $temaId = $tid !== '' ? $tid : null;
            return self::conVista($partida, true, $temaId);
        }
        return self::conVista($partida, false, null);
    }

    /**
     * @param array<string, mixed> $partida
     * @param list<string> $idsEnEsteDestino
     * @return array{hay_tema: bool, tema_id: ?string, tema_vista: ?array}
     */
    private static function desdePatron(array $partida, string $rid, string $did, array $idsEnEsteDestino): array
    {
        if ($did === '') {
            return self::conVista($partida, false, null);
        }
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $presentes = [];
        foreach ($idsEnEsteDestino as $oid) {
            if (is_string($oid) && $oid !== '') {
                $presentes[] = $oid;
            }
        }
        $presentes = array_values(array_unique($presentes));
        if (!in_array($rid, $presentes, true)) {
            $presentes[] = $rid;
        }
        if (count($presentes) < 2) {
            return self::conVista($partida, false, null);
        }

        $mejor = null;
        $mejorPri = 99;
        $catalog = new Catalog(dirname(__DIR__, 2));
        foreach ($presentes as $oid) {
            if ($oid === $rid) {
                continue;
            }
            $par = [$rid, $oid];
            if (!CotilleoNarrativo::patronParLugar($partida, $par, $did, $dia, [])) {
                continue;
            }
            $vista = CopyCoincidenciaPatron::vista($partida, $par, $did, $catalog);
            $pri = self::prioridadCategoria((string) ($vista['categoria'] ?? ''));
            if ($pri < $mejorPri) {
                $mejorPri = $pri;
                $mejor = 'coin_patron:' . CotilleoNarrativo::clavePar($par, $did);
            }
        }
        if ($mejor !== null) {
            return self::conVista($partida, true, $mejor);
        }
        return self::conVista($partida, false, null);
    }

    private static function prioridadCategoria(string $cat): int
    {
        switch ($cat) {
            case CotilleoCategoria::ROMANCE:
                return 0;
            case CotilleoCategoria::DRAMA:
                return 1;
            case CotilleoCategoria::RELACION:
                return 2;
            case CotilleoCategoria::COINCIDENCIAS:
                return 3;
            default:
                return 4;
        }
    }

    /**
     * @return array{hay_tema: bool, tema_id: ?string, tema_vista: ?array}
     */
    private static function conVista(array $partida, bool $hay, ?string $temaId): array
    {
        if (!$hay || $temaId === null || $temaId === '') {
            return ['hay_tema' => false, 'tema_id' => null, 'tema_vista' => null];
        }
        return [
            'hay_tema' => true,
            'tema_id' => $temaId,
            'tema_vista' => HayTemaVista::resolver($partida, $temaId),
        ];
    }

    /**
     * @param array<string, mixed> $msg
     */
    private static function esCotilleoHoy(array $msg, int $dia): bool
    {
        $clas = (string) ($msg['clasificacion'] ?? '');
        $canal = (string) ($msg['canal'] ?? BuzonEngine::canalDe($clas));
        if ($clas !== BuzonEngine::COTILLEO && $canal !== BuzonEngine::CANAL_COTILLEO) {
            return false;
        }
        $dMsg = (int) ($msg['dia'] ?? 0);
        $ts = is_array($msg['ts_juego'] ?? null) ? $msg['ts_juego'] : [];
        if ($dMsg === 0) {
            $dMsg = (int) ($ts['dia'] ?? 0);
        }
        return $dMsg === $dia;
    }

    /**
     * @param array<string, mixed> $msg
     */
    /** @return list<string> */
    private static function actoresDeMensaje(array $msg): array
    {
        $ids = [];
        foreach (is_array($msg['actores'] ?? null) ? $msg['actores'] : [] as $id) {
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }
        $de = $msg['de_persona'] ?? null;
        if (is_string($de) && $de !== '') {
            $ids[] = $de;
        }
        $origen = is_array($msg['origen'] ?? null) ? $msg['origen'] : [];
        $rev = is_array($origen['informacion_revelada'] ?? null) ? $origen['informacion_revelada'] : [];
        foreach (['desde', 'hacia'] as $k) {
            $v = $rev[$k] ?? null;
            if (is_string($v) && $v !== '') {
                $ids[] = $v;
            }
        }
        $clave = (string) ($msg['patron_clave'] ?? '');
        if ($clave !== '' && strpos($clave, '@') !== false) {
            $par = explode('@', $clave, 2)[0];
            foreach (explode('|', $par) as $id) {
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @param array<string, mixed> $msg
     */
    private static function lugarDeMensaje(array $msg): ?string
    {
        $lugar = $msg['lugar_id'] ?? $msg['lugar'] ?? null;
        if (is_string($lugar) && $lugar !== '') {
            return $lugar;
        }
        $clave = (string) ($msg['patron_clave'] ?? '');
        if ($clave !== '' && strpos($clave, '@') !== false) {
            $lug = explode('@', $clave, 2)[1];
            return $lug !== '' ? $lug : null;
        }
        return null;
    }
}
