<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Causa narrativa de un encuentro derivada de factores reales de EncuentroExperiencia.
 * No inventa excusas post hoc: elige el factor ponderado más influyente en la carga.
 */
final class EncuentroExperienciaNarrativa
{
    /**
     * @param array<string, mixed> $exp  salida de EncuentroExperiencia::resolver
     * @param array<string, mixed> $cal
     * @return array<string, mixed>|null
     */
    public static function de(array $partida, array $encuentro, array $exp, array $cal): ?array
    {
        $ids = array_values($encuentro['participantes'] ?? []);
        if (count($ids) < 2) {
            return null;
        }
        $a = (string) $ids[0];
        $b = (string) $ids[1];
        $por = is_array($exp['por_participante'] ?? null) ? $exp['por_participante'] : [];
        $resA = (string) ($por[$a]['resultado'] ?? 'normal');
        $resB = (string) ($por[$b]['resultado'] ?? 'normal');
        $promedio = self::scoreResultado($resA) + self::scoreResultado($resB);
        if ($promedio >= 0) {
            return null;
        }

        $peorPid = self::scoreResultado($resA) <= self::scoreResultado($resB) ? $a : $b;
        $otro = $peorPid === $a ? $b : $a;
        $resPeor = $peorPid === $a ? $resA : $resB;
        $desglose = EncuentroExperiencia::desgloseCarga($exp, $peorPid, $cal);
        $causa = self::causaPrincipal($desglose, $partida, $peorPid, $otro, $encuentro, $cal);

        $nomPeor = IdentidadPublica::nombre($partida, $peorPid);
        $nomOtro = IdentidadPublica::nombre($partida, $otro);
        $texto = self::textoDeCausa($causa, $nomPeor, $nomOtro, $resPeor);

        return [
            'resultado_experiencia' => $resPeor,
            'resultado_promedio' => $promedio,
            'causa_principal' => $causa['clave'] ?? 'azar',
            'factores_relevantes' => $causa['factores'] ?? [],
            'persona_mas_afectada' => $peorPid,
            'persona_mas_afectada_nombre' => $nomPeor,
            'texto' => $texto,
        ];
    }

    private static function scoreResultado(string $res): int
    {
        switch ($res) {
            case 'muy_bien':
                return 2;
            case 'bien':
                return 1;
            case 'normal':
                return 0;
            case 'mal':
                return -1;
            case 'muy_mal':
                return -2;
            default:
                return 0;
        }
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $encuentro
     * @param array<string, mixed> $cal
     * @return array{clave: string, factores: list<array{factor: string, contrib: float}>}
     */
    private static function causaPrincipal(
        array $desglose,
        array $partida,
        string $peor,
        string $otro,
        array $encuentro,
        array $cal
    ): array {
        $contrib = is_array($desglose['contribuciones'] ?? null) ? $desglose['contribuciones'] : [];
        $negativos = [];
        foreach ($contrib as $k => $v) {
            if (!is_numeric($v) || (float) $v >= -0.02) {
                continue;
            }
            $negativos[] = ['factor' => (string) $k, 'contrib' => (float) $v];
        }
        usort($negativos, static fn($x, $y) => $x['contrib'] <=> $y['contrib']);
        $clave = 'azar';
        if ($negativos !== []) {
            $clave = (string) $negativos[0]['factor'];
        }
        $emo = (string) ($partida['residentes'][$peor]['runtime']['estado_emocional']['id'] ?? 'neutro');
        if ($clave === 'emocional' || EstadoEmocional::canonId($emo) === EstadoEmocional::ENFADADO) {
            $clave = 'emocional';
        }
        return ['clave' => $clave, 'factores' => array_slice($negativos, 0, 4)];
    }

    /**
     * @param array{clave: string} $causa
     */
    private static function textoDeCausa(array $causa, string $nomPeor, string $nomOtro, string $res): string
    {
        $clave = (string) ($causa['clave'] ?? 'azar');
        switch ($clave) {
            case 'emocional':
                return $nomPeor . ' no estaba en el mejor momento y el plan no fluyó.';
            case 'hobbies_plan':
            case 'lugar':
                return 'El plan no terminó de encajar del todo.';
            case 'conflicto':
                return 'Entre ' . $nomPeor . ' y ' . $nomOtro . ' hubo un roce que no ayudó.';
            case 'vinculo_social':
                return 'Todavía no fluye entre ellos como para ese plan.';
            case 'compat_ab':
            case 'compat_ba':
                return 'No conectaron del todo; la conversación no despegó.';
            case 'quimica':
                return 'No encontraron demasiado de qué hablar.';
            case 'vinculo_romance':
                return 'El ambiente no ayudó a que saliera bien.';
            default:
                return $res === 'muy_mal'
                    ? 'La quedada se torció y el pueblo ya lo nota.'
                    : 'La quedada no terminó como esperaban.';
        }
    }
}
