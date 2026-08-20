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
        }
        return $gente;
    }

    /**
     * @param array<string, mixed> $partida
     * @param list<string> $idsEnEsteDestino
     * @return array{hay_tema: bool, tema_id: ?string}
     */
    public static function de(array $partida, string $rid, string $did, array $idsEnEsteDestino = []): array
    {
        if ($rid === '') {
            return ['hay_tema' => false, 'tema_id' => null];
        }
        $buzon = self::desdeBuzonHoy($partida, $rid, $did);
        if ($buzon['hay_tema']) {
            return $buzon;
        }
        return self::desdePatron($partida, $rid, $did, $idsEnEsteDestino);
    }

    /**
     * @param array<string, mixed> $partida
     * @return array{hay_tema: bool, tema_id: ?string}
     */
    private static function desdeBuzonHoy(array $partida, string $rid, string $did): array
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
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
            if (!self::mensajeInvolucra($msg, $rid)) {
                continue;
            }
            $lugar = self::lugarDeMensaje($msg);
            if ($lugar !== null && $lugar !== $did) {
                continue;
            }
            $tid = (string) ($msg['id'] ?? '');
            return ['hay_tema' => true, 'tema_id' => $tid !== '' ? $tid : null];
        }
        return ['hay_tema' => false, 'tema_id' => null];
    }

    /**
     * Patrón de coincidencia significativa (mismo par/grupo + mismo lugar, varios días).
     * Solo si ahora mismo hay compañía del patrón en ese sitio. Un café solo no basta.
     *
     * @param array<string, mixed> $partida
     * @param list<string> $idsEnEsteDestino
     * @return array{hay_tema: bool, tema_id: ?string}
     */
    private static function desdePatron(array $partida, string $rid, string $did, array $idsEnEsteDestino): array
    {
        if ($did === '') {
            return ['hay_tema' => false, 'tema_id' => null];
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
            return ['hay_tema' => false, 'tema_id' => null];
        }

        if (CotilleoNarrativo::patronParLugar($partida, $presentes, $did, $dia, [])) {
            return [
                'hay_tema' => true,
                'tema_id' => 'coin_patron:' . CotilleoNarrativo::clavePar($presentes, $did),
            ];
        }

        foreach ($presentes as $oid) {
            if ($oid === $rid) {
                continue;
            }
            $par = [$rid, $oid];
            if (CotilleoNarrativo::patronParLugar($partida, $par, $did, $dia, [])) {
                return [
                    'hay_tema' => true,
                    'tema_id' => 'coin_patron:' . CotilleoNarrativo::clavePar($par, $did),
                ];
            }
        }
        return ['hay_tema' => false, 'tema_id' => null];
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
    private static function mensajeInvolucra(array $msg, string $rid): bool
    {
        foreach (self::actoresDeMensaje($msg) as $id) {
            if ($id === $rid) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $msg
     * @return list<string>
     */
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
