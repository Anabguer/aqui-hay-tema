<?php
declare(strict_types=1);
/**
 * Entrada fija de playtest para Neni.
 * Fuerza taller + laboratorio playtest_01 (8 vecinos, sistemas ON, sin tutorial juego_v1).
 * No fija seed: cada Nueva partida puede ser limpia.
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
$query = $_GET;
$query['taller'] = '1';
$query['lab'] = '1';
if (!isset($query['config'])) {
    $query['config'] = 'playtest_01';
}
$qs = http_build_query($query);
header('Location: play.php?' . $qs);
exit;
