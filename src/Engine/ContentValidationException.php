<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class ContentValidationException extends \RuntimeException
{
    /** @param list<array{archivo: string, campo: string, valor: mixed, regla: string}> $errores */
    public function __construct(public readonly array $errores)
    {
        parent::__construct('content_validation_failed: ' . count($errores) . ' error(es)');
    }
}
