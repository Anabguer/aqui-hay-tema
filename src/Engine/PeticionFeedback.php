<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Feedback de Mensajitos B4: distingue cumplida / caducada / ignorada /
 * bloqueada por un tercero.
 *
 * CONTRATO NARRATIVO: el eco lo escribe EL MISMO NPC que pidió, en primera
 * persona y dirigido a Celestine. Nunca un informe del sistema sobre el NPC
 * ("se ha quedado sin respuesta", "no se hizo nada"). El hecho mecánico no
 * cambia: solo cómo se comunica.
 */
final class PeticionFeedback
{
    public const TIPO_RESULTADO = 'peticion_resultado';

    /**
     * Petición cumplida: el peticionario agradece a Celestine.
     *
     * @param array<string, mixed> $peticion
     */
    public static function alCumplir(array &$partida, array $peticion, ?GameLogger $logger = null): void
    {
        self::emitir($partida, $peticion, 'resultado_cumplida', [], false, $logger);
        RegaloRecompensaEngine::porPeticionCumplida($partida, $peticion, $logger);
    }

    /**
     * Petición caducada o ignorada explícitamente: EL MISMO NPC cierra su
     * propio mensaje ("olvídalo, se me pasaron las ganas").
     *
     * @param array<string, mixed> $peticion
     */
    public static function alCaducar(array &$partida, array $peticion, bool $ignorada, ?GameLogger $logger = null): void
    {
        self::emitir(
            $partida,
            $peticion,
            $ignorada ? 'resultado_ignorada' : 'resultado_caducada',
            [],
            false,
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
        self::emitir($partida, $peticion, 'resultado_rechazo_tercero', [
            'otro' => IdentidadPublica::nombre($partida, $terceroId),
        ], true, $logger);
    }

    /**
     * @param array<string, string|int|null> $varsExtra
     * @param array<string, mixed> $peticion
     */
    private static function emitir(
        array &$partida,
        array $peticion,
        string $familia,
        array $varsExtra,
        bool $conPlazoRestante,
        ?GameLogger $logger
    ): void {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
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
        $vars = array_merge([
            'texto' => (string) ($peticion['texto'] ?? ''),
        ], $varsExtra);
        $params = is_array($peticion['params'] ?? null) ? $peticion['params'] : [];
        if (!isset($vars['otro']) && !empty($params['otro'])) {
            $nombreOtro = IdentidadPublica::nombre($partida, (string) $params['otro']);
            if ($nombreOtro !== '' && $nombreOtro !== (string) $params['otro']) {
                $vars['otro'] = $nombreOtro;
            }
        }
        $seed = $familia . '|' . $pid . '|' . (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        if ($rid !== '') {
            // Paridad RNG con el sistema anterior: una tirada canónica por eco.
            $rng = RngService::fromPartida($partida);
            $texto = MensajitoVoz::lineaRng($partida, $familia, $vars, $rid, $rng);
        } else {
            $texto = MensajitoVoz::linea($partida, $familia, $vars, $seed, null);
        }
        if ($texto === '') {
            return;
        }
        if ($conPlazoRestante) {
            // Sigue abierta: el plazo restante se comunica a Celestine.
            $plazo = PeticionPuebloEngine::plazoHumano($peticion, $partida);
            if ($plazo !== '' && $plazo !== 'Cuando puedas.') {
                $texto .= ' ' . $plazo;
            }
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
