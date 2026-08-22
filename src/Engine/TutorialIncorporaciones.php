<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Incorporaciones tutorializadas 3 → ≈8 en el día 1.
 * No usan el sistema de candidatos; solo mientras tutorial activo/recién completado.
 */
final class TutorialIncorporaciones
{
    public const META_OBJETIVO = 8;

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $config
     */
    public static function ensureDesdeConfig(array &$partida, array $config): void
    {
        CandidatoLlegadaEngine::ensure($partida);
        if (!isset($partida['llegadas']['tutorial_cola'])) {
            $cola = [];
            foreach ($config['tutorial_incorporaciones_dia_1'] ?? [] as $id) {
                if (is_string($id) && $id !== '') {
                    $cola[] = $id;
                }
            }
            $partida['llegadas']['tutorial_cola'] = $cola;
        }
        if (!array_key_exists('tutorial_hechas', $partida['llegadas'])) {
            $partida['llegadas']['tutorial_hechas'] = [];
            $partida['llegadas']['tutorial_objetivo'] = (int) ($config['tutorial_objetivo_residentes'] ?? self::META_OBJETIVO);
            $modoTutorial = !empty($config['tutorial_bucle_1']) || !empty($config['tutorial_primeros_pasos']);
            $partida['llegadas']['modo'] = $modoTutorial ? 'tutorial' : 'normal';
            if (($partida['llegadas']['modo'] ?? '') === 'normal') {
                $partida['llegadas']['normal_desde_dia'] = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            }
        }
    }

    /**
     * Tras completar el bucle tutorial: programa incorporaciones del día 1.
     *
     * @return list<array<string, mixed>>
     */
    public static function alCompletarTutorial(array &$partida, string $root, ?GameLogger $logger = null): array
    {
        CandidatoLlegadaEngine::ensure($partida);
        $hechas = [];
        // Primera oleada inmediata (2) + resto a lo largo del día vía tick
        $hechas = array_merge($hechas, self::incorporarHasta($partida, $root, 2, $logger));
        $partida['llegadas']['tutorial_completado_dia'] = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida['llegadas']['tutorial_pendiente_resto'] = true;
        return $hechas;
    }

    /**
     * Durante el día 1 tras tutorial: completar hasta ≈8.
     *
     * @return list<array<string, mixed>>
     */
    public static function tickDia1(array &$partida, string $root, ?GameLogger $logger = null): array
    {
        CandidatoLlegadaEngine::ensure($partida);
        if (TutorialPrimerosPasos::bloqueaIncorporaciones($partida)) {
            return [];
        }
        $tut = TutorialBucle::vista($partida);
        if (!empty($tut['activo'])) {
            return []; // aún en tutorial: no incorporar
        }
        if (empty($partida['llegadas']['tutorial_pendiente_resto'])
            && empty($partida['llegadas']['tutorial_cola'])) {
            if (($partida['llegadas']['modo'] ?? '') === 'tutorial') {
                CandidatoLlegadaEngine::activarModoNormal($partida);
            }
            return [];
        }

        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $hora = (int) ($partida['reloj']['hora_actual'] ?? 0);
        $n = count(self::residentesActivos($partida));
        $objetivo = (int) ($partida['llegadas']['tutorial_objetivo'] ?? self::META_OBJETIVO);

        // Espaciar: a partir de completar, cada ~2–3 h una llegada tutorial
        $ultimaHora = (int) ($partida['llegadas']['tutorial_ultima_hora'] ?? -99);
        if ($hora - $ultimaHora < 2 && $n < $objetivo) {
            return [];
        }

        $hechas = self::incorporarHasta($partida, $root, 1, $logger);
        if ($hechas !== []) {
            $partida['llegadas']['tutorial_ultima_hora'] = $hora;
        }

        $n2 = count(self::residentesActivos($partida));
        if ($n2 >= $objetivo || ($partida['llegadas']['tutorial_cola'] ?? []) === []) {
            $partida['llegadas']['tutorial_pendiente_resto'] = false;
            CandidatoLlegadaEngine::activarModoNormal($partida);
        }

        // Si se acabó el día 1 sin llegar a 8, forzar el resto al cierre
        if ($dia > (int) ($partida['llegadas']['tutorial_completado_dia'] ?? 1)
            && !empty($partida['llegadas']['tutorial_cola'])) {
            $hechas = array_merge($hechas, self::incorporarHasta($partida, $root, 99, $logger));
            $partida['llegadas']['tutorial_pendiente_resto'] = false;
            CandidatoLlegadaEngine::activarModoNormal($partida);
        }

        return $hechas;
    }

    /**
     * Cierre de seguridad: si el día 1 termina y el tutorial ya se completó, completar ≈8.
     *
     * @return list<array<string, mixed>>
     */
    public static function alCerrarDia1SiToca(array &$partida, string $root, ?GameLogger $logger = null): array
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $compDia = (int) ($partida['llegadas']['tutorial_completado_dia'] ?? 0);
        if ($compDia !== 1 || $dia !== 1) {
            // cuando cruzamos a día 2
            if ($compDia === 1 && $dia >= 2 && !empty($partida['llegadas']['tutorial_cola'])) {
                $h = self::incorporarHasta($partida, $root, 99, $logger);
                $partida['llegadas']['tutorial_pendiente_resto'] = false;
                CandidatoLlegadaEngine::activarModoNormal($partida);
                return $h;
            }
            return [];
        }
        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function incorporarHasta(
        array &$partida,
        string $root,
        int $max,
        ?GameLogger $logger
    ): array {
        $hechas = [];
        $objetivo = (int) ($partida['llegadas']['tutorial_objetivo'] ?? self::META_OBJETIVO);
        $ops = new ResidenteOperations(new Catalog($root), $logger);
        $cola = is_array($partida['llegadas']['tutorial_cola'] ?? null)
            ? $partida['llegadas']['tutorial_cola']
            : [];

        while ($max > 0 && $cola !== [] && count(self::residentesActivos($partida)) < $objetivo) {
            $id = (string) array_shift($cola);
            if ($id === '' || isset($partida['residentes'][$id])) {
                continue;
            }
            if (CapacidadViviendas::huecos($partida) <= 0) {
                break;
            }
            $r = $ops->incorporarCatalogo($partida, $id, 'residente');
            if (!($r['ok'] ?? false)) {
                continue;
            }
            HistorialPersonajesPartida::marcar($partida, $id);
            $max--;
            $nombre = IdentidadPublica::nombre($partida, $id);
            BuzonEngine::crear($partida, [
                'id' => 'msg_tut_inc_' . $id . '_' . bin2hex(random_bytes(2)),
                'clasificacion' => BuzonEngine::COTILLEO,
                'canal' => BuzonEngine::CANAL_COTILLEO,
                'tipo' => 'llegada_pueblo',
                'texto' => CotilleoLlegadaCopy::texto($nombre, $id),
                'cotilleo_meta' => CotilleoCategoria::meta(CotilleoCategoria::PUEBLO, true),
                'de_persona' => $id,
                'actores' => [$id],
                'origen' => ['tipo_evento' => 'tutorial_incorporacion', 'es_narrativo' => false],
            ]);
            $row = [
                'catalog_id' => $id,
                'vivienda_id' => $r['vivienda_id'] ?? null,
                'via' => 'tutorial',
            ];
            $hechas[] = $row;
            $partida['llegadas']['tutorial_hechas'][] = $row;
        }
        $partida['llegadas']['tutorial_cola'] = $cola;
        return $hechas;
    }

    /** @return list<string> */
    public static function residentesActivos(array $partida): array
    {
        $ids = [];
        foreach ($partida['residentes'] ?? [] as $id => $r) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            if (($r['presencia'] ?? 'residente') === 'residente') {
                $ids[] = $id;
            }
        }
        return $ids;
    }
}
