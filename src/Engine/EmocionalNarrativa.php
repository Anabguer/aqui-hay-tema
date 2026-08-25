<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Copy y cotilleo derivados del origen emocional real del motor.
 * Sin números internos ni secretos; solo lo observable o deducible.
 */
final class EmocionalNarrativa
{
    public static function esSignificativo(string $estadoId): bool
    {
        $id = EstadoEmocional::canonId($estadoId);
        return in_array($id, [EstadoEmocional::TRISTE, EstadoEmocional::ENFADADO, EstadoEmocional::ALEGRE], true);
    }

    /**
     * Explicación humana completa del estado actual, derivada de origen+contexto.
     * Null si el estado es neutro o el origen no es explicable.
     * Nunca expone IDs técnicos, valores internos ni deltas.
     *
     * @param array<string, mixed> $estado
     * @return array{texto_estado: string, explicacion: string, desde_texto: string, diario_evento_id: ?string}|null
     */
    public static function explicacionCompleta(array $partida, string $residenteId, array $estado): ?array
    {
        $estadoId = EstadoEmocional::canonId((string) ($estado['id'] ?? ''));
        if ($estadoId === EstadoEmocional::NEUTRO || !self::esSignificativo($estadoId)) {
            return null;
        }
        $origen = (string) ($estado['origen'] ?? '');
        $ctx = is_array($estado['contexto'] ?? null) ? $estado['contexto'] : [];
        $nombre = IdentidadPublica::nombre($partida, $residenteId);
        if ($nombre === '') {
            return null;
        }

        $explicacion = null;
        $diarioEventoId = null;

        switch ($origen) {
            case 'encuentro':
            case 'encuentro_intervencion':
                $res = (string) ($ctx['resultado_experiencia'] ?? '');
                $otroNombre = self::nombreOtroDeEncuentro($partida, $residenteId, $ctx);
                $motivo = (string) ($ctx['motivo'] ?? '');
                if ($motivo === 'hobby_recuperacion' || $origen === 'hobby_recuperacion') {
                    $explicacion = 'Un rato con su hobby le ha sentado de fábula.';
                } elseif ($res === 'muy_mal') {
                    $explicacion = 'Su encuentro con ' . $otroNombre . ' no salió como esperaba. Aquello la dejó hecha polv' . self::oA($partida, $residenteId) . '.';
                    $diarioEventoId = self::eventoDiarioDeEncuentro($ctx);
                } elseif ($res === 'mal') {
                    $explicacion = 'Compartió un rato con ' . $otroNombre . ' que se torció, y salió de allí con el ánimo por los suelos.';
                    $diarioEventoId = self::eventoDiarioDeEncuentro($ctx);
                } elseif ($estadoId === EstadoEmocional::ALEGRE) {
                    $explicacion = 'Ha tenido un encuentro que le ha animado el día.';
                    $diarioEventoId = null;
                } else {
                    $explicacion = 'Su estado cambió después de un encuentro reciente.';
                    $diarioEventoId = self::eventoDiarioDeEncuentro($ctx);
                }
                break;

            case 'perder_trabajo':
                $explicacion = 'Le han soltado del trabajo. Anda con la moral por los suelos y mucho tiempo libre entre manos.';
                $diarioEventoId = self::eventoDiarioDeTrabajo($partida, $residenteId, 'perder', $estado);
                break;

            case 'encontrar_trabajo':
                $explicacion = 'Ha encontrado trabajo y hoy se le ve por las nubes.';
                $diarioEventoId = self::eventoDiarioDeTrabajo($partida, $residenteId, 'encontrar', $estado);
                break;

            case 'rechazo_repetido':
                $hacia = (string) ($ctx['hacia'] ?? '');
                $nombreOtro = $hacia !== '' && $hacia !== $residenteId ? IdentidadPublica::nombre($partida, $hacia) : '';
                if ($nombreOtro !== '') {
                    $explicacion = $nombreOtro . ' le ha dicho que no demasiadas veces. A la larga, eso pesa.';
                    $diaDesde = (int) ($estado['desde']['dia'] ?? 0);
                    if ($diaDesde > 0) {
                        // Mismo evento_id determinista que DiarioResidenteBridge.
                        $candidato = 'rechazo_repetido:' . $residenteId . ':' . $hacia . ':' . $diaDesde;
                        if (DiarioEngine::entradaPorEvento($partida, $candidato) !== null) {
                            $diarioEventoId = $candidato;
                        }
                    }
                } else {
                    $explicacion = 'Le han rechazado planes demasiadas veces seguidas.';
                }
                break;

            case 'hobby_recuperacion':
            case 'encuentro_y_hobby':
                $explicacion = 'Un rato a solas con su hobby le ha levantado el ánimo.';
                break;

            default:
                return null; // inicial, expiración, manual: nada explicable
        }

        return [
            'texto_estado' => 'Está ' . self::textoEstado($estadoId, $partida, $residenteId),
            'explicacion' => $explicacion,
            'desde_texto' => self::desdeTexto($estado, $partida),
            'diario_evento_id' => ($diarioEventoId !== null && DiarioEngine::entradaPorEvento($partida, $diarioEventoId) !== null)
                ? $diarioEventoId
                : null,
        ];
    }

    private static function textoEstado(string $estadoId, array $partida, string $rid): string
    {
        switch ($estadoId) {
            case EstadoEmocional::ALEGRE:
                return 'feliz';
            case EstadoEmocional::TRISTE:
                return 'triste';
            case EstadoEmocional::ENFADADO:
                return 'enfadad' . self::oA($partida, $rid);
        }
        return $estadoId;
    }

    /** @param array<string, mixed> $estado */
    private static function desdeTexto(array $estado, array $partida): string
    {
        $desde = is_array($estado['desde'] ?? null) ? $estado['desde'] : [];
        $diaDesde = (int) ($desde['dia'] ?? 0);
        $hoy = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        if ($diaDesde <= 0) {
            return 'Desde hace poco.';
        }
        $dias = max(0, $hoy - $diaDesde);
        if ($dias === 0) {
            return 'Desde hoy mismo.';
        }
        return 'Desde hace ' . $dias . ' día' . ($dias === 1 ? '.' : 's.');
    }

    /** @param array<string, mixed> $ctx */
    private static function nombreOtroDeEncuentro(array $partida, string $residenteId, array $ctx): string
    {
        $encId = (string) ($ctx['encuentro_id'] ?? '');
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc) || (string) ($enc['id'] ?? '') !== $encId) {
                continue;
            }
            foreach (($enc['participantes'] ?? []) as $pid) {
                $pid = (string) $pid;
                if ($pid !== $residenteId && isset($partida['residentes'][$pid])) {
                    $n = IdentidadPublica::nombre($partida, $pid);
                    if ($n !== '') {
                        return $n;
                    }
                }
            }
        }
        return 'otra persona'; // sin nombre resolvible: copy genérico sin IDs
    }

    /** @param array<string, mixed> $ctx */
    private static function eventoDiarioDeEncuentro(array $ctx): ?string
    {
        $encId = (string) ($ctx['encuentro_id'] ?? '');
        return $encId !== '' ? 'encuentro:' . $encId : null;
    }

    /**
     * Evento_id del diario para ánimos de trabajo (mismo formato que DiarioResidenteBridge).
     * Solo se devuelve si la entrada existe: partidas antiguas sin diario no enlazan.
     *
     * @param array<string, mixed> $estado
     */
    private static function eventoDiarioDeTrabajo(array $partida, string $residenteId, string $variante, array $estado): ?string
    {
        $dia = (int) ($estado['desde']['dia'] ?? ($partida['reloj']['dia_pueblo'] ?? 0));
        if ($dia <= 0) {
            return null;
        }
        $candidato = 'trabajo_' . $variante . ':' . $residenteId . ':' . $dia;
        return DiarioEngine::entradaPorEvento($partida, $candidato) !== null ? $candidato : null;
    }

    /**
     * Pista breve en ficha (sin exponer reglas internas).
     *
     * @param array<string, mixed> $estado
     */
    public static function pistaFicha(array $estado): ?string
    {
        if (!self::esSignificativo((string) ($estado['id'] ?? ''))) {
            return null;
        }
        $origen = (string) ($estado['origen'] ?? '');
        $ctx = is_array($estado['contexto'] ?? null) ? $estado['contexto'] : [];

        switch ($origen) {
            case 'perder_trabajo':
                return 'Acaba de perder el trabajo.';
            case 'encontrar_trabajo':
                return 'Acaba de encontrar trabajo.';
            case 'rechazo_repetido':
                return 'Le han rechazado planes repetidas veces.';
            case 'encuentro':
            case 'encuentro_intervencion':
                $res = (string) ($ctx['resultado_experiencia'] ?? '');
                if ($res === 'muy_mal') {
                    return 'Le ha sentado muy mal un encuentro reciente.';
                }
                if ($res === 'mal') {
                    return 'Ha salido malhumorada de un encuentro reciente.';
                }
                if (($estado['id'] ?? '') === EstadoEmocional::ALEGRE) {
                    return 'Ha tenido un encuentro que le ha animado.';
                }
                return 'Su estado cambió tras un encuentro reciente.';
            case 'hobby_recuperacion':
            case 'encuentro_y_hobby':
                return 'Un rato con su hobby le ha sentado bien.';
            default:
                return null;
        }
    }

    /**
     * Texto social para El Cotilleo / buzón.
     */
    public static function cotilleoParaOrigen(
        array $partida,
        string $residenteId,
        string $origen,
        array $contexto = []
    ): ?string {
        $nombre = IdentidadPublica::nombre($partida, $residenteId);
        if ($nombre === '') {
            return null;
        }
        $oA = self::oA($partida, $residenteId);

        switch ($origen) {
            case 'perder_trabajo':
                return 'Parece que a ' . $nombre . ' le han soltado del trabajo. Está hecha polv' . $oA . '.';
            case 'encontrar_trabajo':
                return $nombre . ' ha encontrado trabajo. Se le nota más animad' . $oA . '.';
            case 'rechazo_repetido':
                $quien = (string) ($contexto['hacia'] ?? $contexto['quien'] ?? '');
                $nomQ = $quien !== '' ? IdentidadPublica::nombre($partida, $quien) : 'alguien';
                if ($nomQ === '') {
                    $nomQ = 'alguien';
                }
                return $nombre . ' está desanimad' . $oA . ': ' . $nomQ . ' le ha dicho que no demasiadas veces.';
            case 'encuentro':
            case 'encuentro_intervencion':
                $res = (string) ($contexto['resultado_experiencia'] ?? '');
                if ($res === 'muy_mal') {
                    return 'A ' . $nombre . ' le ha sentado fatal un encuentro. Se le nota en la cara.';
                }
                if ($res === 'mal') {
                    return $nombre . ' ha salido de un encuentro con el ánimo por los suelos.';
                }
                return null;
            default:
                return null;
        }
    }

    /**
     * Publica cotilleo en buzón si hay copy y el flag buzón está activo.
     *
     * @param array<string, mixed> $metaExtra
     */
    public static function publicarCotilleo(
        array &$partida,
        string $residenteId,
        string $origen,
        array $contexto = [],
        ?GameLogger $logger = null,
        array $metaExtra = []
    ): ?array {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return null;
        }
        $texto = self::cotilleoParaOrigen($partida, $residenteId, $origen, $contexto);
        if ($texto === null || $texto === '') {
            return null;
        }
        $tipo = (string) ($metaExtra['tipo'] ?? 'estado_emocional');
        $categoria = (string) ($metaExtra['categoria'] ?? CotilleoCategoria::DRAMA);
        $destacado = (bool) ($metaExtra['destacado'] ?? true);

        return BuzonEngine::crear($partida, [
            'clasificacion' => BuzonEngine::COTILLEO,
            'tipo' => $tipo,
            'texto' => $texto,
            'cotilleo_meta' => CotilleoCategoria::meta($categoria, $destacado),
            'de_persona' => $residenteId,
            'actores' => [$residenteId],
            'importancia' => 'relevante',
            'origen' => [
                'evento_id' => $metaExtra['evento_id'] ?? $origen,
                'tipo_evento' => 'estado_emocional',
                'es_narrativo' => false,
                'informacion_revelada' => [
                    'origen_emocional' => $origen,
                    'contexto' => $contexto,
                ],
                '_placeholder' => false,
            ],
            '_placeholder_contenido' => false,
        ]);
    }

    private static function oA(array $partida, string $rid): string
    {
        $g = (string) ($partida['residentes'][$rid]['identidad_publica']['genero'] ?? '');
        return $g === 'mujer' ? 'a' : 'o';
    }
}
