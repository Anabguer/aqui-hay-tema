<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class RelojOperations
{
    private string $projectRoot;
    private ?GameLogger $logger;
    private ?EmotionalStateService $emociones;

    public function __construct(
        string $projectRoot,
        ?GameLogger $logger = null,
        ?EmotionalStateService $emociones = null
    ) {
        $this->projectRoot = $projectRoot;
        $this->logger = $logger;
        $this->emociones = $emociones;
    }

    public function avanzar(array &$partida, int $horas): array
    {
        if ($horas < 0) {
            return GameError::respuesta(GameError::RELOJ_NO_REWIND, ['horas' => $horas]);
        }

        $antes = $partida['reloj'];
        Reloj::avanzarHoras($partida, $horas);
        // Coincidencias ANTES de sincronizar: los encuentros siguen programado/en_curso
        // y aún ocupan lugar. Coincidir ≠ interactuar.
        $coins = CoincidenciasEngine::detectarEnIntervalo(
            $partida,
            $this->projectRoot,
            $antes,
            $horas,
            $this->logger
        );
        $sync = EncuentroLifecycle::sincronizarConReloj($partida, $this->logger);
        $expirados = $this->emociones !== null ? $this->emociones->expirarVencidos($partida) : 0;
        $peticionesCaducadas = PeticionEngine::caducarVencidas($partida, $this->logger);
        $propuestasCaducadas = PropuestaEncuentroEngine::caducarVencidas($partida);

        DomainEventDispatcher::emit($partida, DomainEvents::TIEMPO_AVANZADO, [
            'horas' => $horas,
            'antes' => $antes,
            'despues' => $partida['reloj'],
            'encuentros_resueltos' => $sync['resueltos'],
            'estados_expirados' => $expirados,
            'coincidencias_detectadas' => count($coins),
        ], $this->logger, 'RelojOperations::avanzar');

        return [
            'ok' => true,
            'reloj' => $partida['reloj'],
            'texto' => Reloj::formatear($partida['reloj']),
            'encuentros_resueltos' => $sync['resueltos'],
            'estados_expirados' => $expirados,
            'coincidencias_detectadas' => count($coins),
            'peticiones_caducadas' => $peticionesCaducadas,
            'propuestas_caducadas' => $propuestasCaducadas,
            'horas' => $horas,
        ];
    }

    /**
     * Avanza hora a hora para no saltar lifecycle/eventos intermedios.
     * Uso play (+8h, ir al próximo). La simulación QA puede seguir usando avanzar() en bloque.
     */
    public function avanzarPasoAPaso(array &$partida, int $horas): array
    {
        if ($horas < 0) {
            return GameError::respuesta(GameError::RELOJ_NO_REWIND, ['horas' => $horas]);
        }

        $acum = [
            'ok' => true,
            'encuentros_resueltos' => 0,
            'estados_expirados' => 0,
            'coincidencias_detectadas' => 0,
            'pasos' => 0,
            'horas' => $horas,
        ];

        $snap = AvanceResumen::snapshot($partida);
        $iter = $horas === 0 ? 1 : $horas;
        $pasoHoras = $horas === 0 ? 0 : 1;
        for ($i = 0; $i < $iter; $i++) {
            $r = $this->avanzar($partida, $pasoHoras);
            if (($r['ok'] ?? true) === false) {
                return $r;
            }
            $acum['encuentros_resueltos'] += (int) ($r['encuentros_resueltos'] ?? 0);
            $acum['estados_expirados'] += (int) ($r['estados_expirados'] ?? 0);
            $acum['coincidencias_detectadas'] += (int) ($r['coincidencias_detectadas'] ?? 0);
            $acum['pasos']++;
            if ($horas === 0) {
                break;
            }
        }

        $acum['reloj'] = $partida['reloj'];
        $acum['texto'] = Reloj::formatear($partida['reloj']);
        $acum['resumen_avance'] = self::enriquecerResumen($partida, AvanceResumen::desdeSnapshot($partida, $snap));
        return $acum;
    }

    /**
     * Atajo de play: avanza hasta el inicio del siguiente encuentro programado.
     * No rebobina. Procesa cada hora con la misma tubería que +1h.
     */
    public function irAlProximoEncuentro(array &$partida): array
    {
        $next = self::proximoEncuentroProgramado($partida);
        if ($next === null) {
            return GameError::respuesta(GameError::SIN_PROXIMO_ENCUENTRO);
        }

        $actual = self::ahoraAbsoluto($partida);
        $objetivo = ((int) $next['dia']) * 24 + (int) $next['hora'];
        if ($objetivo < $actual) {
            return GameError::respuesta(GameError::RELOJ_NO_REWIND, [
                'encuentro_id' => $next['id'] ?? null,
                'actual' => ['dia' => $partida['reloj']['dia_pueblo'], 'hora' => $partida['reloj']['hora_actual']],
                'objetivo' => ['dia' => $next['dia'], 'hora' => $next['hora']],
            ]);
        }

        $horas = $objetivo - $actual;
        $adv = $this->avanzarPasoAPaso($partida, $horas);
        if (($adv['ok'] ?? true) === false) {
            return $adv;
        }

        $actualizado = null;
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (($enc['id'] ?? '') === ($next['id'] ?? '')) {
                $actualizado = $enc;
                break;
            }
        }

        return [
            'ok' => true,
            '_atajo_play' => true,
            'horas_avanzadas' => $horas,
            'encuentro' => $actualizado ?? $next,
            'reloj' => $adv,
            'resumen_avance' => $adv['resumen_avance'] ?? ['lineas' => [], 'total' => 0],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function proximoEncuentroProgramado(array $partida): ?array
    {
        $now = self::ahoraAbsoluto($partida);
        $best = null;
        $bestT = null;
        foreach (EncuentroEngine::list($partida) as $enc) {
            if (($enc['estado'] ?? '') !== 'programado') {
                continue;
            }
            $t = ((int) ($enc['dia'] ?? 0)) * 24 + (int) ($enc['hora'] ?? 0);
            if ($t < $now) {
                continue;
            }
            if ($bestT === null || $t < $bestT) {
                $best = $enc;
                $bestT = $t;
            }
        }
        return is_array($best) ? $best : null;
    }

    public static function ahoraAbsoluto(array $partida): int
    {
        return ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24
            + (int) ($partida['reloj']['hora_actual'] ?? 0);
    }

    /**
     * @param array<string, mixed> $resumen
     * @return array<string, mixed>
     */
    private function enriquecerResumen(array $partida, array $resumen): array
    {
        $catalog = new Catalog($this->projectRoot);
        $vistas = [];
        foreach ($resumen['encuentros_terminados_ids'] ?? [] as $id) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            foreach (EncuentroEngine::list($partida) as $enc) {
                if (($enc['id'] ?? '') === $id) {
                    $vistas[] = EncuentroResultadoVista::de($partida, $enc, $catalog, $this->projectRoot);
                    break;
                }
            }
        }
        $resumen['encuentros_terminados'] = $vistas;
        $resumen['encuentros_terminados_count'] = count($vistas);
        return $resumen;
    }
}
