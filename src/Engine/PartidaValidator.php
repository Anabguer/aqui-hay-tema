<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class PartidaValidator
{
    public static function validar(array $partida): array
    {
        $errores = [];
        if (!isset($partida['meta']['partida_id'])) {
            $errores[] = ['campo' => 'meta.partida_id', 'regla' => 'requerido'];
        }
        if (!isset($partida['reloj']['dia_pueblo'], $partida['reloj']['hora_actual'])) {
            $errores[] = ['campo' => 'reloj', 'regla' => 'incompleto'];
        }
        foreach ($partida['encuentros'] ?? [] as $i => $enc) {
            foreach ($enc['participantes'] ?? [] as $pid) {
                if (!isset($partida['residentes'][$pid])) {
                    $errores[] = ['campo' => "encuentros[$i].participantes", 'valor' => $pid, 'regla' => 'participante_inexistente'];
                }
            }
        }
        return $errores;
    }
}
