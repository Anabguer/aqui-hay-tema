<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Máx. 2 cotilleos autónomos/día. Pool persistido en partida; selección determinista.
 * No limita citas, discusiones ni señales románticas.
 */
final class CotilleoAutonomoCadencia
{
    public const MAX_POR_DIA = 2;
    public const TIPO_BUZON = 'cotilleo_autonomo';

    public static function ensure(array &$partida): void
    {
        $hoy = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $est = is_array($partida['cotilleo_autonomo'] ?? null) ? $partida['cotilleo_autonomo'] : [];
        if ((int) ($est['dia'] ?? 0) !== $hoy) {
            $partida['cotilleo_autonomo'] = [
                'dia' => $hoy,
                'candidatos' => [],
                'publicados' => [],
            ];
        }
        $partida['cotilleo_autonomo']['candidatos'] ??= [];
        $partida['cotilleo_autonomo']['publicados'] ??= [];
    }

    /**
     * Registra candidato y sincroniza buzón (máx. 2 publicados hoy).
     *
     * @param array<string, mixed> $enc
     * @param array<string, mixed> $mensaje
     */
    public static function registrar(array &$partida, array $enc, array $mensaje, ?Catalog $catalog = null): void
    {
        self::ensure($partida);
        $encId = (string) ($enc['id'] ?? '');
        if ($encId === '') {
            return;
        }
        $participantes = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
        $rid = (string) ($participantes[0] ?? '');
        if ($rid === '') {
            return;
        }
        $lugarId = (string) ($enc['lugar'] ?? $enc['lugar_id'] ?? '');
        $hora = (int) ($enc['hora'] ?? $enc['hora_inicio'] ?? 0);
        $emo = (string) ($partida['residentes'][$rid]['runtime']['estado_emocional']['id'] ?? EstadoEmocional::NEUTRO);
        $emo = EstadoEmocional::canonId($emo);
        $rutinario = self::esLugarRutinario($partida, $rid, $lugarId);

        $orden = count($partida['cotilleo_autonomo']['candidatos']) + 1;
        $candidato = [
            'encuentro_id' => $encId,
            'residente_id' => $rid,
            'lugar_id' => $lugarId,
            'hora' => $hora,
            'emo' => $emo,
            'rutinario' => $rutinario,
            'orden' => $orden,
            'mensaje' => $mensaje,
        ];

        $reemplazado = false;
        foreach ($partida['cotilleo_autonomo']['candidatos'] as $i => $c) {
            if (is_array($c) && (string) ($c['encuentro_id'] ?? '') === $encId) {
                $candidato['orden'] = (int) ($c['orden'] ?? $orden);
                $partida['cotilleo_autonomo']['candidatos'][$i] = $candidato;
                $reemplazado = true;
                break;
            }
        }
        if (!$reemplazado) {
            $partida['cotilleo_autonomo']['candidatos'][] = $candidato;
        }

        self::sincronizarBuzon($partida);
    }

    public static function contarPublicadosHoy(array $partida): int
    {
        $hoy = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $n = 0;
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            if ((string) ($m['tipo'] ?? '') !== self::TIPO_BUZON) {
                continue;
            }
            if ((int) ($m['dia'] ?? 0) !== $hoy) {
                continue;
            }
            $n++;
        }
        return $n;
    }

    /**
     * @return list<string> encuentro_ids publicados hoy
     */
    public static function idsPublicadosHoy(array $partida): array
    {
        self::ensure($partida);
        return array_values($partida['cotilleo_autonomo']['publicados'] ?? []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function candidatosHoy(array $partida): array
    {
        self::ensure($partida);
        $out = [];
        foreach ($partida['cotilleo_autonomo']['candidatos'] ?? [] as $c) {
            if (is_array($c)) {
                $out[] = $c;
            }
        }
        return $out;
    }

    public static function sincronizarBuzon(array &$partida): void
    {
        self::ensure($partida);
        $hoy = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $cands = self::candidatosHoy($partida);
        if ($cands === []) {
            $partida['cotilleo_autonomo']['publicados'] = [];
            self::filtrarBuzon($partida, $hoy, []);
            return;
        }

        usort($cands, static function (array $a, array $b): int {
            $ea = (string) ($a['emo'] ?? EstadoEmocional::NEUTRO) !== EstadoEmocional::NEUTRO ? 1 : 0;
            $eb = (string) ($b['emo'] ?? EstadoEmocional::NEUTRO) !== EstadoEmocional::NEUTRO ? 1 : 0;
            if ($ea !== $eb) {
                return $eb <=> $ea;
            }
            $ra = !empty($a['rutinario']) ? 1 : 0;
            $rb = !empty($b['rutinario']) ? 1 : 0;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            return ((int) ($a['orden'] ?? 0)) <=> ((int) ($b['orden'] ?? 0));
        });

        $top = array_slice($cands, 0, self::MAX_POR_DIA);
        $ids = [];
        foreach ($top as $c) {
            $eid = (string) ($c['encuentro_id'] ?? '');
            if ($eid !== '') {
                $ids[] = $eid;
            }
        }
        $partida['cotilleo_autonomo']['publicados'] = $ids;
        self::filtrarBuzon($partida, $hoy, $ids);

        foreach ($top as $c) {
            $eid = (string) ($c['encuentro_id'] ?? '');
            if ($eid === '' || self::yaEnBuzon($partida, $hoy, $eid)) {
                continue;
            }
            $msg = is_array($c['mensaje'] ?? null) ? $c['mensaje'] : [];
            $encId = (string) ($c['encuentro_id'] ?? '');
            $encData = null;
            foreach ($partida['encuentros'] ?? [] as $e) {
                if (is_array($e) && (string) ($e['id'] ?? '') === $encId) {
                    $encData = $e;
                    break;
                }
            }
            if ($encData !== null) {
                $enriched = EncuentroCotilleoCopy::mensajeAutonomoEnriquecido($partida, $encData);
                if ($enriched !== null && $enriched !== '') {
                    $msg['texto'] = $enriched;
                }
            }
            $msg['id'] = 'msg_auto_' . $eid;
            $r = BuzonEngine::crear($partida, $msg);
            DiarioNarrativaBridge::mirrorCotilleoBuzon($partida, $r);
        }
    }

    private static function esLugarRutinario(array $partida, string $residenteId, string $lugarId): bool
    {
        if ($lugarId === '') {
            return false;
        }
        $perfil = PerfilPartida::de($partida, $residenteId);
        if (!is_array($perfil)) {
            return false;
        }
        $prefs = is_array($perfil['lugares_preferentes'] ?? null) ? $perfil['lugares_preferentes'] : [];
        return in_array($lugarId, $prefs, true);
    }

    public static function esLugarRutinarioPublico(array $partida, string $residenteId, string $lugarId): bool
    {
        return self::esLugarRutinario($partida, $residenteId, $lugarId);
    }

    /**
     * @param list<string> $idsPermitidos
     */
    private static function filtrarBuzon(array &$partida, int $dia, array $idsPermitidos): void
    {
        $permit = array_flip($idsPermitidos);
        $partida['buzon'] = array_values(array_filter(
            $partida['buzon'] ?? [],
            static function ($m) use ($dia, $permit) {
                if (!is_array($m)) {
                    return true;
                }
                if ((string) ($m['tipo'] ?? '') !== self::TIPO_BUZON) {
                    return true;
                }
                if ((int) ($m['dia'] ?? 0) !== $dia) {
                    return true;
                }
                $eid = (string) ($m['origen']['evento_id'] ?? '');
                return $eid !== '' && isset($permit[$eid]);
            }
        ));
    }

    private static function yaEnBuzon(array $partida, int $dia, string $encuentroId): bool
    {
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            if ((string) ($m['tipo'] ?? '') !== self::TIPO_BUZON) {
                continue;
            }
            if ((int) ($m['dia'] ?? 0) !== $dia) {
                continue;
            }
            if ((string) ($m['origen']['evento_id'] ?? '') === $encuentroId) {
                return true;
            }
        }
        return false;
    }
}
