<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Copy y ritual de marchas (§23.2-bis): despedida visible, causa conocida, legado narrativo.
 * No altera evaluación ni probabilidades de marcha.
 */
final class MarchaPresentacionEngine
{
    /** Copy pública por causa (solo causas del motor). */
    private const FRAGMENTO_CAUSA = [
        MarchaEngine::CAUSA_AISLAMIENTO => 'Llevaba días sin relacionarse con nadie.',
        MarchaEngine::CAUSA_EMOCION_NEGATIVA => 'Llevaba un tiempo muy bajo de ánimo.',
        MarchaEngine::CAUSA_CONFLICTO => 'Había tensión con otros vecinos.',
        MarchaEngine::CAUSA_CRISIS => 'Pasó por un momento difícil en el pueblo.',
    ];

    /** @var list<string> */
    private const BANCO_DESPEDIDA = [
        'Gracias por todo, Celestine. Me llevo buenos recuerdos.',
        'No ha sido fácil decidirlo, pero es hora. Cuídate.',
        'Me voy con lo justo. Gracias por haberme acogido.',
        'Te aviso antes de que lo suelte el pueblo: gracias por escucharme.',
    ];

    /** @var list<string> */
    private const BANCO_LEGADO = [
        'No me cabe en la maleta. Creo que aquí tendrá mejor vida.',
        'Para ti, Celestine. No sabía qué regalarte, así que he elegido esto.',
        'Quiero que esto quede contigo. Me hace ilusión que lo uses.',
    ];

    public static function textoIntencion(
        array $partida,
        string $rid,
        string $causa,
        int $dia
    ): string {
        $familia = 'marcha_intencion_' . $causa;
        $nombre = IdentidadPublica::nombre($partida, $rid);
        $texto = MensajitoVoz::linea(
            $partida,
            $familia,
            ['nombre' => $nombre, 'causa' => $causa],
            'marcha_intencion|' . $rid . '|' . $causa . '|' . $dia,
            $rid
        );
        if ($texto !== '') {
            return $texto;
        }
        return MensajitoVoz::linea(
            $partida,
            'marcha_intencion',
            ['nombre' => $nombre],
            'marcha_intencion|' . $rid . '|' . $causa . '|' . $dia,
            $rid
        );
    }

    public static function fragmentoCausaPublica(string $causa): string
    {
        return self::FRAGMENTO_CAUSA[$causa] ?? '';
    }

    /**
     * Cotilleo enriquecido + despedida en Mensajito (+ legado opcional).
     */
    public static function alDejarIr(
        array &$partida,
        string $rid,
        string $causa,
        ?GameLogger $logger = null
    ): void {
        $nombre = IdentidadPublica::nombre($partida, $rid);
        $fragmento = self::fragmentoCausaPublica($causa);
        $seed = 'marcha_efectiva|' . $rid . '|' . $causa;
        $vars = ['nombre' => $nombre];
        if ($fragmento !== '') {
            $vars['causa'] = $fragmento;
            $texto = CopyCotilleoFamilias::linea('marcha_efectiva_causa', $vars, $seed);
        } else {
            $texto = CopyCotilleoFamilias::linea('marcha_efectiva', $vars, $seed);
        }
        if ($texto === '') {
            $texto = $nombre . ' se ha ido del pueblo.';
            if ($fragmento !== '') {
                $texto .= ' ' . $fragmento;
            }
            $texto .= ' La vivienda queda libre.';
        }

        BuzonEngine::crear($partida, [
            'clasificacion' => BuzonEngine::COTILLEO,
            'canal' => BuzonEngine::CANAL_COTILLEO,
            'tipo' => 'marcha_publica',
            'texto' => $texto,
            'cotilleo_meta' => CotilleoCategoria::meta(CotilleoCategoria::PUEBLO, true),
            'actores' => [$rid],
            'de_persona' => $rid,
            'origen' => [
                'tipo_evento' => DomainEvents::MARCHA_EFECTIVA,
                'es_narrativo' => true,
                'informacion_revelada' => $causa !== '' ? ['causa' => $causa] : null,
            ],
        ]);

        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $seedDesp = 'despedida|' . $rid . '|' . $dia;
        $idx = abs(crc32($seedDesp)) % count(self::BANCO_DESPEDIDA);
        $textoDesp = MensajitoVoz::linea(
            $partida,
            'marcha_despedida',
            [],
            $seedDesp,
            $rid
        );
        if ($textoDesp === '') {
            $textoDesp = self::BANCO_DESPEDIDA[$idx];
        }
        BuzonEngine::crear($partida, [
            'id' => 'msg_despedida_' . $rid . '_' . $dia,
            'clasificacion' => BuzonEngine::IMPORTANTE,
            'tipo' => 'marcha_despedida',
            'texto' => $textoDesp,
            'de_persona' => $rid,
            'actores' => [$rid],
            'origen' => [
                'tipo_evento' => DomainEvents::MARCHA_EFECTIVA,
                'es_narrativo' => true,
                'causa' => $causa !== '' ? $causa : null,
            ],
        ]);

        $rng = RngService::fromPartida($partida);
        $dejaLegado = $rng->nextFloat() < 0.42;
        $rng->persistToPartida($partida);
        if ($dejaLegado) {
            $idxL = abs(crc32('legado|' . $rid)) % count(self::BANCO_LEGADO);
            $textoLegado = MensajitoVoz::linea(
                $partida,
                'marcha_legado',
                [],
                'legado|' . $rid,
                $rid
            );
            if ($textoLegado === '') {
                $textoLegado = self::BANCO_LEGADO[$idxL];
            }
            BuzonEngine::crear($partida, [
                'clasificacion' => BuzonEngine::IMPORTANTE,
                'tipo' => 'legado_despedida',
                'texto' => $textoLegado,
                'de_persona' => $rid,
                'actores' => [$rid],
                'origen' => [
                    'tipo_evento' => DomainEvents::MARCHA_EFECTIVA,
                    'es_narrativo' => true,
                    'legado_despedida' => true,
                ],
            ]);
        }

        \aht_log_optional($logger, $partida, 'marcha_presentacion', [
            'residente_id' => $rid,
            'causa' => $causa,
            'legado' => $dejaLegado,
        ]);
    }
}
