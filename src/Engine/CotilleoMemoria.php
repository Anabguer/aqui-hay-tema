<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Memoria temporal de cotilleos publicados por pareja.
 * Responde: ¿esta publicación aporta información narrativa nueva?
 */
final class CotilleoMemoria
{
    private const CLAVE = 'cotilleo_memoria_par';

    public static function ensure(array &$partida): void
    {
        $partida[self::CLAVE] ??= [];
    }

    /**
     * Registra una publicación de cotilleo para un par.
     */
    public static function registrar(array &$partida, array $actores, string $tipo, string $resultado): void
    {
        self::ensure($partida);
        $par = self::parKey($actores);
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $partida[self::CLAVE][$par][] = [
            'dia' => $dia,
            'tipo' => $tipo,
            'resultado' => $resultado,
        ];
    }

    /**
     * Evalúa si un nuevo cotilleo de encuentro para este par debe publicarse.
     *
     * @return array{deberia_publicar: bool, semantica: string, n_previos: int}
     *   semantica: 'primera_vez' | 'repeticion' | 'patron' | 'silencio'
     */
    public static function evaluar(array $partida, array $actores, string $resultadoActual): array
    {
        self::ensure($partida);
        $par = self::parKey($actores);
        $previos = $partida[self::CLAVE][$par] ?? [];

        if ($previos === []) {
            return ['deberia_publicar' => true, 'semantica' => 'primera_vez', 'n_previos' => 0];
        }

        $nPrevios = count($previos);
        $ultimo = end($previos);
        $diaActual = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $diaUltimo = $ultimo['dia'] ?? 0;

        $encuentrosPrevios = array_filter($previos, fn($p) => ($p['tipo'] ?? '') === 'encuentro');
        $nEncuentros = count($encuentrosPrevios);

        if ($nEncuentros === 0) {
            return ['deberia_publicar' => true, 'semantica' => 'primera_vez', 'n_previos' => $nPrevios];
        }

        $mismoResultado = ($ultimo['resultado'] ?? '') === $resultadoActual;
        $mismoDia = $diaActual === $diaUltimo;
        $consecutivo = ($diaActual - $diaUltimo) <= 2;

        if ($mismoResultado && $consecutivo && $nEncuentros >= 2) {
            return ['deberia_publicar' => false, 'semantica' => 'silencio', 'n_previos' => $nPrevios];
        }

        if ($nEncuentros >= 3) {
            return ['deberia_publicar' => true, 'semantica' => 'patron', 'n_previos' => $nPrevios];
        }

        return ['deberia_publicar' => true, 'semantica' => 'repeticion', 'n_previos' => $nPrevios];
    }

    private static function parKey(array $actores): string
    {
        $ids = array_values(array_filter($actores, fn($a) => is_string($a) && $a !== ''));
        sort($ids);
        return implode('/', $ids);
    }
}
