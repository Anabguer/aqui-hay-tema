<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Feedback de Mensajitos B4: distingue cumplida / caducada / ignorada /
 * bloqueada por un tercero. Un mensajito corto por resultado, copy variado.
 * No es narrativa: solo el eco del encargo real.
 */
final class PeticionFeedback
{
    public const TIPO_RESULTADO = 'peticion_resultado';

    /** @return list<string> */
    private static function poolCumplida(): array
    {
        return [
            '"{texto}" · Hecho. {nombre} está que se sale.',
            'Encargo cumplido: "{texto}". {nombre} no para de sonreír.',
            '{nombre} lo pedía por Mensajitos y ya está hecho. Le ha hecho ilusión.',
            '"{texto}" · Celestine lo organizó y a {nombre} le ha salido redondo.',
        ];
    }

    /** @return list<string> */
    private static function poolCaducada(): array
    {
        return [
            'Se le ha pasado el arroz: "{texto}". A {nombre} se le quedó la idea a medias.',
            '"{texto}" · No se hizo nada y a {nombre} se le fue pasando.',
            '{nombre} esperaba eso de "{texto}" y al final nada. Se le nota.',
            'El mensajito de {nombre} ("{texto}") se ha quedado sin respuesta.',
        ];
    }

    /** @return list<string> */
    private static function poolIgnorada(): array
    {
        return [
            '{nombre} ha visto que pasabas de su mensajito ("{texto}").',
            '"{texto}" · Lo dejaste ahí y {nombre} lo ha notado.',
            'A {nombre} le ha sentado regular que ignoraras su petición.',
        ];
    }

    /** @return list<string> */
    private static function poolRechazoTercero(): array
    {
        return [
            '"{texto}" · {pet} sí quería, pero {otro} ha dicho que no.',
            '{pet} tenía ganas ("{texto}"), pero {otro} prefiere que sea otro día.',
            'Nada esta vez: {pet} iba con todo, pero {otro} no ha querido.',
            '"{texto}" · La idea era de {pet}, pero {otro} no está por la labor.',
        ];
    }

    /**
     * Petición cumplida: eco positivo.
     *
     * @param array<string, mixed> $peticion
     */
    public static function alCumplir(array &$partida, array $peticion, ?GameLogger $logger = null): void
    {
        self::emitir($partida, $peticion, self::poolCumplida(), [], $logger);
    }

    /**
     * Petición caducada o ignorada explícitamente: eco negativo distinto.
     *
     * @param array<string, mixed> $peticion
     */
    public static function alCaducar(array &$partida, array $peticion, bool $ignorada, ?GameLogger $logger = null): void
    {
        self::emitir(
            $partida,
            $peticion,
            $ignorada ? self::poolIgnorada() : self::poolCaducada(),
            [],
            $logger
        );
    }

    /**
     * El peticionario quería, pero un tercero necesario ha dicho que no.
     * La petición SIGUE abierta: el copy debe dejarlo claro.
     *
     * @param array<string, mixed> $peticion
     */
    public static function alRechazoTercero(array &$partida, array $peticion, string $terceroId, ?GameLogger $logger = null): void
    {
        if ($terceroId === '') {
            return;
        }
        self::emitir($partida, $peticion, self::poolRechazoTercero(), [
            '{pet}' => IdentidadPublica::nombre($partida, (string) ($peticion['residente_id'] ?? '')),
            '{otro}' => IdentidadPublica::nombre($partida, $terceroId),
        ], $logger);
    }

    /**
     * @param list<string> $pool
     * @param array<string, string> $extraTokens
     * @param array<string, mixed> $peticion
     */
    private static function emitir(array &$partida, array $peticion, array $pool, array $extraTokens, ?GameLogger $logger): void
    {
        if ($pool === [] || !FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return;
        }
        // Labs B3/B4: sin buzón, sin eventos (igual que PeticionEngine::crear).
        if (!empty($partida['_lab_peticiones_b4']) || !empty($partida['_lab_misiones_b3'])) {
            return;
        }
        $pid = (string) ($peticion['id'] ?? '');
        if ($pid === '') {
            return;
        }
        $rid = (string) ($peticion['residente_id'] ?? '');
        $tokens = array_merge([
            '{texto}' => (string) ($peticion['texto'] ?? ''),
            '{nombre}' => IdentidadPublica::nombre($partida, $rid),
        ], $extraTokens);
        $rng = RngService::fromPartida($partida);
        $plantilla = (string) $pool[$rng->nextInt(0, count($pool) - 1)];
        $rng->persistToPartida($partida);
        $texto = strtr($plantilla, $tokens);
        $plazo = PeticionPuebloEngine::plazoHumano($peticion, $partida);
        if ($plazo !== '' && $plazo !== 'Cuando puedas.') {
            $texto .= ' ' . $plazo;
        }
        $r = BuzonEngine::crear($partida, [
            'clasificacion' => BuzonEngine::IMPORTANTE,
            'tipo' => self::TIPO_RESULTADO,
            'canal' => BuzonEngine::CANAL_BUZON,
            'de_persona' => $rid !== '' ? $rid : null,
            'actores' => array_values(array_filter([$rid])),
            'texto' => $texto,
            'peticion_id' => $pid,
            'origen' => [
                'evento_id' => $pid,
                'tipo_evento' => self::TIPO_RESULTADO,
                'es_narrativo' => false,
                'informacion_revelada' => [],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);
        DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
            'mensaje' => $r['mensaje'] ?? null,
            'origen_evento' => self::TIPO_RESULTADO,
        ], $logger, 'PeticionFeedback');
    }
}
