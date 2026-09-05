<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * F10 — Ritual contextual (cumpleaños §19.6 / §22.3).
 *
 * Disparado por calendario real del pueblo (fecha_ancla + dia_pueblo).
 * Variante jugable de aviso contextual: participar / organizar / ignorar.
 * Los eventos colectivos del pueblo (B2) NO son F10; van por EventosPuebloAnuncioEngine.
 *
 * F10.1: Reacción social ligera — 1-2 vecinos cercanos reaccionan con follow-up.
 */
final class MensajitoContextualEngine
{
    /** Máximo de vecinos que reaccionan además del remitente principal. */
    private const MAX_REACCIONES_SOCIALES = 2;

    /** Umbral social mínimo para reacción de follow-up. */
    private const UMBRAL_SOCIAL_FOLLOWUP = 15;

    /**
     * Evalúa cumpleaños al comenzar un nuevo día de pueblo.
     *
     * @param array<string, mixed> $cal
     * @return list<array<string, mixed>>
     */
    public static function evaluarAlComenzarDia(
        array &$partida,
        array $cal,
        Catalog $catalog,
        ?GameLogger $logger = null
    ): array {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return [];
        }
        $partida['mensajitos_cumpleanos_emitidos'] ??= [];
        $emitidos = [];

        foreach (array_keys($partida['residentes'] ?? []) as $rid) {
            if (!self::esResidenteActivo($partida, $rid)) {
                continue;
            }
            if (!ResidenteCumpleanosEngine::esCumpleanosHoy($partida, $rid, $catalog)) {
                continue;
            }
            $claveAnual = ResidenteCumpleanosEngine::claveAnual($partida, $rid);
            if (!empty($partida['mensajitos_cumpleanos_emitidos'][$claveAnual])) {
                continue;
            }
            $eventoId = 'f10_cumple_' . $claveAnual;
            if (CanalDeduplicador::yaPublicado($partida, $eventoId, BuzonEngine::CANAL_BUZON)) {
                $partida['mensajitos_cumpleanos_emitidos'][$claveAnual] = true;
                continue;
            }

            $remitente = self::elegirRemitente($partida, $rid);
            if ($remitente === null) {
                continue;
            }
            $nombreCumple = IdentidadPublica::nombre($partida, $rid);
            $esAuto = $remitente === $rid;
            $texto = MensajitoVoz::linea(
                $partida,
                $esAuto ? 'f_ritual_cumple_invitacion' : 'f_ritual_cumple_aviso',
                ['otro' => $nombreCumple],
                'f10_cumple|' . $rid . '|' . $remitente . '|' . $claveAnual,
                $remitente
            );
            if ($texto === '') {
                continue;
            }

            $msgId = 'msg_f10_' . substr(md5($eventoId), 0, 12);
            $datos = [
                'subtipo' => 'cumpleanos',
                'cumpleanero_id' => $rid,
                'cumpleanero_nombre' => $nombreCumple,
                'clave' => $claveAnual,
                'auto_invitacion' => $esAuto,
            ];
            $r = CanalDeduplicador::crearSiAplica($partida, [
                'id' => $msgId,
                'clasificacion' => BuzonEngine::IMPORTANTE,
                'tipo' => 'ritual_contextual_cumpleanos',
                'canal' => BuzonEngine::CANAL_BUZON,
                'de_persona' => $remitente,
                'actores' => array_values(array_unique([$remitente, $rid])),
                'texto' => $texto,
                'acciones' => ['participar_cumple', 'organizar_cumple', 'ignorar_contextual'],
                'familia_mensajito' => 'f_ritual_contextual',
                'datos_familia' => $datos,
                'hilo_id' => $msgId,
                'hilo_estado' => 'abierto',
                'origen' => [
                    'evento_id' => $eventoId,
                    'tipo_evento' => 'ritual_contextual_cumpleanos',
                    'es_narrativo' => true,
                    '_placeholder' => false,
                ],
                '_placeholder_contenido' => false,
            ]);

            if ($r === null || !($r['ok'] ?? false)) {
                continue;
            }

            $partida['mensajitos_cumpleanos_emitidos'][$claveAnual] = true;
            MensajitosCadenciaEngine::registrar($partida, $remitente, 'f_ritual_contextual', 'contextual', $claveAnual);
            $emitidos[] = [
                'cumpleanero_id' => $rid,
                'remitente_id' => $remitente,
                'mensaje_id' => $msgId,
            ];
            DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
                'mensaje' => $r['mensaje'] ?? null,
                'origen_evento' => 'ritual_contextual_cumpleanos',
            ], $logger, 'MensajitoContextualEngine');

            // F10.1: Reacción social — follow-up de 1-2 vecinos cercanos
            $followups = self::generarFollowups($partida, $rid, $remitente, $claveAnual, $cal, $logger);
            $emitidos = array_merge($emitidos, $followups);
        }

        return $emitidos;
    }

    /**
     * Celestine felicita (micro-efecto emocional + contacto social).
     *
     * Efectos:
     *   - Cumpleañero: estado ALEGRE 12h, origen cumple_felicidad
     *   - Celestine ↔ cumpleañero: contacto social +3 (calidad "normal")
     *   - detallito_hook para posible regalo sorpresa
     *
     * @return array<string, mixed>
     */
    public static function participarCumple(array &$partida, string $mensajeId): array
    {
        $ctx = self::contextoDe($partida, $mensajeId);
        if ($ctx === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $cumpleId = (string) ($ctx['cumpleanero_id'] ?? '');
        if ($cumpleId !== '' && isset($partida['residentes'][$cumpleId])) {
            EstadoEmocional::ensureResidente($partida['residentes'][$cumpleId], $partida['reloj'] ?? null);
            $reloj = $partida['reloj'] ?? [];
            $partida['residentes'][$cumpleId]['runtime']['estado_emocional'] = EstadoEmocional::estructura(
                EstadoEmocional::ALEGRE,
                1,
                'cumple_felicidad',
                EstadoEmocional::marcaReloj($reloj),
                EstadoEmocional::hastaDesdeDuracion($reloj, 12),
                ['fuente' => 'f10_cumpleanos'],
                12
            );
            $partida['residentes'][$cumpleId]['runtime']['animo'] = EstadoEmocional::ALEGRE;

            // F10.1: Contacto social Celestine → cumpleañero (+3, calidad normal)
            $celestineId = self::buscarCelestine($partida);
            if ($celestineId !== null && $celestineId !== $cumpleId) {
                $cal = CalibracionConfig::load('');
                RelacionEngine::registrarContacto(
                    $partida,
                    $celestineId,
                    $cumpleId,
                    ContactoCalidad::NORMAL,
                    $cal,
                    1
                );
            }
        }
        self::cerrarHilo($partida, $mensajeId, ['accion' => 'participar_cumple', 'cumpleanero_id' => $cumpleId]);
        return [
            'ok' => true,
            'mensaje_ui' => 'Le daré la enhorabuena.',
            'detallito_hook' => ['pendiente' => true, 'motivo' => 'cumpleanos_felicitar'],
        ];
    }

    /** Máximo de invitados además del cumpleañero. */
    private const MAX_INVITADOS_FIESTA = 5;

    /** Umbral social mínimo para considerar candidato a invitado. */
    private const UMBRAL_SOCIAL_MIN_INVITADO = 0;

    /** ID de hito Historia del Pueblo para la primera fiesta de cumpleaños. */
    public const HITO_EL_PRIMER_CUMPLE = 'el_primer_cumple';

    /** Prefijo de clave de dedup de fiestas de cumpleaños. */
    private const CLAVE_FIESTA_PREFIJO = 'cumple_fiesta_';

    /**
     * Organiza una fiesta de cumpleaños real: selecciona lugar, asistentes
     * y programa un encuentro que se resolverá por el pipeline normal.
     *
     * @return array<string, mixed>
     */
    public static function organizarCumple(array &$partida, string $mensajeId): array
    {
        $ctx = self::contextoDe($partida, $mensajeId);
        if ($ctx === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        $cumpleId = (string) ($ctx['cumpleanero_id'] ?? '');
        if ($cumpleId === '') {
            return ['ok' => false, 'error' => 'sin_cumpleanero'];
        }

        $reloj = $partida['reloj'] ?? [];
        $diaActual = (int) ($reloj['dia_pueblo'] ?? 1);
        $horaActual = (int) ($reloj['hora_actual'] ?? 9);

        // Dedup: una fiesta por cumpleaños real del residente
        $claveAnual = ResidenteCumpleanosEngine::claveAnual($partida, $cumpleId);
        $claveFiesta = self::CLAVE_FIESTA_PREFIJO . $claveAnual;
        $partida['fiestas_cumple_emitidas'] ??= [];
        if (!empty($partida['fiestas_cumple_emitidas'][$claveFiesta])) {
            self::cerrarHilo($partida, $mensajeId, ['accion' => 'organizar_cumple', 'cumpleanero_id' => $cumpleId]);
            return [
                'ok' => true,
                'mensaje_ui' => 'Ya se está organizando.',
                'ya_programada' => true,
            ];
        }

        // Seleccionar lugar
        $lugar = self::seleccionarLugarFiesta($partida, $diaActual, $horaActual);
        if ($lugar === null) {
            self::cerrarHilo($partida, $mensajeId, ['accion' => 'organizar_cumple', 'cumpleanero_id' => $cumpleId]);
            return [
                'ok' => false,
                'error' => 'no_hay_lugar_valido',
                'mensaje_ui' => 'No hay ningún sitio abierto donde celebrarlo.',
            ];
        }

        // Seleccionar asistentes (cumpleañero + invitados)
        $asistentes = self::seleccionarAsistentesFiesta($partida, $cumpleId, $lugar);
        if (count($asistentes) < 1) {
            self::cerrarHilo($partida, $mensajeId, ['accion' => 'organizar_cumple', 'cumpleanero_id' => $cumpleId]);
            return [
                'ok' => false,
                'error' => 'sin_participantes',
                'mensaje_ui' => 'No hay nadie que pueda venir.',
            ];
        }

        // Buscar franja horaria válida
        $attr = LugarAtributos::de($lugar);
        $durH = $attr['horas'];
        $franja = self::buscarFranjaFiesta($partida, $lugar, $durH, $diaActual, $horaActual);
        if ($franja === null) {
            self::cerrarHilo($partida, $mensajeId, ['accion' => 'organizar_cumple', 'cumpleanero_id' => $cumpleId]);
            return [
                'ok' => false,
                'error' => 'sin_franja',
                'mensaje_ui' => 'No hay hueco hoy para la fiesta.',
            ];
        }

        // Programar encuentro real
        $r = EncuentroEngine::programar(
            $partida,
            $asistentes,
            $franja['dia'],
            $franja['hora'],
            'amistad',
            $lugar,
            null,
            null,
            false,
            true
        );
        if (!($r['ok'] ?? false)) {
            self::cerrarHilo($partida, $mensajeId, ['accion' => 'organizar_cumple', 'cumpleanero_id' => $cumpleId]);
            return [
                'ok' => false,
                'error' => $r['error'] ?? 'encuentro_fallido',
                'mensaje_ui' => 'No se ha podido organizar la fiesta.',
            ];
        }

        // Marcar intención como fiesta de cumpleaños
        $encId = (string) ($r['encuentro']['id'] ?? '');
        foreach ($partida['encuentros'] as &$enc) {
            if (($enc['id'] ?? '') !== $encId) {
                continue;
            }
            $enc['intencion'] = 'fiesta_cumpleanos';
            $enc['cumpleanero_id'] = $cumpleId;
            $enc['reserva_agenda'] = ['tipo' => 'encuentro', 'origen' => 'fiesta_cumple'];
            break;
        }
        unset($enc);

        // Aplicar ALEGRE al cumpleañero
        if (isset($partida['residentes'][$cumpleId])) {
            EstadoEmocional::ensureResidente($partida['residentes'][$cumpleId], $reloj);
            $partida['residentes'][$cumpleId]['runtime']['estado_emocional'] = EstadoEmocional::estructura(
                EstadoEmocional::ALEGRE,
                1,
                'cumple_fiesta',
                EstadoEmocional::marcaReloj($reloj),
                EstadoEmocional::hastaDesdeDuracion($reloj, 12),
                ['fuente' => 'f10_cumpleanos', 'lugar' => $lugar],
                12
            );
            $partida['residentes'][$cumpleId]['runtime']['animo'] = EstadoEmocional::ALEGRE;
        }

        // Registrar dedup
        $partida['fiestas_cumple_emitidas'][$claveFiesta] = true;

        // Cerrar hilo del mensajito
        self::cerrarHilo($partida, $mensajeId, ['accion' => 'organizar_cumple', 'cumpleanero_id' => $cumpleId]);

        $nombreLugar = self::nombreLugarHumano($lugar);
        $horaUi = str_pad((string) $franja['hora'], 2, '0', STR_PAD_LEFT) . ':00';
        $diaUi = $franja['dia'] === $diaActual ? 'esta tarde' : 'mañana';

        return [
            'ok' => true,
            'mensaje_ui' => "Listo. {$diaUi} celebrarán el cumple en {$nombreLugar} a las {$horaUi}.",
            'encuentro_id' => $encId,
            'lugar' => $lugar,
            'asistentes' => $asistentes,
            'dia' => $franja['dia'],
            'hora' => $franja['hora'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ignorarContextual(array &$partida, string $mensajeId): array
    {
        $ctx = self::contextoDe($partida, $mensajeId);
        if ($ctx === null) {
            return ['ok' => false, 'error' => 'mensaje_no_encontrado'];
        }
        self::cerrarHilo($partida, $mensajeId, ['accion' => 'ignorar_contextual']);
        return ['ok' => true, 'mensaje_ui' => 'Vale, esta vez paso.'];
    }

    private static function esResidenteActivo(array $partida, string $rid): bool
    {
        $r = $partida['residentes'][$rid] ?? null;
        if (!is_array($r)) {
            return false;
        }
        $pres = (string) ($r['presencia'] ?? 'residente');
        return $pres === 'residente' || $pres === 'nuevo';
    }

    private static function elegirRemitente(array $partida, string $cumpleaneroId): ?string
    {
        $candidatos = [];
        foreach ($partida['relaciones_sociales'] ?? [] as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            $otro = null;
            if ($a === $cumpleaneroId) {
                $otro = $b;
            } elseif ($b === $cumpleaneroId) {
                $otro = $a;
            } else {
                continue;
            }
            if ($otro === $cumpleaneroId || !self::esResidenteActivo($partida, $otro)) {
                continue;
            }
            $dir = RelacionEngine::socialHacia($partida, $cumpleaneroId, $otro);
            $social = (float) (($dir['valor']) ?? 0);
            if ($social >= 20) {
                $candidatos[] = $otro;
            }
        }
        if ($candidatos !== []) {
            return $candidatos[array_rand($candidatos)];
        }
        if (self::esResidenteActivo($partida, $cumpleaneroId)) {
            return $cumpleaneroId;
        }
        return null;
    }

    /**
     * F10.1: Genera follow-up de 1-2 vecinos cercanos que también reaccionan al cumpleaños.
     * Selecciona por vínculo social (≥ UMBRAL_SOCIAL_FOLLOWUP), excluye el remitente principal.
     *
     * @param array<string, mixed> $cal
     * @return list<array<string, mixed>>
     */
    private static function generarFollowups(
        array &$partida,
        string $cumpleaneroId,
        string $remitentePrincipal,
        string $claveAnual,
        array $cal,
        ?GameLogger $logger
    ): array {
        $candidatos = [];
        foreach ($partida['relaciones_sociales'] ?? [] as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            $otro = null;
            if ($a === $cumpleaneroId) {
                $otro = $b;
            } elseif ($b === $cumpleaneroId) {
                $otro = $a;
            } else {
                continue;
            }
            if ($otro === $cumpleaneroId || $otro === $remitentePrincipal) {
                continue;
            }
            if (!self::esResidenteActivo($partida, $otro)) {
                continue;
            }
            $dir = RelacionEngine::socialHacia($partida, $cumpleaneroId, $otro);
            $social = (float) (($dir['valor']) ?? 0);
            if ($social >= self::UMBRAL_SOCIAL_FOLLOWUP) {
                $candidatos[$otro] = $social;
            }
        }

        if ($candidatos === []) {
            return [];
        }

        // Ordenar por social descendente, tomar los N primeros
        arsort($candidatos);
        $elegidos = array_slice(array_keys($candidatos), 0, self::MAX_REACCIONES_SOCIALES, true);

        $nombreCumple = IdentidadPublica::nombre($partida, $cumpleaneroId);
        $emitidos = [];

        foreach ($elegidos as $followupId => $_) {
            $eventoId = 'f10_followup_' . $claveAnual . '_' . $followupId;
            $msgId = 'msg_f10_fu_' . substr(md5($eventoId), 0, 10);

            if (CanalDeduplicador::yaPublicado($partida, $eventoId, BuzonEngine::CANAL_BUZON)) {
                continue;
            }

            $texto = MensajitoVoz::linea(
                $partida,
                'f_cumple_seguimiento',
                ['otro' => $nombreCumple],
                'f10_followup|' . $followupId . '|' . $cumpleaneroId,
                $followupId
            );
            if ($texto === '') {
                continue;
            }

            $r = CanalDeduplicador::crearSiAplica($partida, [
                'id' => $msgId,
                'clasificacion' => BuzonEngine::COTILLEO,
                'tipo' => 'ritual_contextual_cumpleanos_followup',
                'canal' => BuzonEngine::CANAL_BUZON,
                'de_persona' => $followupId,
                'actores' => [$followupId, $cumpleaneroId],
                'texto' => $texto,
                'acciones' => [],
                'familia_mensajito' => 'f_cumple_seguimiento',
                'datos_familia' => [
                    'subtipo' => 'cumpleanos_followup',
                    'cumpleanero_id' => $cumpleaneroId,
                    'seguimiento_de' => $remitentePrincipal,
                ],
                'hilo_id' => $msgId,
                'hilo_estado' => 'respondido',
                'seguimiento_pendiente' => false,
                'origen' => [
                    'evento_id' => $eventoId,
                    'tipo_evento' => 'ritual_contextual_cumpleanos_followup',
                    'es_narrativo' => false,
                    '_placeholder' => false,
                ],
                '_placeholder_contenido' => false,
            ]);

            if ($r === null || !($r['ok'] ?? false)) {
                continue;
            }

            MensajitosCadenciaEngine::registrar($partida, $followupId, 'f_cumple_seguimiento', 'contextual', $claveAnual);
            $emitidos[] = [
                'cumpleanero_id' => $cumpleaneroId,
                'remitente_id' => $followupId,
                'mensaje_id' => $msgId,
                'tipo' => 'followup',
            ];
            DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
                'mensaje' => $r['mensaje'] ?? null,
                'origen_evento' => 'ritual_contextual_cumpleanos_followup',
            ], $logger, 'MensajitoContextualEngine::generarFollowups');
        }

        return $emitidos;
    }

    /**
     * Busca el ID de Celestine (el jugador) en la partida.
     * Celestine es el primer residente placeholder o el identificado como 'celestine'.
     */
    private static function buscarCelestine(array $partida): ?string
    {
        foreach ($partida['residentes'] ?? [] as $rid => $res) {
            if (!is_array($res)) {
                continue;
            }
            if (!empty($res['_placeholder'])) {
                return $rid;
            }
        }
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function contextoDe(array $partida, string $mensajeId): ?array
    {
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m) || (string) ($m['id'] ?? '') !== $mensajeId) {
                continue;
            }
            $datos = is_array($m['datos_familia'] ?? null) ? $m['datos_familia'] : [];
            return $datos;
        }
        return null;
    }

    /**
     * Selecciona un lugar válido para la fiesta entre los desbloqueados y abiertos.
     * Aplica variedad determinista via RNG del partido.
     */
    private static function seleccionarLugarFiesta(array $partida, int $diaActual, int $horaActual): ?string
    {
        $operativos = $partida['celeste']['lugares_desbloqueados'] ?? [];
        $candidatos = [];

        foreach ($operativos as $lugarId) {
            $lugarId = (string) $lugarId;
            if (!LugaresCanonicos::esCanonico($lugarId)) {
                continue;
            }
            if (!ComplejoCatalog::estaAbierto($lugarId, $horaActual)) {
                // Probar si abrirá pronto (en las próximas 3 horas)
                $encontrado = false;
                for ($h = $horaActual + 1; $h <= min($horaActual + 3, 23); $h++) {
                    if (ComplejoCatalog::estaAbierto($lugarId, $h)) {
                        $encontrado = true;
                        break;
                    }
                }
                if (!$encontrado) {
                    continue;
                }
            }
            // Excluir lugares no aptos para fiesta social
            if (in_array($lugarId, ['lug_biblioteca', 'lug_gimnasio'], true)) {
                continue;
            }
            $attr = LugarAtributos::de($lugarId);
            $candidatos[] = [
                'id' => $lugarId,
                'aforo' => $attr['aforo'],
            ];
        }

        if ($candidatos === []) {
            return null;
        }

        // Selección determinista con RNG para variedad
        $rng = RngService::fromPartida($partida);
        $idx = $rng->nextInt(0, count($candidatos) - 1);
        $rng->persistToPartida($partida);

        return $candidatos[$idx]['id'];
    }

    /**
     * Selecciona asistentes para la fiesta de cumpleaños.
     * Incluye al cumpleañero + hasta MAX_INVITADOS_FIESTA invitados.
     * Prioriza: pareja > amigos cercanos > relaciones positivas.
     * No exige social >= 20: permite fiestas pequeñas en pueblos con pocos contactos.
     *
     * @return list<string>
     */
    private static function seleccionarAsistentesFiesta(array $partida, string $cumpleaneroId, string $lugarId): array
    {
        $asistentes = [$cumpleaneroId];
        $candidatos = [];

        // Buscar pareja
        $parejaId = TerceroRomantico::parejaDe($partida, $cumpleaneroId);
        if ($parejaId !== null && $parejaId !== $cumpleaneroId && self::esResidenteActivo($partida, $parejaId)) {
            $candidatos[$parejaId] = 200; // Prioridad máxima
        }

        // Buscar otros candidatos por social
        foreach ($partida['relaciones_sociales'] ?? [] as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            $otro = null;
            if ($a === $cumpleaneroId) {
                $otro = $b;
            } elseif ($b === $cumpleaneroId) {
                $otro = $a;
            } else {
                continue;
            }
            if ($otro === $cumpleaneroId || $otro === $parejaId) {
                continue;
            }
            if (!self::esResidenteActivo($partida, $otro)) {
                continue;
            }

            $dir = RelacionEngine::socialHacia($partida, $cumpleaneroId, $otro);
            $social = (float) (($dir['valor']) ?? 0);

            // Excluir si hay conflicto activo fuerte
            $entre = RelacionEngine::obtenerEntre($partida, $cumpleaneroId, $otro);
            $confIntensidad = (int) (($entre['conflicto'] ?? [])['intensidad'] ?? 0);
            if ($confIntensidad >= 9) {
                continue;
            }

            if ($social >= self::UMBRAL_SOCIAL_MIN_INVITADO) {
                $candidatos[$otro] = (int) $social;
            }
        }

        // Ordenar por social descendente
        arsort($candidatos);

        $aforoMax = LugarAtributos::de($lugarId)['aforo'];
        $maxInvitados = min(self::MAX_INVITADOS_FIESTA, $aforoMax - 1);

        foreach ($candidatos as $invitadoId => $_) {
            if (count($asistentes) >= $maxInvitados + 1) {
                break;
            }
            $asistentes[] = $invitadoId;
        }

        return $asistentes;
    }

    /**
     * Busca una franja horaria válida para la fiesta.
     * Prioriza la tarde del día actual si el lugar está abierto.
     *
     * @return array{dia: int, hora: int}|null
     */
    private static function buscarFranjaFiesta(
        array $partida,
        string $lugarId,
        int $durH,
        int $diaActual,
        int $horaActual
    ): ?array {
        // Probar hoy desde la hora actual +1 hasta 22
        for ($h = max($horaActual + 1, 17); $h <= 22 - $durH + 1; $h++) {
            if (!ComplejoCatalog::estaAbierto($lugarId, $h)) {
                continue;
            }
            if (!Reloj::esFuturo($partida['reloj'] ?? [], $diaActual, $h)) {
                continue;
            }
            return ['dia' => $diaActual, 'hora' => $h];
        }

        // Probar mañana 19:00
        $manana = $diaActual + 1;
        $horaManana = 19;
        if (ComplejoCatalog::estaAbierto($lugarId, $horaManana)
            && Reloj::esFuturo($partida['reloj'] ?? [], $manana, $horaManana)
        ) {
            return ['dia' => $manana, 'hora' => $horaManana];
        }

        return null;
    }

    /** Nombre legible de un lugar a partir de su ID. */
    private static function nombreLugarHumano(string $lugarId): string
    {
        $map = [
            'lug_cafeteria' => 'la Cafetería',
            'lug_bar' => 'el Bar',
            'lug_restaurante' => 'el Restaurante',
            'lug_bingo' => 'el Bingo',
            'lug_parque' => 'el Parque',
            'lug_cine' => 'el Cine',
            'lug_discoteca' => 'la Discoteca',
            'lug_plaza' => 'la Plaza',
        ];
        return $map[$lugarId] ?? ucfirst(str_replace(['lug_', '_'], ['', ' '], $lugarId));
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
            $m['hilo_estado'] = 'respondido';
            $m['seguimiento_pendiente'] = false;
            break;
        }
        unset($m);
    }

    // ── Historia del Pueblo: EL PRIMER CUMPLEAÑOS ──────────────

    /**
     * Registra "EL PRIMER CUMPLEAÑOS" en Historia del Pueblo si es la primera
     * fiesta de cumpleaños celebrada en la partida.
     * Se llama cuando un encuentro con intención 'fiesta_cumpleanos' termina.
     *
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $encuentro
     * @return array{ok: bool, ya_existia: bool}|null
     */
    public static function registrarPrimerCumpleHistoria(array &$partida, array $encuentro): ?array
    {
        if (($encuentro['intencion'] ?? '') !== 'fiesta_cumpleanos') {
            return null;
        }
        if (($encuentro['estado'] ?? '') !== 'terminado') {
            return null;
        }
        $resultado = $encuentro['resultado'] ?? null;
        if (!is_array($resultado)) {
            return null;
        }

        $protagonistas = $encuentro['participantes'] ?? [];
        if ($protagonistas === []) {
            return null;
        }

        $contexto = [
            'cumpleanero_id' => $encuentro['cumpleanero_id'] ?? $protagonistas[0],
            'lugar' => $encuentro['lugar'] ?? null,
        ];

        return HistoriaPuebloEngine::registrar(
            $partida,
            self::HITO_EL_PRIMER_CUMPLE,
            $protagonistas,
            $contexto
        );
    }
}
