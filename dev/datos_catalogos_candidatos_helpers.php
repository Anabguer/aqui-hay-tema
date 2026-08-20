<?php
declare(strict_types=1);

/**
 * Helpers de volcado JSON candidato V2. No se cargan en PLAY.
 */

/**
 * @param list<string> $desc
 * @return array<string, list<int>>
 */
function aht_reparto_canales(array $desc): array
{
    $n = count($desc);
    if ($n < 1) {
        return [
            'libreta' => [],
            'cotilleo' => [],
            'conversacion' => [],
            'mensaje' => [],
            'plan' => [],
        ];
    }
    $lib = [];
    $cot = [];
    $con = [];
    $men = [];
    $pla = [];
    for ($i = 0; $i < $n; $i++) {
        if ($i < 3 || $i % 5 === 0) {
            $lib[] = $i;
        }
        if ($i === 0 || $i % 5 === 1) {
            $con[] = $i;
        }
        if ($i === $n - 1 || $i % 5 === 2) {
            $cot[] = $i;
        }
        if ($i === min(2, $n - 1) || $i % 5 === 3) {
            $men[] = $i;
        }
        if ($i === min(3, $n - 1) || $i % 5 === 4) {
            $pla[] = $i;
        }
    }
    $uniq = static function (array $a): array {
        return array_values(array_unique($a));
    };
    return [
        'libreta' => $uniq($lib),
        'cotilleo' => $uniq($cot),
        'conversacion' => $uniq($con),
        'mensaje' => $uniq($men),
        'plan' => $uniq($pla),
    ];
}

/**
 * @param list<string> $lugares
 * @param list<string> $usos
 * @param list<string> $desc
 * @param list<string> $hooks
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function aht_candidato_item(
    string $id,
    string $familia,
    string $etiqueta,
    array $lugares,
    array $usos,
    array $desc,
    array $hooks,
    array $extra = []
): array {
    $canales = $extra['canales'] ?? aht_reparto_canales($desc);
    unset($extra['canales']);
    $row = [
        'id' => $id,
        'familia' => $familia,
        'etiqueta' => $etiqueta,
        'lugar_ids' => $lugares,
        'usos' => $usos,
        'descubrimientos' => array_values($desc),
        'canales' => $canales,
        'hooks' => $hooks,
    ];
    return array_merge($row, $extra);
}

/**
 * @param list<string> $afinidadAficion
 * @param list<string> $lugares
 * @param list<string> $desc
 * @param list<string> $hooks
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function aht_g(
    string $id,
    string $familia,
    string $eti,
    array $afinidadAficion,
    array $lugares,
    array $desc,
    array $hooks,
    array $extra = []
): array {
    $canales = $extra['canales'] ?? aht_reparto_canales($desc);
    unset($extra['canales']);
    return array_merge([
        'id' => $id,
        'familia' => $familia,
        'etiqueta' => $eti,
        'afinidad_aficiones' => $afinidadAficion,
        'lugar_ids' => $lugares,
        'usos' => ['conversaciones', 'regalos', 'compatibilidad', 'descubrimientos', 'eventos', 'celestine'],
        'descubrimientos' => array_values($desc),
        'canales' => $canales,
        'hooks' => $hooks,
    ], $extra);
}

/**
 * @param list<string> $desc
 * @param list<string> $hooks
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function aht_r(string $id, string $eje, string $eti, array $desc, array $hooks, array $extra = []): array
{
    $canales = $extra['canales'] ?? aht_reparto_canales($desc);
    unset($extra['canales']);
    return array_merge([
        'id' => $id,
        'eje' => $eje,
        'etiqueta' => $eti,
        'usos' => ['compatibilidad', 'conversaciones', 'descubrimientos', 'celestine', 'eventos'],
        'descubrimientos' => array_values($desc),
        'canales' => $canales,
        'hooks' => $hooks,
        'modula' => true,
        'asigna_destino' => false,
    ], $extra);
}

/**
 * @param list<string> $desc
 * @param list<string> $hooks
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function aht_m(string $id, string $eti, array $desc, array $hooks, array $extra = []): array
{
    $canales = $extra['canales'] ?? aht_reparto_canales($desc);
    unset($extra['canales']);
    return array_merge([
        'id' => $id,
        'etiqueta' => $eti,
        'mecanico' => false,
        'usos' => ['descubrimientos', 'cotilleo', 'conversaciones', 'regalos', 'cumpleaños'],
        'descubrimientos' => array_values($desc),
        'canales' => $canales,
        'hooks' => $hooks,
    ], $extra);
}
