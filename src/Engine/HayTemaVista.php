<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Vista pública de un «Aquí hay tema»: categoría, hecho y pista desde tema_id canónico.
 * El frontend no deduce categorías del texto.
 */
final class HayTemaVista
{
    /**
     * @return array<string, mixed>|null
     */
    public static function resolver(array $partida, ?string $temaId, ?Catalog $catalog = null): ?array
    {
        if ($temaId === null || $temaId === '') {
            return null;
        }
        $root = $catalog !== null ? $catalog->getRoot() : dirname(__DIR__, 2);
        if ($catalog === null) {
            $catalog = new Catalog($root);
        }

        if (str_starts_with($temaId, 'coin_patron:')) {
            return self::desdePatron($partida, substr($temaId, strlen('coin_patron:')), $catalog);
        }

        foreach ($partida['buzon'] ?? [] as $msg) {
            if (!is_array($msg) || (string) ($msg['id'] ?? '') !== $temaId) {
                continue;
            }
            return self::desdeMensaje($partida, $msg, $catalog);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function desdePatron(array $partida, string $clave, Catalog $catalog): ?array
    {
        $p = strpos($clave, '@');
        if ($p === false) {
            return null;
        }
        $parRaw = substr($clave, 0, $p);
        $lugarId = substr($clave, $p + 1);
        $ids = array_values(array_filter(explode('|', $parRaw), static fn($id) => $id !== ''));
        sort($ids);
        if (count($ids) < 2 || $lugarId === '') {
            return null;
        }
        $vista = CotilleoNarrativo::vistaPatron($partida, $ids, $lugarId, $catalog);
        $vista['tema_id'] = 'coin_patron:' . $clave;
        $vista['tipo'] = 'cotilleo_patron';
        return $vista;
    }

    /**
     * @param array<string, mixed> $msg
     * @return array<string, mixed>
     */
    private static function desdeMensaje(array $partida, array $msg, Catalog $catalog): array
    {
        $tipo = (string) ($msg['tipo'] ?? 'cotilleo');
        $actores = self::actoresDe($msg);
        $lugarId = (string) ($msg['lugar_id'] ?? $msg['lugar'] ?? '');
        if ($lugarId === '' && ($msg['patron_clave'] ?? '') !== '') {
            $clave = (string) $msg['patron_clave'];
            if (str_contains($clave, '@')) {
                $lugarId = explode('@', $clave, 2)[1];
            }
        }
        if ($tipo === 'cotilleo_patron' && count($actores) >= 2 && $lugarId !== '') {
            $vista = CotilleoNarrativo::vistaPatron($partida, $actores, $lugarId, $catalog);
            $vista['tema_id'] = (string) ($msg['id'] ?? '');
            $vista['tipo'] = $tipo;
            return $vista;
        }

        $cat = CotilleoCategoria::de($msg);
        $texto = trim((string) ($msg['texto'] ?? ''));
        if ($tipo === 'senal_romantica') {
            $texto = CopySenalRomantica::textoDeMensaje($partida, $msg);
        }
        $nombres = [];
        foreach ($actores as $id) {
            $nombres[] = IdentidadPublica::nombre($partida, $id);
        }
        $lugarNombre = $lugarId !== '' ? EtiquetaFicha::lugar($lugarId, $catalog->store()) : '';

        return [
            'tema_id' => (string) ($msg['id'] ?? ''),
            'tipo' => $tipo,
            'categoria' => $cat['id'],
            'categoria_etiqueta' => $cat['etiqueta'],
            'categoria_icono' => $cat['icono'],
            'destacado' => $cat['destacado'],
            'texto' => $texto,
            'pista' => self::pistaDeMensaje($partida, $msg, $actores, $catalog),
            'actores' => $actores,
            'actores_nombres' => $nombres,
            'lugar_id' => $lugarId !== '' ? $lugarId : null,
            'lugar_nombre' => $lugarNombre !== '' ? $lugarNombre : null,
            'nivel' => self::nivelDeTipo($tipo),
        ];
    }

    /**
     * @param array<string, mixed> $msg
     * @param list<string> $actores
     */
    private static function pistaDeMensaje(array $partida, array $msg, array $actores, Catalog $catalog): string
    {
        $tipo = (string) ($msg['tipo'] ?? 'cotilleo');
        if ($tipo === 'senal_romantica') {
            return 'Aquí hay señales claras. Puedes proponer que queden.';
        }
        if ($tipo === 'discusion') {
            return 'La cosa está tensa. Quizá no convenga encerrarlos juntos.';
        }
        if ($tipo === 'cotilleo_patron' && count($actores) >= 2) {
            $vista = CotilleoNarrativo::vistaPatron($partida, $actores, (string) ($msg['lugar_id'] ?? ''), $catalog);
            return (string) ($vista['pista'] ?? '');
        }
        if ($tipo === 'cotilleo') {
            $meta = is_array($msg['cotilleo_meta'] ?? null) ? $msg['cotilleo_meta'] : [];
            $cat = (string) ($meta['categoria'] ?? '');
            if ($cat === CotilleoCategoria::ROMANCE) {
                return 'Hay buen rollo en el aire. Podrías empujar un plan entre ellos.';
            }
            if ($cat === CotilleoCategoria::DESCUBRIMIENTO) {
                return 'Has pillado una pista. Anótala y úsala en el momento.';
            }
            return 'Algo ha pasado hoy. Si te apetece mover ficha, organízalo.';
        }
        return 'Hay algo que contar aquí. Mira con calma antes de mover ficha.';
    }

    private static function nivelDeTipo(string $tipo): string
    {
        switch ($tipo) {
            case 'senal_romantica':
                return 'romance';
            case 'discusion':
                return 'drama';
            case 'cotilleo_patron':
                return 'patron';
            default:
                return 'hecho';
        }
    }

    /**
     * @param array<string, mixed> $msg
     * @return list<string>
     */
    private static function actoresDe(array $msg): array
    {
        $ids = [];
        foreach (is_array($msg['actores'] ?? null) ? $msg['actores'] : [] as $id) {
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }
        $de = $msg['de_persona'] ?? null;
        if (is_string($de) && $de !== '') {
            $ids[] = $de;
        }
        $origen = is_array($msg['origen'] ?? null) ? $msg['origen'] : [];
        $rev = is_array($origen['informacion_revelada'] ?? null) ? $origen['informacion_revelada'] : [];
        foreach (['desde', 'hacia'] as $k) {
            $v = $rev[$k] ?? null;
            if (is_string($v) && $v !== '') {
                $ids[] = $v;
            }
        }
        return array_values(array_unique($ids));
    }
}
