<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PartidaService
{
    private string $root;
    private Catalog $catalog;
    private PartidaRepository $repo;

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $this->catalog = new Catalog($this->root);
        $this->repo = new PartidaRepository($this->root);
    }

    public function nuevaPartida(string $configId = 'debug_v0', ?string $seed = null): array
    {
        $partida = PartidaSchema::nueva($this->root, $configId, $seed);
        $config = $this->catalog->loadConfigPrevalidada($configId);

        foreach ($config['residentes_iniciales'] ?? [] as $entry) {
            $this->incorporarResidenteCatalogo($partida, $entry['catalog_id'], $entry['presencia'] ?? 'residente');
        }

        $this->repo->guardar($partida);
        return $partida;
    }

    public function cargar(string $partidaId): array
    {
        $partida = $this->repo->cargar($partidaId);
        Reloj::calcularCatchUpPendiente($partida);
        $this->repo->guardar($partida);
        return $partida;
    }

    public function guardar(array $partida): void
    {
        $this->repo->guardar($partida);
    }

    public function reiniciar(string $partidaId): array
    {
        if ($this->repo->existe($partidaId)) {
            unlink($this->repo->pathFor($partidaId));
        }
        return $this->nuevaPartida('debug_v0');
    }

    public function listarPartidas(): array
    {
        return $this->repo->listar();
    }

    public function incorporarResidenteCatalogo(array &$partida, string $catalogId, string $presencia = 'residente'): array
    {
        if (isset($partida['residentes'][$catalogId])) {
            return ['ok' => false, 'error' => 'ya_presente'];
        }
        $catalogo = $this->catalog->loadPersonaje($catalogId);
        $runtime = ResidenteRuntime::crearDesdeCatalogo($catalogo, $presencia);
        $partida['residentes'][$catalogId] = $runtime;

        $asig = BloqueA::asignarAutomatico($partida, $catalogId);
        if ($asig['error'] !== null) {
            return ['ok' => false, 'error' => $asig['error'], 'residente' => $runtime];
        }
        return ['ok' => true, 'residente' => $runtime, 'vivienda_id' => $asig['vivienda_id']];
    }

    public function crearResidentePlaceholderDev(array &$partida): array
    {
        $num = 1;
        while (isset($partida['residentes']["per_placeholder_dev_" . str_pad((string) $num, 2, '0', STR_PAD_LEFT)])) {
            $num++;
        }
        $runtime = ResidenteRuntime::crearPlaceholderDev($num);
        $id = $runtime['catalog_id'];
        $partida['residentes'][$id] = $runtime;
        $asig = BloqueA::asignarAutomatico($partida, $id);
        return ['ok' => true, 'residente' => $runtime, 'vivienda_id' => $asig['vivienda_id']];
    }

    public function liberarVivienda(array &$partida, string $viviendaId): array
    {
        $ok = BloqueA::liberar($partida, $viviendaId);
        return ['ok' => $ok];
    }

    public function avanzarReloj(array &$partida, int $horas): array
    {
        Reloj::avanzarHoras($partida, $horas);
        return ['reloj' => $partida['reloj'], 'texto' => Reloj::formatear($partida['reloj'])];
    }

    public function fichaResidente(array $partida, string $residenteId): array
    {
        $runtime = $partida['residentes'][$residenteId] ?? null;
        if ($runtime === null) {
            throw new \InvalidArgumentException('residente no encontrado');
        }

        $catalogo = ResidenteRuntime::catalogoParaRuntime($runtime, $this->catalog);
        $dia = (int) $partida['reloj']['dia_pueblo'];
        $agenda = AgendaEngine::resolverDia($partida, $residenteId, $dia, $this->catalog);

        $relaciones = [];
        foreach ($partida['residentes'] as $otroId => $_) {
            if ($otroId === $residenteId) {
                continue;
            }
            $rel = RelacionEngine::obtenerEntre($partida, $residenteId, $otroId);
            if ($rel['social'] !== null || $rel['romance'] !== null) {
                $relaciones[$otroId] = $rel;
            }
        }

        $hobbiesConocidos = [];
        $hobbiesDesconocidos = [];
        if ($catalogo !== null) {
            $hobbyP = $catalogo['vida']['hobby_principal'] ?? null;
            if ($hobbyP) {
                $hobbiesConocidos[] = $hobbyP;
            }
            foreach ($catalogo['vida']['hobbies_secundarios'] ?? [] as $h) {
                $hobbiesConocidos[] = $h;
            }
            $hobbiesDesconocidos = ['_placeholder' => true, 'nota' => 'Hobbies ocultos pendientes de descubrimiento'];
        }

        return [
            '_ui' => 'provisional_v0',
            'id' => $residenteId,
            'identidad' => [
                'nombre' => $runtime['identidad_publica']['nombre'],
                'slot_catalogo' => $runtime['identidad_publica']['slot_catalogo'],
                'edad' => $catalogo['identidad']['edad'] ?? null,
                'genero' => $catalogo['identidad']['genero'] ?? null,
            ],
            'vivienda_id' => $runtime['vivienda_id'],
            'presencia' => $runtime['presencia'],
            'trabajo' => [
                'ocupacion' => $runtime['runtime']['ocupacion'],
                'franja' => $catalogo['vida']['franja_disponibilidad'] ?? null,
            ],
            'hobbies' => [
                'conocidos' => $hobbiesConocidos,
                'desconocidos' => $hobbiesDesconocidos,
            ],
            'relaciones' => $relaciones,
            'agenda_hoy' => $agenda,
            'estado' => [
                'flag_nuevo' => $runtime['flag_nuevo'],
                'estado_en_partida' => $runtime['estado_en_partida'],
            ],
            'acciones_disponibles' => [
                'ver_agenda',
                'programar_cita_si_dos_residentes',
                '_placeholder' => true,
            ],
            'placeholder' => $runtime['_placeholder'] ?? false,
        ];
    }

    public function estadoResumido(array $partida): array
    {
        return [
            'meta' => $partida['meta'],
            'reloj' => $partida['reloj'],
            'reloj_texto' => Reloj::formatear($partida['reloj']),
            'celeste' => $partida['celeste'],
            'bloque_a' => BloqueA::resumen($partida),
            'residentes_count' => count($partida['residentes']),
            'citas_activas' => count(CitaEngine::listarActivas($partida)),
            'relaciones_sociales' => count($partida['relaciones_sociales']),
            'relaciones_romanticas' => count($partida['relaciones_romanticas']),
        ];
    }

    public function getCatalog(): Catalog
    {
        return $this->catalog;
    }

    public function getRepo(): PartidaRepository
    {
        return $this->repo;
    }
}
