<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Puente Necesidades → Peticiones.
 * Una necesidad baja puede ser UNA CAUSA para que una petición nazca con más probabilidad.
 * No crea peticiones automáticamente; pondera el scoring existente.
 */
final class NecesidadPeticionBridge
{
    /** Necesidad con bajo nivel que puede alimentar peticiones. */
    public const UMBRAL_BOOST = 50;

    /**
     * Peso base de boost según urgencia de la necesidad.
     */
    private const BOOST_PESOS = [
        'en_rojo' => 18,
        'lo_necesita' => 10,
        'le_vendria_bien' => 4,
    ];

    /**
     * Mapa plantilla → necesidades que puede satisfacer.
     * Solo plantillas cuya ejecución puede cubrir una necesidad concreta.
     */
    private const NECESIDADES_POR_PLANTILLA = [
        'salir_de_casa' => ['social', 'diversion'],
        'ir_al_lugar'    => ['social', 'diversion', 'actividad', 'calma'],
        'conocer_a_alguien' => ['social'],
        'volver_a_ver'   => ['social'],
        'quedar_con_x'   => ['social', 'diversion'],
        'algo_distinto'  => ['diversion', 'actividad'],
        'primera_cita_pet' => ['social'],
    ];

    /**
     * Calcula el boost de prioridad para una plantilla + residente,
     * basado en las necesidades activas del residente.
     *
     * @param array<string, mixed> $partida
     * @param string $residenteId
     * @param array<string, mixed> $plantilla
     * @return int 0 si no hay boost, >0 si hay necesidad que pondera
     */
    public static function boostPrioridad(
        array $partida,
        string $residenteId,
        array $plantilla
    ): int {
        if (!FeatureConfig::isEnabled($partida, 'necesidades_enabled')) {
            return 0;
        }
        if (!isset($partida['residentes'][$residenteId]['runtime']['necesidades'])) {
            return 0;
        }

        $plantillaId = (string) ($plantilla['id'] ?? '');
        $necesidadesQuePuede = self::NECESIDADES_POR_PLANTILLA[$plantillaId] ?? [];
        if ($necesidadesQuePuede === []) {
            return 0;
        }

        $necesidades = $partida['residentes'][$residenteId]['runtime']['necesidades'];
        $boost = 0;

        foreach ($necesidadesQuePuede as $necId) {
            if (!isset($necesidades[$necId])) {
                continue;
            }
            $valor = (float) ($necesidades[$necId]['valor'] ?? 85);
            $banda = NecesidadEstado::calcularBanda($valor);
            if ($banda === NecesidadEstado::BANDA_BIEN) {
                continue;
            }
            $boost += (int) (self::BOOST_PESOS[$banda] ?? 0);
        }

        return $boost;
    }

    /**
     * Devuelve la necesidad más urgente de un residente (para contextualizar copy).
     *
     * @param array<string, mixed> $partida
     * @param string $residenteId
     * @return string|null nec_id o null si todas están bien
     */
    public static function necesidadMasUrgente(array $partida, string $residenteId): ?string
    {
        if (!FeatureConfig::isEnabled($partida, 'necesidades_enabled')) {
            return null;
        }
        if (!isset($partida['residentes'][$residenteId]['runtime']['necesidades'])) {
            return null;
        }

        $necesidades = $partida['residentes'][$residenteId]['runtime']['necesidades'];
        $masBaja = 100;
        $id = null;

        foreach ($necesidades as $necId => $nec) {
            $valor = (float) ($nec['valor'] ?? 85);
            if ($valor < $masBaja) {
                $masBaja = $valor;
                $id = $necId;
            }
        }

        if ($id !== null && $masBaja < self::UMBRAL_BOOST) {
            return $id;
        }

        return null;
    }

    /**
     * Copy narrativa contextual para peticiones alimentadas por necesidad.
     *
     * @param string $necId
     * @return list<string>
     */
    public static function copiasDesdeNecesidad(string $necId): array
    {
        $map = [
            'social' => [
                'Tengo ganas de hacer compañía.',
                'Me apetece estar con gente.',
                'Hoy me apetece socializar.',
                'Quiero estar con alguien.',
            ],
            'diversion' => [
                'Necesito pasarlo bien.',
                'Tengo ganas de diversion.',
                'Hoy me apetece hacer algo divertido.',
                'Necesito un plan que me saque una sonrisa.',
            ],
            'actividad' => [
                'Necesito moverme.',
                'Tengo ganas de hacer algo activo.',
                'Hoy necesito gastar energía.',
                'Necesito un plan con movimiento.',
            ],
            'calma' => [
                'Necesito un rato tranquilo.',
                'Hoy me apetece algo calmado.',
                'Necesito desconectar.',
                'Quiero un plan tranquilo.',
            ],
        ];

        return $map[$necId] ?? [];
    }
}
