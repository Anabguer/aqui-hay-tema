<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Diagnóstico DEV: hobbies, emociones y PlanAfinidad sin duplicar fórmulas de juego. */
final class HobbyEmocionDev
{
    /**
     * @return array<string, mixed>
     */
    public static function diagnostico(
        array $partida,
        string $residenteId,
        ?string $lugarId,
        Catalog $catalog
    ): array {
        if (!isset($partida['residentes'][$residenteId])) {
            return ['ok' => false, 'error' => 'residente_no_encontrado'];
        }
        $store = $catalog->store();
        $res = $partida['residentes'][$residenteId];
        EstadoEmocional::ensureResidente($res, $partida['reloj'] ?? null);
        $perfil = PerfilPartida::de($partida, $residenteId);
        $hobbies = is_array($perfil['hobbies'] ?? null) ? array_values($perfil['hobbies']) : [];
        $hobbyPrincipal = (string) ($perfil['hobby_principal'] ?? ($hobbies[0] ?? ''));

        $hobbiesDetalle = [];
        foreach ($hobbies as $hid) {
            if (!is_string($hid) || $hid === '') {
                continue;
            }
            $lugares = HobbyAccionable::lugaresCanonico($hid, $store);
            $hobbiesDetalle[] = [
                'id' => $hid,
                'nombre' => EtiquetaFicha::hobby($hid, $store),
                'lugar_ids' => $lugares,
                'accionable' => $lugares !== [],
                'descubierto' => DiscoveryReveal::jugadorSabeHobby($partida, $residenteId, $hid),
                'pista' => DiscoveryReveal::jugadorSabeHobby($partida, $residenteId, $hid)
                    ? HobbyAccionable::pista($hid, $store) : null,
            ];
        }

        $afinidad = null;
        $distribucion = null;
        if (is_string($lugarId) && $lugarId !== '') {
            $afinidad = PlanAfinidad::paraParticipante($partida, $residenteId, $lugarId, $catalog);
            $cal = CalibracionConfig::load($catalog->getRoot());
            $resultados = CalibracionConfig::get($cal, 'resolucion_encuentro.resultados', ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien']);
            if (!is_array($resultados)) {
                $resultados = ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien'];
            }
            $carga = EncuentroExperiencia::cargaDe([
                'factores' => [
                    'plan_a' => $afinidad,
                    'emocional_a' => $res['runtime']['estado_emocional']['id'] ?? 'neutro',
                ],
                'por_participante' => [],
            ], $residenteId, $cal);
            $distribucion = self::distribucionAzar($resultados, $carga, $cal);
        }

        $prefs = is_array($perfil['preferencias'] ?? null) ? $perfil['preferencias'] : [];

        return [
            'ok' => true,
            'residente_id' => $residenteId,
            'nombre' => IdentidadPublica::nombre($partida, $residenteId),
            'estado_emocional' => $res['runtime']['estado_emocional'],
            'hobby_principal' => $hobbyPrincipal !== '' ? $hobbyPrincipal : null,
            'hobbies' => $hobbiesDetalle,
            'hobby_principal_accionable' => $hobbyPrincipal !== '' && HobbyAccionable::esAccionable($hobbyPrincipal, $store),
            'preferencias_interpersonales' => [
                'hobbies_pos' => $prefs['hobbies_pos'] ?? [],
                'hobbies_neg' => $prefs['hobbies_neg'] ?? [],
                'nota' => 'Valoran/rechazan hobbies EN OTRA persona; no son hobbies propios.',
            ],
            'lugares_preferentes' => $perfil['lugares_preferentes'] ?? [],
            'nota_lugares_preferentes' => 'Rutina visual (PresenciaEngine); no sustituyen hobby→lugar.',
            'lugar_consulta' => $lugarId,
            'plan_afinidad' => $afinidad,
            'distribucion_experiencia' => $distribucion,
        ];
    }

    /**
     * @param list<string> $resultados
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function distribucionAzar(array $resultados, float $carga, array $cal): array
    {
        $min = (float) CalibracionConfig::get($cal, 'azar_ponderado.carga_min', -1);
        $max = (float) CalibracionConfig::get($cal, 'azar_ponderado.carga_max', 1);
        if ($carga < $min) {
            $carga = $min;
        }
        if ($carga > $max) {
            $carga = $max;
        }
        $n = count($resultados);
        $pesos = [];
        for ($i = 0; $i < $n; $i++) {
            $t = $n === 1 ? 0.0 : ($i / ($n - 1)) * 2 - 1;
            $pesos[] = max(0.01, 1 + ($carga * $t));
        }
        $sum = array_sum($pesos);
        $pct = [];
        foreach ($resultados as $i => $r) {
            $pct[$r] = $sum > 0 ? round(100.0 * $pesos[$i] / $sum, 1) : null;
        }
        return [
            'carga' => $carga,
            'pesos' => array_combine($resultados, $pesos),
            'porcentajes_aprox' => $pct,
            'nota' => 'Distribución derivada de AzarPonderado::tirar (misma fórmula de pesos).',
        ];
    }
}
