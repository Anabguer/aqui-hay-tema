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

    /** @var list<array{id: string, nombre: string}> Catálogo visual editorial (33 posiciones, orden fijo) */
    public const CATÁLOGO_VISUAL = [
        ['id' => 'empezo_el_cotarro',  'nombre' => 'AQUÍ EMPEZÓ EL COTARRO'],
        ['id' => 'hito_02',            'nombre' => '???'],
        ['id' => 'hito_03',            'nombre' => '???'],
        ['id' => 'hito_04',            'nombre' => '???'],
        ['id' => 'hito_05',            'nombre' => '???'],
        ['id' => 'hito_06',            'nombre' => '???'],
        ['id' => 'hito_07',            'nombre' => '???'],
        ['id' => 'hito_08',            'nombre' => '???'],
        ['id' => 'hito_09',            'nombre' => '???'],
        ['id' => 'hito_10',            'nombre' => '???'],
        ['id' => 'hito_11',            'nombre' => '???'],
        ['id' => 'hito_12',            'nombre' => '???'],
        ['id' => 'hito_13',            'nombre' => '???'],
        ['id' => 'hito_14',            'nombre' => '???'],
        ['id' => 'hito_15',            'nombre' => '???'],
        ['id' => 'hito_16',            'nombre' => '???'],
        ['id' => 'hito_17',            'nombre' => '???'],
        ['id' => 'hito_18',            'nombre' => '???'],
        ['id' => 'hito_19',            'nombre' => '???'],
        ['id' => 'hito_20',            'nombre' => '???'],
        ['id' => 'hito_21',            'nombre' => '???'],
        ['id' => 'hito_22',            'nombre' => '???'],
        ['id' => 'hito_23',            'nombre' => '???'],
        ['id' => 'hito_24',            'nombre' => '???'],
        ['id' => 'hito_25',            'nombre' => '???'],
        ['id' => 'hito_26',            'nombre' => '???'],
        ['id' => 'hito_27',            'nombre' => '???'],
        ['id' => 'hito_28',            'nombre' => '???'],
        ['id' => 'hito_29',            'nombre' => '???'],
        ['id' => 'hito_30',            'nombre' => '???'],
        ['id' => 'hito_31',            'nombre' => '???'],
        ['id' => 'hito_32',            'nombre' => '???'],
        ['id' => 'hito_33',            'nombre' => '???'],
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
     * @return list<array{id: string, nombre: string, revelado: bool, entrada: ?array, orden: int}>
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
