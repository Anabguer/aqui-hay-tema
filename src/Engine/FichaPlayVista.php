<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Vista de ficha para play: sin IDs técnicos ni hobbies duplicados. */
final class FichaPlayVista
{
    /**
     * @param array<string, mixed> $ficha
     * @return array<string, mixed>
     */
    public static function de(array $ficha, CatalogStore $store): array
    {
        $campos = is_array($ficha['discovery']['campos'] ?? null) ? $ficha['discovery']['campos'] : [];
        $hobbies = [];
        $rasgos = [];
        foreach (['vida.hobby_principal', 'vida.hobbies_secundarios'] as $k) {
            $row = $campos[$k] ?? null;
            if (!is_array($row) || ($row['visible_jugador'] ?? true) === false) {
                continue;
            }
            $val = $row['valor'] ?? null;
            $ids = is_array($val) ? $val : ($val !== null && $val !== '' ? [$val] : []);
            foreach ($ids as $id) {
                if (!is_string($id) || $id === '') {
                    continue;
                }
                $hobbies[] = EtiquetaFicha::hobby($id, $store);
            }
        }
        $hobbies = array_values(array_unique($hobbies));

        $rowR = $campos['vida.rasgos_publicos'] ?? null;
        if (is_array($rowR) && ($rowR['visible_jugador'] ?? true) !== false) {
            $val = $rowR['valor'] ?? [];
            if (is_array($val)) {
                foreach ($val as $id) {
                    if (is_string($id) && $id !== '') {
                        $rasgos[] = EtiquetaFicha::rasgo($id, $store);
                    }
                }
            }
        }

        $ocId = $ficha['trabajo']['ocupacion'] ?? null;
        $ocupacion = is_string($ocId) && $ocId !== '' ? EtiquetaFicha::ocupacion($ocId, $store) : null;

        $pistas = [];
        $nombre = (string) ($ficha['identidad']['nombre'] ?? '');
        foreach ($ficha['descubrimientos'] ?? [] as $d) {
            if (!is_array($d)) {
                continue;
            }
            $campo = (string) ($d['campo'] ?? '');
            if ($campo === '' || (strpos($campo, 'gusto_') !== 0 && strpos($campo, 'rechazo_') !== 0)) {
                continue;
            }
            $txt = CopyDescubrimiento::texto($nombre, $campo, $d['valor'] ?? null, $store);
            if (is_string($txt) && $txt !== '') {
                $pistas[] = $txt;
            }
        }

        return [
            'nombre' => $ficha['identidad']['nombre'] ?? '',
            'edad' => $ficha['identidad']['edad'] ?? null,
            'ocupacion' => $ocupacion,
            'gusta' => $hobbies,
            'manera_de_ser' => $rasgos,
            'pistas' => $pistas,
            'estado_animo' => $ficha['estado_emocional']['id'] ?? 'neutro',
        ];
    }
}
