<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Presentación del Diario personal: convierte entradas técnicas en historias legibles.
 * Solo lectura; no altera partida ni genera narrativa nueva.
 */
final class DiarioVista
{
    /** @var array<string, string> */
    private const TITULOS_SUBTIPO = [
        RelacionBitacora::SE_CONOCIERON => 'Primer contacto',
        RelacionBitacora::PRIMERA_CITA => 'Primera cita',
        RelacionBitacora::RECHAZO_IMPORTANTE => 'Rechazó un plan',
        RelacionBitacora::REGALO => 'Un detalle especial',
        RelacionBitacora::FLECHAZO => 'Un flechazo',
        RelacionBitacora::INICIO_PAREJA => 'Nueva pareja',
        RelacionBitacora::VUELTA => 'Segunda oportunidad',
        RelacionBitacora::RECONCILIACION => 'Reconciliación',
        RelacionBitacora::RUPTURA => 'Ruptura',
        RelacionBitacora::CRISIS => 'Crisis de pareja',
        RelacionBitacora::DISCUSION_FUERTE => 'Una discusión fuerte',
        RelacionBitacora::DECLARACION => 'Una declaración rechazada',
        RelacionBitacora::HITO_ROMANTICO => 'Un momento romántico',
        RelacionBitacora::APOYO_IMPORTANTE => 'Apoyo entre vecinos',
        'encuentro' => 'Un encuentro difícil',
        'descubrimiento' => 'Algo nuevo',
    ];

    /** @var array<string, string> */
    private const TITULOS_TIPO = [
        'cotilleo' => 'Se vieron',
        'cotilleo_hito' => 'Algo entre vecinos',
        'cotilleo_patron' => 'Coincidencia en el pueblo',
        'cotilleo_autonomo' => 'En el pueblo',
        'cotilleo_casual_descubrimiento' => 'Descubrimiento',
        'estado_emocional' => 'Cambió de ánimo',
        'llegada_pueblo' => 'Llegó al pueblo',
        'llegada_bienvenida' => 'Bienvenida al pueblo',
        'marcha_publica' => 'Se marchó',
        'discusion' => 'Discusión',
        'senal_romantica' => 'Señal romántica',
        'acontecimiento_perder_trabajo' => 'Perdió el trabajo',
        'acontecimiento_encontrar_trabajo' => 'Encontró trabajo',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarParaResidente(array $partida, string $residenteId): array
    {
        $crudas = DiarioEngine::listarPorResidente($partida, $residenteId);
        if ($crudas === []) {
            return [];
        }

        $indices = self::indicesHito($crudas);
        $out = [];
        $vistos = [];

        foreach ($crudas as $entrada) {
            if (!is_array($entrada)) {
                continue;
            }
            if (!empty($entrada['_placeholder_contenido']) && trim((string) ($entrada['texto'] ?? '')) === '') {
                continue;
            }
            if (self::esRedundante($entrada, $indices)) {
                continue;
            }

            $vista = self::presentar($partida, $residenteId, $entrada);
            if ($vista === null) {
                continue;
            }
            $clave = self::claveDedup($vista);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $out[] = $vista;
        }

        usort($out, static function (array $a, array $b): int {
            $cmp = ((int) ($b['dia'] ?? 0)) <=> ((int) ($a['dia'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
            return self::marcaTemporal($b) <=> self::marcaTemporal($a);
        });

        return $out;
    }

    /**
     * @param array<string, mixed> $entrada
     * @return array<string, mixed>|null
     */
    private static function presentar(array $partida, string $residenteId, array $entrada): ?array
    {
        $titulo = self::tituloDe($entrada);
        $explicacion = self::explicacionDe($entrada);
        if ($titulo === '' && $explicacion === '') {
            return null;
        }
        if ($titulo === '' && $explicacion !== '') {
            $titulo = self::tituloDesdeTexto($explicacion);
        }

        $tipo = (string) ($entrada['tipo'] ?? '');
        $subtipo = (string) ($entrada['subtipo'] ?? '');
        $origen = is_array($entrada['origen'] ?? null) ? $entrada['origen'] : [];
        $tipoEvento = (string) ($origen['tipo_evento'] ?? '');
        $cat = CotilleoCategoria::de($entrada);
        $filtro = self::filtroGrupo($entrada, $cat);
        $tono = self::tonoDe($entrada);

        return [
            'id' => (string) ($entrada['id'] ?? ''),
            'dia' => (int) ($entrada['dia'] ?? 0),
            'fecha_corta' => (string) ($entrada['fecha_corta'] ?? ''),
            'ts_juego' => is_array($entrada['ts_juego'] ?? null) ? $entrada['ts_juego'] : null,
            'titulo' => $titulo,
            'explicacion' => $explicacion,
            'categoria_etiqueta' => self::etiquetaCategoria($entrada, $cat, $filtro),
            'filtro_grupo' => $filtro,
            'tono' => $tono,
            'personas' => self::personasDe($partida, $residenteId, $entrada),
            'origen' => [
                'evento_id' => (string) ($origen['evento_id'] ?? ''),
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $entradas
     * @return array{relacional: array<string, true>, encuentro: array<string, true>}
     */
    private static function indicesHito(array $entradas): array
    {
        $relacional = [];
        $encuentro = [];
        foreach ($entradas as $e) {
            if (!is_array($e) || (string) ($e['tipo'] ?? '') !== 'diario_hito') {
                continue;
            }
            $origen = is_array($e['origen'] ?? null) ? $e['origen'] : [];
            $eventoId = (string) ($origen['evento_id'] ?? '');
            if (str_starts_with($eventoId, 'diario_hito:encuentro:')) {
                $encuentro[substr($eventoId, strlen('diario_hito:encuentro:'))] = true;
            }
            $sub = (string) ($e['subtipo'] ?? ($origen['hito_tipo'] ?? ''));
            if ($sub === '') {
                continue;
            }
            $actores = self::actoresOrdenados($e);
            if ($actores === []) {
                continue;
            }
            $par = implode('|', $actores);
            $relacional['diario_hito:' . $sub . ':' . $par] = true;
            $relacional[$sub . ':' . $par] = true;
        }
        return ['relacional' => $relacional, 'encuentro' => $encuentro];
    }

    /**
     * @param array{relacional: array<string, true>, encuentro: array<string, true>} $indices
     * @param array<string, mixed> $entrada
     */
    private static function esRedundante(array $entrada, array $indices): bool
    {
        if ((string) ($entrada['tipo'] ?? '') === 'diario_hito') {
            return false;
        }
        $origen = is_array($entrada['origen'] ?? null) ? $entrada['origen'] : [];
        $eventoId = (string) ($origen['evento_id'] ?? '');
        if ($eventoId === '') {
            return false;
        }
        if (isset($indices['relacional']['diario_hito:' . $eventoId]) || isset($indices['relacional'][$eventoId])) {
            return true;
        }
        return isset($indices['encuentro'][$eventoId]);
    }

    /**
     * @param array<string, mixed> $entrada
     */
    private static function tituloDe(array $entrada): string
    {
        $titulo = trim((string) ($entrada['titulo'] ?? ''));
        if ($titulo !== '') {
            return Utf8Text::paraJson($titulo);
        }

        $subtipo = (string) ($entrada['subtipo'] ?? '');
        if ($subtipo !== '' && isset(self::TITULOS_SUBTIPO[$subtipo])) {
            return self::TITULOS_SUBTIPO[$subtipo];
        }

        $origen = is_array($entrada['origen'] ?? null) ? $entrada['origen'] : [];
        $hitoTipo = (string) ($origen['hito_tipo'] ?? ($entrada['hito_tipo'] ?? ''));
        if ($hitoTipo !== '' && isset(self::TITULOS_SUBTIPO[$hitoTipo])) {
            return self::TITULOS_SUBTIPO[$hitoTipo];
        }

        $tipo = (string) ($entrada['tipo'] ?? '');
        if (isset(self::TITULOS_TIPO[$tipo])) {
            return self::TITULOS_TIPO[$tipo];
        }

        $tipoEvento = (string) ($origen['tipo_evento'] ?? '');
        if ($tipoEvento === 'encuentro_terminado') {
            return 'Se vieron';
        }

        return '';
    }

    private static function tituloDesdeTexto(string $texto): string
    {
        $limpio = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
        if ($limpio === '') {
            return 'Algo pasó';
        }
        if (preg_match('/^(.{1,72}?)([.!?]|$)/u', $limpio, $m) === 1) {
            $corte = trim((string) ($m[1] ?? ''));
            if ($corte !== '') {
                return $corte;
            }
        }
        if (mb_strlen($limpio) > 72) {
            return rtrim(mb_substr($limpio, 0, 69)) . '…';
        }
        return $limpio;
    }

    /**
     * @param array<string, mixed> $entrada
     */
    private static function explicacionDe(array $entrada): string
    {
        $texto = trim((string) ($entrada['texto'] ?? ''));
        $partes = $texto !== '' ? [$texto] : [];
        foreach ((array) ($entrada['consecuencias'] ?? []) as $c) {
            if (!is_string($c)) {
                continue;
            }
            $c = trim($c);
            if ($c === '' || ($texto !== '' && str_contains($texto, $c))) {
                continue;
            }
            $partes[] = $c;
        }
        return Utf8Text::paraJson(implode(' ', $partes));
    }

    /**
     * @param array<string, mixed> $entrada
     * @param array{id: string, etiqueta: string, icono: string, destacado: bool} $cat
     */
    private static function etiquetaCategoria(array $entrada, array $cat, string $filtro): string
    {
        $tipo = (string) ($entrada['tipo'] ?? '');
        if ($tipo === 'diario_hito') {
            $sub = (string) ($entrada['subtipo'] ?? '');
            if ($sub === 'encuentro') {
                return 'Encuentro';
            }
            if ($sub === 'descubrimiento') {
                return 'Descubrimiento';
            }
            return 'Relación';
        }
        if ($tipo === 'estado_emocional') {
            return 'Ánimo';
        }
        if (str_starts_with($tipo, 'acontecimiento_')) {
            return 'Cambio';
        }
        if ($filtro === 'planes') {
            return 'Plan';
        }
        if ($filtro === 'cambios') {
            return 'Cambio';
        }
        return (string) ($cat['etiqueta'] ?? 'Relación');
    }

    /**
     * @param array<string, mixed> $entrada
     * @param array{id: string, etiqueta: string, icono: string, destacado: bool} $cat
     */
    private static function filtroGrupo(array $entrada, array $cat): string
    {
        $tipo = (string) ($entrada['tipo'] ?? '');
        $subtipo = (string) ($entrada['subtipo'] ?? '');
        $origen = is_array($entrada['origen'] ?? null) ? $entrada['origen'] : [];
        $tipoEvento = (string) ($origen['tipo_evento'] ?? '');
        $catId = (string) ($cat['id'] ?? '');

        if ($tipo === 'diario_hito'
            || $tipo === 'cotilleo_hito'
            || $tipo === 'discusion'
            || $tipo === 'senal_romantica'
            || $tipoEvento === 'relacion_hito'
            || in_array($catId, [CotilleoCategoria::ROMANCE, CotilleoCategoria::RELACION, CotilleoCategoria::DRAMA], true)) {
            return 'relaciones';
        }

        if ($tipo === 'estado_emocional'
            || str_contains($tipo, 'llegada')
            || $tipo === 'marcha_publica'
            || str_starts_with($tipo, 'acontecimiento_')
            || $subtipo === 'descubrimiento'
            || $catId === CotilleoCategoria::PUEBLO
            || $catId === CotilleoCategoria::DESCUBRIMIENTO) {
            return 'cambios';
        }

        if ($tipoEvento === 'encuentro_terminado'
            || str_contains($tipo, 'encuentro')
            || str_contains($tipo, 'plan')
            || $subtipo === 'encuentro'
            || $catId === CotilleoCategoria::ENCUENTRO) {
            return 'planes';
        }

        return 'relaciones';
    }

    /**
     * @param array<string, mixed> $vista
     */
    private static function claveDedup(array $vista): string
    {
        $personas = [];
        foreach ($vista['personas'] ?? [] as $p) {
            if (is_array($p) && is_string($p['id'] ?? null) && ($p['id'] ?? '') !== '') {
                $personas[] = (string) $p['id'];
            }
        }
        sort($personas);
        $nucleo = strtolower(trim((string) ($vista['titulo'] ?? '')))
            . '|'
            . strtolower(trim((string) ($vista['explicacion'] ?? '')))
            . '|'
            . implode(',', $personas);
        return (string) ($vista['dia'] ?? 0) . '|' . md5($nucleo);
    }

    /**
     * @param array<string, mixed> $entrada
     */
    private static function tonoDe(array $entrada): ?string
    {
        $sub = (string) ($entrada['subtipo'] ?? '');
        $origen = is_array($entrada['origen'] ?? null) ? $entrada['origen'] : [];
        $tipo = (string) ($entrada['tipo'] ?? '');

        if (in_array($sub, [
            RelacionBitacora::RUPTURA,
            RelacionBitacora::RECHAZO_IMPORTANTE,
            RelacionBitacora::CRISIS,
            RelacionBitacora::DISCUSION_FUERTE,
            'encuentro',
        ], true)) {
            return 'negativo';
        }

        if (in_array($sub, [
            RelacionBitacora::REGALO,
            RelacionBitacora::INICIO_PAREJA,
            RelacionBitacora::RECONCILIACION,
            RelacionBitacora::VUELTA,
            RelacionBitacora::APOYO_IMPORTANTE,
            RelacionBitacora::HITO_ROMANTICO,
            RelacionBitacora::FLECHAZO,
            RelacionBitacora::PRIMERA_CITA,
        ], true)) {
            return 'positivo';
        }

        if ($tipo === 'estado_emocional') {
            $info = is_array($origen['informacion_revelada'] ?? null) ? $origen['informacion_revelada'] : [];
            $estado = EstadoEmocional::canonId((string) ($info['estado'] ?? ''));
            if ($estado === EstadoEmocional::ALEGRE) {
                return 'positivo';
            }
            if (in_array($estado, [EstadoEmocional::TRISTE, EstadoEmocional::ENFADADO], true)) {
                return 'negativo';
            }
            return 'neutro';
        }

        if ($tipo === 'discusion') {
            return 'negativo';
        }
        if ($tipo === 'senal_romantica') {
            return 'positivo';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entrada
     * @return list<array{id: string, nombre: string, retrato_url: ?string}>
     */
    private static function personasDe(array $partida, string $residenteId, array $entrada): array
    {
        $out = [];
        foreach (self::actoresOrdenados($entrada) as $id) {
            if ($id === $residenteId) {
                continue;
            }
            if (!isset($partida['residentes'][$id])) {
                continue;
            }
            $nombre = IdentidadPublica::nombre($partida, $id);
            if ($nombre === $id) {
                continue;
            }
            $res = $partida['residentes'][$id];
            $retrato = is_string($res['retrato_url'] ?? null) && ($res['retrato_url'] ?? '') !== ''
                ? (string) $res['retrato_url']
                : null;
            $out[] = [
                'id' => $id,
                'nombre' => $nombre,
                'retrato_url' => $retrato,
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $entrada
     * @return list<string>
     */
    private static function actoresOrdenados(array $entrada): array
    {
        $raw = is_array($entrada['actores'] ?? null) ? $entrada['actores'] : [];
        $out = [];
        foreach ($raw as $id) {
            if (is_string($id) && $id !== '') {
                $out[] = $id;
            }
        }
        sort($out);
        return array_values(array_unique($out));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function marcaTemporal(array $row): int
    {
        $ts = is_array($row['ts_juego'] ?? null) ? $row['ts_juego'] : [];
        $dia = (int) ($row['dia'] ?? ($ts['dia'] ?? 0));
        $hora = (int) ($ts['hora'] ?? 0);
        $min = (int) ($ts['minuto'] ?? 0);
        return $dia * 10000 + $hora * 100 + $min;
    }
}
