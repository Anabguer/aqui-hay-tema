<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class ContentValidationException extends \RuntimeException
{
    /** @var list<array{archivo: string, campo: string, valor: mixed, regla: string}> */
    public array $errores;

    /** @param list<array{archivo: string, campo: string, valor: mixed, regla: string}> $errores */
    public function __construct(array $errores)
    {
        $this->errores = $errores;
        parent::__construct('content_validation_failed: ' . count($errores) . ' error(es)');
    }
}
