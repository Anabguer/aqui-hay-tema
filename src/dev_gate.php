<?php
declare(strict_types=1);

/** Gate modo dev — no exponer en producción sin AHT_DEV=1 o dev.local.php */
function aht_dev_enabled(): bool
{
    if (getenv('AHT_DEV') === '1') {
        return true;
    }
    return is_file(__DIR__ . '/dev.local.php');
}

/**
 * Acciones DEV de solo lectura permitidas sin el gate completo.
 * Cualquier nueva acción read-only debe añadirse aquí explícitamente.
 */
function aht_dev_readonly_allowed(string $action): bool
{
    static $allowed = [
        'dev.diagnostico.export',
    ];
    return in_array($action, $allowed, true);
}
