<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Etiquetas de ficha para el jugador. Sin números internos. */
final class RelacionVistaJugador
{
    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function de(array $partida, string $yo, string $otro, array $cal = []): array
    {
        $conocidos = RelacionEngine::seConocen($partida, $yo, $otro);
        $valor = RelacionEngine::valorSocialHacia($partida, $yo, $otro);
        $social = RelacionBandas::social($valor, $conocidos, $cal);
        if ($social === 'muy_mala' || $social === 'enemigo') {
            $social = 'cae_mal';
        }
        $pareja = ParejaEngine::estado($partida, $yo, $otro);
        $vinculo = null;
        if ($pareja === ParejaEngine::PAREJA) {
            $vinculo = 'pareja';
        } elseif ($pareja === ParejaEngine::CRISIS) {
            $vinculo = 'crisis';
        } elseif ($pareja === ParejaEngine::EX) {
            $vinculo = 'ex_pareja';
        }
        return [
            'conocidos' => $conocidos,
            'etiqueta_social' => $social,
            'etiqueta_vinculo' => $vinculo,
        ];
    }
}
