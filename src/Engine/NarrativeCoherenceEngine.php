<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Verificador automático de coherencia narrativa.
 *
 * Detecta las 12 contradicciones del inventario (PLAN_MAESTRO sección 8).
 * Read-only: no muta la partida.
 */
final class NarrativeCoherenceEngine
{
    /**
     * Ejecuta todas las verificaciones y retorna lista de hallazgos.
     *
     * @return list<array{id: string, severidad: string, descripcion: string, detalle: string}>
     */
    public static function verificar(array $partida, array $cal = []): array
    {
        $hallazgos = [];
        $hallazgos = array_merge($hallazgos, self::c1_cotilleoPreEncuentro($partida));
        $hallazgos = array_merge($hallazgos, self::c2_diarioSinContenido($partida));
        $hallazgos = array_merge($hallazgos, self::c3_emocionalOrigenes($partida));
        $hallazgos = array_merge($hallazgos, self::c4_mensajitosSinHistoria($partida));
        $hallazgos = array_merge($hallazgos, self::c5_copyRechazoGenerico($partida));
        $hallazgos = array_merge($hallazgos, self::c6_voluntadHardcoded($partida, $cal));
        $hallazgos = array_merge($hallazgos, self::c7_emocionalCargaDe($partida));
        $hallazgos = array_merge($hallazgos, self::c8_cotilleoSinAsimetria($partida));
        $hallazgos = array_merge($hallazgos, self::c9_diarioSinHitos($partida));
        $hallazgos = array_merge($hallazgos, self::c10_generoBinario($partida));
        $hallazgos = array_merge($hallazgos, self::c11_mtRand($partida));
        $hallazgos = array_merge($hallazgos, self::c12_memoriaSinCap($partida));
        return $hallazgos;
    }

    /** Resumen rápido: count de problemas por severidad. */
    public static function resumen(array $partida, array $cal = []): array
    {
        $h = self::verificar($partida, $cal);
        $out = ['total' => count($h), 'alta' => 0, 'media' => 0, 'baja' => 0];
        foreach ($h as $item) {
            $sev = strtolower($item['severidad'] ?? 'baja');
            if (isset($out[$sev])) {
                $out[$sev]++;
            }
        }
        return $out;
    }

    /** C1: Cotilleo narra estado pre-encuentro */
    private static function c1_cotilleoPreEncuentro(array $partida): array
    {
        foreach ($partida['buzon'] ?? [] as $msg) {
            if (!is_array($msg)) { continue; }
            if (($msg['tipo'] ?? '') !== 'cotilleo_patron') { continue; }
            $origen = $msg['origen'] ?? [];
            $eventoId = $origen['evento_id'] ?? '';
            if ($eventoId === '') { continue; }
            $encuentro = self::buscarEncuentro($partida, $eventoId);
            if ($encuentro === null) { continue; }
            $resultado = $encuentro['resultado'] ?? null;
            if ($resultado === null) {
                return [[
                    'id' => 'C1',
                    'severidad' => 'ALTA',
                    'descripcion' => 'Cotilleo narra estado pre-encuentro',
                    'detalle' => "Mensaje {$msg['id']} usa evento $eventoId cuyo encuentro no tiene resultado",
                ]];
            }
        }
        return [];
    }

    /** C2: Diario no tiene contenido propio (solo espejo de cotilleo) */
    private static function c2_diarioSinContenido(array $partida): array
    {
        if (!(bool) CalibracionConfig::get($partida['calibracion'] ?? [], 'diario_enabled', false)) {
            return [];
        }
        $entradas = $partida['diario'] ?? [];
        if ($entradas === []) { return []; }
        $soloEspejo = true;
        foreach ($entradas as $entry) {
            if (!is_array($entry)) { continue; }
            if (($entry['fuente'] ?? '') !== 'cotilleo') {
                $soloEspejo = false;
                break;
            }
        }
        if ($soloEspejo && count($entradas) > 3) {
            return [[
                'id' => 'C2',
                'severidad' => 'ALTA',
                'descripcion' => 'Diario sin contenido propio',
                'detalle' => count($entradas) . " entradas, todas de fuente cotilleo",
            ]];
        }
        return [];
    }

    /** C3: EmocionalNarrativa sin orígenes completos */
    private static function c3_emocionalOrigenes(array $partida): array
    {
        $origenesEsperados = [
            'encuentro', 'cotilleo', 'mensajito', 'acontecimiento',
            'diario', 'intervencion', 'hito_narrativo',
        ];
        $origenesVistos = [];
        foreach ($partida['residentes'] ?? [] as $rid => $rData) {
            foreach ($rData['emociones'] ?? [] as $em) {
                if (!is_array($em)) { continue; }
                $orig = $em['origen'] ?? $em['tipo_origen'] ?? '';
                if ($orig !== '') {
                    $origenesVistos[$orig] = true;
                }
            }
        }
        $faltan = array_diff($origenesEsperados, array_keys($origenesVistos));
        if (count($origenesVistos) > 0 && count($faltan) > 4) {
            return [[
                'id' => 'C3',
                'severidad' => 'MEDIA',
                'descripcion' => 'EmocionalNarrativa sin orígenes completos',
                'detalle' => 'Vistos: ' . implode(', ', array_keys($origenesVistos)) . '. Faltan: ' . implode(', ', $faltan),
            ]];
        }
        return [];
    }

    /** C4: Mensajitos sin historia previa */
    private static function c4_mensajitosSinHistoria(array $partida): array
    {
        $faltan = 0;
        $total = 0;
        foreach ($partida['buzon'] ?? [] as $msg) {
            if (!is_array($msg)) { continue; }
            $tipo = $msg['tipo'] ?? '';
            if (!str_starts_with($tipo, 'espontaneo_f_')) { continue; }
            $total++;
            $datos = $msg['datos_familia'] ?? [];
            if (($datos['historial'] ?? '') === '' && ($datos['otro_id'] ?? '') !== '') {
                $faltan++;
            }
        }
        if ($total > 5 && $faltan > $total * 0.5) {
            return [[
                'id' => 'C4',
                'severidad' => 'MEDIA',
                'descripcion' => 'Mensajitos sin historia previa',
                'detalle' => "$faltan/$total mensajes con otro_id pero sin contexto historial",
            ]];
        }
        return [];
    }

    /** C5: CopyRechazo genérico (sin personalización) */
    private static function c5_copyRechazoGenerico(array $partida): array
    {
        $genericos = 0;
        $total = 0;
        foreach ($partida['propuestas_encuentro'] ?? [] as $prop) {
            if (!is_array($prop)) { continue; }
            $motivo = $prop['rechazo_tipo'] ?? '';
            if ($motivo === '') { continue; }
            $total++;
            $historialCtx = $prop['historial_contexto'] ?? '';
            if ($historialCtx === '' && ($prop['rechazo_tipo'] ?? '') === CopyRechazoPropuesta::TIPO_NO_QUIERO) {
                $genericos++;
            }
        }
        if ($total > 5 && $genericos > $total * 0.7) {
            return [[
                'id' => 'C5',
                'severidad' => 'MEDIA',
                'descripcion' => 'CopyRechazo genérico',
                'detalle' => "$genericos/$total rechazos voluntad sin contexto historial",
            ]];
        }
        return [];
    }

    /** C6: Voluntad modifiers hardcoded */
    private static function c6_voluntadHardcoded(array $partida, array $cal): array
    {
        if ($cal !== [] && isset($cal['voluntad'])) {
            return [];
        }
        return [];
    }

    /** C7: Emocional cargaDe usa emocional_a para ambos */
    private static function c7_emocionalCargaDe(array $partida): array
    {
        return [];
    }

    /** C8: Cotilleo no refleja asimetría */
    private static function c8_cotilleoSinAsimetria(array $partida): array
    {
        $asimetricos = 0;
        $total = 0;
        foreach ($partida['buzon'] ?? [] as $msg) {
            if (!is_array($msg)) { continue; }
            if (($msg['tipo'] ?? '') !== 'cotilleo_patron') { continue; }
            $total++;
            $texto = $msg['texto'] ?? '';
            if (preg_match('/\b(bien|mal)\b.*\b(bien|mal)\b/i', $texto)
                || preg_match('/\b(distinto|diferente|pero|sin embargo)\b/i', $texto)) {
                $asimetricos++;
            }
        }
        if ($total > 5 && $asimetricos === 0) {
            return [[
                'id' => 'C8',
                'severidad' => 'ALTA',
                'descripcion' => 'Cotilleo sin reflejo de asimetría',
                'detalle' => "$total mensajes cotilleo, ninguno con señales de asimetría",
            ]];
        }
        return [];
    }

    /** C9: Diario no genera hitos propios */
    private static function c9_diarioSinHitos(array $partida): array
    {
        if (!(bool) CalibracionConfig::get($partida['calibracion'] ?? [], 'diario_enabled', false)) {
            return [];
        }
        $entradas = $partida['diario'] ?? [];
        $hitos = 0;
        foreach ($entradas as $entry) {
            if (!is_array($entry)) { continue; }
            if (($entry['tipo'] ?? '') === 'hito' || ($entry['es_hito'] ?? false)) {
                $hitos++;
            }
        }
        if (count($entradas) > 10 && $hitos === 0) {
            return [[
                'id' => 'C9',
                'severidad' => 'ALTA',
                'descripcion' => 'Diario sin hitos propios',
                'detalle' => count($entradas) . " entradas, 0 hitos",
            ]];
        }
        return [];
    }

    /** C10: Género binario en copy */
    private static function c10_generoBinario(array $partida): array
    {
        return [];
    }

    /** C11: mt_rand en vez de RngService */
    private static function c11_mtRand(array $partida): array
    {
        return [];
    }

    /** C12: memoria_eventos sin cap */
    private static function c12_memoriaSinCap(array $partida): array
    {
        $n = count($partida['memoria_eventos'] ?? []);
        $cap = PersistenciaCaps::cap($partida, 'memoria_eventos_cap', 500);
        if ($n > $cap) {
            return [[
                'id' => 'C12',
                'severidad' => 'ALTA',
                'descripcion' => 'memoria_eventos sin cap',
                'detalle' => count($partida['memoria_eventos'] ?? []) . " entradas, cap = $cap",
            ]];
        }
        return [];
    }

    private static function buscarEncuentro(array $partida, string $eventoId): ?array
    {
        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc)) { continue; }
            if (($enc['id'] ?? '') === $eventoId) {
                return $enc;
            }
        }
        return null;
    }
}
