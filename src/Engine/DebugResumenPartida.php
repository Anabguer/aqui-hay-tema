<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\DomainEvents;

final class DebugResumenPartida
{
    public static function resumen(array $partida, int $limiteHistorial = 20): array
    {
        self::ensureVidaPueblo($partida);
        
        return [
            'header' => self::header($partida),
            'parejas' => self::parejas($partida),
            'romance' => self::romance($partida),
            'vida' => self::vida($partida),
            'jugador' => self::jugador($partida),
            'equilibrio' => self::equilibrio($partida),
            'historial' => self::historial($partida, $limiteHistorial),
        ];
    }

    private static function ensureVidaPueblo(array &$partida): void
    {
        if (!isset($partida['vida_pueblo']) || !is_array($partida['vida_pueblo'])) {
            $defaults = VidaPuebloEngine::defaults();
            $partida['vida_pueblo'] = [
                'valor' => $defaults['inicial'],
                'valor_inicial' => $defaults['inicial'],
                'latidos' => 0,
                'positivos_desde_latido' => 0,
                'positivos_validos_total' => 0,
                'negativos_total' => 0,
                'umbral_positivos_latido' => $defaults['umbral_positivos_latido'],
                'valor_post_latido' => $defaults['post_latido'],
                'primer_latido_dia' => null,
                'ultimo_latido_dia' => null,
                'ultimo_latido_hora' => null,
                'game_over_pendiente' => false,
                'game_over_activo' => false,
                'llego_a_cero' => false,
                'origen_ultimo_cero' => null,
                'dias_en_critico' => 0,
                'offline_dano_ultima_ausencia' => 0,
                'ledger' => [],
                'ledger_archivo' => [],
            ];
        }
    }

    private static function header(array $partida): array
    {
        $reloj = $partida['reloj'] ?? [];
        $dia = (int) ($reloj['dia_pueblo'] ?? 1);
        $hora = (int) ($reloj['hora_actual'] ?? 0);
        $temporada = (string) ($reloj['temporada_id'] ?? 'temp_01');
        $diaTemporada = (int) ($reloj['dia_en_temporada'] ?? 1);

        $vecinosActuales = 0;
        $llegadasTotal = 0;
        $marchasTotal = 0;

        foreach ($partida['residentes'] ?? [] as $res) {
            $estado = $res['estado_en_partida'] ?? 'residente';
            if ($estado === 'residente' || $estado === 'nuevo') {
                $vecinosActuales++;
            }
        }

        $llegadasHist = $partida['llegadas']['historial'] ?? [];
        if (is_array($llegadasHist)) {
            $llegadasTotal = count($llegadasHist);
        }

        $marchasHist = $partida['marchas']['historial'] ?? [];
        if (is_array($marchasHist)) {
            $marchasTotal = count($marchasHist);
        }

        return [
            'dia' => $dia,
            'hora' => $hora,
            'temporada' => $temporada,
            'dia_temporada' => $diaTemporada,
            'vecinos_actuales' => $vecinosActuales,
            'llegadas_total' => $llegadasTotal,
            'marchas_total' => $marchasTotal,
        ];
    }

    private static function parejas(array $partida): array
    {
        $actuales = 0;
        $creadasTotal = 0;
        $rupturas = 0;
        $primeraParejaDia = null;

        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            $estadoPareja = (string) ($rel['estado_pareja'] ?? 'ninguna');
            if ($estadoPareja === 'pareja' || $estadoPareja === 'crisis') {
                $actuales++;
            }

            $hist = $rel['historial_parejas'] ?? [];
            if (is_array($hist)) {
                $creadasTotal += count($hist);
                foreach ($hist as $h) {
                    if (!empty($h['fin'])) {
                        $rupturas++;
                    }
                }
            }

            if ($primeraParejaDia === null && !empty($hist)) {
                $first = $hist[0];
                if (!empty($first['inicio']['dia'])) {
                    $primeraParejaDia = (int) $first['inicio']['dia'];
                }
            }
        }

        return [
            'actuales' => $actuales,
            'creadas_total' => $creadasTotal,
            'rupturas' => $rupturas,
            'primera_pareja_dia' => $primeraParejaDia,
        ];
    }

    private static function romance(array $partida): array
    {
        $flechazos = 0;
        $primerasCitas = 0;
        $citasRealizadas = 0;
        $citasRechazadas = 0;
        $planesAutonomos = 0;
        $relacionesAvanzadas = 0;
        $relacionesEstancadas = 0;

        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            $flechazos += count($rel['flechazos'] ?? []);

            $citas = $rel['historial_citas'] ?? [];
            if (is_array($citas)) {
                $citasRealizadas += count($citas);
                foreach ($citas as $cita) {
                    if (($cita['es_primera'] ?? false) === true) {
                        $primerasCitas++;
                    }
                }
            }

            $fase = $rel['fase'] ?? null;
            if (in_array($fase, ['estable', 'tension', 'crisis', 'posible_ruptura'], true)) {
                $relacionesAvanzadas++;
            }

            $ultimaCita = null;
            foreach ($citas as $cita) {
                $d = (int) ($cita['dia'] ?? 0);
                if ($d > 0 && ($ultimaCita === null || $d > $ultimaCita)) {
                    $ultimaCita = $d;
                }
            }
            $diaActual = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            if ($ultimaCita !== null && ($diaActual - $ultimaCita) > 14 && $fase !== null) {
                $relacionesEstancadas++;
            }
        }

        $propuestas = $partida['propuestas_encuentro'] ?? [];
        if (is_array($propuestas)) {
            foreach ($propuestas as $p) {
                if (($p['estado'] ?? '') === 'rechazada' && ($p['tipo'] ?? '') === 'cita') {
                    $citasRechazadas++;
                }
            }
        }

        $planesPend = $partida['npc_autonomo']['planes_pendientes'] ?? [];
        if (is_array($planesPend)) {
            $planesAutonomos = count($planesPend);
        }

        return [
            'flechazos' => $flechazos,
            'primeras_citas' => $primerasCitas,
            'citas_realizadas' => $citasRealizadas,
            'citas_rechazadas' => $citasRechazadas,
            'planes_autonomos' => $planesAutonomos,
            'relaciones_mas_avanzadas' => $relacionesAvanzadas,
            'relaciones_estancadas' => $relacionesEstancadas,
        ];
    }

    private static function vida(array $partida): array
    {
        $trabajosActuales = 0;
        $trabajosPerdidos = 0;
        $trabajosEncontrados = 0;
        $acontecimientosRelevantes = 0;
        $estadosEmocionalesActivos = [];

        foreach ($partida['residentes'] ?? [] as $res) {
            $runtime = $res['runtime'] ?? [];
            $estadoEmocional = $runtime['estado_emocional'] ?? null;
            if (is_array($estadoEmocional) && ($estadoEmocional['id'] ?? '') !== 'neutro' && ($estadoEmocional['id'] ?? '') !== '') {
                $estadosEmocionalesActivos[] = [
                    'residente' => $res['identidad_publica']['nombre'] ?? '?',
                    'estado' => $estadoEmocional['id'],
                    'intensidad' => $estadoEmocional['intensidad'] ?? null,
                    'hasta' => $estadoEmocional['hasta'] ?? null,
                ];
            }
        }

        $huecos = $partida['huecos_vida'] ?? [];
        if (is_array($huecos)) {
            foreach ($huecos as $h) {
                if (($h['tipo'] ?? '') === 'trabajo') {
                    $trabajosActuales++;
                }
            }
        }

        $acontecimientosLog = $partida['acontecimientos_log'] ?? [];
        if (is_array($acontecimientosLog)) {
            foreach ($acontecimientosLog as $a) {
                $imp = (string) ($a['importancia'] ?? '');
                $vis = (string) ($a['visibilidad_jugador'] ?? '');
                if ($imp === 'hito' || $vis === 'importante' || $vis === 'aviso') {
                    $acontecimientosRelevantes++;
                }
                $eventoId = (string) ($a['evento_id'] ?? '');
                if ($eventoId === 'perder_trabajo') {
                    $trabajosPerdidos++;
                } elseif ($eventoId === 'encontrar_trabajo') {
                    $trabajosEncontrados++;
                }
            }
        }

        return [
            'trabajos_actuales' => $trabajosActuales,
            'trabajos_perdidos' => $trabajosPerdidos,
            'trabajos_encontrados' => $trabajosEncontrados,
            'acontecimientos_relevantes' => $acontecimientosRelevantes,
            'estados_emocionales_activos' => $estadosEmocionalesActivos,
        ];
    }

    private static function jugador(array $partida): array
    {
        $misionesCompletadas = 0;
        $misionesFalladas = 0;
        $peticionesRecibidas = 0;
        $peticionesAtendidas = 0;
        $peticionesCaducadas = 0;
        $diasConsecutivosSinMision = 0;

        $misiones = $partida['misiones_diarias']['items'] ?? [];
        if (is_array($misiones)) {
            foreach ($misiones as $m) {
                $estado = $m['estado'] ?? '';
                if ($estado === 'cumplida') {
                    $misionesCompletadas++;
                } elseif ($estado === 'caducada') {
                    $misionesFalladas++;
                }
            }
        }

        $peticiones = $partida['peticiones'] ?? [];
        if (is_array($peticiones)) {
            foreach ($peticiones as $p) {
                $estado = $p['estado'] ?? '';
                $peticionesRecibidas++;
                if ($estado === 'atendida' || $estado === 'resuelta' || $estado === 'cumplida') {
                    $peticionesAtendidas++;
                } elseif ($estado === 'caducada' || $estado === 'ignorada') {
                    $peticionesCaducadas++;
                }
            }
        }

        $peticionesPueblo = $partida['peticiones_pueblo']['validos_dia_n'] ?? 0;
        $peticionesRecibidas += (int) $peticionesPueblo;

        $diasConsecutivosSinMision = self::calcularDiasSinMision($partida);

        return [
            'misiones_completadas' => $misionesCompletadas,
            'misiones_falladas' => $misionesFalladas,
            'peticiones_recibidas' => $peticionesRecibidas,
            'peticiones_atendidas' => $peticionesAtendidas,
            'peticiones_caducadas' => $peticionesCaducadas,
            'dias_consecutivos_sin_mision' => $diasConsecutivosSinMision,
        ];
    }

    private static function calcularDiasSinMision(array $partida): int
    {
        $diaActual = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $misiones = $partida['misiones_diarias']['items'] ?? [];
        $diasConMision = [];

        foreach ($misiones as $m) {
            $dia = (int) ($m['dia'] ?? 0);
            $estado = $m['estado'] ?? '';
            if ($dia > 0 && $estado === 'cumplida') {
                $diasConMision[$dia] = true;
            }
        }

        if (empty($diasConMision)) {
            return $diaActual;
        }

        $ultimoDiaMision = max(array_keys($diasConMision));
        return $diaActual - $ultimoDiaMision;
    }

    private static function equilibrio(array $partida): array
    {
        $vp = $partida['vida_pueblo'] ?? [];
        $valor = (int) ($vp['valor'] ?? VidaPuebloEngine::defaults()['inicial']);
        $cfg = VidaPuebloEngine::cfg();

        $minHistorico = $valor;
        $ledger = $vp['ledger'] ?? [];
        if (is_array($ledger)) {
            foreach ($ledger as $l) {
                $despues = (int) ($l['valor_despues'] ?? $valor);
                if ($despues < $minHistorico) {
                    $minHistorico = $despues;
                }
            }
        }
        $ledgerArchivo = $vp['ledger_archivo'] ?? [];
        if (is_array($ledgerArchivo)) {
            foreach ($ledgerArchivo as $arch) {
            }
        }

        $umbralDerrota = (int) ($cfg['bandas'][0]['max'] ?? 19);

        $penalizacionesAcumuladas = (int) ($vp['negativos_total'] ?? 0);

        $causaExactaFinal = null;
        $diaEstadoCritico = null;

        if ((bool) ($vp['llego_a_cero'] ?? false)) {
            $causaExactaFinal = (string) ($vp['origen_ultimo_cero'] ?? 'desconocido');
            $diaEstadoCritico = (int) ($vp['primer_latido_dia'] ?? $vp['dias_en_critico'] ?? 0);
            if ($diaEstadoCritico === 0) {
                foreach (array_reverse($ledger, true) as $l) {
                    if (($l['valor_despues'] ?? 100) <= $umbralDerrota) {
                        $diaEstadoCritico = (int) ($l['dia'] ?? 0);
                        break;
                    }
                }
            }
        } else {
            $diasEnCritico = (int) ($vp['dias_en_critico'] ?? 0);
            if ($diasEnCritico > 0) {
                $diaEstadoCritico = (int) ($partida['reloj']['dia_pueblo'] ?? 1) - $diasEnCritico + 1;
            }
        }

        return [
            'valor_actual' => $valor,
            'minimo_historico' => $minHistorico,
            'umbral_derrota' => $umbralDerrota,
            'penalizaciones_acumuladas' => $penalizacionesAcumuladas,
            'causa_exacta_final' => $causaExactaFinal,
            'dia_estado_critico' => $diaEstadoCritico,
        ];
    }

    private static function historial(array $partida, int $limite): array
    {
        $eventos = [];

        $domainEvents = $partida['domain_events'] ?? [];
        if (is_array($domainEvents)) {
            foreach (array_reverse($domainEvents, true) as $e) {
                if (count($eventos) >= $limite) break;
                $eventos[] = self::formatearEvento($e, 'dominio');
            }
        }

        $acontecimientosLog = $partida['acontecimientos_log'] ?? [];
        if (is_array($acontecimientosLog) && count($eventos) < $limite) {
            foreach (array_reverse($acontecimientosLog, true) as $a) {
                if (count($eventos) >= $limite) break;
                $eventos[] = self::formatearAcontecimiento($a);
            }
        }

        $eventLog = $partida['event_log'] ?? [];
        if (is_array($eventLog) && count($eventos) < $limite) {
            foreach (array_reverse($eventLog, true) as $e) {
                if (count($eventos) >= $limite) break;
                $eventos[] = self::formatearEventLog($e);
            }
        }

        usort($eventos, fn($a, $b) => (int)($b['dia'] ?? 0) <=> (int)($a['dia'] ?? 0));

        return array_slice($eventos, 0, $limite);
    }

    private static function formatearEvento(array $e, string $tipo): array
    {
        $ts = $e['ts_juego'] ?? [];
        return [
            'tipo' => $tipo,
            'evento' => $e['evento'] ?? 'desconocido',
            'dia' => (int) ($ts['dia'] ?? 0),
            'hora' => (int) ($ts['hora'] ?? 0),
            'correlacion_id' => $e['correlacion_id'] ?? null,
            'detalle' => $e['payload_keys'] ?? [],
        ];
    }

    private static function formatearAcontecimiento(array $a): array
    {
        return [
            'tipo' => 'acontecimiento',
            'evento' => $a['evento_id'] ?? 'desconocido',
            'dia' => (int) ($a['dia'] ?? 0),
            'hora' => (int) ($a['hora'] ?? 0),
            'importancia' => $a['importancia'] ?? 'ninguna',
            'visibilidad' => $a['visibilidad_jugador'] ?? 'ninguna',
            'residente' => $a['residente_id'] ?? null,
        ];
    }

    private static function formatearEventLog(array $e): array
    {
        return [
            'tipo' => 'event_log',
            'evento' => $e['evento'] ?? $e['tipo'] ?? 'desconocido',
            'dia' => (int) ($e['dia'] ?? 0),
            'hora' => (int) ($e['hora'] ?? 0),
            'residente' => $e['residente_id'] ?? null,
            'detalle' => $e['mensaje'] ?? $e['resumen'] ?? '',
        ];
    }
}