<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Etiquetas de ficha para el jugador. Sin química ni compatibilidad. */
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
        $socialBanda = RelacionBandas::social($valor, $conocidos, $cal);
        if ($socialBanda === 'muy_mala' || $socialBanda === 'enemigo') {
            $socialBanda = 'cae_mal';
        }
        $socialUi = EtiquetaRelacionPlay::social($socialBanda, $conocidos);

        $pareja = ParejaEngine::estado($partida, $yo, $otro);
        $vinculo = null;
        if ($pareja === ParejaEngine::PAREJA) {
            $vinculo = 'pareja';
        } elseif ($pareja === ParejaEngine::CRISIS) {
            $vinculo = 'crisis';
        } elseif ($pareja === ParejaEngine::EX) {
            $vinculo = 'ex_pareja';
        }

        $romance = EtiquetaRelacionPlay::romanceHacia($partida, $yo, $otro, $cal);

        return [
            'conocidos' => $conocidos,
            'etiqueta_social' => $socialBanda,
            'etiqueta_social_ui' => $socialUi['etiqueta'],
            'emoji_social' => $socialUi['emoji'],
            'social_valor' => $conocidos ? $valor : 0,
            'social_bar_pct' => EtiquetaRelacionPlay::barraSocialPct($valor, $conocidos),
            'social_negativo' => $valor < 0,
            'etiqueta_vinculo' => $vinculo,
            'romance_visible' => $romance !== null,
            'etiqueta_romance' => $romance !== null ? $romance['etiqueta'] : null,
            'emoji_romance' => $romance !== null ? $romance['emoji'] : null,
            'romance_banda' => $romance !== null ? $romance['banda'] : null,
        ];
    }
}
