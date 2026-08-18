<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Resolver técnico de visibilidad (publico/oculto/parcial/por_evento) para valores de campos.
 * No decide qué secretos concretos existen: solo aplica política + estado de descubrimiento.
 */
final class DiscoveryVisibilityResolver
{
    public const PARCIAL_PLACEHOLDER = '__PARCIAL__';

    public function __construct(private array $config, private array $partida)
    {
    }

    /**
     * @param array{
     *   residente_id: string,
     *   campo: string,
     *   valor_real: mixed,
     *   eventos_alcanzados?: list<string>
     * } $in
     */
    public function sanitizarValor(array $in): array
    {
        $residenteId = (string) ($in['residente_id'] ?? '');
        $campo = (string) ($in['campo'] ?? '');
        $valorReal = $in['valor_real'] ?? null;
        $eventosAlcanzados = is_array($in['eventos_alcanzados'] ?? null) ? array_values($in['eventos_alcanzados']) : [];

        $v = DiscoveryVisibilityPolicy::visibilidad($this->partida, $residenteId, $campo, $this->config);
        $politica = (string) ($v['politica'] ?? DiscoveryVisibilityPolicy::SIN_POLITICA);

        $base = [
            'campo' => $campo,
            'politica' => $politica,
            'descubrimiento' => $v['descubrimiento'] ?? DiscoveryEngine::DESCONOCIDO,
            'eventos_alcanzados' => $eventosAlcanzados,
        ];

        if ($politica === DiscoveryVisibilityPolicy::PUBLICO) {
            return $base + ['visible_jugador' => true, 'valor' => $valorReal];
        }

        if ($politica === DiscoveryVisibilityPolicy::SIN_POLITICA) {
            // No hay política aplicada: no ocultamos nada por este motor.
            return $base + ['visible_jugador' => null, 'valor' => $valorReal];
        }

        if ($v['visible_jugador'] === true) {
            return $base + ['visible_jugador' => true, 'valor' => $valorReal];
        }

        if ($politica === DiscoveryVisibilityPolicy::PARCIAL) {
            return $base + [
                'visible_jugador' => false,
                'valor' => self::PARCIAL_PLACEHOLDER,
                '_nota' => 'Placeholder técnico. El recorte narrativo del campo está bloqueado.',
            ];
        }

        // OCULTO o POR_EVENTO: oculto hasta DiscoveryEngine::DESCUBIERTO.
        // eventos_alcanzados forma parte del contrato; qué evento revela qué campo está bloqueado.
        return $base + [
            'visible_jugador' => false,
            'valor' => null,
            '_nota' => $politica === DiscoveryVisibilityPolicy::POR_EVENTO
                ? 'por_evento acepta eventos_alcanzados; el puente evento→campo no está definido.'
                : null,
        ];
    }
}

