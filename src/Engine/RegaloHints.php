<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Regalos F2 - Hints al regalar. SOLO conocimiento ya descubierto por el
 * jugador (DiscoveryEngine). Jamas lee preferencias ocultas del perfil:
 * sin descubrimientos previos no hay pista. Prioridad = precedencia de la
 * reaccion real: no_gusta > encanta > gusta.
 */
final class RegaloHints
{
    /**
     * @param list<string> $hobbyIds hobby_ids del objeto del catalogo
     * @return array{nivel: string, campo: string, valor: string}|null
     */
    public static function paraObjeto(array $partida, string $residenteId, array $hobbyIds): ?array
    {
        $ids = [];
        foreach ($hobbyIds as $h) {
            if (is_string($h) && $h !== '') {
                $ids[] = $h;
            }
        }
        if ($ids === []) {
            return null;
        }
        // 1) rechazo descubierto gana siempre (precedencia canonica)
        foreach ($ids as $id) {
            $campo = ConocimientoNpc::campoRechazo('hobby', $id);
            if (self::sabe($partida, $residenteId, $campo)) {
                return ['nivel' => 'no_le_gusta', 'campo' => $campo, 'valor' => $id];
            }
        }
        // 2) gusto fuerte descubierto
        foreach ($ids as $id) {
            $campo = ConocimientoNpc::campoGusto('hobby', $id);
            if (self::sabe($partida, $residenteId, $campo)) {
                return ['nivel' => 'le_encanta', 'campo' => $campo, 'valor' => $id];
            }
        }
        // 3) hobby conocido (reveal inicial / encuentros): regalo afin a lo suyo
        foreach ($ids as $id) {
            if (DiscoveryReveal::jugadorSabeHobby($partida, $residenteId, $id)) {
                return ['nivel' => 'le_gusta', 'campo' => ConocimientoNpc::campoHobby($id), 'valor' => $id];
            }
        }
        return null;
    }

    /** Copy humano para el selector. Sin IDs tecnicos ni tono de informe. */
    public static function textoDe(array $hint, string $residenteNombre, CatalogStore $catalog): string
    {
        $label = EtiquetaFicha::hobby((string) ($hint['valor'] ?? ''), $catalog);
        switch ($hint['nivel'] ?? '') {
            case 'no_le_gusta':
                return 'Has descubierto que ' . $residenteNombre . ' no soporta ' . self::min($label) . '.';
            case 'le_encanta':
                return 'Sabes que a ' . $residenteNombre . ' le encanta ' . $label . '.';
            case 'le_gusta':
                return 'Sabes que a ' . $residenteNombre . ' le gusta ' . $label . '.';
        }
        return '';
    }

    private static function min(string $s): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($s, 'UTF-8');
        }
        return strtolower($s);
    }

    private static function sabe(array $partida, string $residenteId, string $campo): bool
    {
        return DiscoveryEngine::estado($partida, $residenteId, $campo) === DiscoveryEngine::DESCUBIERTO;
    }
}
