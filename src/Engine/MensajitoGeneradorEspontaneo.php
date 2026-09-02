<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Generador espontaneo de Mensajitos (familias F1, F2, F4, F6, F7, F8, F11, F14, F15).
 *
 * Los NPCs se dirigen a Celestine cuando tienen algo que decir, disparado
 * por su estado real (emociones, relaciones, eventos recientes, personalidad).
 * NO es feed general: solo publica cuando hay valor jugable para Celestine.
 *
 * Provisional: cifras y probabilidades son tecnicas, no canon.
 */
final class MensajitoGeneradorEspontaneo
{
    public static function evaluar(array &$partida, string $residenteId, array $cal, RngService $rng): ?array
    {
        if (!FeatureConfig::isEnabled($partida, 'buzon_enabled')) {
            return null;
        }
        if (MensajitosCadenciaEngine::enCooldownVecino($partida, $residenteId, $cal)) {
            return null;
        }
        if (!MensajitosCadenciaEngine::hayPresupuesto($partida, $cal)) {
            return null;
        }
        $familias = self::familiasCandidatas($partida, $residenteId, $cal, $rng);
        if ($familias === []) {
            return null;
        }
        usort($familias, static function (array $a, array $b): int {
            $pa = MensajitosCadenciaEngine::prioridad((string) ($a['familia'] ?? ''));
            $pb = MensajitosCadenciaEngine::prioridad((string) ($b['familia'] ?? ''));
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            return ((int) ($a['peso'] ?? 99)) <=> ((int) ($b['peso'] ?? 99));
        });
        $elegida = $familias[0];
        $candidato = [
            'residente_id' => $residenteId,
            'familia' => (string) ($elegida['familia'] ?? ''),
            'peso' => (int) ($elegida['peso'] ?? 3),
            'datos' => is_array($elegida['datos'] ?? null) ? $elegida['datos'] : [],
        ];
        return self::publicar($partida, $residenteId, $candidato, $cal, $rng);
    }

    /**
     * @return list<array{residente_id: string, familia: string, peso: int, datos: array<string, mixed>}>
     */
    public static function recolectarCandidatos(array $partida, array $cal): array
    {
        $rng = RngService::fromPartida($partida);
        $out = [];
        foreach (PeticionPuebloEngine::residentes($partida) as $rid) {
            if (MensajitosCadenciaEngine::enCooldownVecino($partida, $rid, $cal)) {
                continue;
            }
            foreach (self::familiasCandidatas($partida, $rid, $cal, $rng) as $fam) {
                $out[] = [
                    'residente_id' => $rid,
                    'familia' => (string) ($fam['familia'] ?? ''),
                    'peso' => (int) ($fam['peso'] ?? 3),
                    'datos' => is_array($fam['datos'] ?? null) ? $fam['datos'] : [],
                ];
            }
        }
        return $out;
    }

    /**
     * @param array{residente_id?: string, familia: string, peso?: int, datos?: array<string, mixed>} $candidato
     */
    public static function publicar(array &$partida, string $residenteId, array $candidato, array $cal, RngService $rng): ?array
    {
        $fam = [
            'familia' => (string) ($candidato['familia'] ?? ''),
            'peso' => (int) ($candidato['peso'] ?? 3),
            'datos' => is_array($candidato['datos'] ?? null) ? $candidato['datos'] : [],
        ];
        if ($fam['familia'] === '') {
            return null;
        }
        return self::generar($partida, $residenteId, $fam, $cal, $rng);
    }

    /**
     * @return list<array{familia: string, peso: int, datos: array<string, mixed>}>
     */
    private static function familiasCandidatas(array $partida, string $residenteId, array $cal, RngService $rng): array
    {
        $c = [];
        $catalog = new Catalog(dirname(__DIR__, 2));
        $f4 = MensajitoColectivoEngine::candidato($partida, $residenteId, $cal, $catalog);
        if ($f4 !== null) {
            $c[] = $f4;
        }
        $f1 = self::candidatoF1($partida, $residenteId, $cal, $rng);
        if ($f1 !== null) {
            $c[] = $f1;
        }
        $f2 = self::candidatoF2($partida, $residenteId, $rng);
        if ($f2 !== null) {
            $c[] = $f2;
        }
        $f6 = self::candidatoF6($partida, $residenteId, $rng);
        if ($f6 !== null) {
            $c[] = $f6;
        }
        $f7 = self::candidatoF7($partida, $residenteId, $rng);
        if ($f7 !== null) {
            $c[] = $f7;
        }
        $f8 = MensajitoDudaPermanenciaEngine::candidatoEspontaneo($partida, $residenteId, $cal);
        if ($f8 !== null) {
            $c[] = $f8;
        }
        $f11 = MensajitoMediacionEngine::candidato($partida, $residenteId);
        if ($f11 !== null) {
            $c[] = $f11;
        }
        $f15 = self::candidatoF15($partida, $residenteId, $cal, $rng);
        if ($f15 !== null) {
            $c[] = $f15;
        }
        return $c;
    }

    private static function candidatoF1(array $partida, string $rid, array $cal, RngService $rng): ?array
    {
        $rels = self::relacionesSignificativas($partida, $rid);
        if ($rels === []) { return null; }
        $prob = (float) CalibracionConfig::get($cal, 'mensajitos.f1_prob_base', 0.15);
        if ($rng->nextInt(1, 10000) > $prob * 10000) { return null; }
        $rel = $rels[$rng->nextInt(0, count($rels) - 1)];
        $clave = 'f_opinion|' . ($rel['otro_id'] ?? '');
        if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, 'f_opinion', $clave)) {
            return null;
        }
        return ['familia' => 'f_opinion', 'peso' => 2, 'datos' => ['otro_id' => $rel['otro_id'], 'otro_nombre' => $rel['otro_nombre'], 'tipo_relacion' => $rel['tipo'], 'clave' => $clave]];
    }

    private static function candidatoF2(array $partida, string $rid, RngService $rng): ?array
    {
        $rom = self::senalesRomanticas($partida, $rid);
        if (count($rom) < 2) { return null; }
        $pair = $rng->pickUnique($rom, 2);
        $clave = 'f_dilema|' . ($pair[0]['otro_id'] ?? '') . '|' . ($pair[1]['otro_id'] ?? '');
        if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, 'f_dilema', $clave)) {
            return null;
        }
        return ['familia' => 'f_dilema', 'peso' => 2, 'datos' => ['opcion_a_id' => $pair[0]['otro_id'], 'opcion_a_nombre' => $pair[0]['otro_nombre'], 'opcion_b_id' => $pair[1]['otro_id'], 'opcion_b_nombre' => $pair[1]['otro_nombre'], 'clave' => $clave]];
    }

    private static function candidatoF6(array $partida, string $rid, RngService $rng): ?array
    {
        $crush = self::crushLatente($partida, $rid);
        if ($crush !== null) {
            $clave = 'confidencia|crush|' . ($crush['otro_id'] ?? '');
            $rec = MensajitoPromesaEngine::datosRecuerdoSiAplica($partida, $rid, [
                'subtipo' => 'crush',
                'otro_id' => $crush['otro_id'],
                'clave' => $clave,
            ]);
            if ($rec !== null) {
                return ['familia' => 'f_promesa', 'peso' => 1, 'datos' => $rec];
            }
            if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, 'f_confidencia', $clave)) {
                return null;
            }
            return [
                'familia' => 'f_confidencia',
                'peso' => 3,
                'datos' => [
                    'subtipo' => 'crush',
                    'otro_id' => $crush['otro_id'],
                    'otro_nombre' => $crush['otro_nombre'],
                    'emocion' => 'nervioso' . GeneroConcordancia::oa($partida, $rid),
                    'clave' => $clave,
                ],
            ];
        }
        $ems = self::emocionesFuertes($partida, $rid);
        if ($ems === []) {
            return null;
        }
        $em = $ems[$rng->nextInt(0, count($ems) - 1)];
        $clave = 'confidencia|' . ($em['tipo'] ?? 'general') . '|' . $rid;
        $rec = MensajitoPromesaEngine::datosRecuerdoSiAplica($partida, $rid, [
            'emocion' => $em['tipo'],
            'subtipo' => $em['tipo'],
            'clave' => $clave,
        ]);
        if ($rec !== null) {
            return ['familia' => 'f_promesa', 'peso' => 1, 'datos' => $rec];
        }
        if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, 'f_confidencia', $clave)) {
            return null;
        }
        return [
            'familia' => 'f_confidencia',
            'peso' => 3,
            'datos' => [
                'emocion' => $em['tipo'],
                'subtipo' => $em['tipo'],
                'intensidad' => $em['intensidad'],
                'clave' => $clave,
            ],
        ];
    }

    /**
     * @return array{otro_id: string, otro_nombre: string}|null
     */
    private static function crushLatente(array $partida, string $rid): ?array
    {
        foreach (self::senalesRomanticas($partida, $rid) as $r) {
            $otro = (string) ($r['otro_id'] ?? '');
            if ($otro === '') {
                continue;
            }
            $rom = (float) (RelacionEngine::romanceHacia($partida, $rid, $otro) ?? 0);
            if ($rom >= 12 && $rom <= 28) {
                return $r;
            }
        }
        return null;
    }

    private static function candidatoF7(array $partida, string $rid, RngService $rng): ?array
    {
        $ap = self::vecinosApagados($partida, $rid);
        if ($ap === []) { return null; }
        $t = $ap[$rng->nextInt(0, count($ap) - 1)];
        $clave = 'f_alerta|' . ($t['residente_id'] ?? '');
        if (MensajitoConsejoEngine::yaExisteHiloReciente($partida, $rid, 'f_alerta_vecinal', $clave)) {
            return null;
        }
        return ['familia' => 'f_alerta_vecinal', 'peso' => 2, 'datos' => ['observado_id' => $t['residente_id'], 'observado_nombre' => $t['nombre'], 'clave' => $clave]];
    }

    private static function candidatoF15(array $partida, string $rid, array $cal, RngService $rng): ?array
    {
        $cen = self::cercaniaNarrativa($partida, $rid);
        $umbral = (float) CalibracionConfig::get($cal, 'mensajitos.f15_cercania_umbral', 0.6);
        if ($cen < $umbral) { return null; }
        $prob = (float) CalibracionConfig::get($cal, 'mensajitos.f15_prob_base', 0.08);
        if ($rng->nextInt(1, 10000) > $prob * 10000) { return null; }
        return ['familia' => 'f_curiosidad_celestine', 'peso' => 3, 'datos' => []];
    }

    private static function generar(array &$partida, string $rid, array $fam, array $cal, RngService $rng): ?array
    {
        $texto = self::textoFamilia($partida, $rid, $fam['familia'], $fam['datos']);
        if ($texto === '') { return null; }
        switch ($fam['familia']) {
            case 'f_alerta_vecinal':
                $clas = BuzonEngine::IMPORTANTE;
                break;
            case 'f_duda_permanencia':
                $clas = BuzonEngine::IMPORTANTE;
                break;
            case 'f_colectivo':
                $clas = BuzonEngine::OPORTUNIDAD;
                break;
            default:
                $clas = BuzonEngine::OPORTUNIDAD;
        }
        switch ($fam['familia']) {
            case 'f_opinion':
            case 'f_dilema':
                $acciones = ['responder_consejo'];
                break;
            case 'f_confidencia':
            case 'f_promesa':
                $acciones = ['responder_escuchar'];
                break;
            case 'f_colectivo':
                $acciones = ['aceptar_evento', 'declinar_evento'];
                break;
            case 'f_alerta_vecinal':
                $acciones = ['investigar', 'organizar_algo', 'no_meterse'];
                break;
            case 'f_duda_permanencia':
                $acciones = ['organizar_algo', 'responder_escuchar', 'no_meterse'];
                break;
            case 'f_mediacion':
                $acciones = ['mediar_reparar', 'responder_consejo', 'no_meterse'];
                break;
            case 'f_curiosidad_celestine':
                $acciones = ['responder_celestine'];
                break;
            default:
                $acciones = [];
        }
        $msgId = 'msg_' . bin2hex(random_bytes(4));
        $clave = (string) ($fam['datos']['clave'] ?? '');
        if ($clave === '') {
            $clave = $fam['familia'] . '|' . $rid . '|d' . (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        }
        $horaJuego = ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24 + (int) ($partida['reloj']['hora_actual'] ?? 0);
        $eventoId = CanalDeduplicador::eventoIdVentana($fam['familia'], $rid, $clave, $horaJuego);
        $r = CanalDeduplicador::crearSiAplica($partida, [
            'id' => $msgId,
            'clasificacion' => $clas,
            'tipo' => 'espontaneo_' . $fam['familia'],
            'canal' => BuzonEngine::CANAL_BUZON,
            'de_persona' => $rid,
            'actores' => [$rid],
            'texto' => $texto,
            'acciones' => $acciones,
            'familia_mensajito' => $fam['familia'],
            'datos_familia' => array_merge($fam['datos'], ['clave' => $clave]),
            'dedup_clave' => $clave,
            'hilo_id' => $msgId,
            'hilo_estado' => 'abierto',
            'seguimiento_pendiente' => in_array($fam['familia'], ['f_opinion', 'f_dilema', 'f_confidencia'], true),
            'origen' => ['evento_id' => $eventoId, 'tipo_evento' => 'espontaneo_' . $fam['familia'], 'es_narrativo' => true, 'informacion_revelada' => [], '_placeholder' => false],
            '_placeholder_contenido' => false,
        ]);
        if ($r === null || !($r['ok'] ?? false)) {
            return null;
        }
        if ($fam['familia'] === MensajitoDudaPermanenciaEngine::FAMILIA) {
            MensajitoDudaPermanenciaEngine::registrarPendientePublico(
                $partida,
                $rid,
                (string) ($r['mensaje']['id'] ?? $msgId)
            );
        }
        MensajitosCadenciaEngine::registrar($partida, $rid, $fam['familia'], 'espontaneo', $clave);
        DomainEventDispatcher::emit($partida, DomainEvents::BUZON_MENSAJE, [
            'mensaje' => $r['mensaje'] ?? null,
            'origen_evento' => 'espontaneo_' . $fam['familia'],
        ], null, 'MensajitoGeneradorEspontaneo');
        return $r['mensaje'] ?? null;
    }

    private static function textoFamilia(array &$partida, string $rid, string $familia, array $datos): string
    {
        switch ($familia) {
            case 'f_opinion':
                $otro = $datos['otro_id'] ?? '';
                $historial = $otro !== '' ? HistorialPar::contextoNarrativo($partida, $rid, $otro) : '';
                return MensajitoVoz::linea($partida, 'f_opinion', ['otro' => $datos['otro_nombre'] ?? '', 'texto' => $datos['tipo_relacion'] ?? 'amigo', 'historial' => $historial], 'f_opinion|' . $rid . '|' . ($datos['otro_id'] ?? ''), $rid);
            case 'f_dilema':
                $histA = ($datos['opcion_a_id'] ?? '') !== '' ? HistorialPar::contextoNarrativo($partida, $rid, $datos['opcion_a_id']) : '';
                $histB = ($datos['opcion_b_id'] ?? '') !== '' ? HistorialPar::contextoNarrativo($partida, $rid, $datos['opcion_b_id']) : '';
                return MensajitoVoz::linea($partida, 'f_dilema', ['nombre_a' => $datos['opcion_a_nombre'] ?? '', 'nombre_b' => $datos['opcion_b_nombre'] ?? '', 'texto' => 'dos personas', 'historial' => $histA !== '' ? $histA : $histB], 'f_dilema|' . $rid, $rid);
            case 'f_confidencia':
                if (($datos['subtipo'] ?? '') === 'crush') {
                    $otro = $datos['otro_id'] ?? '';
                    $historial = $otro !== '' ? HistorialPar::contextoNarrativo($partida, $rid, $otro) : '';
                    return MensajitoVoz::linea(
                        $partida,
                        'f_confidencia_crush',
                        ['otro' => $datos['otro_nombre'] ?? '', 'historial' => $historial],
                        'f_confidencia_crush|' . $rid . '|' . ($datos['otro_id'] ?? ''),
                        $rid
                    );
                }
                return MensajitoVoz::linea(
                    $partida,
                    'f_confidencia',
                    ['texto' => str_replace('/a', GeneroConcordancia::oa($partida, $rid), $datos['emocion'] ?? 'algo personal')],
                    'f_confidencia|' . $rid . '|' . ($datos['subtipo'] ?? ''),
                    $rid
                );
            case 'f_promesa':
                return MensajitoVoz::linea(
                    $partida,
                    'f_promesa',
                    ['texto' => $datos['tema'] ?? 'lo de siempre'],
                    'f_promesa|' . $rid . '|' . ($datos['clave'] ?? ''),
                    $rid
                );
            case 'f_colectivo':
                return MensajitoVoz::linea(
                    $partida,
                    'f_colectivo',
                    ['texto' => $datos['evento_nombre'] ?? 'algo en el pueblo'],
                    'f_colectivo|' . $rid . '|' . ($datos['evento_catalogo_id'] ?? ''),
                    $rid
                );
            case 'f_alerta_vecinal':
                $observado = $datos['observado_id'] ?? '';
                $historial = $observado !== '' ? HistorialPar::contextoNarrativo($partida, $rid, $observado) : '';
                $oaRef = $observado !== '' ? GeneroConcordancia::oa($partida, $observado) : 'o';
                return MensajitoVoz::linea($partida, 'f_alerta_vecinal', ['otro' => $datos['observado_nombre'] ?? '', 'texto' => 'apagado', 'historial' => $historial, 'oa_ref' => $oaRef], 'f_alerta|' . $rid . '|' . ($datos['observado_id'] ?? ''), $rid);
            case 'f_duda_permanencia':
                return MensajitoVoz::linea(
                    $partida,
                    'f_duda_permanencia',
                    ['texto' => $datos['motivo'] ?? 'un poco invisible'],
                    'f_duda|' . $rid . '|' . ($datos['clave'] ?? ''),
                    $rid
                );
            case 'f_mediacion':
                return MensajitoMediacionEngine::texto($partida, $rid, $datos);
            case 'f_curiosidad_celestine':
                return MensajitoVoz::linea($partida, 'f_curiosidad_celestine', [], 'f_curiosidad|' . $rid, $rid);
        }
        return '';
    }

    private static function relacionesSignificativas(array $partida, string $rid): array
    {
        $out = [];
        $seen = [];
        foreach ($partida['relaciones_sociales'] ?? [] as $rel) {
            if (!is_array($rel)) { continue; }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            if ($a !== $rid && $b !== $rid) { continue; }
            $otro = $a === $rid ? $b : $a;
            if (isset($seen[$otro])) { continue; }
            $dir = RelacionEngine::socialHacia($partida, $rid, $otro);
            $social = (float) (($dir['valor']) ?? 0);
            $romDir = RelacionEngine::romanceHacia($partida, $rid, $otro);
            $romance = (float) ($romDir ?? 0);
            $tipo = $romance > 10 ? 'romance' : ($social > 30 ? 'muy_cercano' : ($social > 20 ? 'cercano' : 'amigo'));
            $nombre = IdentidadPublica::nombre($partida, $otro);
            if ($social > 20 || $romance > 10) {
                $out[] = ['otro_id' => $otro, 'otro_nombre' => $nombre, 'tipo' => $tipo];
                $seen[$otro] = true;
            }
        }
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if (!is_array($rel)) { continue; }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            if ($a !== $rid && $b !== $rid) { continue; }
            $otro = $a === $rid ? $b : $a;
            if (isset($seen[$otro])) { continue; }
            $romDir = RelacionEngine::romanceHacia($partida, $rid, $otro);
            $romance = (float) ($romDir ?? 0);
            $social = 0.0;
            $dir = RelacionEngine::socialHacia($partida, $rid, $otro);
            $social = (float) (($dir['valor']) ?? 0);
            $tipo = $romance > 10 ? 'romance' : ($social > 30 ? 'muy_cercano' : ($social > 20 ? 'cercano' : 'amigo'));
            $nombre = IdentidadPublica::nombre($partida, $otro);
            if ($social > 20 || $romance > 10) {
                $out[] = ['otro_id' => $otro, 'otro_nombre' => $nombre, 'tipo' => $tipo];
                $seen[$otro] = true;
            }
        }
        return $out;
    }

    private static function senalesRomanticas(array $partida, string $rid): array
    {
        $out = [];
        $seen = [];
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if (!is_array($rel)) { continue; }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            if ($a !== $rid && $b !== $rid) { continue; }
            $otro = $a === $rid ? $b : $a;
            if (isset($seen[$otro])) { continue; }
            $romDir = RelacionEngine::romanceHacia($partida, $rid, $otro);
            $romance = (float) ($romDir ?? 0);
            if ($romance > 15) {
                $out[] = ['otro_id' => $otro, 'otro_nombre' => IdentidadPublica::nombre($partida, $otro)];
                $seen[$otro] = true;
            }
        }
        return $out;
    }

    private static function emocionesFuertes(array $partida, string $rid): array
    {
        $out = [];
        foreach ($partida['residentes'][$rid]['emociones'] ?? [] as $em) {
            if (is_array($em) && (int) ($em['intensidad'] ?? 0) >= 3) {
                $out[] = ['tipo' => (string) ($em['tipo'] ?? 'general'), 'intensidad' => (int) $em['intensidad']];
            }
        }
        return $out;
    }

    private static function vecinosApagados(array $partida, string $rid): array
    {
        $out = [];
        $bajos = ['triste', 'solo', 'aislado', 'preocupado', 'apagado', 'neutro'];
        foreach ($partida['residentes'] ?? [] as $otro => $rData) {
            if ($otro === $rid || !is_array($rData)) { continue; }
            if (!self::seConocen($partida, $rid, $otro)) { continue; }
            $motivo = null;
            $ems = $rData['emociones'] ?? [];
            if (is_array($ems)) {
                foreach ($ems as $em) {
                    if (!is_array($em)) { continue; }
                    $est = (string) ($em['estado'] ?? $em['tipo'] ?? '');
                    if (in_array($est, $bajos, true) && (int) ($em['intensidad'] ?? 1) >= 2) {
                        $motivo = $est;
                        break;
                    }
                }
            }
            if ($motivo === null) {
                $emoRt = (string) ($rData['runtime']['estado_emocional']['id'] ?? '');
                if (in_array($emoRt, ['triste', 'apagado'], true)) {
                    $motivo = $emoRt;
                }
            }
            if ($motivo !== null) {
                $out[] = ['residente_id' => $otro, 'nombre' => IdentidadPublica::nombre($partida, $otro), 'motivo' => $motivo];
            }
        }
        return $out;
    }

    private static function seConocen(array $partida, string $a, string $b): bool
    {
        return RelacionEngine::seConocen($partida, $a, $b);
    }

    private static function cercaniaNarrativa(array $partida, string $rid): float
    {
        $hist = $partida['mensajitos_historial'] ?? [];
        $n = 0;
        foreach ($hist as $h) {
            if (is_array($h) && ($h['residente_id'] ?? '') === $rid) {
                $n++;
            }
        }
        return min(1.0, $n / 10.0);
    }
}