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
        ['id' => 'empezo_el_cotarro', 'nombre' => 'AQUÍ EMPEZÓ EL COTARRO',    'imagen' => 'assets/img/historia/01.png', 'plantilla' => '{p1}, {p2} y {p3} acaban de llegar al pueblo. Aquí empieza todo.', 'recompensa' => null],
        ['id' => 'hito_02',           'nombre' => 'HOLA, ¿TÚ QUIÉN ERES?',    'imagen' => 'assets/img/historia/02.png', 'plantilla' => '{p1} y {p2} se han cruzado por primera vez. A ver qué sale de aquí.', 'recompensa' => null],
        ['id' => 'hito_03',           'nombre' => 'UY. 👀',                    'imagen' => 'assets/img/historia/03.png', 'plantilla' => '{p1} ha empezado a mirar a {p2} con otros ojitos. Uy, uy, uy…', 'recompensa' => null],
        ['id' => 'hito_04',           'nombre' => 'PUES PARECE QUE ES MUTUO',  'imagen' => 'assets/img/historia/04.png', 'plantilla' => 'Pues mira, {p1} y {p2} sienten lo mismo. Esto se pone interesante.', 'recompensa' => null],
        ['id' => 'hito_05',           'nombre' => 'ESTO TÉCNICAMENTE ES UNA CITA', 'imagen' => 'assets/img/historia/05.png', 'plantilla' => '{p1} y {p2} se han citado. Que conste, esto es una cita.', 'recompensa' => null],
        ['id' => 'hito_06',           'nombre' => 'PUES NO HA IDO NADA MAL…',  'imagen' => 'assets/img/historia/06.png', 'plantilla' => 'La cita de {p1} y {p2} ha ido pero que muy bien. Aquí hay material.', 'recompensa' => null],
        ['id' => 'hito_07',           'nombre' => 'TIERRA, TRÁGAME',           'imagen' => 'assets/img/historia/07.png', 'plantilla' => 'La cita de {p1} y {p2} no ha podido ir peor. Tierra, trágame.', 'recompensa' => null],
        ['id' => 'hito_08',           'nombre' => '¡QUE SE HAN BESADO!',       'imagen' => 'assets/img/historia/08.png', 'plantilla' => '¡{p1} y {p2} se han besado! Esto había que apuntarlo.', 'recompensa' => null],
        ['id' => 'hito_09',           'nombre' => 'ERA COQUETEO. CONFIRMADO.', 'imagen' => 'assets/img/historia/09.png', 'plantilla' => '{p1} le ha tirado la caña a {p2}… y ha funcionado. Coqueteo confirmado.', 'recompensa' => null],
        ['id' => 'hito_10',           'nombre' => 'YA TENEMOS TORTOLITOS',     'imagen' => 'assets/img/historia/10.png', 'plantilla' => '{p1} y {p2} ya son oficialmente pareja. ¡Ya tenemos tortolitos!', 'recompensa' => null],
        ['id' => 'hito_11',           'nombre' => 'SE LO HA DICHO. SE LO HA DICHO.', 'imagen' => 'assets/img/historia/11.png', 'plantilla' => '{p1} se lo ha dicho a {p2}. Sí, sí. SE LO HA DICHO.', 'recompensa' => null],
        ['id' => 'hito_12',           'nombre' => 'BUENO, PUES NO.',           'imagen' => 'assets/img/historia/12.png', 'plantilla' => '{p1} lo tenía claro, pero {p2} ha dicho que no. Bueno, pues no.', 'recompensa' => null],
        ['id' => 'hito_13',           'nombre' => 'SE HA LIAO',                'imagen' => 'assets/img/historia/13.png', 'plantilla' => '{p1} y {p2} se han liado parda. Esto huele a pelea.', 'recompensa' => null],
        ['id' => 'hito_14',           'nombre' => 'TENEMOS DRAMA',             'imagen' => 'assets/img/historia/14.png', 'plantilla' => '{p1} y {p2} tienen un problema gordo. Tenemos drama en el pueblo.', 'recompensa' => null],
        ['id' => 'hito_15',           'nombre' => 'HASTA AQUÍ HEMOS LLEGADO',  'imagen' => 'assets/img/historia/15.png', 'plantilla' => '{p1} y {p2} lo han dejado. Tenemos ruptura oficial.', 'recompensa' => null],
        ['id' => 'hito_16',           'nombre' => 'DONDE DIJE DIGO…',          'imagen' => 'assets/img/historia/16.png', 'plantilla' => 'Pues nada, {p1} y {p2} han vuelto. Donde dije digo, digo Diego.', 'recompensa' => null],
        ['id' => 'hito_17',           'nombre' => 'YO NO LLORO, TÚ LLORAS',   'imagen' => 'assets/img/historia/17.png', 'plantilla' => 'Hoy {p1} no está para fiestas. Yo no lloro, tú lloras.', 'recompensa' => null],
        ['id' => 'hito_18',           'nombre' => 'HOY NO ME TOQUES LAS NARICES', 'imagen' => 'assets/img/historia/18.png', 'plantilla' => '{p1} está que trina. Hoy mejor mantener las distancias.', 'recompensa' => null],
        ['id' => 'hito_19',           'nombre' => 'MIRA QUÉ CONTENTA/O VA',   'imagen' => 'assets/img/historia/19.png', 'plantilla' => 'A {p1} hoy no le cabe la alegría en el cuerpo. Mira qué bien va.', 'recompensa' => null],
        ['id' => 'hito_20',           'nombre' => 'TOMA, PORQUE SÍ 🎁',       'imagen' => 'assets/img/historia/20.png', 'plantilla' => 'Celestine le ha regalado algo a {p1}. Porque sí, porque se lo merece.', 'recompensa' => null],
        ['id' => 'hito_21',           'nombre' => 'INAUGURAMOS EL CHIRINGUITO', 'imagen' => 'assets/img/historia/21.png', 'plantilla' => '{p1} y {p2} se han encontrado en el bar por primera vez. Inauguramos.', 'recompensa' => null],
        ['id' => 'hito_22',           'nombre' => 'AQUÍ YA HAY CONFIANZA',     'imagen' => 'assets/img/historia/22.png', 'plantilla' => '{p1} y {p2} ya se cuentan las cosas. Aquí ya hay confianza de verdad.', 'recompensa' => null],
        ['id' => 'hito_23',           'nombre' => 'SUJÉTAME EL CUBATA',        'imagen' => 'assets/img/historia/23.png', 'plantilla' => 'Hoy se ha juntado medio pueblo. Esto promete. Sujétame el cubata.', 'recompensa' => null],
        ['id' => 'hito_24',           'nombre' => 'ESTE PUEBLO YA TIENE VIDA', 'imagen' => 'assets/img/historia/24.png', 'plantilla' => 'Ya pasan cosas sin que nadie las empuje. Este pueblo empieza a tener vida propia.', 'recompensa' => null],
        ['id' => 'hito_25',           'nombre' => 'PUES YO ME VOY',            'imagen' => 'assets/img/historia/25.png', 'plantilla' => '{p1} ha hecho las maletas y se ha marchado del pueblo. Esto no era fácil.', 'recompensa' => null],
        ['id' => 'hito_26',           'nombre' => 'NO TODO IBA A SER COTILLEO', 'imagen' => 'assets/img/historia/26.png', 'plantilla' => '{p1} ha echado una mano a {p2} cuando más hacía falta. No todo es cotilleo.', 'recompensa' => null],
        ['id' => 'hito_27',           'nombre' => 'QUEDAMOS COMO AMIGOS… ¿NO?', 'imagen' => 'assets/img/historia/27.png', 'plantilla' => 'Entre {p1} y {p2} parecía que podía haber chispa… Pues va a ser que amigos.', 'recompensa' => null],
        ['id' => 'hito_28',           'nombre' => 'DE AQUÍ NO ME MUEVE NADIE', 'imagen' => 'assets/img/historia/28.png', 'plantilla' => 'Lo de {p1} y {p2} ya no es cosa de un día. Aquí hay un vínculo de los buenos.', 'recompensa' => null],
        ['id' => 'hito_29',           'nombre' => 'VEN, QUE LO ARREGLAMOS',    'imagen' => 'assets/img/historia/29.png', 'plantilla' => '{p1} y {p2} han dejado el enfado atrás. Venga, va. Hacemos las paces.', 'recompensa' => null],
        ['id' => 'hito_30',           'nombre' => 'AHORA SÍ SOMOS UÑA Y CARNE', 'imagen' => 'assets/img/historia/30.png', 'plantilla' => '{p1} y {p2} ya son uña y carne. Esto va a ser para rato.', 'recompensa' => null],
        ['id' => 'hito_31',           'nombre' => '¿OTRA VEZ VOSOTROS DOS?',   'imagen' => 'assets/img/historia/31.png', 'plantilla' => '{p1} y {p2} vuelven a quedar juntos. ¿Otra vez vosotros dos?', 'recompensa' => null],
        ['id' => 'hito_32',           'nombre' => 'TODO EL MUNDO PA\' DENTRO', 'imagen' => 'assets/img/historia/32.png', 'plantilla' => 'Aquí ya no falta nadie. Todo el mundo pa\' dentro, que esto empieza.', 'recompensa' => null],
        ['id' => 'hito_33',           'nombre' => 'AQUÍ HUELE A COTILLEO',     'imagen' => 'assets/img/historia/33.png', 'plantilla' => '{p1} ha estado pendiente de lo que no le importa. Uy… aquí huele a cotilleo.', 'recompensa' => null],
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
