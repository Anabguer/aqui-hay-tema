<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Copy y cotilleo derivados del origen emocional real del motor.
 * Sin números internos ni secretos; solo lo observable o deducible.
 */
final class EmocionalNarrativa
{
    /**
     * Pensamiento en primera persona para el modal de ánimo.
     * Generado exclusivamente a partir de datos reales del estado.
     * Null si neutro o origen no explicable.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $estado
     * @return array{texto_estado: string, pensamiento: string, desde_texto: ?string, estado_id: string}|null
     */
    public static function pensamientoModal(array $partida, string $residenteId, array $estado): ?array
    {
        $estadoId = EstadoEmocional::canonId((string) ($estado['id'] ?? ''));
        if ($estadoId === EstadoEmocional::NEUTRO || !self::esSignificativo($estadoId)) {
            return null;
        }

        $origen = (string) ($estado['origen'] ?? '');
        $ctx = is_array($estado['contexto'] ?? null) ? $estado['contexto'] : [];
        $pensamiento = null;

        switch ($origen) {
            case 'cumple_felicidad':
                $pensamiento = 'Hoy me han dedicado unas palabras muy bonitas. Me ha encantado.';
                break;
            case 'encontrar_trabajo':
                $pensamiento = 'He encontrado trabajo y estoy que me salgo.';
                break;
            case 'hobby_recuperacion':
            case 'encuentro_y_hobby':
                $pensamiento = 'Me he dedicado un rato a lo mío y estoy mucho mejor. A veces hace falta.';
                break;
            case 'consejo_celestine':
                $pensamiento = 'Me has dado un buen consejo. Se te nota.';
                break;
            case 'formacion_pareja':
                $parejaId = (string) ($ctx['pareja_id'] ?? '');
                $nombrePareja = $parejaId !== '' ? IdentidadPublica::nombre($partida, $parejaId) : '';
                if ($nombrePareja !== '') {
                    $pensamiento = 'Estoy de enhorabuena. He empezado una relación con ' . $nombrePareja . ' y quería contártelo.';
                } else {
                    $pensamiento = 'Estoy de enhorabuena. He empezado una relación y quería contártelo.';
                }
                break;
            case 'perder_trabajo':
                $pensamiento = 'Me han soltado del trabajo. Ando con la moral por los suelos.';
                break;
            case 'rechazo_repetido':
                $pensamiento = 'Esta vez no doy más. Necesito un respiro de planes, ¿de acuerdo?';
                break;
            case 'rechazo_emocional':
                $pensamiento = 'Me ha dolido que me hayan dicho que no. Necesito un momento.';
                break;
            case 'encuentro':
            case 'encuentro_intervencion':
                $res = (string) ($ctx['resultado_experiencia'] ?? '');
                if ($res === 'bien' || $res === 'muy_bien') {
                    $pensamiento = 'He pasado un buen rato y se me ha pasado el mal humor.';
                } elseif ($res === 'mal' || $res === 'muy_mal') {
                    $pensamiento = 'He pasado un mal rato. Estoy de bajón.';
                }
                break;
            default:
                return null;
        }

        if ($pensamiento === null) {
            return null;
        }

        return [
            'texto_estado' => 'Estoy ' . self::textoEstadoPensamiento($estadoId, $partida, $residenteId),
            'pensamiento' => $pensamiento,
            'desde_texto' => self::desdeTextoModal($estado, $partida),
            'estado_id' => $estadoId,
        ];
    }

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
                $otroId = self::idOtroDeEncuentro($partida, $residenteId, $ctx);
                $motivo = (string) ($ctx['motivo'] ?? '');
                $histCtx = $otroId !== '' ? HistorialPar::contextoNarrativo($partida, $residenteId, $otroId) : '';
                if ($motivo === 'hobby_recuperacion' || $origen === 'hobby_recuperacion') {
                    $explicacion = 'Un rato con su hobby le ha sentado de fábula.';
                } elseif ($res === 'muy_mal') {
                    $explicacion = 'Su encuentro con ' . $otroNombre . ' no salió como esperaba. Aquello la dejó hecha polv' . GeneroConcordancia::oa($partida, $residenteId) . '.';
                    if ($histCtx !== '') {
                        $explicacion .= ' ' . ucfirst($histCtx) . '.';
                    }
                    $diarioEventoId = self::eventoDiarioDeEncuentro($ctx);
                } elseif ($res === 'mal') {
                    $explicacion = 'Compartió un rato con ' . $otroNombre . ' que se torció, y salió de allí con el ánimo por los suelos.';
                    if ($histCtx !== '') {
                        $explicacion .= ' ' . ucfirst($histCtx) . '.';
                    }
                    $diarioEventoId = self::eventoDiarioDeEncuentro($ctx);
                } elseif ($estadoId === EstadoEmocional::ALEGRE) {
                    $otroNombreLimpio = ($otroNombre !== '' && $otroNombre !== 'otra persona') ? $otroNombre : '';
                    if ($otroNombreLimpio !== '') {
                        $explicacion = 'Su encuentro con ' . $otroNombreLimpio . ' le ha animado el día.';
                    } else {
                        $explicacion = 'Ha tenido un encuentro que le ha animado el día.';
                    }
                    $diarioEventoId = self::eventoDiarioDeEncuentro($ctx);
                } else {
                    $explicacion = 'Su estado cambió después de un encuentro reciente.';
                    $diarioEventoId = self::eventoDiarioDeEncuentro($ctx);
                }
                break;

            case 'perder_trabajo':
                $explicacion = 'Le han soltado del trabajo. Anda con la moral por los suelos.';
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

            case 'rechazo_emocional':
                $hacia = (string) ($ctx['hacia'] ?? '');
                $nombreOtro = $hacia !== '' && $hacia !== $residenteId ? IdentidadPublica::nombre($partida, $hacia) : '';
                if ($nombreOtro !== '') {
                    $explicacion = 'Un rechazo de ' . $nombreOtro . ' le ha sentado mal. Necesita un respiro.';
                } else {
                    $explicacion = 'Le han rechazado un plan y le ha dolido.';
                }
                break;

            case 'hobby_recuperacion':
            case 'encuentro_y_hobby':
                $explicacion = 'Un rato a solas con su hobby le ha levantado el ánimo.';
                break;

            case 'cumple_felicidad':
                $explicacion = 'Ha recibido la enhorabuena de sus vecinos y se le nota content' . GeneroConcordancia::oa($partida, $residenteId) . '.';
                break;

            case 'consejo_celestine':
                $explicacion = 'Un buen consejo a tiempo puede cambiar el día de cualquiera.';
                break;

            case 'formacion_pareja':
                $parejaId = (string) ($ctx['pareja_id'] ?? '');
                $nombrePareja = $parejaId !== '' ? IdentidadPublica::nombre($partida, $parejaId) : '';
                if ($nombrePareja !== '') {
                    $explicacion = 'Está muy content' . GeneroConcordancia::oa($partida, $residenteId)
                        . ' desde que empezó a salir con ' . $nombrePareja . '.';
                } else {
                    $explicacion = 'Está muy content' . GeneroConcordancia::oa($partida, $residenteId)
                        . ' desde que empezó una relación.';
                }
                $diaDesde = (int) ($estado['desde']['dia'] ?? 0);
                if ($diaDesde > 0) {
                    $diarioEventoId = 'formacion_pareja:' . $residenteId . ':' . $diaDesde;
                }
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

    /**
     * Explicación en PRIMERA PERSONA para el Diario personal.
     * El residente escribe sobre su propia experiencia emocional.
     * Null si el estado es neutro o el origen no es explicable.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $estado
     * @return array{texto_estado: string, explicacion: string, desde_texto: string, diario_evento_id: ?string}|null
     */
    public static function explicacionParaDiario(array $partida, string $residenteId, array $estado): ?array
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
                $otroId = self::idOtroDeEncuentro($partida, $residenteId, $ctx);
                $motivo = (string) ($ctx['motivo'] ?? '');
                $histCtx = $otroId !== '' ? HistorialPar::contextoNarrativo($partida, $residenteId, $otroId) : '';
                if ($motivo === 'hobby_recuperacion' || $origen === 'hobby_recuperacion') {
                    $explicacion = 'Un rato con mi hobby me ha sentado de fábula.';
                } elseif ($res === 'muy_mal') {
                    $explicacion = 'Mi encuentro con ' . $otroNombre . ' no salió como esperaba. Aquello me dejó hecha polv' . GeneroConcordancia::oa($partida, $residenteId) . '.';
                    if ($histCtx !== '') {
                        $explicacion .= ' ' . ucfirst($histCtx) . '.';
                    }
                    $diarioEventoId = self::eventoDiarioDeEncuentro($ctx);
                } elseif ($res === 'mal') {
                    $explicacion = 'Compartí un rato con ' . $otroNombre . ' que se torció, y salí de allí con el ánimo por los suelos.';
                    if ($histCtx !== '') {
                        $explicacion .= ' ' . ucfirst($histCtx) . '.';
                    }
                    $diarioEventoId = self::eventoDiarioDeEncuentro($ctx);
                } elseif ($estadoId === EstadoEmocional::ALEGRE) {
                    $otroNombreLimpio = ($otroNombre !== '' && $otroNombre !== 'otra persona') ? $otroNombre : '';
                    if ($otroNombreLimpio !== '') {
                        $explicacion = 'Mi encuentro con ' . $otroNombreLimpio . ' me ha animado el día.';
                    } else {
                        $explicacion = 'He tenido un encuentro que me ha animado el día.';
                    }
                    $diarioEventoId = self::eventoDiarioDeEncuentro($ctx);
                } else {
                    $explicacion = 'Mi estado cambió después de un encuentro reciente.';
                    $diarioEventoId = self::eventoDiarioDeEncuentro($ctx);
                }
                break;

            case 'perder_trabajo':
                $explicacion = 'Me han soltado del trabajo. Ando con la moral por los suelos.';
                $diarioEventoId = self::eventoDiarioDeTrabajo($partida, $residenteId, 'perder', $estado);
                break;

            case 'encontrar_trabajo':
                $explicacion = 'He encontrado trabajo y hoy me siento por las nubes.';
                $diarioEventoId = self::eventoDiarioDeTrabajo($partida, $residenteId, 'encontrar', $estado);
                break;

            case 'rechazo_repetido':
                $hacia = (string) ($ctx['hacia'] ?? '');
                $nombreOtro = $hacia !== '' && $hacia !== $residenteId ? IdentidadPublica::nombre($partida, $hacia) : '';
                if ($nombreOtro !== '') {
                    $explicacion = $nombreOtro . ' me ha dicho que no demasiadas veces. A la larga, eso pesa.';
                    $diaDesde = (int) ($estado['desde']['dia'] ?? 0);
                    if ($diaDesde > 0) {
                        $candidato = 'rechazo_repetido:' . $residenteId . ':' . $hacia . ':' . $diaDesde;
                        if (DiarioEngine::entradaPorEvento($partida, $candidato) !== null) {
                            $diarioEventoId = $candidato;
                        }
                    }
                } else {
                    $explicacion = 'Me han rechazado planes demasiadas veces seguidas.';
                }
                break;

            case 'rechazo_emocional':
                $hacia = (string) ($ctx['hacia'] ?? '');
                $nombreOtro = $hacia !== '' && $hacia !== $residenteId ? IdentidadPublica::nombre($partida, $hacia) : '';
                if ($nombreOtro !== '') {
                    $explicacion = 'Me ha dolido que ' . $nombreOtro . ' me haya dicho que no. Necesito un momento.';
                } else {
                    $explicacion = 'Me han rechazado un plan y me ha dolido.';
                }
                break;

            case 'hobby_recuperacion':
            case 'encuentro_y_hobby':
                $explicacion = 'Un rato a solas con mi hobby me ha levantado el ánimo.';
                break;

            case 'cumple_felicidad':
                $explicacion = 'He recibido la enhorabuena de mis vecinos y me lo noto.';
                break;

            case 'consejo_celestine':
                $explicacion = 'Me has dado un buen consejo. Se te nota.';
                break;

            case 'formacion_pareja':
                $parejaId = (string) ($ctx['pareja_id'] ?? '');
                $nombrePareja = $parejaId !== '' ? IdentidadPublica::nombre($partida, $parejaId) : '';
                if ($nombrePareja !== '') {
                    $explicacion = 'Estoy muy content' . GeneroConcordancia::oa($partida, $residenteId)
                        . ' desde que empecé a salir con ' . $nombrePareja . '.';
                } else {
                    $explicacion = 'Estoy muy content' . GeneroConcordancia::oa($partida, $residenteId)
                        . ' desde que empecé una relación.';
                }
                $diaDesde = (int) ($estado['desde']['dia'] ?? 0);
                if ($diaDesde > 0) {
                    $diarioEventoId = 'formacion_pareja:' . $residenteId . ':' . $diaDesde;
                }
                break;

            default:
                return null;
        }

        return [
            'texto_estado' => 'Estoy ' . self::textoEstadoPensamiento($estadoId, $partida, $residenteId),
            'explicacion' => $explicacion,
            'desde_texto' => self::desdeTexto($estado, $partida),
            'diario_evento_id' => ($diarioEventoId !== null && DiarioEngine::entradaPorEvento($partida, $diarioEventoId) !== null)
                ? $diarioEventoId
                : null,
        ];
    }


    /**
     * Payload para modal de animo en ficha (vista jugador).
     * Devuelve pensamiento en primera persona sin explicación, consejo ni consecuencias.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $estado
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    public static function vistaModalAnimo(array $partida, string $residenteId, array $estado, array $cal = []): ?array
    {
        return self::pensamientoModal($partida, $residenteId, $estado);
    }

    /**
     * @param array<string, mixed> $cal
     * @return list<array{icono: string, texto: string}>
     */
    public static function consecuenciasModal(string $estadoId, array $cal = []): array
    {
        $mods = EstadoEmocional::modificadores(EstadoEmocional::canonId($estadoId), $cal);
        $out = [];

        if ((int) ($mods['aceptar_planes'] ?? 0) < 0) {
            $out[] = ['icono' => '✕', 'texto' => 'Puede rechazar algunos planes'];
        }
        if ((int) ($mods['riesgo_conflicto'] ?? 0) > 0 || (int) ($mods['experiencia_encuentro'] ?? 0) < 0) {
            $out[] = ['icono' => '💔', 'texto' => 'Sus relaciones pueden cambiar'];
        }
        if ((int) ($mods['iniciativa_social'] ?? 0) < 0) {
            $out[] = ['icono' => '💬', 'texto' => 'Puede evitar quedar con la gente'];
        }

        return $out;
    }

    public static function consejoModal(string $estadoId): ?string
    {
        $id = EstadoEmocional::canonId($estadoId);
        if (in_array($id, [EstadoEmocional::TRISTE, EstadoEmocional::ENFADADO], true)) {
            return 'Un buen plan podría mejorar su ánimo';
        }

        return null;
    }

    private static function textoEstado(string $estadoId, array $partida, string $rid): string
    {
        switch ($estadoId) {
            case EstadoEmocional::ALEGRE:
                return 'feliz';
            case EstadoEmocional::TRISTE:
                return 'triste';
            case EstadoEmocional::ENFADADO:
                return 'enfadad' . GeneroConcordancia::oa($partida, $rid);
        }
        return $estadoId;
    }

    private static function textoEstadoPensamiento(string $estadoId, array $partida, string $rid): string
    {
        switch ($estadoId) {
            case EstadoEmocional::ALEGRE:
                return 'alegre';
            case EstadoEmocional::TRISTE:
                return 'triste';
            case EstadoEmocional::ENFADADO:
                return 'enfadad' . GeneroConcordancia::oa($partida, $rid);
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

    /**
     * Temporalidad para pensamiento en primera persona.
     * mismo día → Desde las HH:00; día anterior → Desde ayer · HH:00; más antiguo → Desde hace N días · HH:00.
     *
     * @param array<string, mixed> $estado
     * @return string|null
     */
    private static function desdeTextoModal(array $estado, array $partida): ?string
    {
        $desde = is_array($estado['desde'] ?? null) ? $estado['desde'] : [];
        $diaDesde = (int) ($desde['dia'] ?? 0);
        $horaDesde = (int) ($desde['hora'] ?? 0);
        if ($diaDesde <= 0) {
            return null;
        }
        $hoy = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        if ($hoy <= 0) {
            return null;
        }
        $dias = $hoy - $diaDesde;
        if ($dias < 0) {
            return null;
        }
        $horaFmt = sprintf('%02d:00', $horaDesde);
        if ($dias === 0) {
            return 'Desde las ' . $horaFmt;
        }
        if ($dias === 1) {
            return 'Desde ayer · ' . $horaFmt;
        }
        return 'Desde hace ' . $dias . ' días · ' . $horaFmt;
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

    /**
     * ID del otro participante del encuentro (para HistorialPar).
     */
    private static function idOtroDeEncuentro(array $partida, string $residenteId, array $ctx): string
    {
        $encId = (string) ($ctx['encuentro_id'] ?? '');
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc) || (string) ($enc['id'] ?? '') !== $encId) {
                continue;
            }
            foreach (($enc['participantes'] ?? []) as $pid) {
                $pid = (string) $pid;
                if ($pid !== $residenteId && isset($partida['residentes'][$pid])) {
                    return $pid;
                }
            }
        }
        return '';
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
            case 'rechazo_emocional':
                return 'Le han rechazado un plan y le ha dolido.';
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
            case 'cumple_felicidad':
                return 'Ha tenido un día especial con sus vecinos.';
            case 'consejo_celestine':
                return 'Le han dado un buen consejo.';
            case 'formacion_pareja':
                return 'Acaba de empezar una relación.';
            default:
                return null;
        }
    }

    /**
     * Copy en PRIMERA PERSONA para el canal Mensajitos (canal buzón):
     * el propio vecino cuenta lo suyo a Celestine. Solo orígenes explicables;
     * null si no hay nada que el NPC contaría por mensajito.
     */
    public static function mensajitoParaOrigen(
        array $partida,
        string $residenteId,
        string $origen,
        array $contexto = []
    ): ?string {
        $nombre = IdentidadPublica::nombre($partida, $residenteId);
        if ($nombre === '') {
            return null;
        }
        switch ($origen) {
            case 'perder_trabajo':
                return 'Celestine, te lo cuento yo antes de que corra: me han soltado del trabajo.';
            case 'encontrar_trabajo':
                return '¡Tengo trabajo nuevo! Estoy que me salgo y quería decirlo.';
            case 'rechazo_repetido':
                return 'Esta vez no doy más. Necesito un respiro de planes, ¿de acuerdo?';
            case 'rechazo_emocional':
                return 'Me ha dolido que me hayan dicho que no. Necesito un momento.';
            case 'hobby_recuperacion':
            case 'encuentro_y_hobby':
                return 'Me he dedicado un rato a lo mío y estoy mucho mejor. A veces hace falta.';
            case 'cumple_felicidad':
                return 'Hoy me han dedicado unas palabras muy bonitas. Me ha encantado.';
            case 'consejo_celestine':
                return 'Oye, que me has dado un buen consejo. Se te nota.';
            case 'formacion_pareja':
                return '¡Estoy de enhorabuena! He empezado una relación y quería contártelo.';
            case 'encuentro':
            case 'encuentro_intervencion':
                $res = (string) ($contexto['resultado_experiencia'] ?? '');
                if ($res === 'muy_mal' || $res === 'mal') {
                    return 'No me ha ido bien en el último plan. Estoy de bajón.';
                }
                if ($res === 'muy_bien' || $res === 'bien') {
                    return 'He pasado un buen rato y se me ha pasado el mal humor.';
                }
                return null;
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
        $oA = GeneroConcordancia::oa($partida, $residenteId);

        switch ($origen) {
            case 'perder_trabajo':
                return 'Parece que a ' . $nombre . ' le han soltado del trabajo. Está de bajón.';
            case 'encontrar_trabajo':
                return $nombre . ' ha encontrado trabajo. Se le nota más animad' . $oA . '.';
            case 'rechazo_repetido':
                $quien = (string) ($contexto['hacia'] ?? $contexto['quien'] ?? '');
                $nomQ = $quien !== '' ? IdentidadPublica::nombre($partida, $quien) : 'alguien';
                if ($nomQ === '') {
                    $nomQ = 'alguien';
                }
                return $nombre . ' y ' . $nomQ . ' no parecen congeniar últimamente.';
            case 'rechazo_emocional':
                $quien = (string) ($contexto['hacia'] ?? $contexto['quien'] ?? '');
                $nomQ = $quien !== '' ? IdentidadPublica::nombre($partida, $quien) : 'alguien';
                if ($nomQ === '') {
                    $nomQ = 'alguien';
                }
                return $nombre . ' le ha sentado mal un rechazo de ' . $nomQ . '.';
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
            case 'cumple_felicidad':
                return $nombre . ' está de enhorabuena. Le han felicitado y se le nota.';
            case 'consejo_celestine':
                return $nombre . ' parece más animad' . $oA . ' tras un buen consejo.';
            case 'formacion_pareja':
                $parejaId = (string) ($contexto['pareja_id'] ?? '');
                $nomPareja = $parejaId !== '' ? IdentidadPublica::nombre($partida, $parejaId) : '';
                if ($nomPareja !== '') {
                    return $nombre . ' y ' . $nomPareja . ' han empezado a salir. Se les ve ilusionad' . $oA . '.';
                }
                return $nombre . ' parece muy content' . $oA . ' últimamente.';
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
}
