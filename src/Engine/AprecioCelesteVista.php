<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Regalos F2 - Aprecio del vecino hacia Celestine (runtime.aprecio_celeste,
 * int -100..100 de Fase 1) expresado como BANDA comprensible. Nunca se expone
 * el numero bruto ni una barra: es percepcion, no metrica de romance.
 * No toca relaciones residente-residente (grafo social exclusivo entre vecinos).
 *
 * Bandas en calibracion regalos.bandas_aprecio (lista ordenada por min DESC,
 * gana la primera cuyo min <= valor). Fallback canonico si no hay calibracion.
 */
final class AprecioCelesteVista
{
    /**
     * @param array<string, mixed> $cal
     * @return array{banda: string, texto: string}
     */
    public static function vista(int $valor, array $cal = []): array
    {
        $bandas = CalibracionConfig::get($cal, 'regalos.bandas_aprecio', null);
        if (is_array($bandas) && $bandas !== []) {
            $ordenadas = $bandas;
            usort($ordenadas, static function ($a, $b) {
                return ((int) ($b['min'] ?? 0)) <=> ((int) ($a['min'] ?? 0));
            });
            foreach ($ordenadas as $b) {
                if (!is_array($b)) {
                    continue;
                }
                if ($valor >= (int) ($b['min'] ?? -100)) {
                    return [
                        'banda' => (string) ($b['banda'] ?? 'neutral'),
                        'texto' => (string) ($b['texto'] ?? ''),
                    ];
                }
            }
        }
        // Fallback canonico (sin calibracion o lista corrupta)
        if ($valor >= 60) {
            return ['banda' => 'confianza', 'texto' => 'Confía en ti.'];
        }
        if ($valor >= 20) {
            return ['banda' => 'cariño', 'texto' => 'Le caes bien.'];
        }
        if ($valor > -20) {
            return ['banda' => 'neutral', 'texto' => 'Trato correcto, sin más.'];
        }
        if ($valor > -60) {
            return ['banda' => 'distancia', 'texto' => 'Está distante contigo.'];
        }
        return ['banda' => 'molestia', 'texto' => 'Algo le molestas.'];
    }
}
