<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Historia del Pueblo — Álbum de primeras veces.
 * Persiste hitos únicos de la partida como recuerdos coleccionables.
 */
final class HistoriaPuebloEngine
{
    public const HITO_EMPEZO_COTARRO = 'empezo_el_cotarro';

    /** @var list<array{id: string, nombre: string, imagen: string, plantilla: string, recompensa: array|null}> */
    public const CATÁLOGO_VISUAL = [
        ['id' => 'empezo_el_cotarro', 'nombre' => 'AQUÍ EMPEZÓ EL COTARRO', 'imagen' => 'assets/img/historia/01.png', 'plantilla' => '{p1}, {p2} y {p3} acaban de llegar al pueblo. Aquí empieza todo.', 'recompensa' => null],
        ['id' => 'hito_02',           'nombre' => '???',                     'imagen' => 'assets/img/historia/02.png', 'plantilla' => '{p1} y {p2} se han conocido por primera vez.', 'recompensa' => null],
        ['id' => 'hito_03',           'nombre' => '???',                     'imagen' => 'assets/img/historia/03.png', 'plantilla' => '{p1} ha ayudado a {p2} en un momento difícil.', 'recompensa' => null],
        ['id' => 'hito_04',           'nombre' => '???',                     'imagen' => 'assets/img/historia/04.png', 'plantilla' => '{p1} y {p2} han tenido su primera discusión.', 'recompensa' => null],
        ['id' => 'hito_05',           'nombre' => '???',                     'imagen' => 'assets/img/historia/05.png', 'plantilla' => '{p1} ha descubierto un secreto del pueblo.', 'recompensa' => null],
        ['id' => 'hito_06',           'nombre' => '???',                     'imagen' => 'assets/img/historia/06.png', 'plantilla' => '{p1} y {p2} han compartido un momento especial.', 'recompensa' => null],
        ['id' => 'hito_07',           'nombre' => '???',                     'imagen' => 'assets/img/historia/07.png', 'plantilla' => '{p1} ha sido el primero en llegar a un lugar importante.', 'recompensa' => null],
        ['id' => 'hito_08',           'nombre' => '???',                     'imagen' => 'assets/img/historia/08.png', 'plantilla' => '{p1} y {p2} se han dado el primer beso del pueblo.', 'recompensa' => null],
        ['id' => 'hito_09',           'nombre' => '???',                     'imagen' => 'assets/img/historia/09.png', 'plantilla' => '{p1} ha organizado el primer evento del pueblo.', 'recompensa' => null],
        ['id' => 'hito_10',           'nombre' => '???',                     'imagen' => 'assets/img/historia/10.png', 'plantilla' => '{p1} ha tenido un momento de reflexión profunda.', 'recompensa' => null],
        ['id' => 'hito_11',           'nombre' => '???',                     'imagen' => 'assets/img/historia/11.png', 'plantilla' => '{p1} y {p2} han superado un malentendido.', 'recompensa' => null],
        ['id' => 'hito_12',           'nombre' => '???',                     'imagen' => 'assets/img/historia/12.png', 'plantilla' => '{p1} ha recibido una noticia inesperada.', 'recompensa' => null],
        ['id' => 'hito_13',           'nombre' => '???',                     'imagen' => 'assets/img/historia/13.png', 'plantilla' => '{p1} ha tomado una decisión importante para el pueblo.', 'recompensa' => null],
        ['id' => 'hito_14',           'nombre' => '???',                     'imagen' => 'assets/img/historia/14.png', 'plantilla' => '{p1} y {p2} han celebrado algo juntos.', 'recompensa' => null],
        ['id' => 'hito_15',           'nombre' => '???',                     'imagen' => 'assets/img/historia/15.png', 'plantilla' => '{p1} ha descubierto algo sorprendente sobre {p2}.', 'recompensa' => null],
        ['id' => 'hito_16',           'nombre' => '???',                     'imagen' => 'assets/img/historia/16.png', 'plantilla' => '{p1} ha tenido un encuentro inesperado.', 'recompensa' => null],
        ['id' => 'hito_17',           'nombre' => '???',                     'imagen' => 'assets/img/historia/17.png', 'plantilla' => '{p1} ha demostrado ser una persona muy valiosa.', 'recompensa' => null],
        ['id' => 'hito_18',           'nombre' => '???',                     'imagen' => 'assets/img/historia/18.png', 'plantilla' => '{p1} y {p2} han vivido una aventura juntos.', 'recompensa' => null],
        ['id' => 'hito_19',           'nombre' => '???',                     'imagen' => 'assets/img/historia/19.png', 'plantilla' => '{p1} ha cambiado de opinión sobre algo importante.', 'recompensa' => null],
        ['id' => 'hito_20',           'nombre' => '???',                     'imagen' => 'assets/img/historia/20.png', 'plantilla' => '{p1} ha hecho algo que nadie esperaba.', 'recompensa' => null],
        ['id' => 'hito_21',           'nombre' => '???',                     'imagen' => 'assets/img/historia/21.png', 'plantilla' => '{p1} y {p2} han fortalecido su relación.', 'recompensa' => null],
        ['id' => 'hito_22',           'nombre' => '???',                     'imagen' => 'assets/img/historia/22.png', 'plantilla' => '{p1} ha sido reconocido por su esfuerzo.', 'recompensa' => null],
        ['id' => 'hito_23',           'nombre' => '???',                     'imagen' => 'assets/img/historia/23.png', 'plantilla' => '{p1} ha tenido un momento de gran felicidad.', 'recompensa' => null],
        ['id' => 'hito_24',           'nombre' => '???',                     'imagen' => 'assets/img/historia/24.png', 'plantilla' => '{p1} ha tomado una decisión que cambiará las cosas.', 'recompensa' => null],
        ['id' => 'hito_25',           'nombre' => '???',                     'imagen' => 'assets/img/historia/25.png', 'plantilla' => '{p1} ha sido el primero en hacer algo especial.', 'recompensa' => null],
        ['id' => 'hito_26',           'nombre' => '???',                     'imagen' => 'assets/img/historia/26.png', 'plantilla' => '{p1} y {p2} han compartido una experiencia única.', 'recompensa' => null],
        ['id' => 'hito_27',           'nombre' => '???',                     'imagen' => 'assets/img/historia/27.png', 'plantilla' => '{p1} ha superado un obstáculo importante.', 'recompensa' => null],
        ['id' => 'hito_28',           'nombre' => '???',                     'imagen' => 'assets/img/historia/28.png', 'plantilla' => '{p1} ha inspirado a otros con su ejemplo.', 'recompensa' => null],
        ['id' => 'hito_29',           'nombre' => '???',                     'imagen' => 'assets/img/historia/29.png', 'plantilla' => '{p1} ha vivido un momento de gran intensidad.', 'recompensa' => null],
        ['id' => 'hito_30',           'nombre' => '???',                     'imagen' => 'assets/img/historia/30.png', 'plantilla' => '{p1} y {p2} han creado un recuerdo inolvidable.', 'recompensa' => null],
        ['id' => 'hito_31',           'nombre' => '???',                     'imagen' => 'assets/img/historia/31.png', 'plantilla' => '{p1} ha demostrado su verdadero carácter.', 'recompensa' => null],
        ['id' => 'hito_32',           'nombre' => '???',                     'imagen' => 'assets/img/historia/32.png', 'plantilla' => '{p1} ha dado un paso importante en su vida.', 'recompensa' => null],
        ['id' => 'hito_33',           'nombre' => '???',                     'imagen' => 'assets/img/historia/33.png', 'plantilla' => '{p1} ha dejado una huella imborrable en el pueblo.', 'recompensa' => null],
    ];

    /** @var array<string, array{day: int, hora: int}> Hitos registrados => timestamp */
    private static array $catálogoHitos = [
        self::HITO_EMPEZO_COTARRO => ['day' => 1, 'hora' => 8],
    ];

    public static function ensure(array &$partida): void
    {
        $partida['historia_pueblo'] ??= [];
    }

    /**
     * Registra un hito si no existe ya para esa clave.
     * Si es nuevo, crea celebración pendiente y concede recompensa si existe.
     *
     * @param array<string, mixed> $partida
     * @param list<string> $protagonistas IDs de residentes involucrados
     * @return array{ok: bool, ya_existia: bool, entrada: ?array}
     */
    public static function registrar(
        array &$partida,
        string $hitoId,
        array $protagonistas,
        ?array $contexto = null
    ): array {
        self::ensure($partida);

        $clave = self::clave($hitoId, $protagonistas);
        if (self::existe($partida, $clave)) {
            return ['ok' => true, 'ya_existia' => true, 'entrada' => self::obtener($partida, $clave)];
        }

        $reloj = $partida['reloj'] ?? [];
        $dia = (int) ($reloj['dia_pueblo'] ?? 1);
        $hora = (int) ($reloj['hora_actual'] ?? 8);

        $nombres = [];
        foreach ($protagonistas as $pid) {
            $nombres[(string) $pid] = IdentidadPublica::nombre($partida, (string) $pid);
        }

        $entrada = [
            'hito_id' => $hitoId,
            'clave' => $clave,
            'protagonistas' => $protagonistas,
            'nombres' => $nombres,
            'dia' => $dia,
            'hora' => $hora,
            'contexto' => $contexto ?? [],
            'revelado' => true,
            'celebracion_estado' => 'pendiente',
        ];

        $partida['historia_pueblo'][] = $entrada;

        DomainEventDispatcher::emit(
            $partida,
            DomainEvents::HISTORIA_PUEBLO_HITO,
            [
                'hito_id' => $hitoId,
                'protagonistas' => $protagonistas,
                'dia' => $dia,
            ],
        );

        return ['ok' => true, 'ya_existia' => false, 'entrada' => $entrada];
    }

    public static function existe(array $partida, string $clave): bool
    {
        foreach ($partida['historia_pueblo'] ?? [] as $e) {
            if (($e['clave'] ?? '') === $clave) {
                return true;
            }
        }
        return false;
    }

    public static function obtener(array $partida, string $clave): ?array
    {
        foreach ($partida['historia_pueblo'] ?? [] as $e) {
            if (($e['clave'] ?? '') === $clave) {
                return $e;
            }
        }
        return null;
    }

    /**
     * @return list<array>
     */
    public static function listar(array $partida): array
    {
        self::ensure($partida);
        return $partida['historia_pueblo'];
    }

    /**
     * Catálogo visual completo (33 posiciones en orden editorial).
     *
     * @return list<array{id: string, nombre: string, revelado: bool, entrada: ?array, orden: int, imagen: string}>
     */
    public static function catalogo(array $partida): array
    {
        self::ensure($partida);
        $resultado = [];

        foreach (self::CATÁLOGO_VISUAL as $i => $slot) {
            $hitoId = $slot['id'];
            $entrada = self::buscarPorHito($partida, $hitoId);
            $revelado = $entrada !== null && !empty($entrada['revelado']);

            $resultado[] = [
                'id' => $hitoId,
                'nombre' => $revelado ? ($slot['nombre']) : '???',
                'revelado' => $revelado,
                'entrada' => $entrada,
                'orden' => $i + 1,
                'imagen' => $slot['imagen'],
            ];
        }

        return $resultado;
    }

    /**
     * Obtiene el slot del catálogo visual para un hito.
     */
    public static function slotCatalogo(string $hitoId): ?array
    {
        foreach (self::CATÁLOGO_VISUAL as $slot) {
            if ($slot['id'] === $hitoId) {
                return $slot;
            }
        }
        return null;
    }

    // ── Celebraciones ──────────────────────────────────────────────

    /**
     * Retorna celebraciones pendientes ordenadas por orden editorial.
     *
     * @return list<array>
     */
    public static function celebracionesPendientes(array $partida): array
    {
        self::ensure($partida);
        $pendientes = [];

        foreach (self::CATÁLOGO_VISUAL as $i => $slot) {
            $entrada = self::buscarPorHito($partida, $slot['id']);
            if ($entrada === null) {
                continue;
            }
            // Legacy entries without celebracion_estado are treated as consumed
            if (($entrada['celebracion_estado'] ?? 'consumida') !== 'pendiente') {
                continue;
            }
            $pendientes[] = [
                'hito_id' => $slot['id'],
                'nombre' => $slot['nombre'],
                'imagen' => $slot['imagen'],
                'orden' => $i + 1,
                'protagonistas' => self::protagonistasParaUI($partida, $entrada),
                'dia' => $entrada['dia'] ?? 1,
                'texto_narrativo' => self::generarTextoNarrativo($slot, $entrada),
                'recompensa' => $entrada['_recompensa'] ?? null,
            ];
        }

        return $pendientes;
    }

    /**
     * Marca una celebración como consumida (ACK idempotente).
     */
    public static function ack(array &$partida, string $hitoId): bool
    {
        self::ensure($partida);
        foreach ($partida['historia_pueblo'] as &$e) {
            if (($e['hito_id'] ?? '') === $hitoId && ($e['celebracion_estado'] ?? '') === 'pendiente') {
                $e['celebracion_estado'] = 'consumida';
                return true;
            }
        }
        return false; // ya estaba consumida o no existe
    }

    // ── Helpers ────────────────────────────────────────────────────

    /**
     * Genera el texto narrativo de un hito usando la plantilla y los protagonistas reales.
     */
    private static function generarTextoNarrativo(array $slot, array $entrada): string
    {
        $plantilla = $slot['plantilla'] ?? '';
        if ($plantilla === '') {
            return 'Primer recuerdo del pueblo descubierto.';
        }

        $nombres = $entrada['nombres'] ?? [];
        $protagonistas = $entrada['protagonistas'] ?? [];
        $tags = ['{p1}', '{p2}', '{p3}', '{p4}', '{p5}'];

        foreach ($tags as $i => $tag) {
            $pid = $protagonistas[$i] ?? null;
            $nombre = $pid !== null ? ($nombres[$pid] ?? $pid) : '';
            $plantilla = str_replace($tag, $nombre, $plantilla);
        }

        return $plantilla;
    }

    /**
     * Prepara protagonistas para el frontend.
     *
     * @return list<array{id: string, nombre: string}>
     */
    private static function protagonistasParaUI(array $partida, array $entrada): array
    {
        $resultado = [];
        foreach ($entrada['protagonistas'] ?? [] as $pid) {
            $pid = (string) $pid;
            $resultado[] = [
                'id' => $pid,
                'nombre' => $entrada['nombres'][$pid] ?? IdentidadPublica::nombre($partida, $pid),
            ];
        }
        return $resultado;
    }

    private static function buscarPorHito(array $partida, string $hitoId): ?array
    {
        foreach ($partida['historia_pueblo'] ?? [] as $e) {
            if (($e['hito_id'] ?? '') === $hitoId) {
                return $e;
            }
        }
        return null;
    }

    public static function clave(string $hitoId, array $protagonistas): string
    {
        $sorted = $protagonistas;
        sort($sorted, SORT_STRING);
        return $hitoId . ':' . implode('|', $sorted);
    }

    public static function totalRevelados(array $partida): int
    {
        self::ensure($partida);
        $n = 0;
        foreach ($partida['historia_pueblo'] as $e) {
            if (!empty($e['revelado'])) {
                $n++;
            }
        }
        return $n;
    }
}
