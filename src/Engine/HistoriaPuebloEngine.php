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

    /** @var list<array{id: string, nombre: string, imagen: string}> Catálogo visual editorial (33 posiciones, orden fijo) */
    public const CATÁLOGO_VISUAL = [
        ['id' => 'empezo_el_cotarro',  'nombre' => 'AQUÍ EMPEZÓ EL COTARRO',  'imagen' => 'assets/img/historia/01.png'],
        ['id' => 'hito_02',            'nombre' => '???',                      'imagen' => 'assets/img/historia/02.png'],
        ['id' => 'hito_03',            'nombre' => '???',                      'imagen' => 'assets/img/historia/03.png'],
        ['id' => 'hito_04',            'nombre' => '???',                      'imagen' => 'assets/img/historia/04.png'],
        ['id' => 'hito_05',            'nombre' => '???',                      'imagen' => 'assets/img/historia/05.png'],
        ['id' => 'hito_06',            'nombre' => '???',                      'imagen' => 'assets/img/historia/06.png'],
        ['id' => 'hito_07',            'nombre' => '???',                      'imagen' => 'assets/img/historia/07.png'],
        ['id' => 'hito_08',            'nombre' => '???',                      'imagen' => 'assets/img/historia/08.png'],
        ['id' => 'hito_09',            'nombre' => '???',                      'imagen' => 'assets/img/historia/09.png'],
        ['id' => 'hito_10',            'nombre' => '???',                      'imagen' => 'assets/img/historia/10.png'],
        ['id' => 'hito_11',            'nombre' => '???',                      'imagen' => 'assets/img/historia/11.png'],
        ['id' => 'hito_12',            'nombre' => '???',                      'imagen' => 'assets/img/historia/12.png'],
        ['id' => 'hito_13',            'nombre' => '???',                      'imagen' => 'assets/img/historia/13.png'],
        ['id' => 'hito_14',            'nombre' => '???',                      'imagen' => 'assets/img/historia/14.png'],
        ['id' => 'hito_15',            'nombre' => '???',                      'imagen' => 'assets/img/historia/15.png'],
        ['id' => 'hito_16',            'nombre' => '???',                      'imagen' => 'assets/img/historia/16.png'],
        ['id' => 'hito_17',            'nombre' => '???',                      'imagen' => 'assets/img/historia/17.png'],
        ['id' => 'hito_18',            'nombre' => '???',                      'imagen' => 'assets/img/historia/18.png'],
        ['id' => 'hito_19',            'nombre' => '???',                      'imagen' => 'assets/img/historia/19.png'],
        ['id' => 'hito_20',            'nombre' => '???',                      'imagen' => 'assets/img/historia/20.png'],
        ['id' => 'hito_21',            'nombre' => '???',                      'imagen' => 'assets/img/historia/21.png'],
        ['id' => 'hito_22',            'nombre' => '???',                      'imagen' => 'assets/img/historia/22.png'],
        ['id' => 'hito_23',            'nombre' => '???',                      'imagen' => 'assets/img/historia/23.png'],
        ['id' => 'hito_24',            'nombre' => '???',                      'imagen' => 'assets/img/historia/24.png'],
        ['id' => 'hito_25',            'nombre' => '???',                      'imagen' => 'assets/img/historia/25.png'],
        ['id' => 'hito_26',            'nombre' => '???',                      'imagen' => 'assets/img/historia/26.png'],
        ['id' => 'hito_27',            'nombre' => '???',                      'imagen' => 'assets/img/historia/27.png'],
        ['id' => 'hito_28',            'nombre' => '???',                      'imagen' => 'assets/img/historia/28.png'],
        ['id' => 'hito_29',            'nombre' => '???',                      'imagen' => 'assets/img/historia/29.png'],
        ['id' => 'hito_30',            'nombre' => '???',                      'imagen' => 'assets/img/historia/30.png'],
        ['id' => 'hito_31',            'nombre' => '???',                      'imagen' => 'assets/img/historia/31.png'],
        ['id' => 'hito_32',            'nombre' => '???',                      'imagen' => 'assets/img/historia/32.png'],
        ['id' => 'hito_33',            'nombre' => '???',                      'imagen' => 'assets/img/historia/33.png'],
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
     * Cruza el catálogo editorial con el estado real de la partida.
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
     * Busca una entrada por hito_id (independiente de protagonistas).
     */
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

    /**
     * Placeholder para futura integración con el catálogo de hitos
     * que se irá ampliando pieza a pieza.
     */
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
