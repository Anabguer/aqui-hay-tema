<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Etiquetas canónicas de relación para PLAY (una sola taxonomía con el motor). */
final class EtiquetaRelacionPlay
{
    /**
     * @return array{etiqueta: string, emoji: string}
     */
    public static function social(string $banda, bool $conocidos): array
    {
        if (!$conocidos) {
            return ['etiqueta' => 'Desconocido', 'emoji' => ''];
        }
        $map = [
            'desconocido' => ['Desconocido', ''],
            'conocido' => ['Conocido', '👋'],
            'cae_bien' => ['Le cae bien', '🙂'],
            'amigo' => ['Amigo', '🙂'],
            'buen_amigo' => ['Buen amigo', '🤝'],
            'mejor_amigo' => ['Mejor amigo', '🤝'],
            'cae_mal' => ['Le cae mal', '😒'],
            'muy_mala' => ['Le cae mal', '😒'],
            'enemigo' => ['Le cae mal', '😒'],
        ];
        $row = $map[$banda] ?? [str_replace('_', ' ', $banda), ''];
        return ['etiqueta' => $row[0], 'emoji' => $row[1]];
    }

    /**
     * @return array{etiqueta: string, emoji: string, banda: string}|null
     */
    public static function romanceHacia(array $partida, string $desde, string $hacia, array $cal = []): ?array
    {
        $pareja = ParejaEngine::estado($partida, $desde, $hacia);
        if ($pareja === ParejaEngine::PAREJA) {
            return ['etiqueta' => 'Pareja', 'emoji' => '❤️', 'banda' => 'pareja'];
        }
        if ($pareja === ParejaEngine::CRISIS) {
            return ['etiqueta' => 'En crisis', 'emoji' => '💔', 'banda' => 'crisis'];
        }
        if ($pareja === ParejaEngine::EX) {
            return ['etiqueta' => 'Ex pareja', 'emoji' => '💔', 'banda' => 'ex'];
        }

        $senal = SenalRomantica::desdeHacia($partida, $desde, $hacia, $cal);
        $valor = RelacionEngine::romanceHacia($partida, $desde, $hacia);
        $n = $valor === null ? 0 : (int) $valor;
        $banda = RelacionBandas::romance($n, $cal);

        if (!empty($senal['flechazo']) || ($senal['motivo'] ?? '') === 'flechazo') {
            return ['etiqueta' => 'Me gusta', 'emoji' => '💘', 'banda' => 'flechazo'];
        }
        if (empty($senal['ok']) && $n <= 0) {
            return null;
        }

        switch ($banda) {
            case 'tilin':
            case 'interes':
                $texto = 'Me gusta';
                break;
            case 'pillado':
                $texto = 'Pillado';
                break;
            case 'enamorado':
                $texto = 'Enamorado';
                break;
            default:
                $texto = $n > 0 ? 'Me gusta' : null;
                break;
        }
        if ($texto === null) {
            return null;
        }
        return ['etiqueta' => $texto, 'emoji' => '💘', 'banda' => $banda];
    }

    /**
     * Barra 0–100 para UI: refleja valor social real (−100…100) sin mostrar el número.
     */
    public static function barraSocialPct(int $valor, bool $conocidos): int
    {
        if (!$conocidos) {
            return 8;
        }
        if ($valor < 0) {
            $pct = 10 + (int) round((100 - min(100, abs($valor))) * 0.25);
            return max(5, min(35, $pct));
        }
        $pct = 16 + (int) round($valor * 0.84);
        return max(12, min(100, $pct));
    }
}
