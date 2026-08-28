<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Respuestas jugables de Celestine a familias F1/F2/F7/F15 (y escucha F6).
 * Inclina conducta vía ConsejoEngine; no crea parejas ni modifica barras.
 */
final class MensajitoConsejoEngine
{
    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $mensaje
     * @return array<string, mixed>
     */
    public static function enriquecerParaUi(array &$partida, array $mensaje): array
    {
        $familia = (string) ($mensaje['familia_mensajito'] ?? '');
        if ($familia === '' || !BuzonEngine::tieneDecisionPendiente($mensaje)) {
            return $mensaje;
        }
        if (!empty($mensaje['opciones_consejo']) && is_array($mensaje['opciones_consejo'])) {
            return $mensaje;
        }
        $datos = is_array($mensaje['datos_familia'] ?? null) ? $mensaje['datos_familia'] : [];
        switch ($familia) {
            case 'f_opinion':
                $opciones = self::opcionesF1($partida, $datos);
                break;
            case 'f_dilema':
                $opciones = self::opcionesF2($partida, $datos);
                break;
            case 'f_curiosidad_celestine':
                $opciones = self::opcionesF15();
                break;
            case 'f_confidencia':
                $opciones = self::opcionesEscuchar();
                break;
            case 'f_promesa':
                $opciones = self::opcionesF14();
                break;
            case 'f_duda_permanencia':
                $opciones = self::opcionesF8();
                break;
            case 'f_mediacion':
                $opciones = self::opcionesF11($partida, $datos);
                break;
            default:
                $opciones = [];
        }
        if ($opciones !== []) {
            $mensaje['opciones_consejo'] = $opciones;
            switch ($familia) {
                case 'f_opinion':
                case 'f_dilema':
                    $mensaje['consejo_titulo'] = '¿Qué le dices?';
                    break;
                case 'f_curiosidad_celestine':
                    $mensaje['consejo_titulo'] = '¿Cómo respondes?';
                    break;
                case 'f_confidencia':
                    $mensaje['consejo_titulo'] = '¿Qué le contestas?';
                    break;
                case 'f_promesa':
                    $mensaje['consejo_titulo'] = '¿Qué le dices?';
                    break;
                default:
                    $mensaje['consejo_titulo'] = '¿Qué le dices?';
            }
        }
        return $mensaje;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function responderOpcion(
        array &$partida,
        string $mensajeId,
        string $opcionId,
        array $payload = []
    ): array {
        $mensaje = self::buscarRaw($partida, $mensajeId);
        if ($mensaje === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $familia = (string) ($mensaje['familia_mensajito'] ?? '');
        $rid = (string) ($mensaje['de_persona'] ?? '');
        if ($rid === '' || $familia === '') {
            return ['ok' => false, 'error' => 'mensaje_sin_familia'];
        }
        $datos = is_array($mensaje['datos_familia'] ?? null) ? $mensaje['datos_familia'] : [];
        $opciones = self::opcionesDeFamilia($partida, $familia, $datos);
        $op = null;
        foreach ($opciones as $cand) {
            if (is_array($cand) && (string) ($cand['id'] ?? '') === $opcionId) {
                $op = $cand;
                break;
            }
        }
        if ($op === null) {
            return ['ok' => false, 'error' => 'opcion_no_permitida', 'opcion' => $opcionId];
        }

        $consejoId = (string) ($op['consejo_id'] ?? '');
        $objetivoId = isset($op['objetivo_id']) && is_string($op['objetivo_id']) && $op['objetivo_id'] !== ''
            ? $op['objetivo_id']
            : null;
        $tema = (string) ($op['tema'] ?? 'romance');

        if ($consejoId !== '' && $consejoId !== 'decide_tu') {
            ConsejoEngine::responder($partida, $rid, $consejoId, $objetivoId, $tema);
            self::enlazarSeguimiento($partida, $rid, $consejoId, $mensajeId, $objetivoId);
        }
        self::microEmocion($partida, $rid, (string) ($op['emocion'] ?? ''));

        if ($familia === 'f_duda_permanencia') {
            MensajitoDudaPermanenciaEngine::marcarAtendidaPublico($partida, $rid, 'consejo');
        }
        if ($familia === 'f_confidencia' && in_array($opcionId, ['op_escucha', 'op_apoyo'], true)) {
            MensajitoPromesaEngine::registrarDesdeConfidencia(
                $partida,
                $rid,
                $datos,
                $mensajeId,
                (string) ($mensaje['hilo_id'] ?? $mensajeId)
            );
        }
        if ($familia === 'f_promesa') {
            MensajitoPromesaEngine::cerrarPromesa(
                $partida,
                $rid,
                (string) ($datos['clave'] ?? MensajitoPromesaEngine::claveDe($rid, $datos))
            );
        }

        $metaCierre = [
            'opcion_id' => $opcionId,
            'consejo_id' => $consejoId,
            'objetivo_id' => $objetivoId,
        ];
        if ($familia === 'f_confidencia') {
            $metaCierre['detallito_hook'] = ['pendiente' => true, 'motivo' => 'confidencia_escuchada'];
        }
        self::cerrarHilo($partida, $mensajeId, $metaCierre);

        return [
            'ok' => true,
            'opcion_id' => $opcionId,
            'consejo_id' => $consejoId,
            'mensaje_ui' => (string) ($op['eco_ui'] ?? ''),
        ];
    }

    /**
     * F7: dirigir atención a la ficha del vecino observado.
     *
     * @return array<string, mixed>
     */
    public static function investigar(array &$partida, string $mensajeId): array
    {
        $mensaje = self::buscarRaw($partida, $mensajeId);
        if ($mensaje === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $datos = is_array($mensaje['datos_familia'] ?? null) ? $mensaje['datos_familia'] : [];
        $observado = (string) ($datos['observado_id'] ?? '');
        if ($observado === '' || !isset($partida['residentes'][$observado])) {
            return ['ok' => false, 'error' => 'sin_observado'];
        }
        self::cerrarHilo($partida, $mensajeId, ['accion' => 'investigar', 'observado_id' => $observado]);
        return [
            'ok' => true,
            'abrir_ficha' => $observado,
            'mensaje_ui' => 'Vale, le echo un ojo.',
        ];
    }

    /**
     * F7: preset para organizar algo con el vecino observado.
     *
     * @return array<string, mixed>
     */
    public static function organizarAlgo(array &$partida, string $mensajeId): array
    {
        $mensaje = self::buscarRaw($partida, $mensajeId);
        if ($mensaje === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $fam = (string) ($mensaje['familia_mensajito'] ?? '');
        if ($fam === MensajitoDudaPermanenciaEngine::FAMILIA) {
            return MensajitoDudaPermanenciaEngine::organizarContacto($partida, $mensajeId);
        }
        $datos = is_array($mensaje['datos_familia'] ?? null) ? $mensaje['datos_familia'] : [];
        $observado = (string) ($datos['observado_id'] ?? '');
        if ($observado === '') {
            return ['ok' => false, 'error' => 'sin_observado'];
        }
        self::cerrarHilo($partida, $mensajeId, ['accion' => 'organizar_algo', 'observado_id' => $observado]);
        return [
            'ok' => true,
            'preset_organizar' => [
                'modo' => 'individual',
                'participantes' => [$observado],
                'tipo' => 'individual',
                'intencion' => 'animo',
            ],
            'mensaje_ui' => 'Vamos a montarle algo.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function noMeterse(array &$partida, string $mensajeId): array
    {
        $mensaje = self::buscarRaw($partida, $mensajeId);
        if ($mensaje === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $fam = (string) ($mensaje['familia_mensajito'] ?? '');
        if ($fam === MensajitoDudaPermanenciaEngine::FAMILIA) {
            MensajitoDudaPermanenciaEngine::marcarEscaladaPublico(
                $partida,
                (string) ($mensaje['de_persona'] ?? ''),
                $mensajeId
            );
        }
        self::cerrarHilo($partida, $mensajeId, ['accion' => 'no_meterse']);
        return ['ok' => true, 'mensaje_ui' => 'De acuerdo, no me meto.'];
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<array<string, mixed>>
     */
    private static function opcionesF1(array $partida, array $datos): array
    {
        $otro = (string) ($datos['otro_nombre'] ?? $datos['otro_id'] ?? 'esa persona');
        $otroId = (string) ($datos['otro_id'] ?? '');
        return [
            [
                'id' => 'op_animar',
                'etiqueta' => 'Yo ahí veo buena onda con ' . $otro,
                'estilo' => 'primario',
                'consejo_id' => 'lanzate',
                'objetivo_id' => $otroId !== '' ? $otroId : null,
                'tema' => 'romance',
                'emocion' => 'alegre',
            ],
            [
                'id' => 'op_cautela',
                'etiqueta' => 'Yo iría con calma, no sé si encajáis tanto',
                'estilo' => 'suave',
                'consejo_id' => 'no_es_el_momento',
                'objetivo_id' => $otroId !== '' ? $otroId : null,
                'tema' => 'romance',
                'emocion' => '',
            ],
            [
                'id' => 'op_neutro',
                'etiqueta' => 'Eso lo decides tú, ¿eh?',
                'estilo' => 'suave',
                'consejo_id' => 'decide_tu',
                'tema' => 'romance',
                'emocion' => '',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<array<string, mixed>>
     */
    private static function opcionesF2(array $partida, array $datos): array
    {
        $aNom = (string) ($datos['opcion_a_nombre'] ?? 'uno');
        $bNom = (string) ($datos['opcion_b_nombre'] ?? 'otro');
        $aId = (string) ($datos['opcion_a_id'] ?? '');
        $bId = (string) ($datos['opcion_b_id'] ?? '');
        return [
            [
                'id' => 'op_inclinar_a',
                'etiqueta' => 'Con ' . $aNom . ' tienes buena vibra',
                'estilo' => 'primario',
                'consejo_id' => 'lanzate',
                'objetivo_id' => $aId !== '' ? $aId : null,
                'tema' => 'romance',
                'emocion' => 'alegre',
            ],
            [
                'id' => 'op_inclinar_b',
                'etiqueta' => 'Yo me decantaría por ' . $bNom,
                'estilo' => 'primario',
                'consejo_id' => 'lanzate',
                'objetivo_id' => $bId !== '' ? $bId : null,
                'tema' => 'romance',
                'emocion' => 'alegre',
            ],
            [
                'id' => 'op_conocer_ambos',
                'etiqueta' => 'Conóceles mejor a los dos y luego me cuentas',
                'estilo' => 'suave',
                'consejo_id' => 'queda_mas',
                'tema' => 'romance',
                'emocion' => '',
            ],
            [
                'id' => 'op_neutro',
                'etiqueta' => 'No voy a elegir por ti',
                'estilo' => 'suave',
                'consejo_id' => 'decide_tu',
                'tema' => 'romance',
                'emocion' => '',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function opcionesF15(): array
    {
        return [
            [
                'id' => 'op_calida',
                'etiqueta' => 'Buena pregunta… algún día te cuento 😊',
                'estilo' => 'primario',
                'consejo_id' => 'decide_tu',
                'tema' => 'personal',
                'emocion' => 'alegre',
            ],
            [
                'id' => 'op_evasiva',
                'etiqueta' => 'Prefiero escucharos a vosotros',
                'estilo' => 'suave',
                'consejo_id' => 'decide_tu',
                'tema' => 'personal',
                'emocion' => '',
            ],
            [
                'id' => 'op_honesta',
                'etiqueta' => 'Aquí estoy para ayudaros, eso sí',
                'estilo' => 'suave',
                'consejo_id' => 'decide_tu',
                'tema' => 'personal',
                'emocion' => '',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function opcionesF14(): array
    {
        return [
            [
                'id' => 'op_recuerda',
                'etiqueta' => 'Tienes razón, lo recuerdo',
                'estilo' => 'primario',
                'consejo_id' => 'decide_tu',
                'tema' => 'personal',
                'emocion' => '',
                'eco_ui' => 'Gracias por avisarme.',
            ],
            [
                'id' => 'op_animo',
                'etiqueta' => 'Ánimo, puedes con esto',
                'estilo' => 'suave',
                'consejo_id' => 'decide_tu',
                'tema' => 'personal',
                'emocion' => 'alegre',
                'eco_ui' => 'Lo intentaré.',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function opcionesEscuchar(): array
    {
        return [
            [
                'id' => 'op_escucha',
                'etiqueta' => 'Te escucho, cuéntame más',
                'estilo' => 'primario',
                'consejo_id' => 'decide_tu',
                'tema' => 'personal',
                'emocion' => 'alegre',
            ],
            [
                'id' => 'op_apoyo',
                'etiqueta' => 'Lo siento, aquí estoy si me necesitas',
                'estilo' => 'suave',
                'consejo_id' => 'decide_tu',
                'tema' => 'personal',
                'emocion' => 'alegre',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function opcionesF8(): array
    {
        return [
            [
                'id' => 'op_quedamos',
                'etiqueta' => 'Claro que sí, aquí tienes sitio',
                'estilo' => 'primario',
                'consejo_id' => 'decide_tu',
                'tema' => 'personal',
                'emocion' => 'alegre',
                'eco_ui' => 'Gracias… de verdad.',
            ],
            [
                'id' => 'op_escucha',
                'etiqueta' => 'Te escucho, cuéntame más',
                'estilo' => 'suave',
                'consejo_id' => 'decide_tu',
                'tema' => 'personal',
                'emocion' => '',
            ],
            [
                'id' => 'op_animo',
                'etiqueta' => 'Ánimo, esto se arregla',
                'estilo' => 'suave',
                'consejo_id' => 'decide_tu',
                'tema' => 'personal',
                'emocion' => 'alegre',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<array<string, mixed>>
     */
    private static function opcionesF11(array $partida, array $datos): array
    {
        $otro = (string) ($datos['otro_nombre'] ?? 'esa persona');
        $otroId = (string) ($datos['otro_id'] ?? '');
        return [
            [
                'id' => 'op_hablar',
                'etiqueta' => 'Habladlo con calma, yo os echo una mano',
                'estilo' => 'primario',
                'consejo_id' => 'queda_mas',
                'objetivo_id' => $otroId !== '' ? $otroId : null,
                'tema' => 'social',
                'emocion' => '',
            ],
            [
                'id' => 'op_neutral',
                'etiqueta' => 'Dale tiempo a ' . $otro . ', no fuerces',
                'estilo' => 'suave',
                'consejo_id' => 'no_es_el_momento',
                'objetivo_id' => $otroId !== '' ? $otroId : null,
                'tema' => 'social',
                'emocion' => '',
            ],
            [
                'id' => 'op_apoyo',
                'etiqueta' => 'Estoy contigo, lo que necesites',
                'estilo' => 'suave',
                'consejo_id' => 'decide_tu',
                'tema' => 'personal',
                'emocion' => 'alegre',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<array<string, mixed>>
     */
    private static function opcionesDeFamilia(array $partida, string $familia, array $datos): array
    {
        switch ($familia) {
            case 'f_opinion':
                return self::opcionesF1($partida, $datos);
            case 'f_dilema':
                return self::opcionesF2($partida, $datos);
            case 'f_curiosidad_celestine':
                return self::opcionesF15();
            case 'f_confidencia':
                return self::opcionesEscuchar();
            case 'f_promesa':
                return self::opcionesF14();
            case 'f_duda_permanencia':
                return self::opcionesF8();
            case 'f_mediacion':
                return self::opcionesF11($partida, $datos);
            default:
                return [];
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function cerrarHiloPublico(array &$partida, string $mensajeId, array $meta): void
    {
        self::cerrarHilo($partida, $mensajeId, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private static function cerrarHilo(array &$partida, string $mensajeId, array $meta): void
    {
        foreach ($partida['buzon'] as &$m) {
            if (!is_array($m) || (string) ($m['id'] ?? '') !== $mensajeId) {
                continue;
            }
            $m['respuesta_celestine'] = $meta;
            $m['seguimiento_pendiente'] = false;
            $m['hilo_estado'] = 'respondido';
            $hiloId = (string) ($m['hilo_id'] ?? $mensajeId);
            $partida['mensajitos_hilos'][$hiloId] = [
                'mensaje_id' => $mensajeId,
                'residente_id' => (string) ($m['de_persona'] ?? ''),
                'familia' => (string) ($m['familia_mensajito'] ?? ''),
                'estado' => 'respondido',
                'respuesta' => $meta,
                'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            ];
            break;
        }
        unset($m);
    }

    private static function enlazarSeguimiento(
        array &$partida,
        string $rid,
        string $consejoId,
        string $mensajeId,
        ?string $objetivoId
    ): void {
        $pend = $partida['seguimientos_consejo_pendientes'] ?? [];
        for ($i = count($pend) - 1; $i >= 0; $i--) {
            if (!is_array($pend[$i])) {
                continue;
            }
            if (($pend[$i]['residente_id'] ?? '') !== $rid || ($pend[$i]['consejo_id'] ?? '') !== $consejoId) {
                continue;
            }
            if (!empty($pend[$i]['resuelto'])) {
                continue;
            }
            $partida['seguimientos_consejo_pendientes'][$i]['mensaje_origen_id'] = $mensajeId;
            if ($objetivoId !== null && $objetivoId !== '') {
                $partida['seguimientos_consejo_pendientes'][$i]['objetivo_id'] = $objetivoId;
            }
            return;
        }
    }

    private static function microEmocion(array &$partida, string $rid, string $hacia): void
    {
        if ($hacia === '' || !isset($partida['residentes'][$rid])) {
            return;
        }
        $res = &$partida['residentes'][$rid];
        EstadoEmocional::ensureResidente($res, $partida['reloj'] ?? null);
        $actual = (string) ($res['runtime']['estado_emocional']['id'] ?? EstadoEmocional::NEUTRO);
        if ($hacia === EstadoEmocional::ALEGRE && in_array($actual, [EstadoEmocional::TRISTE, EstadoEmocional::NEUTRO], true)) {
            $reloj = $partida['reloj'] ?? [];
            $res['runtime']['estado_emocional'] = EstadoEmocional::estructura(
                EstadoEmocional::ALEGRE,
                1,
                'consejo_celestine',
                EstadoEmocional::marcaReloj($reloj),
                EstadoEmocional::hastaDesdeDuracion($reloj, 12),
                ['fuente' => 'mensajito_consejo'],
                12
            );
            $res['runtime']['animo'] = EstadoEmocional::ALEGRE;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function buscarRaw(array $partida, string $mensajeId): ?array
    {
        foreach ($partida['buzon'] ?? [] as $m) {
            if (is_array($m) && (string) ($m['id'] ?? '') === $mensajeId) {
                return $m;
            }
        }
        return null;
    }

    /**
     * Evita repetir el mismo mensajito (familia + objetivo) en ventana reciente.
     */
    public static function yaExisteHiloReciente(array $partida, string $residenteId, string $familia, string $clave): bool
    {
        $now = ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24 + (int) ($partida['reloj']['hora_actual'] ?? 0);
        foreach ($partida['mensajitos_historial'] ?? [] as $h) {
            if (!is_array($h)) {
                continue;
            }
            if (($h['residente_id'] ?? '') !== $residenteId) {
                continue;
            }
            if (($h['familia'] ?? '') !== $familia) {
                continue;
            }
            if (($h['clave'] ?? '') !== $clave) {
                continue;
            }
            $t = ((int) ($h['dia'] ?? 0)) * 24 + (int) ($h['hora'] ?? 0);
            if ($now - $t < 72) {
                return true;
            }
        }
        return false;
    }
}
