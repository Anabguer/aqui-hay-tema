<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Deduplicacion tecnica de canales (§22.6 del Plan Maestro).
 *
 * Evita que el mismo hecho canonico se publique automaticamente en multiples
 * canales (Mensajitos + Cotilleo + Diario). Cada hecho selecciona los canales
 * que mejor lo expresan (regla §21.2: economia narrativa).
 *
 * Mecanismo: registro partida['canales_publicados'][evento_id][canal] +
 * guards en la creacion de mensajes. Un mismo (hecho, canal) nunca se
 * publica dos veces; cruzar canales exige perspectiva diferente con valor propio.
 */
final class CanalDeduplicador
{
    /**
     * ¿Ya se publico este evento en este canal?
     */
    public static function yaPublicado(array $partida, ?string $eventoId, string $canal): bool
    {
        if ($eventoId === null || $eventoId === '') {
            return false;
        }
        $reg = $partida['canales_publicados'][$eventoId] ?? [];
        return isset($reg[$canal]) && $reg[$canal] === true;
    }

    /**
     * Registra que un evento fue publicado en un canal.
     */
    public static function registrar(array &$partida, ?string $eventoId, string $canal): void
    {
        if ($eventoId === null || $eventoId === '') {
            return;
        }
        $partida['canales_publicados'] ??= [];
        $partida['canales_publicados'][$eventoId] ??= [];
        $partida['canales_publicados'][$eventoId][$canal] = true;
    }

    /**
     * ¿Es este evento elegible para este canal segun la tabla de permisos?
     *
     * @param array<string, list<string>> $permisos Tipo => canales permitidos
     */
    public static function elegibleParaCanal(string $tipoEvento, string $canal, array $permisos): bool
    {
        if (!isset($permisos[$tipoEvento])) {
            // Sin regla explicita: permitir por defecto
            return true;
        }
        return in_array($canal, $permisos[$tipoEvento], true);
    }

    /**
     * Tabla provisional de permisos por tipo de evento.
     * Cada tipo lista los canales donde PUEDE aparecer (prioridad: primero = preferido).
     *
     * @return array<string, list<string>>
     */
    public static function permisos(): array
    {
        return [
            // Llegadas: Mensajitos (interactivo) + Cotilleo si es publico
            'candidato_llegada' => [BuzonEngine::CANAL_BUZON, BuzonEngine::CANAL_COTILLEO],
            // Marchas: Mensajitos (el vecino habla) + Diario
            'marcha_intencion' => [BuzonEngine::CANAL_BUZON],
            // Discusion: Cotilleo (publico), no Mensajitos
            'discusion' => [BuzonEngine::CANAL_COTILLEO],
            // Senal romantica: Cotilleo (publico)
            'senal_romantica' => [BuzonEngine::CANAL_COTILLEO],
            // Encuentro terminado: Cotilleo
            'encuentro_terminado' => [BuzonEngine::CANAL_COTILLEO],
            // Peticion: Mensajitos
            'peticion' => [BuzonEngine::CANAL_BUZON],
            'peticion_creada' => [BuzonEngine::CANAL_BUZON],
            // Respuesta plan rechazado: Mensajitos
            'respuesta_plan' => [BuzonEngine::CANAL_BUZON],
            // Feedback de peticion: Mensajitos
            'peticion_resultado' => [BuzonEngine::CANAL_BUZON],
            // Espontaneos: solo Mensajitos
            'espontaneo_f_opinion' => [BuzonEngine::CANAL_BUZON],
            'espontaneo_f_dilema' => [BuzonEngine::CANAL_BUZON],
            'espontaneo_f_confidencia' => [BuzonEngine::CANAL_BUZON],
            'espontaneo_f_alerta_vecinal' => [BuzonEngine::CANAL_BUZON],
            'espontaneo_f_curiosidad_celestine' => [BuzonEngine::CANAL_BUZON],
            'espontaneo_f_promesa' => [BuzonEngine::CANAL_BUZON],
            'espontaneo_f_colectivo' => [BuzonEngine::CANAL_BUZON],
            // Seguimiento: Mensajitos
            'seguimiento_consejo' => [BuzonEngine::CANAL_BUZON],
            'seguimiento_peticion' => [BuzonEngine::CANAL_BUZON],
            'anuncio_evento_pueblo' => [BuzonEngine::CANAL_BUZON],
            'cierre_evento_pueblo' => [BuzonEngine::CANAL_BUZON],
            'ritual_contextual_cumpleanos' => [BuzonEngine::CANAL_BUZON],
            'espontaneo_f_duda_permanencia' => [BuzonEngine::CANAL_BUZON],
            'espontaneo_f_mediacion' => [BuzonEngine::CANAL_BUZON],
            'espontaneo_f_ritual_contextual' => [BuzonEngine::CANAL_BUZON],
            'espontaneo_f_seguimiento' => [BuzonEngine::CANAL_BUZON],
            'peticion_f_peticion' => [BuzonEngine::CANAL_BUZON],
            'peticion_f_presentacion' => [BuzonEngine::CANAL_BUZON],
        ];
    }

    /**
     * Identidad estable por ventana temporal (§22.6): misma causa no duplica en tick/refresh/catch-up.
     */
    public static function eventoIdVentana(
        string $familia,
        string $residenteId,
        string $clave,
        int $horaJuego,
        int $ventanaHoras = 72
    ): string {
        $slot = (int) floor($horaJuego / max(1, $ventanaHoras));
        $base = implode('|', [$familia, $residenteId, $clave, (string) $slot]);
        return 'evt_v_' . substr(hash('sha256', $base), 0, 20);
    }

    /**
     * ¿Ya hay un mensaje en buzón con este evento_id?
     */
    public static function existeEnBuzon(array $partida, string $eventoId, string $canal): bool
    {
        if ($eventoId === '') {
            return false;
        }
        foreach ($partida['buzon'] ?? [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            if (!BuzonEngine::tieneContenido($m)) {
                continue;
            }
            if (!empty($m['_compactado'])) {
                continue;
            }
            $canalMsg = (string) ($m['canal'] ?? BuzonEngine::canalDe((string) ($m['clasificacion'] ?? BuzonEngine::PETICION)));
            if ($canalMsg !== $canal) {
                continue;
            }
            $eid = (string) ($m['origen']['evento_id'] ?? '');
            if ($eid !== '' && $eid === $eventoId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Intenta crear un mensaje respetando la deduplicacion.
     * Si el evento ya fue publicado en el canal, retorna null.
     * Si el evento no es elegible para el canal, retorna null.
     * Si el evento id es null (sin tracking), permite el paso.
     *
     * @param array<string, mixed> $mensaje
     * @return array<string, mixed>|null El resultado de BuzonEngine::crear o null si dedup bloquea
     */
    public static function crearSiAplica(array &$partida, array $mensaje): ?array
    {
        $eventoId = $mensaje['origen']['evento_id'] ?? null;
        $canal = $mensaje['canal'] ?? BuzonEngine::canalDe($mensaje['clasificacion'] ?? BuzonEngine::PETICION);
        $tipoEvento = $mensaje['origen']['tipo_evento'] ?? ($mensaje['tipo'] ?? '');

        if ($eventoId !== null && self::yaPublicado($partida, $eventoId, $canal)) {
            return null;
        }
        if ($eventoId !== null && self::existeEnBuzon($partida, $eventoId, $canal)) {
            return null;
        }

        $rid = (string) ($mensaje['de_persona'] ?? '');
        $familia = (string) ($mensaje['familia_mensajito'] ?? '');
        $clave = (string) (($mensaje['datos_familia'] ?? [])['clave'] ?? ($mensaje['dedup_clave'] ?? ''));
        if ($rid !== '' && $familia !== '' && $clave !== ''
            && MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, $familia, $clave)) {
            return null;
        }

        $perm = self::permisos();
        if (!self::elegibleParaCanal($tipoEvento, $canal, $perm)) {
            return null;
        }

        $r = BuzonEngine::crear($partida, $mensaje);

        if (($r['ok'] ?? false) && $eventoId !== null) {
            self::registrar($partida, $eventoId, $canal);
        }

        return $r;
    }
}