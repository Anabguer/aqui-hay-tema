<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Nivel de intervención: presentar / quedada / cita / plan de pareja.
 * Mismo motor debajo: propuesta → voluntad → agenda → encuentro.
 */
final class PropuestaNivel
{
    public const PRESENTAR = 'conocerse';
    public const QUEDADA = 'amistad';
    public const CITA = 'romantico';
    public const PLAN_PAREJA = 'otro';

    public const MODO_ORGANIZAR = 'organizar';
    public const MODO_PROPONER = 'proponer';

    public static function tipoPara(array $partida, string $a, string $b): string
    {
        $est = ParejaEngine::estado($partida, $a, $b);
        if ($est === ParejaEngine::PAREJA || $est === ParejaEngine::CRISIS) {
            return self::PLAN_PAREJA;
        }
        if (!RelacionEngine::seConocen($partida, $a, $b)) {
            return self::PRESENTAR;
        }
        $ra = RelacionEngine::romanceHacia($partida, $a, $b);
        $rb = RelacionEngine::romanceHacia($partida, $b, $a);
        if (($ra !== null && $ra >= 8) || ($rb !== null && $rb >= 8)) {
            return self::CITA;
        }
        return self::QUEDADA;
    }
}
